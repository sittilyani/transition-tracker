<?php
require_once '../includes/config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Facility
    $facility_id   = intval($_POST['facility_id'] ?? 0);
    $facility_name = trim($_POST['facilityname'] ?? '');
    $mflcode       = trim($_POST['mflcode'] ?? '');
    $county        = trim($_POST['county'] ?? '');
    $subcounty     = trim($_POST['subcounty'] ?? '');

    // Staff
    /*$staff_name        = trim($_POST['staff_name'] ?? '');  */
    $staff_department  = trim($_POST['department'] ?? '');
    $staff_designation = '';
    $staff_p_no        = trim($_POST['staff_p_no'] ?? '');
    $staff_phone       = trim($_POST['staff_phone'] ?? '');
    $staff_cadre       = '';
    $sex_name          = trim($_POST['sex_name'] ?? '');
    $email             = trim($_POST['email'] ?? '');

    // Training
    $course_id         = intval($_POST['course_id'] ?? 0);
    $course_name       = trim($_POST['course_name'] ?? '');
    $trainingtype_name = trim($_POST['trainingtype_name'] ?? '');

    $duration_id   = intval($_POST['duration_id'] ?? 0);
    $duration_name = trim($_POST['duration_name'] ?? '');

    $training_date = trim($_POST['training_date'] ?? '');

    $location_id   = intval($_POST['location_id'] ?? 0);
    $location_name = trim($_POST['location_name'] ?? '');

    // Facilitator
    $facilitator_name  = trim($_POST['facilitator_name'] ?? '');
    $cadre_id          = intval($_POST['cadre_id'] ?? 0);
    $cadrename         = trim($_POST['cadre_name'] ?? '');
    $fac_level_id      = intval($_POST['fac_level_id'] ?? 0);
    $facilitator_level = trim($_POST['facilitator_level'] ?? '');

    // Other
    $remarks = trim($_POST['remarks'] ?? '');

    // Basic validation
    if (!$facility_name || !$staff_name || !$course_name || !$training_date) {
        $_SESSION['error'] = 'Please fill in all required fields.';
        header('Location: staff_training_form.php?error=1');
        exit();
    }

    // Validate foreign key references
    $errors = [];

    // Check if course_id exists in courses table (only if course_id is provided)
    if ($course_id > 0) {
        $check_course = $conn->prepare("SELECT course_id FROM courses WHERE course_id = ?");
        $check_course->bind_param("i", $course_id);
        $check_course->execute();
        $check_course->store_result();
        if ($check_course->num_rows === 0) {
            $errors[] = "Invalid course selected. Course ID does not exist.";
        }
        $check_course->close();
    } else {
        // If no course_id provided, set it to NULL
        $course_id = null;
    }

    // Check if facility_id exists (only if facility_id is provided)
    if ($facility_id > 0) {
        $check_facility = $conn->prepare("SELECT facility_id FROM facilities WHERE facility_id = ?");
        $check_facility->bind_param("i", $facility_id);
        $check_facility->execute();
        $check_facility->store_result();
        if ($check_facility->num_rows === 0) {
            $errors[] = "Invalid facility selected. Facility ID does not exist.";
        }
        $check_facility->close();
    } else {
        $facility_id = null;
    }

    // Check if duration_id exists (only if duration_id is provided)
    if ($duration_id > 0) {
        $check_duration = $conn->prepare("SELECT duration_name FROM course_durations WHERE duration_id = ?");
        $check_duration->bind_param("i", $duration_id);
        $check_duration->execute();
        $check_duration->store_result();
        if ($check_duration->num_rows === 0) {
            $errors[] = "Invalid duration selected. Duration ID does not exist.";
        }
        $check_duration->close();
    } else {
        $duration_id = null;
    }

    // Check if location_id exists (only if location_id is provided)
    if ($location_id > 0) {
        $check_location = $conn->prepare("SELECT location_id FROM training_locations WHERE location_id = ?");
        $check_location->bind_param("i", $location_id);
        $check_location->execute();
        $check_location->store_result();
        if ($check_location->num_rows === 0) {
            $errors[] = "Invalid location selected. Location ID does not exist.";
        }
        $check_location->close();
    } else {
        $location_id = null;
    }

    // Check if cadre_id exists (only if cadre_id is provided)
    if ($cadre_id > 0) {
        $check_cadre = $conn->prepare("SELECT cadre_id FROM cadres WHERE cadre_id = ?");
        $check_cadre->bind_param("i", $cadre_id);
        $check_cadre->execute();
        $check_cadre->store_result();
        if ($check_cadre->num_rows === 0) {
            $errors[] = "Invalid cadre selected. Cadre ID does not exist.";
        }
        $check_cadre->close();
    } else {
        $cadre_id = null;
    }

    // Check if fac_level_id exists (only if fac_level_id is provided)
    if ($fac_level_id > 0) {
        $check_fac_level = $conn->prepare("SELECT fac_level_id FROM facilitator_levels WHERE fac_level_id = ?");
        $check_fac_level->bind_param("i", $fac_level_id);
        $check_fac_level->execute();
        $check_fac_level->store_result();
        if ($check_fac_level->num_rows === 0) {
            $errors[] = "Invalid facilitator level selected. Level ID does not exist.";
        }
        $check_fac_level->close();
    } else {
        $fac_level_id = null;
    }

    // If there are validation errors, redirect back
    if (!empty($errors)) {
        $_SESSION['error'] = implode('<br>', $errors);
        header('Location: staff_training_form.php?error=1');
        exit();
    }

    $sql = "INSERT INTO staff_trainings (
        facility_id, facility_name, mflcode, county, subcounty,
        staff_name, staff_department, staff_designation, staff_p_no,
        staff_phone, staff_cadre,
        course_id, course_name, duration_id, duration_name,
        training_date, location_id, location_name,
        facilitator_name, cadre_id, cadrename, fac_level_id,
        facilitator_level, remarks, sex_name, trainingtype_name, email
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "issssssssssisissssissssssss",
        $facility_id, $facility_name, $mflcode, $county, $subcounty,
        $staff_name, $staff_department, $staff_designation, $staff_p_no,
        $staff_phone, $staff_cadre,
        $course_id, $course_name, $duration_id, $duration_name,
        $training_date, $location_id, $location_name,
        $facilitator_name, $cadre_id, $cadrename, $fac_level_id,
        $facilitator_level, $remarks, $sex_name, $trainingtype_name, $email
    );

    if ($stmt->execute()) {
        $_SESSION['success'] = 'Training registration submitted successfully!';
    } else {
        $_SESSION['error'] = 'Database error: ' . $stmt->error;
    }

    $stmt->close();
    header('Location: staff_training_form.php');
    exit();
}
?>