<?php
// Check if DOCTOR_ACCESS is defined
if (!defined('DOCTOR_ACCESS')) {
    header("Location: ../../auth/index.php");
    exit();
}

// Include database connection
require_once '../../config/database.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Get doctor ID from session
$user_id = $_SESSION['user_id'];

// Get doctor's ID from the doctors table
$query = "SELECT id FROM doctors WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $user_id);
$stmt->execute();
$doctor = $stmt->fetch();
$doctor_id = $doctor['id'];

// Get current date and day of week
$current_date = date('Y-m-d');
$php_day = date('N'); // 1 (Monday) to 7 (Sunday)
// Convert to match the database format (1 = Sunday, 7 = Saturday)
$day_of_week = $php_day == 7 ? 1 : $php_day + 1;

// Debug day conversion
error_log("PHP day of week: " . $php_day);
error_log("Converted day of week: " . $day_of_week);

// Get doctor's schedule for the calendar
$query = "SELECT ds.*, c.name as clinic_name 
          FROM doctor_schedules ds 
          JOIN clinics c ON ds.clinic_id = c.id 
          WHERE ds.doctor_id = :doctor_id";
$stmt = $db->prepare($query);
$stmt->bindParam(":doctor_id", $doctor_id);
$stmt->execute();
$schedules = $stmt->fetchAll();

// Debug schedules
error_log("Schedules found: " . count($schedules));
foreach ($schedules as $schedule) {
    error_log("Schedule - Day: " . $schedule['day_of_week'] . ", Clinic: " . $schedule['clinic_name']);
}

// Convert schedules to calendar events format
$calendar_events = [];
// Remove schedule events from calendar display
// foreach ($schedules as $schedule) {
//     $calendar_events[] = [...];
// }

// Get appointments for the calendar
$query = "SELECT a.*, 
                 CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                 p.contact_number,
                 c.name as clinic_name
          FROM appointments a 
          JOIN patients p ON a.patient_id = p.id 
          JOIN clinics c ON a.clinic_id = c.id
          WHERE a.doctor_id = :doctor_id 
          AND a.date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
          AND a.date <= DATE_ADD(CURDATE(), INTERVAL 2 MONTH)";
$stmt = $db->prepare($query);
$stmt->bindParam(":doctor_id", $doctor_id);
$stmt->execute();
$appointments = $stmt->fetchAll();

// Convert appointments to calendar events format
foreach ($appointments as $appointment) {
    $calendar_events[] = [
        'title' => $appointment['patient_name'],
        'start' => $appointment['date'] . 'T' . $appointment['time'],
        'end' => $appointment['date'] . 'T' . date('H:i:s', strtotime($appointment['time'] . ' +1 hour')),
        'color' => $appointment['status'] === 'scheduled' ? '#2196F3' : 
                  ($appointment['status'] === 'completed' ? '#4CAF50' : '#FFA726'),
        'textColor' => '#fff',
        'extendedProps' => [
            'type' => 'appointment',
            'appointment_id' => intval($appointment['id']),
            'status' => $appointment['status'],
            'purpose' => $appointment['purpose'],
            'patient_name' => $appointment['patient_name'],
            'clinic_name' => $appointment['clinic_name'],
            'contact_number' => $appointment['contact_number'] ?? 'N/A'
        ]
    ];
}

$calendar_events_json = json_encode($calendar_events);

try {
    // Debug current date and doctor ID
    error_log("Current date: " . date('Y-m-d'));
    error_log("Doctor ID: " . $doctor_id);

    // First, let's check what appointments exist for this doctor without any date filters
    $debug_query = "SELECT COUNT(*) as total FROM appointments WHERE doctor_id = :doctor_id";
    $debug_stmt = $db->prepare($debug_query);
    $debug_stmt->bindParam(":doctor_id", $doctor_id);
    $debug_stmt->execute();
    $total_count = $debug_stmt->fetch(PDO::FETCH_ASSOC);
    error_log("Total appointments for doctor (no date filter): " . $total_count['total']);

    // Check appointments with just the date filter
    $debug_query = "SELECT COUNT(*) as total FROM appointments 
                    WHERE doctor_id = :doctor_id 
                    AND date <= CURDATE()";
    $debug_stmt = $db->prepare($debug_query);
    $debug_stmt->bindParam(":doctor_id", $doctor_id);
    $debug_stmt->execute();
    $past_count = $debug_stmt->fetch(PDO::FETCH_ASSOC);
    error_log("Appointments before today: " . $past_count['total']);

    // Get a sample of appointments to check date format
    $debug_query = "SELECT id, date, status, patient_id 
                    FROM appointments 
                    WHERE doctor_id = :doctor_id 
                    ORDER BY date DESC 
                    LIMIT 5";
    $debug_stmt = $db->prepare($debug_query);
    $debug_stmt->bindParam(":doctor_id", $doctor_id);
    $debug_stmt->execute();
    $sample_appointments = $debug_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Sample appointments:");
    foreach ($sample_appointments as $app) {
        error_log("Appointment - ID: {$app['id']}, Date: {$app['date']}, Status: {$app['status']}");
    }

    // Simplified past appointments query
    $query = "SELECT a.*, 
                     p.first_name, p.last_name, p.contact_number, p.gender, p.date_of_birth as birthdate,
                     CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                     c.name as clinic_name,
                     mr.id as medical_record_id,
                     mr.chief_complaint,
                     mr.diagnosis,
                     mr.prescription,
                     mr.notes as consultation_notes,
                     mr.created_at as consultation_date,
                     CASE 
                         WHEN a.status = 'completed' THEN 'Completed'
                         WHEN a.status = 'cancelled' THEN 'Cancelled'
                         WHEN a.status = 'scheduled' AND a.date < CURDATE() THEN 'No Show'
                         ELSE a.status
                     END as consultation_status,
                     CASE 
                         WHEN a.status = 'completed' THEN 'success'
                         WHEN a.status = 'cancelled' THEN 'danger'
                         WHEN a.status = 'scheduled' AND a.date < CURDATE() THEN 'warning'
                         ELSE 'secondary'
                     END as status_color
              FROM appointments a 
              JOIN patients p ON a.patient_id = p.id 
              JOIN clinics c ON a.clinic_id = c.id
              LEFT JOIN medical_records mr ON a.id = mr.appointment_id
              WHERE a.doctor_id = :doctor_id 
              AND a.date <= CURDATE()
              ORDER BY a.date DESC, a.time DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":doctor_id", $doctor_id);
    $stmt->execute();
    $past_appointments = $stmt->fetchAll();

    // Debug query results
    error_log("Past appointments found (simplified query): " . count($past_appointments));
    if (count($past_appointments) > 0) {
        error_log("First appointment - ID: {$past_appointments[0]['id']}, Date: {$past_appointments[0]['date']}, Status: {$past_appointments[0]['status']}");
    }

    // Get today's appointments
    $query = "SELECT a.*, 
                     p.first_name, p.last_name, p.contact_number, p.gender, p.date_of_birth as birthdate,
                     CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                     c.name as clinic_name,
                     CASE WHEN mr.id IS NOT NULL THEN 1 ELSE 0 END as vitals_recorded
              FROM appointments a 
              JOIN patients p ON a.patient_id = p.id 
              JOIN clinics c ON a.clinic_id = c.id
              LEFT JOIN medical_records mr ON a.id = mr.appointment_id
              LEFT JOIN vital_signs vs ON mr.id = vs.medical_record_id
              WHERE a.doctor_id = :doctor_id 
              AND a.date = :current_date
              ORDER BY a.time ASC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":doctor_id", $doctor_id);
    $stmt->bindParam(":current_date", $current_date);
    $stmt->execute();
    $today_appointments = $stmt->fetchAll();

    // Get upcoming appointments (next 7 days)
    $query = "SELECT a.*, 
                     p.first_name, p.last_name, p.contact_number, p.gender, p.date_of_birth as birthdate,
                     CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                     c.name as clinic_name
              FROM appointments a 
              JOIN patients p ON a.patient_id = p.id 
              JOIN clinics c ON a.clinic_id = c.id
              WHERE a.doctor_id = :doctor_id 
              AND a.date > :current_date
              AND a.date <= DATE_ADD(:current_date, INTERVAL 7 DAY)
              ORDER BY a.date ASC, a.time ASC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":doctor_id", $doctor_id);
    $stmt->bindParam(":current_date", $current_date);
    $stmt->execute();
    $upcoming_appointments = $stmt->fetchAll();

    // Debug current date
    error_log("Current date for past appointments: " . date('Y-m-d'));

} catch (Exception $e) {
    error_log("Error in appointments: " . $e->getMessage());
    $today_appointments = [];
    $upcoming_appointments = [];
    $past_appointments = [];
}
?>

