<?php
require_once '../includes/config.php';

// Check if facilityname is provided
if(isset($_POST['facilityname'])) {
    $facilityname = trim($_POST['facilityname']);

    // Use mysqli instead of PDO
    $sql = "SELECT * FROM facilities WHERE facilityname = ?";
    $stmt = $conn->prepare($sql);

    if($stmt) {
        $stmt->bind_param("s", $facilityname);
        $stmt->execute();
        $result = $stmt->get_result();
        $facility = $result->fetch_assoc();

        if($facility) {
            echo json_encode([
                'success' => true,
                'facility' => $facility
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Facility not found'
            ]);
        }
        $stmt->close();
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Database query error'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No facility name provided'
    ]);
}

// Close connection
$conn->close();
?>