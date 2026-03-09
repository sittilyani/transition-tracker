<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

$id_number = isset($_GET['id_number']) ? $_GET['id_number'] : '';

if (empty($id_number)) {
    header("HTTP/1.0 400 Bad Request");
    exit;
}

$stmt = $conn->prepare("SELECT photo FROM county_staff WHERE id_number = ?");
$stmt->bind_param('s', $id_number);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    if (!empty($row['photo'])) {
        header("Content-Type: image/jpeg");
        echo $row['photo'];
        exit;
    }
}

// Fallback to default image
header("Location: ../assets/images/default-avatar.png");
exit;
?>