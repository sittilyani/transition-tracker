<?php
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'transition_tracker';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ADD THIS - Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>