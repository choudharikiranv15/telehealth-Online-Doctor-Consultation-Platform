<?php
$page_title = "Dashboard";
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Redirect based on role
switch ($_SESSION['role']) {
    case 'admin':
        header("Location: admin/");
        break;
    case 'doctor':
        header("Location: doctor/");
        break;
    case 'patient':
        header("Location: patient/");
        break;
    default:
        // Invalid role, redirect to login
        session_destroy();
        header("Location: login.php");
        break;
}
exit();
?>
