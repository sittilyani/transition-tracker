<?php
include '../includes/config.php';

// Set header to return JSON
header('Content-Type: application/json');

// Check if facility_name is provided
if (isset($_POST['facility_name']) && !empty($_POST['facility_name'])) {
    $facility_name = mysqli_real_escape_string($conn, $_POST['facility_name']);

    // Query to get facility details
    $query = mysqli_query($conn,
        "SELECT county_name, subcounty_name, level_of_care_name
         FROM facilities
         WHERE facility_name = '$facility_name'
         LIMIT 1"
    );

    if ($query && mysqli_num_rows($query) > 0) {
        $row = mysqli_fetch_assoc($query);

        // Make sure the field names match what your JavaScript expects
        $response = array(
            'county_name' => $row['county_name'],
            'subcounty_name' => $row['subcounty_name'],
            'level_of_care_name_name' => $row['level_of_care_name'] // Map level_of_care_name to level_of_care_name_name
        );

        echo json_encode($response);
    } else {
        echo json_encode(['error' => 'Facility not found']);
    }
} else {
    echo json_encode(['error' => 'No facility name provided']);
}
?>