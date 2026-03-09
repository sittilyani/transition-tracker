<?php
require_once '../includes/config.php';

$search = isset($_GET['search']) ? $_GET['search'] : '';

$sql = "SELECT facility_id AS id, facilityname AS text, mflcode
        FROM facilities
        WHERE facilityname LIKE :search OR mflcode LIKE :search
        ORDER BY facilityname
        LIMIT 20";

$stmt = $pdo->prepare($sql);
$stmt->execute(['search' => '%' . $search . '%']);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($results);
?>