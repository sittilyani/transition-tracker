<?php
require_once '../includes/config.php';

// Sample courses
$courses = [
    'Basic Life Support',
    'Advanced Cardiac Life Support',
    'HIV/AIDS Management',
    'Maternal and Child Health',
    'Infection Prevention and Control',
    'Emergency Obstetric Care',
    'Pharmaceutical Management',
    'Laboratory Quality Management',
    'Health Information Systems',
    'Leadership and Management'
];

// Sample durations
$durations = [
    '1 day',
    '2 days',
    '3 days',
    '5 days',
    '1 week',
    '2 weeks',
    '1 month',
    '3 months',
    '6 months'
];

// Sample locations
$locations = [
    'County Referral Hospital',
    'Sub-County Hospital',
    'Health Center',
    'Dispensary',
    'Conference Hall',
    'Training Center',
    'Online/Virtual'
];

// Sample cadres
$cadres = [
    'Medical Officer',
    'Clinical Officer',
    'Nurse',
    'Pharmacist',
    'Lab Technologist',
    'Nutritionist',
    'Health Records Officer',
    'Community Health Worker',
    'Public Health Officer'
];

// Sample facilitator levels
$facilitatorLevels = [
    'National Level',
    'County Level',
    'Sub-County Level',
    'Facility Level',
    'External Consultant'
];

// Insert courses
foreach ($courses as $course) {
    $sql = "INSERT IGNORE INTO courses (course_name) VALUES (?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $course);
    $stmt->execute();
    $stmt->close();
}

// Insert durations
foreach ($durations as $duration) {
    $sql = "INSERT IGNORE INTO course_durations (duration_name) VALUES (?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $duration);
    $stmt->execute();
    $stmt->close();
}

// Insert locations
foreach ($locations as $location) {
    $sql = "INSERT IGNORE INTO training_locations (location_name) VALUES (?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $location);
    $stmt->execute();
    $stmt->close();
}

// Insert cadres
foreach ($cadres as $cadre) {
    $sql = "INSERT IGNORE INTO cadres (cadrename) VALUES (?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $cadre);
    $stmt->execute();
    $stmt->close();
}

// Insert facilitator levels
foreach ($facilitatorLevels as $level) {
    $sql = "INSERT IGNORE INTO facilitator_levels (facilitator_level_name) VALUES (?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $level);
    $stmt->execute();
    $stmt->close();
}

echo "Sample data inserted successfully!";
$conn->close();
?>