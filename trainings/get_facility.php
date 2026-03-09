<?php
session_start();
include '../includes/config.php';
header('Content-Type: application/json');

$facility = $_POST['facilityname'] ?? '';
if (!$facility) {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("SELECT level_of_care, mflcode, countyname, subcountyname FROM facilities WHERE facilityname = ?");
$stmt->bind_param("s", $facility);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
$stmt->close();

echo json_encode($data ?: []);