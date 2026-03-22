<?php
// transitions/edit_transition.php
session_start();
include('../includes/config.php');
include('../includes/session_check.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$assessment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$assessment_id) {
    header('Location: transition_index.php');
    exit();
}

// Get assessment details
$assessment_query = "
    SELECT ta.*, c.county_id, c.county_name
    FROM transition_assessments ta
    JOIN counties c ON ta.county_id = c.county_id
    WHERE ta.assessment_id = $assessment_id
";
$assessment_result = mysqli_query($conn, $assessment_query);
if (mysqli_num_rows($assessment_result) == 0) {
    header('Location: transition_index.php');
    exit();
}
$assessment = mysqli_fetch_assoc($assessment_result);

// Redirect to the assessment form with pre-filled data
header('Location: transition_assessment.php?county=' . $assessment['county_id'] .
       '&period=' . urlencode($assessment['assessment_period']) .
       '&assessment_id=' . $assessment_id . '&sections=all');
exit();