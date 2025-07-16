<?php
session_start();
if(isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Load doctors list
require_once '../config/database.php';
$doctors = [];
$specializations = [];

try {
    // Create database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get active doctors
    $stmt = $conn->prepare("SELECT id, first_name, middle_name, last_name, license_number FROM doctors WHERE status = 'active'");
    $stmt->execute();
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get specializations
    $spec_stmt = $conn->prepare("SELECT id, name FROM specializations ORDER BY name");
    $spec_stmt->execute();
    $specializations = $spec_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    // Handle error silently
    error_log("Error loading doctors or specializations: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MedBuddy - Register</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Material icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary: #34a853;
            --light-bg: #f8fafc;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --shadow-sm: 0 2px 10px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 20px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 8px 30px rgba(0, 0, 0, 0.12);
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            color: var(--text-dark);
            padding: 2rem 0;
        }

        .register-container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .register-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4rem;
        }

        .register-info {
            flex: 1;
            max-width: 500px;
        }

        .register-info h1 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--primary);
            line-height: 1.2;
        }

        .register-info p {
            font-size: 1.1rem;
            color: var(--text-muted);
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .features-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .features-list li {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            color: var(--text-dark);
        }

        .features-list li i {
            color: var(--primary);
            font-size: 1.25rem;
        }

        .register-card {
            flex: 1;
            max-width: 600px;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            transition: var(--transition);
        }

        .register-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg), 0 10px 40px rgba(37, 99, 235, 0.15);
        }

        .card-header {
            background: var(--primary);
            padding: 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .card-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
            transform: rotate(30deg);
        }

        .card-header h2 {
            color: white;
            font-size: 1.75rem;
            font-weight: 600;
            margin: 0;
            position: relative;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .card-body {
            padding: 2.5rem;
        }

        .form-group {
            margin-bottom: 1.75rem;
            position: relative;
        }

        .form-label {
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            font-size: 0.95rem;
        }

        .form-control {
            height: 52px;
            border-radius: 12px;
            padding: 0.75rem 1rem 0.75rem 3rem;
            border: 1.5px solid var(--border-color);
            font-size: 1rem;
            transition: var(--transition);
            background-color: var(--light-bg);
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.15);
            background-color: white;
        }

        textarea.form-control {
            height: auto;
            min-height: 100px;
            padding-top: 1rem;
        }

        .input-icon {
            position: absolute;
            left: 12px;
            top: 40px;
            color: var(--text-muted);
            transition: var(--transition);
        }

        .form-control:focus + .input-icon,
        .form-control:not(:placeholder-shown) + .input-icon {
            color: var(--primary);
        }

        .form-select {
            height: 52px;
            border-radius: 12px;
            padding: 0.75rem 1rem 0.75rem 3rem;
            border: 1.5px solid var(--border-color);
            font-size: 1rem;
            transition: var(--transition);
            background-color: var(--light-bg);
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 16px 12px;
        }

        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.15);
            background-color: white;
        }

        .btn-primary {
            background: var(--primary);
            border: none;
            padding: 0.9rem 1.5rem;
            font-weight: 600;
            font-size: 1rem;
            border-radius: 12px;
            width: 100%;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(37, 99, 235, 0.25);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .card-footer {
            background: var(--light-bg);
            border-top: 1px solid var(--border-color);
            padding: 1.5rem 2rem;
            text-align: center;
        }

        .card-footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-footer a:hover {
            color: var(--primary-dark);
        }

        .role-fields {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 992px) {
            .register-wrapper {
                flex-direction: column;
                gap: 2rem;
            }

            .register-info {
                text-align: center;
                max-width: 100%;
            }

            .features-list {
                display: inline-block;
                text-align: left;
            }

            .register-card {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-wrapper">
            <div class="register-info">
                <div class="text-center mb-4">
                    <img src="../assets/images/medbuddy.png" alt="MedBuddy Logo" style="max-width: 150px; height: auto;">
                </div>
                <h1>Join MedBuddy</h1>
                <p>Create your account and experience the future of healthcare management. Our platform connects patients, doctors, and medical staff in one seamless ecosystem.</p>
                <ul class="features-list">
                    <li><i class="bi bi-shield-check"></i> Secure and private</li>
                    <li><i class="bi bi-clock"></i> 24/7 access to medical records</li>
                    <li><i class="bi bi-chat-dots"></i> Direct communication with healthcare providers</li>
                    <li><i class="bi bi-calendar-check"></i> Easy appointment management</li>
                </ul>
            </div>
            
            <div class="register-card">
                <div class="card-header">
                    
                    <h2>Create Account</h2>
                </div>
                <div class="card-body">
                    <div id="error-message" class="alert alert-danger d-none"></div>
                    <div id="success-message" class="alert alert-success d-none"></div>
                    
                    <form id="registerForm" onsubmit="return handleRegister(event)">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="firstName" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="firstName" name="firstName" placeholder="Enter first name" required>
                                <i class="bi bi-person input-icon"></i>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="lastName" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="lastName" name="lastName" placeholder="Enter last name" required>
                                <i class="bi bi-person input-icon"></i>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email address" required>
                            <i class="bi bi-envelope input-icon"></i>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Create a password" required>
                            <i class="bi bi-lock input-icon"></i>
                        </div>

                        <div class="mb-3">
                            <label for="confirmPassword" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" placeholder="Confirm your password" required>
                            <i class="bi bi-lock-fill input-icon"></i>
                        </div>

                        <div class="mb-3">
                            <label for="role" class="form-label">Register as</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="">Select your role</option>
                                <option value="patient">Patient</option>
                                <option value="doctor">Doctor</option>
                                <option value="staff">Medical Staff</option>
                            </select>
                            <i class="bi bi-person-badge input-icon"></i>
                        </div>

                        <!-- Additional fields for doctors -->
                        <div id="doctorFields" class="role-fields">
                            <div class="mb-3">
                                <label for="specialization" class="form-label">Specialization</label>
                                <select class="form-select" id="specialization" name="specialization">
                                    <option value="">Select Specialization</option>
                                    <?php foreach ($specializations as $spec): ?>
                                        <option value="<?php echo htmlspecialchars($spec['id']); ?>"><?php echo htmlspecialchars($spec['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <i class="bi bi-briefcase input-icon"></i>
                            </div>
                            <div class="mb-3">
                                <label for="licenseNumber" class="form-label">License Number</label>
                                <input type="text" class="form-control" id="licenseNumber" name="licenseNumber" placeholder="Enter your license number">
                                <i class="bi bi-card-text input-icon"></i>
                            </div>
                            <div class="mb-3">
                                <label for="doctorContact" class="form-label">Contact Number</label>
                                <input type="tel" class="form-control" id="doctorContact" name="doctorContact" placeholder="Enter your contact number">
                                <i class="bi bi-telephone input-icon"></i>
                            </div>
                            <div class="mb-3">
                                <label for="doctorAddress" class="form-label">Address</label>
                                <textarea class="form-control" id="doctorAddress" name="doctorAddress" rows="2" placeholder="Enter your address"></textarea>
                                <i class="bi bi-geo-alt input-icon"></i>
                            </div>
                        </div>

                        <!-- Additional fields for patients -->
                        <div id="patientFields" class="role-fields">
                            <div class="mb-3">
                                <label for="dateOfBirth" class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" id="dateOfBirth" name="dateOfBirth" required>
                                <i class="bi bi-calendar input-icon"></i>
                            </div>
                            <div class="mb-3">
                                <label for="gender" class="form-label">Gender</label>
                                <select class="form-select" id="gender" name="gender" required>
                                    <option value="">Select gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                                <i class="bi bi-gender-ambiguous input-icon"></i>
                            </div>
                            <div class="mb-3">
                                <label for="bloodType" class="form-label">Blood Type</label>
                                <select class="form-select" id="bloodType" name="bloodType">
                                    <option value="">Select blood type</option>
                                    <option value="A+">A+</option>
                                    <option value="A-">A-</option>
                                    <option value="B+">B+</option>
                                    <option value="B-">B-</option>
                                    <option value="AB+">AB+</option>
                                    <option value="AB-">AB-</option>
                                    <option value="O+">O+</option>
                                    <option value="O-">O-</option>
                                </select>
                                <i class="bi bi-droplet input-icon"></i>
                            </div>
                            <div class="mb-3">
                                <label for="patientContact" class="form-label">Contact Number</label>
                                <input type="tel" class="form-control" id="patientContact" name="patientContact" placeholder="Enter your contact number" required>
                                <i class="bi bi-telephone input-icon"></i>
                            </div>
                            <div class="mb-3">
                                <label for="patientAddress" class="form-label">Address</label>
                                <textarea class="form-control" id="patientAddress" name="patientAddress" rows="2" placeholder="Enter your address" required></textarea>
                                <i class="bi bi-geo-alt input-icon"></i>
                            </div>
                            <div class="mb-3">
                                <label for="emergencyContact" class="form-label">Emergency Contact Name</label>
                                <input type="text" class="form-control" id="emergencyContact" name="emergencyContact" placeholder="Enter emergency contact name" required>
                                <i class="bi bi-person-plus input-icon"></i>
                            </div>
                            <div class="mb-3">
                                <label for="emergencyNumber" class="form-label">Emergency Contact Number</label>
                                <input type="tel" class="form-control" id="emergencyNumber" name="emergencyNumber" placeholder="Enter emergency contact number" required>
                                <i class="bi bi-telephone-plus input-icon"></i>
                            </div>
                            <div class="mb-3">
                                <label for="medicalHistory" class="form-label">Medical History</label>
                                <textarea class="form-control" id="medicalHistory" name="medicalHistory" rows="3" placeholder="Enter any relevant medical history"></textarea>
                                <i class="bi bi-clipboard2-pulse input-icon"></i>
                            </div>
                            <div class="mb-3">
                                <label for="allergies" class="form-label">Allergies</label>
                                <textarea class="form-control" id="allergies" name="allergies" rows="2" placeholder="Enter any allergies (if none, type 'None')"></textarea>
                                <i class="bi bi-exclamation-triangle input-icon"></i>
                            </div>
                        </div>

                        <!-- Additional fields for staff -->
                        <div id="staffFields" class="role-fields">
                            <div class="mb-3">
                                <label for="staffContact" class="form-label">Contact Number</label>
                                <input type="tel" class="form-control" id="staffContact" name="staffContact" placeholder="Enter your contact number">
                                <i class="bi bi-telephone input-icon"></i>
                            </div>
                            <div class="mb-3">
                                <label for="staffAddress" class="form-label">Address</label>
                                <textarea class="form-control" id="staffAddress" name="staffAddress" rows="2" placeholder="Enter your address"></textarea>
                                <i class="bi bi-geo-alt input-icon"></i>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary" id="registerButton">
                            <i class="bi bi-person-plus"></i>
                            Create Account
                        </button>
                    </form>
                </div>
                <div class="card-footer">
                    <a href="index.php">
                        <i class="bi bi-arrow-left"></i>
                        Back to Login
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Show/Hide Password Toggle -->
    <script src="../assets/js/common.js"></script>
    
    <script>
        // Show/hide fields based on role selection
        document.getElementById('role').addEventListener('change', function() {
            const doctorFields = document.getElementById('doctorFields');
            const patientFields = document.getElementById('patientFields');
            const staffFields = document.getElementById('staffFields');
            
            // Hide all role-specific fields first
            doctorFields.style.display = 'none';
            patientFields.style.display = 'none';
            staffFields.style.display = 'none';
            
            // Remove required attributes
            document.getElementById('specialization').required = false;
            document.getElementById('licenseNumber').required = false;
            document.getElementById('doctorContact').required = false;
            document.getElementById('doctorAddress').required = false;
            document.getElementById('dateOfBirth').required = false;
            document.getElementById('gender').required = false;
            document.getElementById('bloodType').required = false;
            document.getElementById('patientContact').required = false;
            document.getElementById('patientAddress').required = false;
            document.getElementById('emergencyContact').required = false;
            document.getElementById('emergencyNumber').required = false;
            document.getElementById('staffContact').required = false;
            document.getElementById('staffAddress').required = false;
            
            // Show relevant fields based on role
            if (this.value === 'doctor') {
                doctorFields.style.display = 'block';
                document.getElementById('specialization').required = true;
                document.getElementById('licenseNumber').required = true;
                document.getElementById('doctorContact').required = true;
                document.getElementById('doctorAddress').required = true;
            } else if (this.value === 'patient') {
                patientFields.style.display = 'block';
                document.getElementById('dateOfBirth').required = true;
                document.getElementById('gender').required = true;
                document.getElementById('bloodType').required = true;
                document.getElementById('patientContact').required = true;
                document.getElementById('patientAddress').required = true;
                document.getElementById('emergencyContact').required = true;
                document.getElementById('emergencyNumber').required = true;
            } else if (this.value === 'staff') {
                staffFields.style.display = 'block';
                document.getElementById('staffContact').required = true;
                document.getElementById('staffAddress').required = true;
            }
        });

        function showError(message) {
            Swal.fire({
                title: 'Error!',
                text: message,
                icon: 'error',
                confirmButtonText: 'OK',
                confirmButtonColor: '#dc3545'
            });
        }

        function showSuccess(message) {
            Swal.fire({
                title: 'Success!',
                text: message,
                icon: 'success',
                confirmButtonText: 'OK',
                confirmButtonColor: '#28a745',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: false
            }).then(() => {
                window.location.href = 'index.php';
            });
        }

        function handleRegister(event) {
            event.preventDefault();
            
            let registerButton = document.getElementById('registerButton');
            registerButton.disabled = true;
            registerButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Creating account...';
            
            // Get form data
            let formData = {
                firstName: document.getElementById('firstName').value,
                lastName: document.getElementById('lastName').value,
                email: document.getElementById('email').value,
                password: document.getElementById('password').value,
                confirmPassword: document.getElementById('confirmPassword').value,
                role: document.getElementById('role').value
            };

            // Add role-specific fields
            if (formData.role === 'doctor') {
                formData.specialization = document.getElementById('specialization').value;
                formData.licenseNumber = document.getElementById('licenseNumber').value;
                formData.contactNumber = document.getElementById('doctorContact').value;
                formData.address = document.getElementById('doctorAddress').value;
            } else if (formData.role === 'patient') {
                formData.dateOfBirth = document.getElementById('dateOfBirth').value;
                formData.gender = document.getElementById('gender').value;
                formData.bloodType = document.getElementById('bloodType').value;
                formData.contactNumber = document.getElementById('patientContact').value;
                formData.address = document.getElementById('patientAddress').value;
                formData.emergencyContactName = document.getElementById('emergencyContact').value;
                formData.emergencyContactNumber = document.getElementById('emergencyNumber').value;
                formData.medicalHistory = document.getElementById('medicalHistory').value;
                formData.allergies = document.getElementById('allergies').value || 'None';
            } else if (formData.role === 'staff') {
                formData.contactNumber = document.getElementById('staffContact').value;
                formData.address = document.getElementById('staffAddress').value;
            }

            // Validate passwords match
            if (formData.password !== formData.confirmPassword) {
                showError('Passwords do not match!');
                registerButton.disabled = false;
                registerButton.innerHTML = '<i class="bi bi-person-plus"></i> Create Account';
                return false;
            }

            // Validate date of birth for patients
            if (formData.role === 'patient') {
                const dob = new Date(formData.dateOfBirth);
                const today = new Date();
                let age = today.getFullYear() - dob.getFullYear();
                const monthDiff = today.getMonth() - dob.getMonth();
                
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
                    age--;
                }
                
                if (age < 0) {
                    showError('Invalid date of birth!');
                    registerButton.disabled = false;
                    registerButton.innerHTML = '<i class="bi bi-person-plus"></i> Create Account';
                    return false;
                }
            }

            // Send registration request
            fetch('../api/auth/register.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            })
            .then(response => response.json().then(data => ({status: response.status, body: data})))
            .then(({status, body}) => {
                if (status === 201) {
                    showSuccess('Registration successful! Please wait for admin approval.');
                    document.getElementById('registerForm').reset();
                } else {
                    showError(body.message || 'Registration failed. Please try again.');
                }
                registerButton.disabled = false;
                registerButton.innerHTML = '<i class="bi bi-person-plus"></i> Create Account';
            })
            .catch(error => {
                console.error('Error:', error);
                showError('An error occurred during registration. Please try again.');
                registerButton.disabled = false;
                registerButton.innerHTML = '<i class="bi bi-person-plus"></i> Create Account';
            });
            
            return false;
        }
    </script>
</body>
</html> 