<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/session_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: view_staff_trainings.php');
    exit();
}

$training_id = (int)($_POST['training_id'] ?? 0);
$staff_id = (int)($_POST['staff_id'] ?? 0);
$id_number = $_POST['id_number'] ?? '';
$action = $_POST['action'] ?? 'update'; // 'update' or 'submit'

if (!$training_id) {
    $_SESSION['error_msg'] = "Invalid training ID.";
    header('Location: view_staff_trainings.php');
    exit();
}

// Check if training exists and is in draft status
$check_query = "SELECT status FROM staff_self_trainings WHERE self_training_id = ?";
$check_stmt = $conn->prepare($check_query);
$check_stmt->bind_param('i', $training_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();
$training = $check_result->fetch_assoc();

if (!$training) {
    $_SESSION['error_msg'] = "Training record not found.";
    header('Location: view_staff_trainings.php');
    exit();
}

if ($training['status'] != 'draft') {
    $_SESSION['error_msg'] = "Only draft trainings can be edited.";
    header('Location: view_staff_trainings.php');
    exit();
}

$conn->begin_transaction();

try {
    // Get form data
    $course_id = (int)($_POST['course_id'] ?? 0);
    $training_type_id = !empty($_POST['training_type_id']) ? (int)$_POST['training_type_id'] : null;
    $provider_id = !empty($_POST['provider_id']) ? (int)$_POST['provider_id'] : null;
    $location_id = !empty($_POST['location_id']) ? (int)$_POST['location_id'] : null;
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $certificate_number = $_POST['certificate_number'] ?? null;
    $facilitators = $_POST['facilitators'] ?? [];

    // Validate dates
    if ($end_date < $start_date) {
        throw new Exception("End date cannot be before start date.");
    }

    $today = date('Y-m-d');
    if ($start_date > $today) {
        throw new Exception("Start date cannot be in the future.");
    }
    if ($end_date > $today) {
        throw new Exception("End date cannot be in the future.");
    }

    // Get course name
    $course_stmt = $conn->prepare("SELECT course_name FROM courses WHERE course_id = ?");
    $course_stmt->bind_param("i", $course_id);
    $course_stmt->execute();
    $course_result = $course_stmt->get_result();
    $course_row = $course_result->fetch_assoc();
    $course_name = $course_row['course_name'] ?? '';
    $course_stmt->close();

    // Get training type name
    $training_type_name = '';
    if ($training_type_id) {
        $type_stmt = $conn->prepare("SELECT trainingtype_name FROM trainingtypes WHERE trainingtype_id = ?");
        $type_stmt->bind_param("i", $training_type_id);
        $type_stmt->execute();
        $type_result = $type_stmt->get_result();
        $type_row = $type_result->fetch_assoc();
        $training_type_name = $type_row['trainingtype_name'] ?? '';
        $type_stmt->close();
    }

    // Get provider name
    $provider_name = '';
    if ($provider_id) {
        $provider_stmt = $conn->prepare("SELECT facilitator_level_name FROM facilitator_levels WHERE fac_level_id = ?");
        $provider_stmt->bind_param("i", $provider_id);
        $provider_stmt->execute();
        $provider_result = $provider_stmt->get_result();
        $provider_row = $provider_result->fetch_assoc();
        $provider_name = $provider_row['facilitator_level_name'] ?? '';
        $provider_stmt->close();
    }

    // Get venue name
    $venue_name = '';
    if ($location_id) {
        $venue_stmt = $conn->prepare("SELECT location_name FROM training_locations WHERE location_id = ?");
        $venue_stmt->bind_param("i", $location_id);
        $venue_stmt->execute();
        $venue_result = $venue_stmt->get_result();
        $venue_row = $venue_result->fetch_assoc();
        $venue_name = $venue_row['location_name'] ?? '';
        $venue_stmt->close();
    }

    // Process facilitators
    $facilitators_array = [];
    $facilitator_json_data = [];

    foreach ($facilitators as $facilitator) {
        if (!empty($facilitator['name'])) {
            $cadre_id = !empty($facilitator['cadre_id']) ? (int)$facilitator['cadre_id'] : null;
            $cadre_name = '';

            if ($cadre_id) {
                $cadre_stmt = $conn->prepare("SELECT cadre_name FROM cadres WHERE cadre_id = ?");
                $cadre_stmt->bind_param("i", $cadre_id);
                $cadre_stmt->execute();
                $cadre_result = $cadre_stmt->get_result();
                $cadre_row = $cadre_result->fetch_assoc();
                $cadre_name = $cadre_row['cadre_name'] ?? '';
                $cadre_stmt->close();
            }

            $facilitators_array[] = [
                'name' => $facilitator['name'],
                'cadre_id' => $cadre_id,
                'cadre' => $cadre_name
            ];

            $facilitator_json_data[] = [
                'name' => $facilitator['name'],
                'cadre_id' => $cadre_id,
                'cadre' => $cadre_name
            ];
        }
    }

    $facilitator_json = json_encode($facilitator_json_data);

    // Determine new status
    $new_status = ($action == 'submit') ? 'submitted' : 'draft';
    $submission_date = ($action == 'submit') ? date('Y-m-d H:i:s') : null;

    // Update training record
    $update_sql = "UPDATE staff_self_trainings SET
                   course_id = ?, course_name = ?,
                   training_type_id = ?, training_type = ?,
                   provider_id = ?, training_provider = ?,
                   location_id = ?, venue = ?,
                   start_date = ?, end_date = ?,
                   certificate_number = ?,
                   facilitator_details = ?,
                   status = ?, submission_date = ?
                   WHERE self_training_id = ?";

    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param(
        "isisisssssssssi",
        $course_id,
        $course_name,
        $training_type_id,
        $training_type_name,
        $provider_id,
        $provider_name,
        $location_id,
        $venue_name,
        $start_date,
        $end_date,
        $certificate_number,
        $facilitator_json,
        $new_status,
        $submission_date,
        $training_id
    );

    if (!$update_stmt->execute()) {
        throw new Exception("Error updating training: " . $update_stmt->error);
    }

    // Delete old facilitators
    $delete_stmt = $conn->prepare("DELETE FROM training_facilitators WHERE self_training_id = ?");
    $delete_stmt->bind_param("i", $training_id);
    $delete_stmt->execute();
    $delete_stmt->close();

    // Insert new facilitators
    foreach ($facilitators_array as $facilitator) {
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

    $conn->commit();

    if ($action == 'submit') {
        $_SESSION['success_msg'] = "Training submitted for verification successfully!";
    } else {
        $_SESSION['success_msg'] = "Training updated successfully!";
    }

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error_msg'] = "Error: " . $e->getMessage();
}

header('Location: view_staff_trainings.php');
exit();
?>