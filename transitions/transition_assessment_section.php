<?php
// transitions/transition_assessment_section.php
session_start();
include('../includes/config.php');
include('../includes/session_check.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$section_id = isset($_GET['section']) ? (int)$_GET['section'] : 0;
$assessment_id = isset($_GET['assessment_id']) ? (int)$_GET['assessment_id'] : 0;

// Get section details
$section_query = "SELECT * FROM transition_sections WHERE section_id = $section_id";
$section = mysqli_fetch_assoc(mysqli_query($conn, $section_query));

// Get indicators for this section
$indicators_query = "
    SELECT * FROM transition_indicators
    WHERE section_id = $section_id
    ORDER BY display_order
";
$indicators = mysqli_query($conn, $indicators_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $section['section_name'] ?> - Assessment</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Similar styling to previous forms */
        .score-radio {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        .score-radio label {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 8px;
            border: 2px solid #e0e4f0;
            border-radius: 8px;
            cursor: pointer;
            min-width: 50px;
        }
        .score-radio input[type="radio"] {
            display: none;
        }
        .score-radio input[type="radio"]:checked + label {
            border-color: #0D1A63;
            background: #e8edf8;
        }
        .score-value {
            font-weight: 700;
            font-size: 16px;
        }
        .score-desc {
            font-size: 10px;
            color: #666;
        }
    </style>
</head>
<body>
<div class="container">
    <!-- Form content here -->
</div>
</body>
</html>