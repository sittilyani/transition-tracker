<?php
session_start();
include('../includes/config.php');

// Check if user is logged in
if(!isset($_SESSION['user_id'])){
    header('Location: ../login.php');
    exit();
}

// Search filter
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$where_clause = "";
if($search){
    $where_clause = "WHERE first_name LIKE '%$search%' OR last_name LIKE '%$search%' OR other_name LIKE '%$search%' OR staff_phone LIKE '%$search%' OR email LIKE '%$search%' OR facility_name LIKE '%$search%' OR department_name LIKE '%$search%' OR cadre_name LIKE '%$search%' OR id_number LIKE '%$search%'";
}

// Fetch all staff records
$query = "SELECT staff_id, first_name, last_name, other_name, sex, staff_phone, id_number, email, facility_name, subcounty_name, county_name, level_of_care_name, department_name, cadre_name, staff_status, employment_status, created_by, created_at FROM county_staff $where_clause ORDER BY created_at DESC";
$result = mysqli_query($conn, $query);

// Set headers for Excel download
$filename = "county_staff_" . date('Y-m-d_H-i-s') . ".xls";
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// Output column headers
echo "Staff ID\t";
echo "First Name\t";
echo "Last Name\t";
echo "Other Name\t";
echo "Sex\t";
echo "Phone Number\t";
echo "ID Number\t";
echo "Email\t";
echo "Facility Name\t";
echo "Sub-County\t";
echo "County\t";
echo "Level of Care\t";
echo "Department\t";
echo "Cadre\t";
echo "Current Status\t";
echo "Emloyment Status\t";
echo "Created By\t";
echo "Created At\n";

// Output data rows
while($row = mysqli_fetch_assoc($result)){
    echo $row['staff_id'] . "\t";
    echo $row['first_name'] . "\t";
    echo $row['last_name'] . "\t";
    echo $row['other_name'] . "\t";
    echo $row['sex'] . "\t";
    echo $row['staff_phone'] . "\t";
    echo $row['id_number'] . "\t";
    echo $row['email'] . "\t";
    echo $row['facility_name'] . "\t";
    echo $row['subcounty_name'] . "\t";
    echo $row['county_name'] . "\t";
    echo $row['level_of_care_name'] . "\t";
    echo $row['department_name'] . "\t";
    echo $row['cadre_name'] . "\t";
    echo $row['staff_status'] . "\t";
    echo $row['employment_status'] . "\t";
    echo $row['created_by'] . "\t";
    echo date('Y-m-d H:i:s', strtotime($row['created_at'])) . "\n";
}

exit();
?>