<!-- Add FullCalendar CSS and JS -->
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>

<!-- Add SweetAlert2 library -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Custom FullCalendar Styles -->
<style>
/* Calendar container */
#calendar {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

/* Calendar header */
.fc-header-toolbar {
    padding: 1rem !important;
    margin-bottom: 1rem !important;
}

.fc-toolbar-title {
    font-size: 1.25rem !important;
    font-weight: 600 !important;
    color: #2c3e50 !important;
}

/* Calendar grid */
.fc-theme-standard td, .fc-theme-standard th {
    border-color: #edf2f7 !important;
}

.fc-col-header-cell {
    background-color: #f8fafc !important;
    padding: 0.75rem 0 !important;
}

.fc-col-header-cell-cushion {
    color: #4a5568 !important;
    font-weight: 600 !important;
    text-decoration: none !important;
}

/* Today column highlight */
.fc-day-today {
    background-color: #f0f9ff !important;
}

.fc-day-today .fc-col-header-cell-cushion {
    color: #2196F3 !important;
    font-weight: 700 !important;
}

/* Event styling */
.fc-event {
    border: none !important;
    border-radius: 4px !important;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1) !important;
    margin: 1px 2px !important;
    padding: 2px 4px !important;
}

.fc-event-main {
    padding: 2px 4px !important;
}

.fc-event-time {
    font-weight: 500 !important;
    font-size: 0.85em !important;
    opacity: 0.9 !important;
}

.fc-event-title {
    font-weight: 500 !important;
    font-size: 0.9em !important;
}

/* Time grid */
.fc-timegrid-slot {
    height: 40px !important;
}

.fc-timegrid-slot-label {
    font-size: 0.85em !important;
    color: #718096 !important;
}

.fc-timegrid-now-indicator-line {
    border-color: #e53e3e !important;
}

.fc-timegrid-now-indicator-arrow {
    border-color: #e53e3e !important;
}

/* Event hover effect */
.fc-event:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.15) !important;
    transition: all 0.2s ease;
}

/* Month view specific */
.fc-daygrid-day {
    min-height: 100px !important;
}

.fc-daygrid-day-number {
    font-size: 0.9em !important;
    color: #4a5568 !important;
    padding: 4px 8px !important;
}

.fc-daygrid-event {
    margin: 1px 2px !important;
    padding: 2px 4px !important;
    border-radius: 3px !important;
}

/* Mobile responsiveness */
@media (max-width: 768px) {
    .fc-toolbar {
        flex-direction: column !important;
        gap: 0.5rem !important;
    }
    
    .fc-toolbar-title {
        font-size: 1.1rem !important;
    }
    
    .fc-event-time {
        font-size: 0.75em !important;
    }
    
    .fc-event-title {
        font-size: 0.8em !important;
    }
}

/* Calendar navigation buttons */
.btn-group .btn-outline-primary {
    border-color: #e2e8f0 !important;
    color: #4a5568 !important;
}

.btn-group .btn-outline-primary:hover,
.btn-group .btn-outline-primary.active {
    background-color: #2196F3 !important;
    border-color: #2196F3 !important;
    color: #fff !important;
}

/* Event tooltip */
.fc-event:hover::after {
    content: attr(title);
    position: absolute;
    background: rgba(0,0,0,0.8);
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.85em;
    z-index: 1000;
    pointer-events: none;
}
</style>

