<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is a staff
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../../auth/index.php");
    exit();
}

// Define role access constant
define('ROLE_ACCESS', true);
define('STAFF_ACCESS', true);

// Include database connection
require_once '../../config/database.php';

// Initialize database connection
try {
    $database = new Database();
    $conn = $database->getConnection();
} catch(Exception $e) {
    error_log("Error in staff dashboard: " . $e->getMessage());
    header("Location: ../../auth/index.php");
    exit();
}

// Get current page
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// Set page title
$page_title = ucfirst(str_replace('-', ' ', $page));

// Define sidebar menu items
$sidebar_menu = [
    [
        'page' => 'dashboard',
        'icon' => 'dashboard',
        'label' => 'Dashboard'
    ],
    [
        'page' => 'appointments',
        'icon' => 'event',
        'label' => 'Appointments'
    ],
    [
        'page' => 'vital_signs',
        'icon' => 'favorite',
        'label' => 'Vital Signs'
    ],
    [
        'page' => 'lab-requests',
        'icon' => 'science',
        'label' => 'Lab Requests'
    ],
    [
        'page' => 'patients',
        'icon' => 'personal_injury',
        'label' => 'Patients'
    ],
    [
        'page' => 'doctors',
        'icon' => 'medical_services',
        'label' => 'Doctors'
    ],
    [
        'page' => 'messages',
        'icon' => 'message',
        'label' => 'Messages'
    ],
    [
        'page' => 'profile',
        'icon' => 'person',
        'label' => 'Profile'
    ]
];

// Enable notifications
$show_notifications = true;

// Get notifications count
$notification_count = 0; // TODO: Implement notification count

// Get notifications
$notifications = []; // TODO: Implement notifications

// Include the requested page content
ob_start();
$page_file = "pages/{$page}.php";
if (file_exists($page_file)) {
    include $page_file;
} else {
    echo '<div class="alert alert-danger">Page not found.</div>';
}
$content = ob_get_clean();

// Include layout template
include '../../templates/layout.php'; 