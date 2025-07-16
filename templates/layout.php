<?php
// Check if the required constants are defined
if (!defined('ROLE_ACCESS')) {
    header("Location: ../auth/index.php");
    exit();
}

// Get current page
$current_page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// Get user's full name based on role
$user_full_name = '';
if (isset($_SESSION['role']) && isset($_SESSION['user_id'])) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "";
        switch ($_SESSION['role']) {
            case 'doctor':
                $query = "SELECT first_name, last_name FROM doctors WHERE user_id = " . intval($_SESSION['user_id']);
                break;
            case 'patient':
                $query = "SELECT first_name, last_name FROM patients WHERE user_id = " . intval($_SESSION['user_id']);
                break;
            case 'staff':
                $query = "SELECT first_name, last_name FROM staff WHERE user_id = " . intval($_SESSION['user_id']);
                break;
            case 'admin':
                $user_full_name = 'Administrator';
                break;
        }
        
        if (!empty($query)) {
            $stmt = $db->query($query);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $user_full_name = $user['first_name'] . ' ' . $user['last_name'];
            }
        }
    } catch (PDOException $e) {
        // Log the error but don't show it to the user
        error_log("Error fetching user name: " . $e->getMessage());
    }
}

// If no name was found, use a generic label
if (empty($user_full_name)) {
    $user_full_name = 'User';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'MedBuddy'; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Material icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <!-- Google Fonts - Roboto -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../assets/css/style.css">
    <?php if (isset($extra_css)): ?>
        <?php foreach ($extra_css as $css): ?>
            <link rel="stylesheet" href="<?php echo $css; ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <nav id="sidebar" class="sidebar">
            <div class="sidebar-header">
                <h3>MedBuddy</h3>
            </div>
            <div class="pt-3 px-2">
                <ul class="nav flex-column">
                    <?php foreach ($sidebar_menu as $menu_item): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === $menu_item['page'] ? 'active' : ''; ?>" 
                               href="?page=<?php echo $menu_item['page']; ?>">
                                <span class="material-icons"><?php echo $menu_item['icon']; ?></span>
                                <?php echo $menu_item['label']; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </nav>

        <!-- Page Content -->
        <div id="content">
            <!-- Navbar -->
            <nav class="navbar navbar-expand-lg navbar-light">
                <div class="container-fluid">
                    <button type="button" id="sidebarCollapse" class="btn btn-link">
                        <span class="material-icons">menu</span>
                    </button>
                    <span class="navbar-brand"><?php echo $page_title ?? 'Dashboard'; ?></span>
                    <div class="ms-auto d-flex align-items-center">
                        <!-- Notifications -->
                        <?php if (isset($show_notifications) && $show_notifications): ?>
                        <div class="dropdown me-3">
                            <button class="btn btn-link position-relative" type="button" id="notificationsDropdown" data-bs-toggle="dropdown">
                                <span class="material-icons">notifications</span>
                                <?php if (isset($notification_count) && $notification_count > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?php echo $notification_count; ?>
                                </span>
                                <?php endif; ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown">
                                <li><h6 class="dropdown-header">Notifications</h6></li>
                                <?php if (isset($notifications) && !empty($notifications)): ?>
                                    <?php foreach ($notifications as $notification): ?>
                                    <li><a class="dropdown-item" href="<?php echo $notification['link']; ?>"><?php echo $notification['message']; ?></a></li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li><a class="dropdown-item" href="#">No new notifications</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-center" href="?page=notifications">View all notifications</a></li>
                            </ul>
                        </div>
                        <?php endif; ?>

                        <!-- User Menu -->
                        <div class="dropdown">
                            <button class="btn btn-link dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown">
                                <span class="material-icons">account_circle</span>
                                <span><?php echo htmlspecialchars($user_full_name); ?></span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="?page=profile"><span class="material-icons">person</span>Profile</a></li>
                                <li><a class="dropdown-item" href="?page=settings"><span class="material-icons">settings</span>Settings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="#" onclick="confirmLogout(event)"><span class="material-icons">logout</span>Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Main Content -->
            <div class="main-content">
                <?php echo $content ?? ''; ?>
            </div>

            <!-- Footer -->
            <footer class="footer">
                <div class="container-fluid">
                    <p class="text-center mb-0">&copy; <?php echo date('Y'); ?> MedBuddy. All rights reserved.</p>
                </div>
            </footer>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Custom JS -->
    <script src="/Medbuddy/assets/js/common.js"></script>
    <?php if (isset($extra_js)): ?>
        <?php foreach ($extra_js as $js): ?>
            <script src="<?php echo $js; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    <script>
        // Toggle sidebar
        document.getElementById('sidebarCollapse').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('content').classList.toggle('active');
        });

        // Initialize page-specific scripts if they exist
        if (typeof initPage === 'function') {
            initPage();
        }

        // Logout confirmation function
        function confirmLogout(event) {
            event.preventDefault();
            Swal.fire({
                title: 'Logout',
                text: 'Are you sure you want to logout?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, logout',
                cancelButtonText: 'Cancel',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../../auth/logout.php';
                }
            });
        }
    </script>
</body>
</html> 