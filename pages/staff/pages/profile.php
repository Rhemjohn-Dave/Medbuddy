<?php
// Check if STAFF_ACCESS is defined
if (!defined('STAFF_ACCESS')) {
    header("Location: ../../auth/index.php");
    exit();
}

require_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get staff information
$staff_query = "SELECT s.*, u.email, u.approval_status
                FROM staff s
                JOIN users u ON s.user_id = u.id
                WHERE u.id = ?";

$stmt = $db->prepare($staff_query);
$stmt->execute([$_SESSION['user_id']]);
$staff = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        // Update basic information
        $update_sql = "UPDATE staff SET 
            first_name = ?, 
            middle_name = ?, 
            last_name = ?, 
            contact_number = ?, 
            address = ? 
            WHERE id = ?";
        $update_stmt = $db->prepare($update_sql);
        $update_stmt->execute([
            $_POST['first_name'],
            $_POST['middle_name'],
            $_POST['last_name'],
            $_POST['contact_number'],
            $_POST['address'],
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
                                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($staff['email']); ?>" required>
                                </div>
                            </div>

                            <!-- Personal Information -->
                            <div class="col-12">
                                <h6 class="mb-3">Personal Information</h6>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">First Name</label>
                                    <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($staff['first_name']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Middle Name</label>
                                    <input type="text" name="middle_name" class="form-control" value="<?php echo htmlspecialchars($staff['middle_name'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($staff['last_name']); ?>" required>
                                </div>
                            </div>

                            <!-- Contact Information -->
                            <div class="col-12">
                                <h6 class="mb-3">Contact Information</h6>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Contact Number</label>
                                    <input type="tel" name="contact_number" class="form-control" value="<?php echo htmlspecialchars($staff['contact_number'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Address</label>
                                    <textarea name="address" class="form-control" rows="2"><?php echo htmlspecialchars($staff['address'] ?? ''); ?></textarea>
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
document.querySelector('input[name="contact_number"]').addEventListener('input', function (e) {
    let x = e.target.value.replace(/\D/g, '').match(/(\d{0,3})(\d{0,3})(\d{0,4})/);
    e.target.value = !x[2] ? x[1] : '(' + x[1] + ') ' + x[2] + (x[3] ? '-' + x[3] : '');
});
</script> 