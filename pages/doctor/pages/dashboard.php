<?php
// Check if DOCTOR_ACCESS is defined
if (!defined('DOCTOR_ACCESS')) {
    header("Location: ../../auth/index.php");
    exit();
}

// Include database configuration
require_once '../../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Get doctor ID
    $stmt = $conn->prepare("SELECT id FROM doctors WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
    $doctor_id = $doctor['id'];

    // Get today's appointments count
    $today_sql = "SELECT COUNT(*) as count 
                  FROM appointments 
                  WHERE doctor_id = ? 
                  AND date = CURDATE() 
                  AND status = 'scheduled'";
    $today_stmt = $conn->prepare($today_sql);
    $today_stmt->execute([$doctor_id]);
    $today_count = $today_stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Get upcoming appointments count
    $upcoming_sql = "SELECT COUNT(*) as count 
                     FROM appointments 
                     WHERE doctor_id = ? 
                     AND date > CURDATE() 
                     AND status = 'scheduled'";
    $upcoming_stmt = $conn->prepare($upcoming_sql);
    $upcoming_stmt->execute([$doctor_id]);
    $upcoming_count = $upcoming_stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Get completed appointments count
    $completed_sql = "SELECT COUNT(*) as count 
                      FROM appointments 
                      WHERE doctor_id = ? 
                      AND status = 'completed'";
    $completed_stmt = $conn->prepare($completed_sql);
    $completed_stmt->execute([$doctor_id]);
    $completed_count = $completed_stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Get total patients count for this doctor (patients who have had appointments)
    $patients_sql = "SELECT COUNT(DISTINCT patient_id) as count 
                     FROM appointments 
                     WHERE doctor_id = ?";
    $patients_stmt = $conn->prepare($patients_sql);
    $patients_stmt->execute([$doctor_id]);
    $patients_count = $patients_stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Get today's appointments list
    $today_appointments_sql = "SELECT a.*, 
                                     p.first_name as patient_first_name, 
                                     p.last_name as patient_last_name, 
                                     c.name as clinic_name
                              FROM appointments a
                              JOIN patients p ON a.patient_id = p.id
                              JOIN clinics c ON a.clinic_id = c.id
                              WHERE a.doctor_id = ? 
                              AND a.date = CURDATE() 
                              AND a.status = 'scheduled'
                              ORDER BY a.time ASC";
    $today_appointments_stmt = $conn->prepare($today_appointments_sql);
    $today_appointments_stmt->execute([$doctor_id]);
    $today_appointments = $today_appointments_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get upcoming appointments list
    $upcoming_appointments_sql = "SELECT a.*, 
                                        p.first_name as patient_first_name, 
                                        p.last_name as patient_last_name, 
                                        c.name as clinic_name
                                 FROM appointments a
                                 JOIN patients p ON a.patient_id = p.id
                                 JOIN clinics c ON a.clinic_id = c.id
                                 WHERE a.doctor_id = ? 
                                 AND a.date > CURDATE() 
                                 AND a.status = 'scheduled'
                                 ORDER BY a.date ASC, a.time ASC
                                 LIMIT 5"; // Limit to a few upcoming
    $upcoming_appointments_stmt = $conn->prepare($upcoming_appointments_sql);
    $upcoming_appointments_stmt->execute([$doctor_id]);
    $upcoming_appointments = $upcoming_appointments_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Doctor Dashboard error: " . $e->getMessage());
    // Set default values in case of error
    $today_count = 0;
    $upcoming_count = 0;
    $completed_count = 0;
    $patients_count = 0;
    $today_appointments = [];
    $upcoming_appointments = [];
}
?>

