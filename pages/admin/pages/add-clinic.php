<?php
// Check if this file is being included
if (!defined('ADMIN_ACCESS')) {
    header("Location: ../../index.php");
    exit();
}

require_once '../../config/database.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = new Database();
        $conn = $db->getConnection();

        // Validate and sanitize input
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
        $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
        $contact_number = filter_input(INPUT_POST, 'contact_number', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $operating_hours = filter_input(INPUT_POST, 'operating_hours', FILTER_SANITIZE_STRING);
        $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);

        // Validate required fields
        if (empty($name) || empty($address) || empty($contact_number) || empty($email)) {
            throw new Exception("Please fill in all required fields.");
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please enter a valid email address.");
        }

        // Insert clinic into database
        $stmt = $conn->prepare("
            INSERT INTO clinics (name, address, contact_number, email, operating_hours, description, created_at)
            VALUES (:name, :address, :contact_number, :email, :operating_hours, :description, NOW())
        ");

        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':contact_number', $contact_number);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':operating_hours', $operating_hours);
        $stmt->bindParam(':description', $description);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Clinic added successfully!";
            header("Location: index.php?page=reports");
            exit();
        } else {
            throw new Exception("Error adding clinic to database.");
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
}
?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Add New Clinic</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php 
                            echo $_SESSION['error_message'];
                            unset($_SESSION['error_message']);
                            ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="name" class="form-label">Clinic Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required>
                            <div class="invalid-feedback">Please enter the clinic name.</div>
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label">Address <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="address" name="address" rows="3" required></textarea>
                            <div class="invalid-feedback">Please enter the clinic address.</div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="contact_number" class="form-label">Contact Number <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="contact_number" name="contact_number" required>
                                <div class="invalid-feedback">Please enter a valid contact number.</div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" required>
                                <div class="invalid-feedback">Please enter a valid email address.</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="operating_hours" class="form-label">Operating Hours</label>
                            <input type="text" class="form-control" id="operating_hours" name="operating_hours" 
                                   placeholder="e.g., Monday-Friday: 9:00 AM - 5:00 PM">
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="4" 
                                      placeholder="Enter clinic description, services offered, etc."></textarea>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="index.php?page=reports" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="material-icons align-middle me-1">add</i>
                                Add Clinic
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

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
            form.classList.add('was-validated')
        }, false)
    })
})()

// Phone number formatting
document.getElementById('contact_number').addEventListener('input', function (e) {
    let x = e.target.value.replace(/\D/g, '').match(/(\d{0,3})(\d{0,3})(\d{0,4})/);
    e.target.value = !x[2] ? x[1] : '(' + x[1] + ') ' + x[2] + (x[3] ? '-' + x[3] : '');
});
</script>

<style>
.form-label {
    font-weight: 500;
}

.text-danger {
    font-size: 0.875em;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.material-icons {
    font-size: 1.25rem;
}
</style> 