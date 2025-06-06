<?php
// Check if STAFF_ACCESS is defined
if (!defined('STAFF_ACCESS')) {
    die('Access Denied');
}
?>
<footer class="footer mt-auto py-3 bg-white border-top">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-6">
                <span class="text-muted">&copy; <?php echo date('Y'); ?> MedBuddy. All rights reserved.</span>
            </div>
            <div class="col-md-6 text-md-end">
                <a href="#" class="text-muted me-3">Privacy Policy</a>
                <a href="#" class="text-muted me-3">Terms of Service</a>
                <a href="#" class="text-muted">Contact Support</a>
            </div>
        </div>
    </div>
</footer> 