<?php
// Check if PATIENT_ACCESS is defined
if (!defined('PATIENT_ACCESS')) {
    header("Location: ../../auth/index.php");
    exit();
}

require_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get patient information
$patient_query = "SELECT p.*, u.email, u.approval_status
          FROM patients p 
          JOIN users u ON p.user_id = u.id 
                 WHERE u.id = ?";

$stmt = $db->prepare($patient_query);
$stmt->execute([$_SESSION['user_id']]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

// Get medical records
$query = "SELECT mr.*, 
          d.first_name as doctor_first_name, 
          d.last_name as doctor_last_name,
          a.date as appointment_date
          FROM medical_records mr
          LEFT JOIN doctors d ON mr.doctor_id = d.id
          LEFT JOIN appointments a ON mr.appointment_id = a.id
          WHERE mr.patient_id = :patient_id
          ORDER BY mr.created_at DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(":patient_id", $patient['id']);
$stmt->execute();
$medical_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get allergies
$query = "SELECT * FROM allergies WHERE patient_id = :patient_id ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(":patient_id", $patient['id']);
$stmt->execute();
$allergies = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get current medications (active medications)
$query = "SELECT m.*, d.first_name as doctor_first_name, d.last_name as doctor_last_name 
          FROM medications m 
          LEFT JOIN doctors d ON m.prescribed_by = d.id 
          WHERE m.patient_id = :patient_id 
          AND m.status = 'active' 
          ORDER BY m.start_date DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(":patient_id", $patient['id']);
$stmt->execute();
$medications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        // Update basic information
        $update_sql = "UPDATE patients SET 
            first_name = ?, 
            middle_name = ?, 
            last_name = ?, 
            date_of_birth = ?, 
            gender = ?, 
            contact_number = ?, 
            address = ?, 
            emergency_contact_name = ?, 
            emergency_contact_number = ?, 
            medical_history = ? 
            WHERE id = ?";
        $update_stmt = $db->prepare($update_sql);
        $update_stmt->execute([
            $_POST['first_name'],
            $_POST['middle_name'],
            $_POST['last_name'],
            $_POST['date_of_birth'],
            $_POST['gender'],
            $_POST['contact_number'],
            $_POST['address'],
            $_POST['emergency_contact_name'],
            $_POST['emergency_contact_number'],
            $_POST['medical_history'],
            $_SESSION['user_id']
        ]);

        // Update email
        $email_sql = "UPDATE users SET email = ? WHERE id = ?";
        $email_stmt = $db->prepare($email_sql);
        $email_stmt->execute([
            $_POST['email'],
            $_SESSION['user_id']
        ]);

        // Update password if provided
        if (!empty($_POST['new_password'])) {
            if (empty($_POST['current_password'])) {
                throw new Exception("Current password is required to set a new password.");
            }

            // Verify current password
            $verify_sql = "SELECT password FROM users WHERE id = ?";
            $verify_stmt = $db->prepare($verify_sql);
            $verify_stmt->execute([$_SESSION['user_id']]);
            $current_hash = $verify_stmt->fetchColumn();

            if (!password_verify($_POST['current_password'], $current_hash)) {
                throw new Exception("Current password is incorrect.");
            }

            // Update password
            $password_sql = "UPDATE users SET password = ? WHERE id = ?";
            $password_stmt = $db->prepare($password_sql);
            $password_stmt->execute([
                password_hash($_POST['new_password'], PASSWORD_DEFAULT),
                $_SESSION['user_id']
            ]);
        }

        $db->commit();
        $_SESSION['success_message'] = "Profile updated successfully!";
        header("Location: index.php?page=profile");
        exit();

    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error_message'] = $e->getMessage();
    }
}
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Profile Information</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                                            <?php 
                            echo $_SESSION['success_message'];
                            unset($_SESSION['success_message']);
                            ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                            <?php endif; ?>

                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php 
                            echo $_SESSION['error_message'];
                            unset($_SESSION['error_message']);
                            ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                                            <?php endif; ?>

                    <form method="POST" class="needs-validation" novalidate>
                        <div class="row g-3">
                            <!-- Account Information -->
                            <div class="col-12">
                                <h6 class="mb-3">Account Information</h6>
                        </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($patient['email']); ?>" required>
    </div>