<!-- Main Content -->
<div class="container-fluid py-4">
    <!-- Calendar and Upcoming Appointments Row -->
    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Patient Appointments</h5>
                        <div class="d-flex gap-2">
                            <div class="btn-group">
                                <button class="btn btn-outline-primary btn-sm" id="calendarPrev" title="Previous">
                                    <span class="material-icons">chevron_left</span>
                                </button>
                                <button class="btn btn-outline-primary btn-sm" id="calendarToday" title="Today">Today</button>
                                <button class="btn btn-outline-primary btn-sm" id="calendarNext" title="Next">
                                    <span class="material-icons">chevron_right</span>
                                </button>
                            </div>
                            <div class="btn-group">
                                <button class="btn btn-outline-primary btn-sm active" data-view="timeGridWeek" title="Week View">Week</button>
                                <button class="btn btn-outline-primary btn-sm" data-view="timeGridDay" title="Day View">Day</button>
                                <button class="btn btn-outline-primary btn-sm" data-view="dayGridMonth" title="Month View">Month</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div id="calendar"></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Upcoming Appointments</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush" id="upcomingAppointmentsList">
                        <?php
                        // Get upcoming appointments for the doctor
                        $upcoming_sql = "SELECT a.*, 
                                        p.first_name as patient_first_name, 
                                        p.last_name as patient_last_name,
                                        c.name as clinic_name,
                                        c.address as clinic_address
                                        FROM appointments a
                                        JOIN patients p ON a.patient_id = p.id
                                        JOIN clinics c ON a.clinic_id = c.id
                                        WHERE a.doctor_id = ? 
                                        AND a.date >= CURDATE()
                                        AND a.status != 'cancelled'
                                        ORDER BY a.date ASC, a.time ASC
                                        LIMIT 5";
                        $upcoming_stmt = $db->prepare($upcoming_sql);
                        $upcoming_stmt->execute([$doctor_id]);
                        $upcoming_appointments = $upcoming_stmt->fetchAll(PDO::FETCH_ASSOC);

                        if (count($upcoming_appointments) > 0):
                            foreach ($upcoming_appointments as $appointment):
                                $appointment_date = new DateTime($appointment['date']);
                                $appointment_time = new DateTime($appointment['time']);
                                $is_today = $appointment_date->format('Y-m-d') === date('Y-m-d');
                        ?>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">
                                            <?php echo htmlspecialchars($appointment['patient_last_name'] . ', ' . $appointment['patient_first_name']); ?>
                                        </h6>
                                        <p class="mb-1 text-muted">
                                            <?php echo htmlspecialchars($appointment['clinic_name']); ?>
                                        </p>
                                        <small class="text-muted">
                                            <?php echo $appointment_date->format('F d, Y'); ?> at 
                                            <?php echo $appointment_time->format('h:i A'); ?>
                                        </small>
                                    </div>
                                    <span class="badge bg-<?php 
                                        echo match($appointment['status']) {
                                            'scheduled' => 'primary',
                                            'completed' => 'success',
                                            'cancelled' => 'danger',
                                            default => 'secondary'
                                        };
                                    ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </div>
                                <div class="mt-2">
                                    <small class="text-muted">Purpose: <?php echo htmlspecialchars($appointment['purpose']); ?></small>
                                </div>
                                <?php if ($is_today): ?>
                                    <div class="mt-2">
                                        <span class="badge bg-info">Today's Appointment</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php 
                            endforeach;
                        else:
                        ?>
                            <div class="list-group-item text-center py-4">
                                <p class="text-muted mb-0">No upcoming appointments</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <a href="?view=list" class="btn btn-link text-decoration-none p-0">
                        View All Appointments
                        <span class="material-icons align-text-bottom">arrow_forward</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

<!-- Start Consultation Modal -->
<div class="modal fade" id="startConsultationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Start Consultation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="consultationForm">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Patient Name</label>
                            <input type="text" class="form-control" id="consultationPatientName" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date & Time</label>
                            <input type="text" class="form-control" id="consultationDateTime" readonly>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Chief Complaint</label>
                        <textarea class="form-control" id="chiefComplaint" rows="2" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Vital Signs (Recorded by Staff)</label>
                        <div class="row mb-2">
                            <div class="col-md-3">
                                <label class="form-label small">Blood Pressure:</label>
                                <input type="text" class="form-control" id="bloodPressure" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Heart Rate:</label>
                                <input type="text" class="form-control" id="heartRate" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Temperature:</label>
                                <input type="text" class="form-control" id="temperature" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Resp. Rate:</label>
                                <input type="text" class="form-control" id="respiratoryRate" readonly>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-3">
                                <label class="form-label small">Weight (kg):</label>
                                <input type="text" class="form-control" id="weight" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Height (cm):</label>
                                <input type="text" class="form-control" id="height" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">BMI:</label>
                                <input type="text" class="form-control" id="bmi" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">O₂ Sat (%):</label>
                                <input type="text" class="form-control" id="oxygenSaturation" readonly>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3">
                                <label class="form-label small">Pain Scale:</label>
                                <input type="text" class="form-control" id="painScale" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Recorded At:</label>
                                <input type="text" class="form-control" id="recordedAt" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Notes:</label>
                                <input type="text" class="form-control" id="vitalsNotes" readonly>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Diagnosis</label>
                        <div id="diagnosesList" class="mb-2">
                            <!-- Diagnosis items will be added here -->
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="addDiagnosisBtn">
                            <span class="material-icons align-middle me-1">add</span> Add Diagnosis
                        </button>
                        <div class="form-text">Add each diagnosis with its details. You can add multiple diagnoses.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Prescription</label>
                        <div id="prescriptionsList" class="mb-2">
                            <!-- Prescription items will be added here -->
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="addPrescriptionBtn">
                            <span class="material-icons align-middle me-1">add</span> Add Medication
                        </button>
                        <div class="form-text">Add each medication with its details. You can add multiple medications.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="consultationNotes" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveConsultation">Save & Complete</button>
            </div>
        </div>
    </div>
</div>

<!-- Prescription Item Template -->
<template id="prescriptionItemTemplate">
    <div class="prescription-item card mb-2">
        <div class="card-body p-2">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <h6 class="card-title mb-0">Medication</h6>
                <button type="button" class="btn btn-outline-danger btn-sm remove-prescription">
                    <span class="material-icons">delete</span>
                </button>
            </div>
            <div class="row g-2">
                <div class="col-md-6">
                    <label class="form-label small">Medication Name</label>
                    <input type="text" class="form-control form-control-sm prescription-med" placeholder="e.g., Lisinopril" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small">Dosage</label>
                    <input type="text" class="form-control form-control-sm prescription-dosage" placeholder="e.g., 10mg" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Frequency</label>
                    <select class="form-select form-select-sm prescription-frequency" required>
                        <option value="">Select frequency</option>
                        <option value="Once daily">Once daily</option>
                        <option value="Twice daily">Twice daily</option>
                        <option value="Three times daily">Three times daily</option>
                        <option value="Four times daily">Four times daily</option>
                        <option value="Every 6 hours">Every 6 hours</option>
                        <option value="Every 8 hours">Every 8 hours</option>
                        <option value="Every 12 hours">Every 12 hours</option>
                        <option value="Once weekly">Once weekly</option>
                        <option value="As needed">As needed</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Duration</label>
                    <div class="input-group input-group-sm">
                        <input type="number" class="form-control prescription-duration" placeholder="e.g., 30" min="1" required>
                        <select class="form-select prescription-duration-unit" required>
                            <option value="days">days</option>
                            <option value="weeks">weeks</option>
                            <option value="months">months</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Instructions</label>
                    <input type="text" class="form-control form-control-sm prescription-instructions" placeholder="e.g., Take with food" required>
                </div>
            </div>
        </div>
    </div>
