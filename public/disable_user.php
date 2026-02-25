<?php
session_start();
include('../includes/config.php');

// Check if user is logged in
if(!isset($_SESSION['user_id'])){
    header('Location: ../public/login.php');
    exit();
}

// Check if user has permission (Admin only)
if($_SESSION['userrole'] !== 'Admin'){
    $_SESSION['error_msg'] = "You don't have permission to disable users.";
    header('Location: userslist.php');
    exit();
}

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if($user_id > 0){

    // Prevent disabling your own account
    if($user_id == $_SESSION['user_id']){
        $_SESSION['error_msg'] = "You cannot disable your own account!";
        header('Location: userslist.php');
        exit();
    }

    // Update status to disabled - using 'Inactive' instead of 'disabled' to match your table
    $query = "UPDATE tblusers SET status = 'Inactive' WHERE user_id = $user_id";

    if(mysqli_query($conn, $query)){
        $_SESSION['success_msg'] = "User disabled successfully!";
    } else {
        $_SESSION['error_msg'] = "Error disabling user: " . mysqli_error($conn);
    }
} else {
    $_SESSION['error_msg'] = "Invalid user ID!";
}

header('Location: userslist.php');
exit();
?>