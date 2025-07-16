<?php
// Check if STAFF_ACCESS is defined
if (!defined('STAFF_ACCESS')) {
    header("Location: ../../auth/index.php");
    exit();
}

require_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Fetch all doctors and their schedules
try {
    $query = "SELECT d.id as doctor_id, 
                    d.first_name as doctor_first_name, 
                    d.middle_name as doctor_middle_name,
                    d.last_name as doctor_last_name,
                    s.name as specialization,
                    d.license_number,
                    ds.id as schedule_id,
                    ds.day_of_week,
                    ds.start_time,
                    ds.end_time,
                    c.name as clinic_name,
                    c.address as clinic_address
              FROM doctors d
              LEFT JOIN specializations s ON d.specialization_id = s.id
              LEFT JOIN doctor_schedules ds ON d.id = ds.doctor_id
              LEFT JOIN clinics c ON ds.clinic_id = c.id
              ORDER BY d.last_name ASC, ds.day_of_week ASC, ds.start_time ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organize data by doctor
    $doctors = [];
    foreach ($results as $row) {
        $doctor_id = $row['doctor_id'];
        if (!isset($doctors[$doctor_id])) {
            $doctors[$doctor_id] = [
                'id' => $doctor_id,
                'first_name' => $row['doctor_first_name'],
                'middle_name' => $row['doctor_middle_name'],
                'last_name' => $row['doctor_last_name'],
                'specialization' => $row['specialization'],
                'license_number' => $row['license_number'],
                'schedules' => []
            ];
        }
        // Add schedule if it exists for this doctor
        if (!empty($row['schedule_id'])) {
             $doctors[$doctor_id]['schedules'][] = [
                 'id' => $row['schedule_id'],
                 'day_of_week' => $row['day_of_week'],
                 'start_time' => $row['start_time'],
                 'end_time' => $row['end_time'],
                 'clinic_name' => $row['clinic_name'],
                 'clinic_address' => $row['clinic_address']
             ];
        }
    }
    
    // Convert associative array to indexed array for easier iteration in HTML
    $doctors = array_values($doctors);

} catch (PDOException $e) {
    // Log the error and set doctors to empty array
    error_log("Error fetching doctors and schedules: " . $e->getMessage());
    $doctors = [];
    $error_message = "Could not fetch doctor information. Please try again later.";
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
        <h4 class="mb-0">Doctors & Schedules</h4>
    </div>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger">
            <i class="material-icons align-middle me-2">error_outline</i>
            <?php echo $error_message; ?>
        </div>
    <?php elseif (empty($doctors)): ?>
        <div class="alert alert-info">
            <i class="material-icons align-middle me-2">info</i>
            No doctors found.
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php foreach ($doctors as $doctor): ?>
                <div class="col">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars('Dr. ' . $doctor['last_name'] . ', ' . $doctor['first_name']); ?></h5>
                            <p class="card-text mb-2"><strong>Specialization:</strong> <?php echo htmlspecialchars($doctor['specialization'] ?? 'N/A'); ?></p>
                            <p class="card-text mb-3"><strong>License No.:</strong> <?php echo htmlspecialchars($doctor['license_number'] ?? 'N/A'); ?></p>
                            
                            <h6>Schedules:</h6>
                            <?php if (empty($doctor['schedules'])): ?>
                                <p class="text-muted">No schedules available.</p>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($doctor['schedules'] as $schedule): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?php echo htmlspecialchars($day_names[$schedule['day_of_week']]); ?>:</strong>
                                                <?php echo date('h:i A', strtotime($schedule['start_time'])) . ' - ' . date('h:i A', strtotime($schedule['end_time'])); ?><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($schedule['clinic_name']); ?></small>
                                            </div>
                                            <!-- Optional: Add action buttons per schedule item if needed -->
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                        <!-- Optional: Add card footer for general doctor actions -->
                        <!-- <div class="card-footer"></div> -->
                    </div>
                </div>
            <?php endforeach; ?>
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
    padding: 0.75rem 0;
    border-color: #f1f1f1;
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

/* Responsive adjustments */
@media (max-width: 768px) {
    .card-body {
        padding: 1rem;
    }
}
</style> 