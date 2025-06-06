<?php
// Check if STAFF_ACCESS is defined
if (!defined('STAFF_ACCESS')) {
    header("Location: ../../login.php");
    exit();
}

// Get current page
$current_page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
?>
<div class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        <div class="d-flex align-items-center justify-content-between mb-3 px-3">
            <h5 class="text-primary mb-0">MedBuddy</h5>
            <button class="btn d-md-none" type="button" data-bs-toggle="collapse" data-bs-target=".sidebar-collapse">
                <i class="material-icons">menu</i>
            </button>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>" href="?page=dashboard">
                    <i class="material-icons">dashboard</i>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'appointments' ? 'active' : ''; ?>" href="?page=appointments">
                    <i class="material-icons">event</i>
                    Appointments
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'patients' ? 'active' : ''; ?>" href="?page=patients">
                    <i class="material-icons">people</i>
                    Patients
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'messages' ? 'active' : ''; ?>" href="?page=messages">
                    <i class="material-icons">message</i>
                    Messages
                </a>
            </li>
        </ul>
    </div>
</div> 