</div>

                            <!-- Personal Information -->
                            <div class="col-12">
                                <h6 class="mb-3">Personal Information</h6>
            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                            <label class="form-label">First Name</label>
                                    <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($patient['first_name']); ?>" required>
                                </div>
                        </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Middle Name</label>
                                    <input type="text" name="middle_name" class="form-control" value="<?php echo htmlspecialchars($patient['middle_name'] ?? ''); ?>">
                        </div>
                    </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($patient['last_name']); ?>" required>
                        </div>
                    </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                            <label class="form-label">Date of Birth</label>
                                    <input type="date" name="date_of_birth" class="form-control" value="<?php echo htmlspecialchars($patient['date_of_birth']); ?>" required>
                                </div>
                        </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                            <label class="form-label">Gender</label>
                                    <select name="gender" class="form-select" required>
                                        <option value="">Select Gender</option>
                                        <option value="Male" <?php echo $patient['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                                        <option value="Female" <?php echo $patient['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                                        <option value="Other" <?php echo $patient['gender'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>

                            <!-- Contact Information -->
                            <div class="col-12">
                                <h6 class="mb-3">Contact Information</h6>
                        </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Contact Number</label>
                                    <input type="tel" name="contact_number" class="form-control" value="<?php echo htmlspecialchars($patient['contact_number'] ?? ''); ?>">
                        </div>
                    </div>
                            <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                                    <textarea name="address" class="form-control" rows="2"><?php echo htmlspecialchars($patient['address'] ?? ''); ?></textarea>
    </div>
</div>

                            <!-- Emergency Contact -->
                            <div class="col-12">
                                <h6 class="mb-3">Emergency Contact</h6>
            </div>
                    <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Emergency Contact Name</label>
                                    <input type="text" name="emergency_contact_name" class="form-control" value="<?php echo htmlspecialchars($patient['emergency_contact_name'] ?? ''); ?>">
                                </div>
                    </div>
                    <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Emergency Contact Number</label>
                                    <input type="tel" name="emergency_contact_number" class="form-control" value="<?php echo htmlspecialchars($patient['emergency_contact_number'] ?? ''); ?>">
                                </div>
                            </div>

                            <!-- Medical Information -->
                            <div class="col-12">
                                <h6 class="mb-3">Medical Information</h6>
                            </div>
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label">Medical History</label>
                                    <textarea name="medical_history" class="form-control" rows="3"><?php echo htmlspecialchars($patient['medical_history'] ?? ''); ?></textarea>
                    </div>
                </div>

                            <!-- Change Password -->
                    <div class="col-12">
                                <h6 class="mb-3">Change Password</h6>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Current Password</label>
                                    <input type="password" name="current_password" class="form-control">
                    </div>
                </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">New Password</label>
                                    <input type="password" name="new_password" class="form-control">
                    </div>
                </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Confirm New Password</label>
                                    <input type="password" name="confirm_password" class="form-control">
                    </div>
                </div>

                    <div class="col-12">
                                <hr>
                                <button type="submit" class="btn btn-primary">
                                    <i class="material-icons align-middle me-1">save</i>
                                    Save Changes
                                </button>
                            </div>
                    </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Show/Hide Password Toggle -->
<script src="/Medbuddy/assets/js/common.js"></script>

<script>
// Form validation
(function () {
    'use strict'
    var forms = document.querySelectorAll('.needs-validation')
    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
            }

            // Password validation
            var newPassword = form.querySelector('input[name="new_password"]').value;
            var confirmPassword = form.querySelector('input[name="confirm_password"]').value;
            if (newPassword && newPassword !== confirmPassword) {
                event.preventDefault();
                alert('New passwords do not match!');
                return;
                }
                
            form.classList.add('was-validated')
        }, false)
    })
})()

// Phone number formatting
document.querySelectorAll('input[type="tel"]').forEach(function(input) {
    input.addEventListener('input', function (e) {
        let x = e.target.value.replace(/\D/g, '').match(/(\d{0,3})(\d{0,3})(\d{0,4})/);
        e.target.value = !x[2] ? x[1] : '(' + x[1] + ') ' + x[2] + (x[3] ? '-' + x[3] : '');
    });
});
</script> 