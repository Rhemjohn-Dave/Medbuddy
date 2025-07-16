<?php
// Check if STAFF_ACCESS is defined
if (!defined('STAFF_ACCESS')) {
    header("Location: ../../login.php");
    exit();
}
?>
<nav class="navbar navbar-expand-lg navbar-light bg-white mb-4 border-bottom">
    <div class="container-fluid">
        <button class="btn d-md-none" type="button" data-bs-toggle="collapse" data-bs-target=".sidebar-collapse">
            <i class="material-icons">menu</i>
        </button>
        
        <div class="d-flex align-items-center ms-auto">
            <div class="dropdown">
                <button class="btn btn-link text-dark text-decoration-none dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="material-icons">account_circle</i>
                    <span class="ms-1">User</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                    <li>
                        <a class="dropdown-item" href="?page=profile">
                            <i class="material-icons">person</i>
                            Profile
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="?page=settings">
                            <i class="material-icons">settings</i>
                            Settings
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item text-danger" href="../../auth/logout.php">
                            <i class="material-icons">logout</i>
                            Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav> 