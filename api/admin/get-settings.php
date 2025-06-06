<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

require_once '../../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Fetch all settings
    $stmt = $conn->query("SELECT setting_key, setting_value FROM system_settings");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Group settings by category
    $grouped_settings = [
        'general' => [
            'system_name' => $settings['system_name'] ?? '',
            'system_email' => $settings['system_email'] ?? '',
            'default_timezone' => $settings['default_timezone'] ?? 'UTC',
            'date_format' => $settings['date_format'] ?? 'Y-m-d',
            'time_format' => $settings['time_format'] ?? 'H:i:s',
            'items_per_page' => $settings['items_per_page'] ?? '10',
            'maintenance_mode' => $settings['maintenance_mode'] ?? '0'
        ],
        'email' => [
            'smtp_host' => $settings['smtp_host'] ?? '',
            'smtp_port' => $settings['smtp_port'] ?? '',
            'smtp_username' => $settings['smtp_username'] ?? '',
            'smtp_password' => $settings['smtp_password'] ?? '',
            'enable_email_notifications' => $settings['enable_email_notifications'] ?? '0'
        ],
        'security' => [
            'enable_two_factor' => $settings['enable_two_factor'] ?? '0',
            'session_timeout' => $settings['session_timeout'] ?? '30',
            'max_login_attempts' => $settings['max_login_attempts'] ?? '5',
            'password_expiry_days' => $settings['password_expiry_days'] ?? '90',
            'enable_captcha' => $settings['enable_captcha'] ?? '0',
            'captcha_site_key' => $settings['captcha_site_key'] ?? '',
            'captcha_secret_key' => $settings['captcha_secret_key'] ?? ''
        ],
        'backup' => [
            'enable_backup' => $settings['enable_backup'] ?? '0',
            'backup_frequency' => $settings['backup_frequency'] ?? 'daily',
            'backup_retention_days' => $settings['backup_retention_days'] ?? '30',
            'enable_backup_encryption' => $settings['enable_backup_encryption'] ?? '0',
            'backup_encryption_key' => $settings['backup_encryption_key'] ?? ''
        ],
        'api' => [
            'enable_api_access' => $settings['enable_api_access'] ?? '0',
            'api_rate_limit' => $settings['api_rate_limit'] ?? '100',
            'enable_api_documentation' => $settings['enable_api_documentation'] ?? '0',
            'api_documentation_url' => $settings['api_documentation_url'] ?? ''
        ],
        'social' => [
            'enable_social_login' => $settings['enable_social_login'] ?? '0',
            'facebook_app_id' => $settings['facebook_app_id'] ?? '',
            'facebook_app_secret' => $settings['facebook_app_secret'] ?? '',
            'google_client_id' => $settings['google_client_id'] ?? '',
            'google_client_secret' => $settings['google_client_secret'] ?? ''
        ],
        'file' => [
            'enable_file_upload' => $settings['enable_file_upload'] ?? '0',
            'max_file_size' => $settings['max_file_size'] ?? '5',
            'allowed_file_types' => $settings['allowed_file_types'] ?? ''
        ],
        'appearance' => [
            'enable_dark_mode' => $settings['enable_dark_mode'] ?? '0',
            'enable_rtl' => $settings['enable_rtl'] ?? '0',
            'enable_custom_css' => $settings['enable_custom_css'] ?? '0',
            'custom_css' => $settings['custom_css'] ?? '',
            'enable_custom_js' => $settings['enable_custom_js'] ?? '0',
            'custom_js' => $settings['custom_js'] ?? ''
        ],
        'advanced' => [
            'enable_debug_mode' => $settings['enable_debug_mode'] ?? '0',
            'enable_error_reporting' => $settings['enable_error_reporting'] ?? '0',
            'enable_query_log' => $settings['enable_query_log'] ?? '0',
            'enable_system_log' => $settings['enable_system_log'] ?? '0',
            'log_retention_days' => $settings['log_retention_days'] ?? '30',
            'enable_cache' => $settings['enable_cache'] ?? '0',
            'cache_driver' => $settings['cache_driver'] ?? 'file',
            'cache_ttl' => $settings['cache_ttl'] ?? '3600'
        ]
    ];

    echo json_encode(['success' => true, 'settings' => $grouped_settings]);

} catch (Exception $e) {
    error_log("Error fetching system settings: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch settings: ' . $e->getMessage()]);
} 