</template>

<!-- Diagnosis Item Template -->
<template id="diagnosisItemTemplate">
    <div class="diagnosis-item card mb-2">
        <div class="card-body p-2">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <h6 class="card-title mb-0">Diagnosis Entry</h6>
                <button type="button" class="btn btn-outline-danger btn-sm remove-diagnosis">
                    <span class="material-icons">delete</span>
                </button>
            </div>
            <div class="row g-2">
                <div class="col-md-6">
                    <label class="form-label small">Primary Diagnosis</label>
                    <input type="text" class="form-control form-control-sm diagnosis-primary" 
                        placeholder="e.g., Essential Hypertension" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small">ICD-10 Code (Optional)</label>
                    <input type="text" class="form-control form-control-sm diagnosis-icd" 
                        placeholder="e.g., I10">
                </div>
                <div class="col-md-12">
                    <label class="form-label small">Type</label>
                    <select class="form-select form-select-sm diagnosis-type" required>
                        <option value="">Select type</option>
                        <option value="Primary">Primary</option>
                        <option value="Secondary">Secondary</option>
                        <option value="Differential">Differential</option>
                        <option value="Provisional">Provisional</option>
                        <option value="Rule Out">Rule Out</option>
                        <option value="Other">Other</option>
                    </select>
                    <input type="text" class="form-control form-control-sm mt-2 diagnosis-type-other d-none" placeholder="Specify other type">
                </div>
                <div class="col-md-12">
                    <label class="form-label small">Description</label>
                    <textarea class="form-control form-control-sm diagnosis-description" 
                        rows="2" placeholder="Additional details about the diagnosis"></textarea>
                </div>
                <div class="col-md-12">
                    <label class="form-label small">Status</label>
                    <select class="form-select form-select-sm diagnosis-status" required>
                        <option value="Active">Active</option>
                        <option value="Resolved">Resolved</option>
                        <option value="Chronic">Chronic</option>
                        <option value="Recurrent">Recurrent</option>
                        <option value="Rule Out">Rule Out</option>
                        <option value="Other">Other</option>
                    </select>
                    <input type="text" class="form-control form-control-sm mt-2 diagnosis-status-other d-none" placeholder="Specify other status">
                </div>
            </div>
        </div>
    </div>
</template>

