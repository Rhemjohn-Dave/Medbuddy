<?php
// Check if this file is being included
if (!defined('ADMIN_ACCESS')) {
    header("Location: ../../index.php");
    exit();
}

// Fetch current settings
require_once '../../config/database.php';
$db = new Database();
$conn = $db->getConnection();

try {
    $stmt = $conn->query("SELECT * FROM system_settings");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    error_log("Error fetching system settings: " . $e->getMessage());
    $settings = [];
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /medbuddy/login.php');
    exit();
}
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">System Settings</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Settings</li>
    </ol>

    <div class="row">
        <div class="col-xl-12">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-cog me-1"></i>
                    System Configuration
                </div>
                <div class="card-body">
                    <form id="settingsForm">
                        <!-- General Settings -->
                        <div class="mb-4">
                            <h5>General Settings</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="system_name" class="form-label">System Name</label>
                                        <input type="text" class="form-control" id="system_name" name="system_name" value="<?php echo htmlspecialchars($settings['system_name'] ?? 'MedBuddy'); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="system_email" class="form-label">System Email</label>
                                        <input type="email" class="form-control" id="system_email" name="system_email" value="<?php echo htmlspecialchars($settings['system_email'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="default_timezone" class="form-label">Default Timezone</label>
                                        <select class="form-select" id="default_timezone" name="default_timezone">
                                            <?php
                                            $timezones = DateTimeZone::listIdentifiers();
                                            $current_timezone = $settings['timezone'] ?? 'UTC';
                                            foreach ($timezones as $timezone) {
                                                $selected = ($timezone === $current_timezone) ? 'selected' : '';
                                                echo "<option value=\"$timezone\" $selected>$timezone</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="date_format" class="form-label">Date Format</label>
                                        <select class="form-select" id="date_format" name="date_format">
                                            <?php
                                            $date_formats = [
                                                'Y-m-d' => 'YYYY-MM-DD',
                                                'd/m/Y' => 'DD/MM/YYYY',
                                                'm/d/Y' => 'MM/DD/YYYY',
                                                'd.m.Y' => 'DD.MM.YYYY'
                                            ];
                                            $current_format = $settings['date_format'] ?? 'Y-m-d';
                                            foreach ($date_formats as $format => $label) {
                                                $selected = ($format === $current_format) ? 'selected' : '';
                                                echo "<option value=\"$format\" $selected>$label</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="time_format" class="form-label">Time Format</label>
                                        <select class="form-select" id="time_format" name="time_format">
                                            <option value="H:i:s">24-hour (HH:mm:ss)</option>
                                            <option value="h:i:s A">12-hour (hh:mm:ss AM/PM)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Email Settings -->
                        <div class="mb-4">
                            <h5>Email Settings</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="smtp_host" class="form-label">SMTP Host</label>
                                        <input type="text" class="form-control" id="smtp_host" name="smtp_host" value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="smtp_port" class="form-label">SMTP Port</label>
                                        <input type="number" class="form-control" id="smtp_port" name="smtp_port" value="<?php echo htmlspecialchars($settings['smtp_port'] ?? '587'); ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="smtp_username" class="form-label">SMTP Username</label>
                                        <input type="text" class="form-control" id="smtp_username" name="smtp_username" value="<?php echo htmlspecialchars($settings['smtp_username'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="smtp_password" class="form-label">SMTP Password</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="smtp_password" name="smtp_password" value="<?php echo htmlspecialchars($settings['smtp_password'] ?? ''); ?>">
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('smtp_password')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="enable_email_notifications" name="enable_email_notifications" <?php echo ($settings['enable_email_notifications'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="enable_email_notifications">
                                        Enable Email Notifications
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Security Settings -->
                        <div class="mb-4">
                            <h5>Security Settings</h5>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="session_timeout" class="form-label">Session Timeout (minutes)</label>
                                        <input type="number" class="form-control" id="session_timeout" name="session_timeout" value="<?php echo htmlspecialchars($settings['session_timeout'] ?? '30'); ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="max_login_attempts" class="form-label">Max Login Attempts</label>
                                        <input type="number" class="form-control" id="max_login_attempts" name="max_login_attempts" value="<?php echo htmlspecialchars($settings['max_login_attempts'] ?? '5'); ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="password_expiry_days" class="form-label">Password Expiry (days)</label>
                                        <input type="number" class="form-control" id="password_expiry_days" name="password_expiry_days" value="<?php echo htmlspecialchars($settings['password_expiry_days'] ?? '30'); ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="enable_two_factor" name="enable_two_factor" <?php echo ($settings['enable_two_factor'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="enable_two_factor">
                                        Enable Two-Factor Authentication
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Backup Settings -->
                        <div class="mb-4">
                            <h5>Backup Settings</h5>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="backup_frequency" class="form-label">Backup Frequency</label>
                                        <select class="form-select" id="backup_frequency" name="backup_frequency">
                                            <option value="daily">Daily</option>
                                            <option value="weekly">Weekly</option>
                                            <option value="monthly">Monthly</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="backup_retention_days" class="form-label">Backup Retention (days)</label>
                                        <input type="number" class="form-control" id="backup_retention_days" name="backup_retention_days" value="<?php echo htmlspecialchars($settings['backup_retention_days'] ?? '7'); ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="enable_backup" name="enable_backup" <?php echo ($settings['enable_backup'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="enable_backup">
                                        Enable Automatic Backups
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">Save Settings</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Load timezone options
    const timezones = moment.tz.names();
    const timezoneSelect = document.getElementById('default_timezone');
    timezones.forEach(timezone => {
        const option = document.createElement('option');
        option.value = timezone;
        option.textContent = timezone;
        timezoneSelect.appendChild(option);
    });

    // Load current settings
    fetch('/medbuddy/api/admin/get-settings.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const settings = data.settings;
                
                // General settings
                document.getElementById('system_name').value = settings.general.system_name;
                document.getElementById('system_email').value = settings.general.system_email;
                document.getElementById('default_timezone').value = settings.general.default_timezone;
                document.getElementById('date_format').value = settings.general.date_format;
                document.getElementById('time_format').value = settings.general.time_format;

                // Email settings
                document.getElementById('smtp_host').value = settings.email.smtp_host;
                document.getElementById('smtp_port').value = settings.email.smtp_port;
                document.getElementById('smtp_username').value = settings.email.smtp_username;
                document.getElementById('smtp_password').value = settings.email.smtp_password;
                document.getElementById('enable_email_notifications').checked = settings.email.enable_email_notifications === '1';

                // Security settings
                document.getElementById('session_timeout').value = settings.security.session_timeout;
                document.getElementById('max_login_attempts').value = settings.security.max_login_attempts;
                document.getElementById('password_expiry_days').value = settings.security.password_expiry_days;
                document.getElementById('enable_two_factor').checked = settings.security.enable_two_factor === '1';

                // Backup settings
                document.getElementById('backup_frequency').value = settings.backup.backup_frequency;
                document.getElementById('backup_retention_days').value = settings.backup.backup_retention_days;
                document.getElementById('enable_backup').checked = settings.backup.enable_backup === '1';
            }
        })
        .catch(error => {
            console.error('Error loading settings:', error);
            showAlert('Error loading settings. Please try again.', 'danger');
        });

    // Handle form submission
    document.getElementById('settingsForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const settings = {};
        
        for (let [key, value] of formData.entries()) {
            if (key.endsWith('_notifications') || key.endsWith('_two_factor') || key.endsWith('_backup')) {
                settings[key] = value === 'on' ? '1' : '0';
            } else {
                settings[key] = value;
            }
        }

        fetch('/medbuddy/api/admin/save-settings.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(settings)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Settings saved successfully!', 'success');
            } else {
                showAlert(data.error || 'Error saving settings. Please try again.', 'danger');
            }
        })
        .catch(error => {
            console.error('Error saving settings:', error);
            showAlert('Error saving settings. Please try again.', 'danger');
        });
    });
});

function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const icon = input.nextElementSibling.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

function showAlert(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.role = 'alert';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    const container = document.querySelector('.container-fluid');
    container.insertBefore(alertDiv, container.firstChild);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}
</script> 