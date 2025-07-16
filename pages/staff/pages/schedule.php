<?php
// Check if STAFF_ACCESS is defined
if (!defined('STAFF_ACCESS')) {
    header("Location: ../../auth/index.php");
    exit();
}

require_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Fetch all doctor schedules
try {
    $query = "SELECT ds.*, 
                    d.first_name as doctor_first_name, 
                    d.last_name as doctor_last_name,
                    c.name as clinic_name
              FROM doctor_schedules ds
              JOIN doctors d ON ds.doctor_id = d.id
              JOIN clinics c ON ds.clinic_id = c.id
              ORDER BY d.last_name ASC, ds.day_of_week ASC, ds.start_time ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Log the error and set schedules to empty array
    error_log("Error fetching doctor schedules: " . $e->getMessage());
    $schedules = [];
    $error_message = "Could not fetch doctor schedules. Please try again later.";
}

// Map day of week numbers to names for display
$day_names = [
    1 => 'Sunday',
    2 => 'Monday',
    3 => 'Tuesday',
    4 => 'Wednesday',
    5 => 'Thursday',
    6 => 'Friday',
    7 => 'Saturday',
];
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">Doctor Schedules</h4>
        <!-- Optional: Add buttons for filtering or adding schedules if needed later -->
    </div>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger">
            <i class="material-icons align-middle me-2">error_outline</i>
            <?php echo $error_message; ?>
        </div>
    <?php elseif (empty($schedules)): ?>
        <div class="alert alert-info">
            <i class="material-icons align-middle me-2">info</i>
            No doctor schedules found.
        </div>
    <?php else: ?>
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Doctor</th>
                                <th>Day of Week</th>
                                <th>Time</th>
                                <th>Clinic</th>
                                <!-- Add more headers if needed -->
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($schedules as $schedule): ?>
                                <tr>
                                    <td>Dr. <?php echo htmlspecialchars($schedule['doctor_last_name'] . ', ' . $schedule['doctor_first_name']); ?></td>
                                    <td><?php echo htmlspecialchars($day_names[$schedule['day_of_week']]); ?></td>
                                    <td><?php echo date('h:i A', strtotime($schedule['start_time'])) . ' - ' . date('h:i A', strtotime($schedule['end_time'])); ?></td>
                                    <td><?php echo htmlspecialchars($schedule['clinic_name']); ?></td>
                                    <!-- Add more columns if needed -->
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
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