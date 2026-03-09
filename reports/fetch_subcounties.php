<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

if (isset($_POST['county'])) {
    $county = $conn->real_escape_string(trim($_POST['county']));

    $sql = "SELECT DISTINCT subcounty FROM staff_trainings
            WHERE county = ? AND subcounty IS NOT NULL AND subcounty != ''
            ORDER BY subcounty";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $county);
    $stmt->execute();
    $result = $stmt->get_result();

    $subcounties = [];
    while ($row = $result->fetch_assoc()) {
        $subcounties[] = $row['subcounty'];
    }

    $stmt->close();
    $conn->close();

    echo json_encode([
        'success' => true,
        'subcounties' => $subcounties
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No county specified'
    ]);
}
?>