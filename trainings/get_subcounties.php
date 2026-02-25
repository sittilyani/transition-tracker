<?php
include '../includes/config.php';

header('Content-Type: application/json');

if (isset($_POST['county_id'])) {
    $county_id = (int)$_POST['county_id'];

    // Get county name first
    $county_query = mysqli_query($conn, "SELECT county_name FROM counties WHERE county_id = $county_id");
    $county = mysqli_fetch_assoc($county_query);

    if ($county) {
        $county_name = mysqli_real_escape_string($conn, $county['county_name']);

        $query = mysqli_query($conn,
            "SELECT sub_county_id, sub_county_name
             FROM sub_counties
             WHERE county_name = '$county_name'
             ORDER BY sub_county_name"
        );

        $subcounties = [];
        while ($row = mysqli_fetch_assoc($query)) {
            $subcounties[] = $row;
        }

        echo json_encode($subcounties);
    } else {
        echo json_encode([]);
    }
} else {
    echo json_encode(['error' => 'No county ID provided']);
}
?>