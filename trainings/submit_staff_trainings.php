<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/session_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: staff_training_form.php');
    exit();
}

$staff_id = (int)($_POST['staff_id'] ?? 0);
$id_number = $_POST['id_number'] ?? '';
$action = $_POST['action'] ?? 'submit'; // 'draft' or 'submit'
$courses = $_POST['courses'] ?? [];

if (empty($courses)) {
    $_SESSION['error_msg'] = "No courses data received.";
    header('Location: staff_training_form.php');
    exit();
}

$conn->begin_transaction();

try {
    $success_count = 0;
    $draft_count = 0;

    foreach ($courses as $course_data) {
        // Validate required fields
        if (empty($course_data['course_id']) || empty($course_data['start_date']) || empty($course_data['end_date'])) {
            $_SESSION['error_msg'] = "Missing required fields for one of the courses.";
            header('Location: staff_training_form.php');
            exit();
        }

        // Validate that end_date is not before start_date
        if ($course_data['end_date'] < $course_data['start_date']) {
            throw new Exception("End date cannot be before start date for one of the courses.");
        }

        // Ensure dates are not in the future
        $today = date('Y-m-d');
        if ($course_data['start_date'] > $today) {
            throw new Exception("Start date cannot be in the future for one of the courses.");
        }
        if ($course_data['end_date'] > $today) {
            throw new Exception("End date cannot be in the future for one of the courses.");
        }

        // Get course name from database using course_id
        $course_stmt = $conn->prepare("SELECT course_name FROM courses WHERE course_id = ?");
        $course_stmt->bind_param("i", $course_data['course_id']);
        $course_stmt->execute();
        $course_result = $course_stmt->get_result();
        $course_row = $course_result->fetch_assoc();
        $course_name = $course_row['course_name'] ?? '';
        $course_stmt->close();

        // Get training type name from database
        $training_type_name = '';
        $training_type_id = !empty($course_data['training_type_id']) ? $course_data['training_type_id'] : null;
        if (!empty($training_type_id)) {
            $type_stmt = $conn->prepare("SELECT trainingtype_name FROM trainingtypes WHERE trainingtype_id = ?");
            $type_stmt->bind_param("i", $training_type_id);
            $type_stmt->execute();
            $type_result = $type_stmt->get_result();
            $type_row = $type_result->fetch_assoc();
            $training_type_name = $type_row['trainingtype_name'] ?? '';
            $type_stmt->close();
        }

        // Get provider name from database
        $provider_name = '';
        $provider_id = !empty($course_data['provider_id']) ? $course_data['provider_id'] : null;
        if (!empty($provider_id)) {
            $provider_stmt = $conn->prepare("SELECT facilitator_level_name FROM facilitator_levels WHERE fac_level_id = ?");
            $provider_stmt->bind_param("i", $provider_id);
            $provider_stmt->execute();
            $provider_result = $provider_stmt->get_result();
            $provider_row = $provider_result->fetch_assoc();
            $provider_name = $provider_row['facilitator_level_name'] ?? '';
            $provider_stmt->close();
        }

        // Get venue name from database
        $venue_name = '';
        $location_id = !empty($course_data['location_id']) ? $course_data['location_id'] : null;
        if (!empty($location_id)) {
            $venue_stmt = $conn->prepare("SELECT location_name FROM training_locations WHERE location_id = ?");
            $venue_stmt->bind_param("i", $location_id);
            $venue_stmt->execute();
            $venue_result = $venue_stmt->get_result();
            $venue_row = $venue_result->fetch_assoc();
            $venue_name = $venue_row['location_name'] ?? '';
            $venue_stmt->close();
        }

        // Get certificate number
        $certificate_number = $course_data['certificate_number'] ?? null;

        // Process facilitators
        $facilitators = [];
        if (isset($course_data['facilitators']) && is_array($course_data['facilitators'])) {
            foreach ($course_data['facilitators'] as $facilitator) {
                if (!empty($facilitator['name'])) {
                    // Get cadre name if cadre_id is provided
                    $cadre_name = '';
                    $cadre_id = !empty($facilitator['cadre_id']) ? $facilitator['cadre_id'] : null;
                    if (!empty($cadre_id)) {
                        $cadre_stmt = $conn->prepare("SELECT cadre_name FROM cadres WHERE cadre_id = ?");
                        $cadre_stmt->bind_param("i", $cadre_id);
                        $cadre_stmt->execute();
                        $cadre_result = $cadre_stmt->get_result();
                        $cadre_row = $cadre_result->fetch_assoc();
                        $cadre_name = $cadre_row['cadre_name'] ?? '';
                        $cadre_stmt->close();
                    }

                    $facilitators[] = [
                        'name' => $facilitator['name'],
                        'cadre_id' => $cadre_id,
                        'cadre' => $cadre_name
                    ];
                }
            }
        }
        $facilitator_json = json_encode($facilitators);

        // Determine status
        $status = ($action == 'submit') ? 'submitted' : 'draft';
        $submission_date = ($action == 'submit') ? date('Y-m-d H:i:s') : null;
        $created_by = $_SESSION['full_name'] ?? 'Staff';

        // Insert training record - using ? placeholders
        $sql = "INSERT INTO staff_self_trainings (
            staff_id, id_number, course_id, course_name, training_type_id, training_type,
            provider_id, training_provider, location_id, venue, start_date, end_date,
            certificate_number, facilitator_details, status, submission_date, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        // Bind parameters - all must be variables passed by reference
        $stmt->bind_param(
            "iiisisissssssssss",
            $staff_id,
            $id_number,
            $course_data['course_id'],
            $course_name,
            $training_type_id,
            $training_type_name,
            $provider_id,
            $provider_name,
            $location_id,
            $venue_name,
            $course_data['start_date'],
            $course_data['end_date'],
            $certificate_number,
            $facilitator_json,
            $status,
            $submission_date,
            $created_by
        );

        if ($stmt->execute()) {
            $training_id = $stmt->insert_id;

            // Insert individual facilitators into separate table
            foreach ($facilitators as $facilitator) {
                if (!empty($facilitator['name'])) {
                    $fac_stmt = $conn->prepare("
                        INSERT INTO training_facilitators
                        (self_training_id, facilitator_name, facilitator_cadre_id, facilitator_cadre)
                        VALUES (?, ?, ?, ?)
                    ");
                    $fac_stmt->bind_param(
                        "isis",
                        $training_id,
                        $facilitator['name'],
                        $facilitator['cadre_id'],
                        $facilitator['cadre']
                    );
                    $fac_stmt->execute();
                    $fac_stmt->close();
                }
            }

            if ($action == 'submit') {
                $success_count++;
            } else {
                $draft_count++;
            }
        } else {
            throw new Exception("Error inserting training: " . $stmt->error);
        }
        $stmt->close();
    }

    $conn->commit();

    if ($action == 'submit') {
        $_SESSION['success_msg'] = "$success_count training(s) submitted successfully!";
    } else {
        $_SESSION['success_msg'] = "$draft_count training(s) saved as draft.";
    }

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error_msg'] = "Error: " . $e->getMessage();
}

header('Location: staff_training_form.php');
exit();
?>