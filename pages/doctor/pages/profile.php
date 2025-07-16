<?php
// Check if DOCTOR_ACCESS is defined
if (!defined('DOCTOR_ACCESS')) {
    header("Location: ../../auth/index.php");
    exit();
}

require_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get doctor information
$doctor_query = "SELECT d.*, u.email, u.approval_status, s.name as specialization_name
                FROM doctors d
                JOIN users u ON d.user_id = u.id
                LEFT JOIN specializations s ON d.specialization_id = s.id
                WHERE u.id = ?";
$stmt = $db->prepare($doctor_query);
$stmt->execute([$_SESSION['user_id']]);
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);

// Get clinics for this doctor
$clinics_query = "SELECT c.* FROM clinics c
                  JOIN doctor_clinics dc ON c.id = dc.clinic_id
                  WHERE dc.doctor_id = ? AND c.status = 'active'";
$doctor_id = $doctor['id'];
$stmt = $db->prepare($clinics_query);
$stmt->execute([$doctor_id]);
$clinics = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        // Update basic information
        $update_sql = "UPDATE doctors SET 
            first_name = ?, 
            middle_name = ?, 
            last_name = ?, 
            license_number = ?, 
            contact_number = ?, 
            address = ?, 
            specialization_id = ? 
            WHERE id = ?";
        $update_stmt = $db->prepare($update_sql);
        $update_stmt->execute([
            $_POST['first_name'],
            $_POST['middle_name'],
            $_POST['last_name'],
            $_POST['license_number'],
            $_POST['contact_number'],
            $_POST['address'],
            $_POST['specialization_id'],
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
                    <h5 class="card-title mb-0">Doctor Profile</h5>
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
                                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($doctor['email']); ?>" required>
                                </div>
                            </div>

                            <!-- Personal Information -->
                            <div class="col-12">
                                <h6 class="mb-3">Personal Information</h6>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">First Name</label>
                                    <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($doctor['first_name']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Middle Name</label>
                                    <input type="text" name="middle_name" class="form-control" value="<?php echo htmlspecialchars($doctor['middle_name'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($doctor['last_name']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">License Number</label>
                                    <input type="text" name="license_number" class="form-control" value="<?php echo htmlspecialchars($doctor['license_number']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Contact Number</label>
                                    <input type="tel" name="contact_number" class="form-control" value="<?php echo htmlspecialchars($doctor['contact_number'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label">Address</label>
                                    <textarea name="address" class="form-control" rows="2"><?php echo htmlspecialchars($doctor['address'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Specialization</label>
                                    <select name="specialization_id" class="form-select" required>
                                        <option value="">Select Specialization</option>
                                        <?php
                                        $spec_stmt = $db->query("SELECT id, name FROM specializations ORDER BY name");
                                        $specializations = $spec_stmt->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($specializations as $spec): ?>
                                            <option value="<?php echo $spec['id']; ?>" <?php echo ($doctor['specialization_id'] == $spec['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($spec['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Clinic Details (readonly) -->
                            <div class="col-12">
                                <h6 class="mb-3">Clinic Details (Read Only)</h6>
                            </div>
                            <?php if (!empty($clinics)): ?>
                                <?php foreach ($clinics as $clinic): ?>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Clinic Name</label>
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($clinic['name']); ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Clinic Address</label>
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($clinic['address']); ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Clinic Phone</label>
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($clinic['phone']); ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Clinic Email</label>
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($clinic['email']); ?>" readonly>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-12">
                                    <div class="alert alert-info">No clinics assigned.</div>
                                </div>
                            <?php endif; ?>

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
                                    <input type="password" name="confirm_new_password" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="text-end mt-3">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Show/Hide Password Toggle -->
<script src="/Medbuddy/assets/js/common.js"></script> 