<div class="container-fluid py-4">
    <h4>Doctor Dashboard</h4>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Today's Appointments</h5>
                    <h2 class="card-text"><?php echo $today_count; ?></h2>
                    <small>Scheduled for today</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Upcoming</h5>
                    <h2 class="card-text"><?php echo $upcoming_count; ?></h2>
                    <small>Future appointments</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Completed</h5>
                    <h2 class="card-text"><?php echo $completed_count; ?></h2>
                    <small>Total completed appointments</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5 class="card-title">My Patients</h5>
                    <h2 class="card-text"><?php echo $patients_count; ?></h2>
                    <small>Total patients</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Today's Appointments -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Today's Appointments</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($today_appointments)): ?>
                        <div class="text-center text-muted py-4">
                            <span class="material-icons mb-2" style="font-size: 2rem;">event_busy</span>
                            <p class="mb-0">No appointments scheduled for today.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($today_appointments as $appointment): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">Patient: <?php echo htmlspecialchars($appointment['patient_last_name'] . ', ' . $appointment['patient_first_name']); ?></h6>
                                            <p class="mb-1 text-muted small">
                                                <?php echo date('h:i A', strtotime($appointment['time'])); ?>
                                            </p>
                                            <p class="mb-0 text-muted small"><?php echo htmlspecialchars($appointment['clinic_name']); ?></p>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-primary">Scheduled</span>
                                            <div class="mt-2">
                                                <!-- Add action buttons like View Patient, Start Consultation etc. -->
                                                <a href="?page=appointments" class="btn btn-sm btn-outline-success">
                                                    <span class="material-icons">medical_services</span> Go to Appointments
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-white">
                    <a href="?page=appointments" class="btn btn-primary">View All Appointments</a>
                </div>
            </div>
        </div>

        <!-- Upcoming Appointments -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Upcoming Appointments</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($upcoming_appointments)): ?>
                        <div class="text-center text-muted py-4">
                            <span class="material-icons mb-2" style="font-size: 2rem;">event_busy</span>
                            <p class="mb-0">No upcoming appointments.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($upcoming_appointments as $appointment): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">Patient: <?php echo htmlspecialchars($appointment['patient_last_name'] . ', ' . $appointment['patient_first_name']); ?></h6>
                                            <p class="mb-1 text-muted small">
                                                <?php echo date('M d, Y', strtotime($appointment['date'])); ?> at 
                                                <?php echo date('h:i A', strtotime($appointment['time'])); ?>
                                            </p>
                                            <p class="mb-0 text-muted small"><?php echo htmlspecialchars($appointment['clinic_name']); ?></p>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-primary">Scheduled</span>
                                            <div class="mt-2">
                                                 <!-- Add action buttons like View Patient -->
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                 <div class="card-footer bg-white">
                    <a href="?page=appointments" class="btn btn-primary">View All Appointments</a>
                </div>
            </div>
        </div>
    </div>

     <!-- Add other sections like Recent Medical Records handled by this doctor etc. if needed -->

</div>

<!-- Add the styles from patient dashboard for consistency -->
<style>
.card {
    transition: transform 0.2s;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border: none;
    margin-bottom: 1rem;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.card-title {
    color: #344767;
    font-weight: 600;
    font-size: 1.1rem;
}

.badge {
    font-size: 0.8rem;
    padding: 0.5em 0.8em;
    font-weight: 500;
}

.list-group-item {
    border-left: none;
    border-right: none;
    padding: 1rem;
}

.list-group-item:first-child {
    border-top: none;
}

.list-group-item:last-child {
    border-bottom: none;
}

.text-muted {
    font-size: 0.85rem;
    color: #6c757d !important;
}

/* Status badge colors */
.bg-success {
    background-color: #2ecc71 !important;
}

.bg-warning {
    background-color: #f1c40f !important;
}

.bg-primary {
    background-color: #3498db !important;
}

.bg-info {
    background-color: #3498db !important;
}

/* Card body padding adjustments */
.card-body {
    padding: 1.5rem;
}

/* Table responsive adjustments */
.table-responsive {
    margin: 0;
    padding: 0;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .card-body {
        padding: 1rem;
    }
}
</style> 