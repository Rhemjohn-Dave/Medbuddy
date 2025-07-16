<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../auth/index.php");
    exit();
}

// Define role access constant
define('ROLE_ACCESS', true);
define('ADMIN_ACCESS', true);

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
        'page' => 'users',
        'icon' => 'people',
        'label' => 'Users'
    ],
    [
        'page' => 'reports',
        'icon' => 'analytics',
        'label' => 'Reports'
    ],
    [
        'page' => 'messages',
        'icon' => 'message',
        'label' => 'Messages'
    ],
    [
        'page' => 'settings',
        'icon' => 'settings',
        'label' => 'Settings'
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