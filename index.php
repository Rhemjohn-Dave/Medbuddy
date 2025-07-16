<?php
session_start();
if(isset($_SESSION['user_id'])) {
    // Redirect based on user role
    switch($_SESSION['role']) {
        case 'admin':
            header("Location: pages/admin/index.php");
            break;
        case 'doctor':
            header("Location: pages/doctor/index.php");
            break;
        case 'patient':
            header("Location: pages/patient/index.php");
            break;
        case 'staff':
            header("Location: pages/staff/index.php");
            break;
        default:
            header("Location: auth/index.php");
    }
    exit();
} else {
    header("Location: auth/index.php");
    exit();
}
?> 