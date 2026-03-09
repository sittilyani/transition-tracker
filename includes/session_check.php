<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['full_name'])) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Session expired']);
        exit;
    } else {
        // For regular pages, show error but don't exit
        $GLOBALS['session_error'] = 'Session expired. Please <a href="../login/login.php">login again</a>.';
    }
}

// Make session variables available as constants or globals
define('CURRENT_USER_ID', $_SESSION['user_id'] ?? 0);
define('CURRENT_USER_NAME', $_SESSION['full_name'] ?? 'Guest');
define('CURRENT_USER_ROLE', $_SESSION['role'] ?? '');
?>