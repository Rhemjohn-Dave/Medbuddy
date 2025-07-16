<?php
session_start();
if(isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MedBuddy - Healthcare Management System</title>
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

        .login-container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .login-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4rem;
        }

        .login-info {
            flex: 1;
            max-width: 500px;
        }

        .login-info h1 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--primary);
            line-height: 1.2;
        }

        .login-info p {
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

        .login-card {
            flex: 1;
            max-width: 420px;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            transition: var(--transition);
        }

        .login-card:hover {
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

        .form-check {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .form-check-input {
            width: 18px;
            height: 18px;
            margin-right: 10px;
            cursor: pointer;
        }

        .form-check-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .form-check-label {
            font-size: 0.95rem;
            cursor: pointer;
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

        @media (max-width: 992px) {
            .login-wrapper {
                flex-direction: column;
                gap: 2rem;
            }

            .login-info {
                text-align: center;
                max-width: 100%;
            }

            .features-list {
                display: inline-block;
                text-align: left;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-wrapper">
            <div class="login-info">
                <div class="text-center mb-4">
                    <img src="../assets/images/medbuddy.png" alt="MedBuddy Logo" style="max-width: 150px; height: auto;">
                </div>
                <h1>Welcome to MedBuddy</h1>
                <p>Your comprehensive healthcare management solution. Streamline your medical practice with our integrated platform.</p>
                <ul class="features-list">
                    <li><i class="bi bi-check-circle-fill"></i> Easy appointment scheduling</li>
                    <li><i class="bi bi-check-circle-fill"></i> Secure patient records management</li>
                    <li><i class="bi bi-check-circle-fill"></i> Real-time communication</li>
                    <li><i class="bi bi-check-circle-fill"></i> Comprehensive medical history tracking</li>
                </ul>
            </div>
            
            <div class="login-card">
                <div class="card-header">
                    <h2>Sign In</h2>
                </div>
                <div class="card-body">
                    <div id="error-message" class="alert alert-danger d-none"></div>
                    <form id="loginForm" onsubmit="return handleLogin(event)">
                        <div class="form-group">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email" required>
                            <i class="bi bi-envelope input-icon"></i>
                        </div>
                        <div class="form-group">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                            <i class="bi bi-lock input-icon"></i>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="rememberMe" name="rememberMe">
                            <label class="form-check-label" for="rememberMe">Remember me</label>
                        </div>
                        <button type="submit" class="btn btn-primary" id="loginButton">
                            <i class="bi bi-box-arrow-in-right"></i>
                            Sign In
                        </button>
                    </form>
                </div>
                <div class="card-footer">
                    <a href="register.php">
                        <i class="bi bi-person-plus"></i>
                        Create an account
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
        function showError(message) {
            Swal.fire({
                title: 'Error!',
                text: message,
                icon: 'error',
                confirmButtonText: 'OK',
                confirmButtonColor: '#dc3545'
            });
        }

        function handleLogin(event) {
            event.preventDefault();
            
            const loginButton = document.getElementById('loginButton');
            loginButton.disabled = true;
            loginButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Signing in...';
            
            const formData = {
                email: document.getElementById('email').value,
                password: document.getElementById('password').value,
                rememberMe: document.getElementById('rememberMe').checked
            };

            fetch('../api/auth/login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            })
            .then(response => response.json().then(data => ({status: response.status, body: data})))
            .then(({status, body}) => {
                if (status === 200) {
                    window.location.href = '../' + body.redirect_url;
                } else {
                    showError(body.message || 'Login failed. Please try again.');
                }
                loginButton.disabled = false;
                loginButton.innerHTML = '<i class="bi bi-box-arrow-in-right"></i> Sign In';
            })
            .catch(error => {
                console.error('Error:', error);
                showError('An error occurred during login. Please try again.');
                loginButton.disabled = false;
                loginButton.innerHTML = '<i class="bi bi-box-arrow-in-right"></i> Sign In';
            });
            
            return false;
        }
    </script>
</body>
</html>