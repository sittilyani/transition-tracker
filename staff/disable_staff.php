<?php
session_start();
include('../includes/config.php');

// Check if user is logged in
if(!isset($_SESSION['user_id'])){
    header('Location: ../login.php');
    exit();
}

$staff_id = isset($_GET['staff_id']) ? (int)$_GET['staff_id'] : 0;

if($staff_id > 0){
    
    // Update status to disabled
    $query = "UPDATE county_staff SET status = 'disabled' WHERE staff_id = $staff_id";
    
    if(mysqli_query($conn, $query)){
        $_SESSION['success_msg'] = "Staff member disabled successfully!";
    } else {
        $_SESSION['error_msg'] = "Error disabling staff member: " . mysqli_error($conn);
    }
} else {
    $_SESSION['error_msg'] = "Invalid staff ID!";
}

header('Location: staffslist.php');
exit();
?>