<!-- Add Event Details Modal -->
<div class="modal fade" id="eventDetailsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="eventTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="scheduleDetails" style="display: none;">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Clinic Schedule</label>
                        <p class="mb-1">Clinic: <span id="scheduleClinic"></span></p>
                        <p class="mb-1">Time: <span id="scheduleTime"></span></p>
                        <p class="mb-1">Break Time: <span id="scheduleBreak"></span></p>
                        <p class="mb-1">Max Appointments: <span id="scheduleMaxAppointments"></span></p>
                    </div>
                </div>
                <div id="appointmentDetails" style="display: none;">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Appointment Details</label>
                        <p class="mb-1">Patient: <span id="appointmentPatient"></span></p>
                        <p class="mb-1">Clinic: <span id="appointmentClinic"></span></p>
                        <p class="mb-1">Time: <span id="appointmentTime"></span></p>
                        <p class="mb-1">Purpose: <span id="appointmentPurpose"></span></p>
                        <p class="mb-1">Status: <span id="appointmentStatus"></span></p>
                        <p class="mb-1">Contact: <span id="appointmentContact"></span></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <div id="appointmentActions" style="display: none;">
                    <button type="button" class="btn btn-primary" id="startConsultationBtn">Start Consultation</button>
                    <button type="button" class="btn btn-danger" id="cancelAppointmentBtn">Cancel Appointment</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize FullCalendar
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'timeGridWeek',
        headerToolbar: {
            left: '',
            center: 'title',
            right: ''
        },
        slotMinTime: '08:00:00',
        slotMaxTime: '20:00:00',
        allDaySlot: false,
        slotDuration: '00:30:00',
        events: <?php echo $calendar_events_json; ?>,
        eventTimeFormat: {
            hour: 'numeric',
            minute: '2-digit',
            meridiem: 'short'
        },
        eventDisplay: 'block',
        eventDidMount: function(info) {
            // Add tooltips to events
            const eventDetails = info.event.extendedProps;
            let tooltipContent = `
                <strong>${eventDetails.patient_name}</strong><br>
                ${eventDetails.purpose}<br>
                Status: ${eventDetails.status}<br>
                Clinic: ${eventDetails.clinic_name}
            `;
            
            info.el.title = tooltipContent;
            
            // Add status indicator dot
            const dot = document.createElement('span');
            dot.className = 'event-status-dot';
            dot.style.cssText = `
                display: inline-block;
                width: 8px;
                height: 8px;
                border-radius: 50%;
                margin-right: 4px;
                background-color: ${info.event.backgroundColor};
            `;
            info.el.querySelector('.fc-event-title').prepend(dot);
        },
        eventClick: function(info) {
            const eventDetails = info.event.extendedProps;
            const modal = new bootstrap.Modal(document.getElementById('eventDetailsModal'));
            
            // Debug the event details
            console.log('Event clicked:', eventDetails);
            console.log('Event type:', eventDetails.type);
            
            if (eventDetails.type === 'schedule') {
                // Show schedule details
                document.getElementById('scheduleDetails').style.display = 'block';
                document.getElementById('appointmentDetails').style.display = 'none';
                document.getElementById('appointmentActions').style.display = 'none';
                
                // Convert day number to day name
                const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                const dayName = days[eventDetails.day_of_week - 1];
                
                document.getElementById('eventTitle').textContent = `${eventDetails.clinic_name} - ${dayName}`;
                document.getElementById('scheduleClinic').textContent = eventDetails.clinic_name;
                document.getElementById('scheduleTime').textContent = `${eventDetails.start_time} - ${eventDetails.end_time}`;
                document.getElementById('scheduleBreak').textContent = eventDetails.break_start ? 
                    `${eventDetails.break_start} - ${eventDetails.break_end}` : 'No break';
                document.getElementById('scheduleMaxAppointments').textContent = eventDetails.max_appointments;
            } else {
                // Show appointment details
                document.getElementById('scheduleDetails').style.display = 'none';
                document.getElementById('appointmentDetails').style.display = 'block';
                
                document.getElementById('eventTitle').textContent = eventDetails.patient_name;
                document.getElementById('appointmentPatient').textContent = eventDetails.patient_name;
                document.getElementById('appointmentClinic').textContent = eventDetails.clinic_name;
                document.getElementById('appointmentTime').textContent = info.event.start.toLocaleTimeString();
                document.getElementById('appointmentPurpose').textContent = eventDetails.purpose;
                document.getElementById('appointmentStatus').textContent = eventDetails.status;
                document.getElementById('appointmentContact').textContent = eventDetails.contact_number;
                
                // Show/hide action buttons based on appointment status
                const actionsDiv = document.getElementById('appointmentActions');
                actionsDiv.style.display = eventDetails.status === 'scheduled' ? 'block' : 'none';
                
                // Set up action buttons
                const startConsultationBtn = document.getElementById('startConsultationBtn');
                // Make sure we have a valid ID
                const appointmentId = parseInt(eventDetails.appointment_id) || eventDetails.appointment_id;
                startConsultationBtn.dataset.appointmentId = appointmentId;
                console.log('Setting appointment ID:', appointmentId);
                
                startConsultationBtn.onclick = function() {
                    const appointmentId = this.dataset.appointmentId;
                    console.log('Using appointment ID:', appointmentId);
                    
                    // Make sure any open modals are closed
                    const openModals = document.querySelectorAll('.modal.show');
                    openModals.forEach(modalEl => {
                        const modal = bootstrap.Modal.getInstance(modalEl);
                        if (modal) modal.hide();
                    });
                    
                    // Check if appointment is ready for consultation
                    fetch(`../../api/check_consultation_ready.php?appointment_id=${appointmentId}`)
                        .then(response => {
                            console.log('API Response status:', response.status);
                            return response.json();
                        })
                        .then(data => {
                            console.log('API Response data:', data);
                            if (data.success) {
                                // Appointment is ready, close the current modal first
                                const eventModal = bootstrap.Modal.getInstance(document.getElementById('eventDetailsModal'));
                                if (eventModal) {
                                    eventModal.hide();
                                    
                                    // Wait for the modal to close before opening another
                                    setTimeout(() => {
                                        // Fetch patient and appointment details
                                        fetch(`../../api/get_appointment.php?id=${appointmentId}`)
                                            .then(response => response.json())
                                            .then(data => {
                                                console.log('Appointment data:', data);
                                                if (data.success) {
                                                    // Access the nested appointment object from the response
                                                    const appointmentData = data.appointment;
                                                    document.getElementById('consultationPatientName').value = appointmentData.patient_name;
                                                    document.getElementById('consultationDateTime').value = appointmentData.date + ' ' + appointmentData.time;
                                                    
                                                    // Populate vital signs fields if available
                                                    if (appointmentData.vital_signs) {
                                                        const vitalSigns = appointmentData.vital_signs;
                                                        document.getElementById('bloodPressure').value = vitalSigns.blood_pressure || 'N/A';
                                                        document.getElementById('heartRate').value = vitalSigns.heart_rate || 'N/A';
                                                        document.getElementById('temperature').value = vitalSigns.temperature || 'N/A';
                                                        document.getElementById('respiratoryRate').value = vitalSigns.respiratory_rate || 'N/A';
                                                        document.getElementById('weight').value = vitalSigns.weight || 'N/A';
                                                        document.getElementById('height').value = vitalSigns.height || 'N/A';
                                                        document.getElementById('bmi').value = vitalSigns.bmi || 'N/A';
                                                        document.getElementById('oxygenSaturation').value = vitalSigns.oxygen_saturation || 'N/A';
                                                        document.getElementById('painScale').value = vitalSigns.pain_scale || 'N/A';
                                                        document.getElementById('recordedAt').value = vitalSigns.recorded_at || 'N/A';
                                                        document.getElementById('vitalsNotes').value = vitalSigns.vitals_notes || 'N/A';
                                                    }
                                                    
                                                    // Store appointment ID for the form submission
                                                    document.getElementById('consultationForm').dataset.appointmentId = appointmentId;
                                                    
                                                    // Now open the consultation modal
                    const consultationModal = new bootstrap.Modal(document.getElementById('startConsultationModal'));
                    consultationModal.show();
                                                } else {
                                                    alert('Error loading appointment details: ' + data.message);
                                                }
                                            })
                                            .catch(error => {
                                                console.error('Error:', error);
                                                alert('Error loading appointment details');
                                            });
                                    }, 300); // 300ms delay to ensure modal transition is complete
                                } else {
                                    // If eventModal doesn't exist, just show the consultation modal directly
                                    // Fetch patient and appointment details
                                    fetch(`../../api/get_appointment.php?id=${appointmentId}`)
                                        .then(response => response.json())
                                        .then(data => {
                                            console.log('Appointment data:', data);
                                            if (data.success) {
                                                // Access the nested appointment object from the response
                                                const appointmentData = data.appointment;
                                                document.getElementById('consultationPatientName').value = appointmentData.patient_name;
                                                document.getElementById('consultationDateTime').value = appointmentData.date + ' ' + appointmentData.time;
                                                
                                                // Populate vital signs fields if available
                                                if (appointmentData.vital_signs) {
                                                    const vitalSigns = appointmentData.vital_signs;
                                                    document.getElementById('bloodPressure').value = vitalSigns.blood_pressure || 'N/A';
                                                    document.getElementById('heartRate').value = vitalSigns.heart_rate || 'N/A';
                                                    document.getElementById('temperature').value = vitalSigns.temperature || 'N/A';
                                                    document.getElementById('respiratoryRate').value = vitalSigns.respiratory_rate || 'N/A';
                                                    document.getElementById('weight').value = vitalSigns.weight || 'N/A';
                                                    document.getElementById('height').value = vitalSigns.height || 'N/A';
                                                    document.getElementById('bmi').value = vitalSigns.bmi || 'N/A';
                                                    document.getElementById('oxygenSaturation').value = vitalSigns.oxygen_saturation || 'N/A';
                                                    document.getElementById('painScale').value = vitalSigns.pain_scale || 'N/A';
                                                    document.getElementById('recordedAt').value = vitalSigns.recorded_at || 'N/A';
                                                    document.getElementById('vitalsNotes').value = vitalSigns.vitals_notes || 'N/A';
                                                }
                                                
                                                // Store appointment ID for the form submission
                                                document.getElementById('consultationForm').dataset.appointmentId = appointmentId;
                                                
                                                // Now open the consultation modal
                                                const consultationModal = new bootstrap.Modal(document.getElementById('startConsultationModal'));
                                                consultationModal.show();
                                            } else {
                                                alert('Error loading appointment details: ' + data.message);
                                            }
                                        })
                                        .catch(error => {
                                            console.error('Error:', error);
                                            alert('Error loading appointment details');
                                        });
                                }
                            } else {
                                // Show error with SweetAlert or fallback to regular alert
                                if (typeof Swal !== 'undefined') {
                                    Swal.fire({
                                        icon: 'warning',
                                        title: 'Cannot Start Consultation',
                                        text: data.message || 'Patient vital signs need to be recorded by staff before consultation can begin.',
                                        confirmButtonText: 'OK'
                                    });
                                } else {
                                    alert(data.message || 'Patient vital signs need to be recorded by staff before consultation can begin.');
                                }
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            // Show more detailed error information
                            console.error('Failed to check consultation readiness for appointment ID:', appointmentId);
                            alert('An error occurred while checking consultation readiness. See console for details.');
                        });
                };
                
                document.getElementById('cancelAppointmentBtn').onclick = function() {
                    if (confirm('Are you sure you want to cancel this appointment?')) {
                        // Add cancellation logic here
                    }
                };
            }
            
            modal.show();
        },
        eventDidMount: function(info) {
            // Add tooltips to events
            const eventDetails = info.event.extendedProps;
            let tooltipContent = '';
            
            if (eventDetails.type === 'schedule') {
                tooltipContent = `${eventDetails.clinic_name}\n${eventDetails.start_time} - ${eventDetails.end_time}`;
            } else {
                tooltipContent = `${eventDetails.patient_name}\n${eventDetails.purpose}\nStatus: ${eventDetails.status}`;
            }
            
            info.el.title = tooltipContent;
        }
    });
    calendar.render();

    // Calendar navigation buttons
    document.getElementById('calendarPrev').addEventListener('click', function() {
        calendar.prev();
    });

    document.getElementById('calendarNext').addEventListener('click', function() {
        calendar.next();
    });

    document.getElementById('calendarToday').addEventListener('click', function() {
        calendar.today();
    });

    // View switcher buttons
    document.querySelectorAll('[data-view]').forEach(button => {
        button.addEventListener('click', function() {
            const view = this.dataset.view;
            calendar.changeView(view);
            
            // Update active state
            document.querySelectorAll('[data-view]').forEach(btn => {
                btn.classList.remove('active');
            });
            this.classList.add('active');
        });
    });

    // Start consultation button click handler
    const startButtons = document.querySelectorAll('.start-consultation');
    startButtons.forEach(button => {
        button.addEventListener('click', function() {
            const appointmentId = this.dataset.appointmentId;
            console.log('Direct click - Appointment ID:', appointmentId);
            
            // Make sure any open modals are closed
            const openModals = document.querySelectorAll('.modal.show');
            openModals.forEach(modalEl => {
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
            });
            
            // Check if appointment is ready for consultation
            fetch(`../../api/check_consultation_ready.php?appointment_id=${appointmentId}`)
                .then(response => {
                    console.log('API Response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('API Response data:', data);
                    if (data.success) {
                        // Appointment is ready, close the current modal first
                        const eventModal = bootstrap.Modal.getInstance(document.getElementById('eventDetailsModal'));
                        if (eventModal) {
                            eventModal.hide();
                            
                            // Wait for the modal to close before opening another
                            setTimeout(() => {
                                // Fetch patient and appointment details
                                fetch(`../../api/get_appointment.php?id=${appointmentId}`)
                                    .then(response => response.json())
                                    .then(data => {
                                        console.log('Appointment data:', data);
                                        if (data.success) {
                                            // Access the nested appointment object from the response
                                            const appointmentData = data.appointment;
                                            document.getElementById('consultationPatientName').value = appointmentData.patient_name;
                                            document.getElementById('consultationDateTime').value = appointmentData.date + ' ' + appointmentData.time;
                                            
                                            // Populate vital signs fields if available
                                            if (appointmentData.vital_signs) {
                                                const vitalSigns = appointmentData.vital_signs;
                                                document.getElementById('bloodPressure').value = vitalSigns.blood_pressure || 'N/A';
                                                document.getElementById('heartRate').value = vitalSigns.heart_rate || 'N/A';
                                                document.getElementById('temperature').value = vitalSigns.temperature || 'N/A';
                                                document.getElementById('respiratoryRate').value = vitalSigns.respiratory_rate || 'N/A';
                                                document.getElementById('weight').value = vitalSigns.weight || 'N/A';
                                                document.getElementById('height').value = vitalSigns.height || 'N/A';
                                                document.getElementById('bmi').value = vitalSigns.bmi || 'N/A';
                                                document.getElementById('oxygenSaturation').value = vitalSigns.oxygen_saturation || 'N/A';
                                                document.getElementById('painScale').value = vitalSigns.pain_scale || 'N/A';
                                                document.getElementById('recordedAt').value = vitalSigns.recorded_at || 'N/A';
                                                document.getElementById('vitalsNotes').value = vitalSigns.vitals_notes || 'N/A';
                                            }
                                            
                                            // Store appointment ID for the form submission
                                            document.getElementById('consultationForm').dataset.appointmentId = appointmentId;
                                            
                                            // Now open the consultation modal
                                            const consultationModal = new bootstrap.Modal(document.getElementById('startConsultationModal'));
                                            consultationModal.show();
                                        } else {
                                            alert('Error loading appointment details: ' + data.message);
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Error:', error);
                                        alert('Error loading appointment details');
                                    });
                            }, 300); // 300ms delay to ensure modal transition is complete
                        } else {
                            // If eventModal doesn't exist, just show the consultation modal directly
                            // Fetch patient and appointment details
                            fetch(`../../api/get_appointment.php?id=${appointmentId}`)
                                .then(response => response.json())
                                .then(data => {
                                    console.log('Appointment data:', data);
                                    if (data.success) {
                                        // Access the nested appointment object from the response
                                        const appointmentData = data.appointment;
                                        document.getElementById('consultationPatientName').value = appointmentData.patient_name;
                                        document.getElementById('consultationDateTime').value = appointmentData.date + ' ' + appointmentData.time;
                                        
                                        // Populate vital signs fields if available
                                        if (appointmentData.vital_signs) {
                                            const vitalSigns = appointmentData.vital_signs;
                                            document.getElementById('bloodPressure').value = vitalSigns.blood_pressure || 'N/A';
                                            document.getElementById('heartRate').value = vitalSigns.heart_rate || 'N/A';
                                            document.getElementById('temperature').value = vitalSigns.temperature || 'N/A';
                                            document.getElementById('respiratoryRate').value = vitalSigns.respiratory_rate || 'N/A';
                                            document.getElementById('weight').value = vitalSigns.weight || 'N/A';
                                            document.getElementById('height').value = vitalSigns.height || 'N/A';
                                            document.getElementById('bmi').value = vitalSigns.bmi || 'N/A';
                                            document.getElementById('oxygenSaturation').value = vitalSigns.oxygen_saturation || 'N/A';
                                            document.getElementById('painScale').value = vitalSigns.pain_scale || 'N/A';
                                            document.getElementById('recordedAt').value = vitalSigns.recorded_at || 'N/A';
                                            document.getElementById('vitalsNotes').value = vitalSigns.vitals_notes || 'N/A';
                                        }
                                        
                                        // Store appointment ID for the form submission
                                        document.getElementById('consultationForm').dataset.appointmentId = appointmentId;
                                        
                                        // Now open the consultation modal
                                        const consultationModal = new bootstrap.Modal(document.getElementById('startConsultationModal'));
                                        consultationModal.show();
                                    } else {
                                        alert('Error loading appointment details: ' + data.message);
                                    }
                                })
                                .catch(error => {
                                    console.error('Error:', error);
                                    alert('Error loading appointment details');
                                });
                        }
                    } else {
                        // Show error with SweetAlert or fallback to regular alert
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Cannot Start Consultation',
                                text: data.message || 'Patient vital signs need to be recorded by staff before consultation can begin.',
                                confirmButtonText: 'OK'
                            });
                        } else {
                            alert(data.message || 'Patient vital signs need to be recorded by staff before consultation can begin.');
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Show more detailed error information
                    console.error('Failed to check consultation readiness for appointment ID:', appointmentId);
                    alert('An error occurred while checking consultation readiness. See console for details.');
                });
        });
    });

    // Cancel appointment button click handler
    const cancelButtons = document.querySelectorAll('.cancel-appointment');
    cancelButtons.forEach(button => {
        button.addEventListener('click', function() {
            const appointmentId = this.dataset.appointmentId;
            if (confirm('Are you sure you want to cancel this appointment?')) {
                // Add your appointment cancellation logic here
            }
        });
    });

    // View consultation button click handler
    const viewButtons = document.querySelectorAll('.view-consultation');
    viewButtons.forEach(button => {
        button.addEventListener('click', function() {
            const appointmentId = this.dataset.appointmentId;
            // Add your consultation viewing logic here
        });
    });

    // Prescription handling
    const prescriptionsList = document.getElementById('prescriptionsList');
    const addPrescriptionBtn = document.getElementById('addPrescriptionBtn');
    const prescriptionTemplate = document.getElementById('prescriptionItemTemplate');

    // Add first prescription item by default
    addPrescriptionItem();

    // Add prescription button click handler
    addPrescriptionBtn.addEventListener('click', addPrescriptionItem);

    function addPrescriptionItem() {
        const clone = prescriptionTemplate.content.cloneNode(true);
        const prescriptionItem = clone.querySelector('.prescription-item');
        
        // Add remove button handler
        const removeBtn = prescriptionItem.querySelector('.remove-prescription');
        removeBtn.addEventListener('click', function() {
            if (prescriptionsList.children.length > 1) {
                prescriptionItem.remove();
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'Cannot Remove',
                    text: 'At least one prescription is required.',
                    confirmButtonText: 'OK'
                });
            }
        });

        // Add custom frequency handler
        const frequencySelect = prescriptionItem.querySelector('.prescription-frequency');
        frequencySelect.addEventListener('change', function() {
            if (this.value === 'Other') {
                const customFreq = prompt('Please enter custom frequency:');
                if (customFreq) {
                    this.value = customFreq;
                } else {
                    this.value = '';
                }
            }
        });

        prescriptionsList.appendChild(prescriptionItem);
    }

    // Diagnosis handling
    const diagnosesList = document.getElementById('diagnosesList');
    const addDiagnosisBtn = document.getElementById('addDiagnosisBtn');
    const diagnosisTemplate = document.getElementById('diagnosisItemTemplate');

    // Add first diagnosis item by default
    addDiagnosisItem();

    // Add diagnosis button click handler
    addDiagnosisBtn.addEventListener('click', addDiagnosisItem);

    function addDiagnosisItem() {
        const clone = diagnosisTemplate.content.cloneNode(true);
        const diagnosisItem = clone.querySelector('.diagnosis-item');
        
        // Add remove button handler
        const removeBtn = diagnosisItem.querySelector('.remove-diagnosis');
        removeBtn.addEventListener('click', function() {
            if (diagnosesList.children.length > 1) {
                diagnosisItem.remove();
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'Cannot Remove',
                    text: 'At least one diagnosis is required.',
                    confirmButtonText: 'OK'
                });
            }
        });

        // Add logic for diagnosis type 'Other'
        const typeSelect = diagnosisItem.querySelector('.diagnosis-type');
        const typeOtherInput = diagnosisItem.querySelector('.diagnosis-type-other');
        typeSelect.addEventListener('change', function() {
            if (this.value === 'Other') {
                typeOtherInput.classList.remove('d-none');
                typeOtherInput.required = true;
            } else {
                typeOtherInput.classList.add('d-none');
                typeOtherInput.required = false;
                typeOtherInput.value = '';
            }
        });
        // Add logic for diagnosis status 'Other'
        const statusSelect = diagnosisItem.querySelector('.diagnosis-status');
        const statusOtherInput = diagnosisItem.querySelector('.diagnosis-status-other');
        statusSelect.addEventListener('change', function() {
            if (this.value === 'Other') {
                statusOtherInput.classList.remove('d-none');
                statusOtherInput.required = true;
            } else {
                statusOtherInput.classList.add('d-none');
                statusOtherInput.required = false;
                statusOtherInput.value = '';
            }
        });

        diagnosesList.appendChild(diagnosisItem);
    }

    // Update save consultation handler
    document.getElementById('saveConsultation').addEventListener('click', function() {
        // Get form data
        const appointmentId = document.getElementById('consultationForm').dataset.appointmentId;
        const chiefComplaint = document.getElementById('chiefComplaint').value;
        const notes = document.getElementById('consultationNotes').value;
        
        // Validate required fields
        if (!appointmentId) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Appointment ID is missing. Please try again or reload the page.',
                confirmButtonText: 'OK'
            });
            return;
        }
        
        if (!chiefComplaint) {
            Swal.fire({
                icon: 'warning',
                title: 'Missing Information',
                text: 'Please fill in the Chief Complaint field.',
                confirmButtonText: 'OK'
            });
            return;
        }

        // Get and validate diagnoses
        const diagnosisItems = document.querySelectorAll('.diagnosis-item');
        if (diagnosisItems.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Missing Diagnosis',
                text: 'Please add at least one diagnosis.',
                confirmButtonText: 'OK'
            });
            return;
        }

        // Format diagnoses
        const formattedDiagnoses = Array.from(diagnosisItems).map(item => {
            const primary = item.querySelector('.diagnosis-primary').value;
            const icd = item.querySelector('.diagnosis-icd').value;
            let type = item.querySelector('.diagnosis-type').value;
            const typeOther = item.querySelector('.diagnosis-type-other').value;
            if (type === 'Other') type = typeOther;
            const description = item.querySelector('.diagnosis-description').value;
            let status = item.querySelector('.diagnosis-status').value;
            const statusOther = item.querySelector('.diagnosis-status-other').value;
            if (status === 'Other') status = statusOther;

            // Validate required fields
            if (!primary || !type || !status) {
                throw new Error('Please fill in all required fields for each diagnosis.');
            }

            // Format the diagnosis string
            let diagnosisStr = primary;
            if (icd) diagnosisStr += ` (ICD-10: ${icd})`;
            diagnosisStr += ` [${type}]`;
            if (description) diagnosisStr += ` - ${description}`;
            diagnosisStr += ` - Status: ${status}`;

            return diagnosisStr;
        }).join('\n');

        // Get and validate prescriptions
        const prescriptionItems = document.querySelectorAll('.prescription-item');
        if (prescriptionItems.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Missing Prescription',
                text: 'Please add at least one medication.',
                confirmButtonText: 'OK'
            });
            return;
        }

        // Format prescriptions
        const formattedPrescriptions = Array.from(prescriptionItems).map((item, index) => {
            const med = item.querySelector('.prescription-med').value.trim();
            const dosage = item.querySelector('.prescription-dosage').value.trim();
            const frequency = item.querySelector('.prescription-frequency').value.trim();
            const duration = item.querySelector('.prescription-duration').value.trim();
            const durationUnit = item.querySelector('.prescription-duration-unit').value.trim();
            const instructions = item.querySelector('.prescription-instructions').value.trim();

            // Check each required field and provide specific feedback
            const missingFields = [];
            if (!med) missingFields.push('Medication Name');
            if (!dosage) missingFields.push('Dosage');
            if (!frequency) missingFields.push('Frequency');
            if (!duration) missingFields.push('Duration');
            if (!durationUnit) missingFields.push('Duration Unit');

            if (missingFields.length > 0) {
                // Highlight the missing fields
                missingFields.forEach(field => {
                    const fieldElement = item.querySelector(`.prescription-${field.toLowerCase().replace(/\s+/g, '-')}`);
                    if (fieldElement) {
                        fieldElement.classList.add('is-invalid');
                        // Add error message below the field
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'invalid-feedback';
                        errorDiv.textContent = `${field} is required`;
                        fieldElement.parentNode.appendChild(errorDiv);
                    }
                });

                throw new Error(`Please fill in the following required fields for medication #${index + 1}: ${missingFields.join(', ')}`);
            }

            // Validate duration is a positive number
            if (isNaN(duration) || duration <= 0) {
                const durationField = item.querySelector('.prescription-duration');
                durationField.classList.add('is-invalid');
                const errorDiv = document.createElement('div');
                errorDiv.className = 'invalid-feedback';
                errorDiv.textContent = 'Duration must be a positive number';
                durationField.parentNode.appendChild(errorDiv);
                throw new Error(`Duration must be a positive number for medication #${index + 1}`);
            }

            // Clear any previous error states
            item.querySelectorAll('.is-invalid').forEach(el => {
                el.classList.remove('is-invalid');
                const errorDiv = el.parentNode.querySelector('.invalid-feedback');
                if (errorDiv) errorDiv.remove();
            });

            return `${med}|${dosage}|${frequency}|${duration} ${durationUnit}|${instructions || 'No specific instructions'}`;
        }).join('\n');
        
        // Create request body
        const requestBody = {
            appointment_id: parseInt(appointmentId) || appointmentId,
            chief_complaint: chiefComplaint,
            diagnosis: formattedDiagnoses,
            prescription: formattedPrescriptions,
            notes: notes
        };
        
        // Show loading indicator
        Swal.fire({
            title: 'Saving Consultation',
            text: 'Please wait...',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            willOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Send POST request to API
        fetch('../../api/save_consultation.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(requestBody)
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`Server returned ${response.status} ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Server response:', data);
            
            if (data.success) {
                // Show success message
                const consultationModal = bootstrap.Modal.getInstance(document.getElementById('startConsultationModal'));
                if (consultationModal) {
                    consultationModal.hide();
                }
                
                Swal.fire({
                    icon: 'success',
                    title: 'Consultation Complete',
                    text: 'The consultation has been saved successfully.',
                    confirmButtonText: 'OK'
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'An error occurred while saving the consultation',
                    confirmButtonText: 'OK'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message || 'An error occurred while saving the consultation. Please try again.',
                confirmButtonText: 'OK',
                footer: error.message ? `<span class="text-danger">Technical details: ${error.message}</span>` : ''
            });
        });
    });
});
</script>

<style>
/* Add these styles to your existing styles */
.prescription-item {
    border: 1px solid #dee2e6;
    border-radius: 0.25rem;
}

.prescription-item .card-body {
    background-color: #f8f9fa;
}

.prescription-item:hover {
    border-color: #adb5bd;
}

.remove-prescription {
    padding: 0.25rem 0.5rem;
}

.remove-prescription .material-icons {
    font-size: 1.2rem;
}

.form-label.small {
    margin-bottom: 0.2rem;
    color: #6c757d;
}

.prescription-item .form-control,
.prescription-item .form-select {
    font-size: 0.875rem;
}

#addPrescriptionBtn {
    margin-top: 0.5rem;
}

#addPrescriptionBtn .material-icons {
    font-size: 1.2rem;
}

.diagnosis-item {
    border: 1px solid #dee2e6;
    border-radius: 0.25rem;
}

.diagnosis-item .card-body {
    background-color: #f8f9fa;
}

.diagnosis-item:hover {
    border-color: #adb5bd;
}

.remove-diagnosis {
    padding: 0.25rem 0.5rem;
}

.remove-diagnosis .material-icons {
    font-size: 1.2rem;
}

#addDiagnosisBtn {
    margin-top: 0.5rem;
}

#addDiagnosisBtn .material-icons {
    font-size: 1.2rem;
}
</style> 