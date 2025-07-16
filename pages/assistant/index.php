<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define role access constant
define('ROLE_ACCESS', true);
define('ASSISTANT_ACCESS', true);

// Include database connection
require_once '../../config/database.php';

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
        'page' => 'schedule',
        'icon' => 'calendar_today',
        'label' => 'Schedule'
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