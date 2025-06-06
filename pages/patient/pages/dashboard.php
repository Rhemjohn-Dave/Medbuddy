<?php
// Check if PATIENT_ACCESS is defined
if (!defined('PATIENT_ACCESS')) {
    header("Location: ../../auth/index.php");
    exit();
}

// Include database configuration
require_once '../../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Get patient ID
    $stmt = $conn->prepare("SELECT id FROM patients WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    $patient_id = $patient['id'];

    // Get today's appointments
    $today_sql = "SELECT COUNT(*) as count 
                  FROM appointments 
                  WHERE patient_id = ? 
                  AND date = CURDATE() 
                  AND status = 'scheduled'";
    $today_stmt = $conn->prepare($today_sql);
    $today_stmt->execute([$patient_id]);
    $today_count = $today_stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Get upcoming appointments count
    $upcoming_sql = "SELECT COUNT(*) as count 
                     FROM appointments 
                     WHERE patient_id = ? 
                     AND date > CURDATE() 
                     AND status = 'scheduled'";
    $upcoming_stmt = $conn->prepare($upcoming_sql);
    $upcoming_stmt->execute([$patient_id]);
    $upcoming_count = $upcoming_stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Get completed appointments count
    $completed_sql = "SELECT COUNT(*) as count 
                      FROM appointments 
                      WHERE patient_id = ? 
                      AND status = 'completed'";
    $completed_stmt = $conn->prepare($completed_sql);
    $completed_stmt->execute([$patient_id]);
    $completed_count = $completed_stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Get active prescriptions count
    $prescriptions_sql = "SELECT COUNT(DISTINCT p.id) as count 
                         FROM prescriptions p
                         JOIN medical_records mr ON p.medical_record_id = mr.id
                         WHERE mr.patient_id = ? 
                         AND mr.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    $prescriptions_stmt = $conn->prepare($prescriptions_sql);
    $prescriptions_stmt->execute([$patient_id]);
    $prescriptions_count = $prescriptions_stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Get recent test results count
    $test_results_sql = "SELECT COUNT(*) as count 
                        FROM medical_records 
                        WHERE patient_id = ? 
                        AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    $test_results_stmt = $conn->prepare($test_results_sql);
    $test_results_stmt->execute([$patient_id]);
    $test_results_count = $test_results_stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Get unread message count
    $unread_sql = "SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND is_read = 0";
    $unread_stmt = $conn->prepare($unread_sql);
    $unread_stmt->execute([$_SESSION['user_id']]);
    $unread_count = $unread_stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Get today's appointments
    $today_appointments_sql = "SELECT a.*, 
                                     d.first_name as doctor_first_name, 
                                     d.last_name as doctor_last_name,
                                     c.name as clinic_name,
                                     c.address as clinic_address,
                                     a.vitals_recorded
                              FROM appointments a
                              JOIN doctors d ON a.doctor_id = d.id
                              JOIN clinics c ON a.clinic_id = c.id
                              WHERE a.patient_id = ? 
                              AND a.date = CURDATE() 
                              AND a.status = 'scheduled'
                              ORDER BY a.time ASC";
    $today_appointments_stmt = $conn->prepare($today_appointments_sql);
    $today_appointments_stmt->execute([$patient_id]);
    $today_appointments = $today_appointments_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get upcoming appointments
    $upcoming_appointments_sql = "SELECT a.*, 
                                        d.first_name as doctor_first_name, 
                                        d.last_name as doctor_last_name,
                                        c.name as clinic_name,
                                        c.address as clinic_address,
                                        a.vitals_recorded
                                 FROM appointments a
                                 JOIN doctors d ON a.doctor_id = d.id
                                 JOIN clinics c ON a.clinic_id = c.id
                                 WHERE a.patient_id = ? 
                                 AND a.date > CURDATE() 
                                 AND a.status = 'scheduled'
                                 ORDER BY a.date ASC, a.time ASC
                                 LIMIT 5";
    $upcoming_appointments_stmt = $conn->prepare($upcoming_appointments_sql);
    $upcoming_appointments_stmt->execute([$patient_id]);
    $upcoming_appointments = $upcoming_appointments_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent prescriptions
    $recent_prescriptions_sql = "SELECT m.*, 
                                       d.first_name as doctor_first_name,
                                       d.last_name as doctor_last_name
                                FROM medications m
                                JOIN doctors d ON m.prescribed_by = d.id
                                WHERE m.patient_id = ?
                                ORDER BY m.created_at DESC
                                LIMIT 5";
    $recent_prescriptions_stmt = $conn->prepare($recent_prescriptions_sql);
    $recent_prescriptions_stmt->execute([$patient_id]);
    $recent_prescriptions = $recent_prescriptions_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent medical records (test results)
    $recent_tests_sql = "SELECT mr.*, 
                               d.first_name as doctor_first_name,
                               d.last_name as doctor_last_name,
                               vs.*
                        FROM medical_records mr
                        JOIN doctors d ON mr.doctor_id = d.id
                        LEFT JOIN vital_signs vs ON mr.id = vs.medical_record_id
                        WHERE mr.patient_id = ?
                        ORDER BY mr.created_at DESC
                        LIMIT 5";
    $recent_tests_stmt = $conn->prepare($recent_tests_sql);
    $recent_tests_stmt->execute([$patient_id]);
    $recent_tests = $recent_tests_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    // Set default values in case of error
    $today_count = 0;
    $upcoming_count = 0;
    $completed_count = 0;
    $prescriptions_count = 0;
    $test_results_count = 0;
    $unread_count = 0;
    $today_appointments = [];
    $upcoming_appointments = [];
    $recent_prescriptions = [];
    $recent_tests = [];
}
?>

<!-- Add Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container-fluid py-4">
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="d-flex justify-content-end mb-3">
                <div class="form-group">
                    <label for="selectedDate">Select Date:</label>
                    <input type="date" class="form-control" id="selectedDate" name="selectedDate" value="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>
        </div>
    </div>
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Today's Appointments</h5>
                    <h2 class="card-text"><?php echo $today_count; ?></h2>
                    <small>Appointments scheduled for today</small>
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
                    <small>Total completed consultations</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5 class="card-title">Active Prescriptions</h5>
                    <h2 class="card-text"><?php echo $prescriptions_count; ?></h2>
                    <small>Last 30 days</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Today's Appointments -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Today's Appointments</h5>
                    <a href="?page=appointments" class="btn btn-sm btn-outline-primary">
                        <span class="material-icons align-text-bottom">add</span>
                        Schedule New
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($today_appointments)): ?>
                        <div class="text-center text-muted py-4">
                            <span class="material-icons mb-2" style="font-size: 2rem;">event_busy</span>
                            <p class="mb-0">No appointments scheduled for today</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($today_appointments as $appointment): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">Dr. <?php echo htmlspecialchars($appointment['doctor_last_name'] . ', ' . $appointment['doctor_first_name']); ?></h6>
                                            <p class="mb-1 text-muted small">
                                                <?php echo date('h:i A', strtotime($appointment['time'])); ?>
                                            </p>
                                            <p class="mb-0 text-muted small"><?php echo htmlspecialchars($appointment['clinic_name']); ?></p>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-primary">Scheduled</span>
                                            <div class="mt-2">
                                                <button class="btn btn-sm btn-outline-primary view-appointment" 
                                                        data-appointment-id="<?php echo $appointment['id']; ?>"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#appointmentDetailsModal">
                                                    <span class="material-icons">visibility</span>
                                                </button>
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
                            <p class="mb-0">No upcoming appointments</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($upcoming_appointments as $appointment): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">Dr. <?php echo htmlspecialchars($appointment['doctor_last_name'] . ', ' . $appointment['doctor_first_name']); ?></h6>
                                            <p class="mb-1 text-muted small">
                                                <?php echo date('M d, Y', strtotime($appointment['date'])); ?> at 
                                                <?php echo date('h:i A', strtotime($appointment['time'])); ?>
                                            </p>
                                            <p class="mb-0 text-muted small"><?php echo htmlspecialchars($appointment['clinic_name']); ?></p>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-primary">Scheduled</span>
                                            <div class="mt-2">
                                                <button class="btn btn-sm btn-outline-primary view-appointment" 
                                                        data-appointment-id="<?php echo $appointment['id']; ?>"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#appointmentDetailsModal">
                                                    <span class="material-icons">visibility</span>
                                                </button>
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

    <div class="row">
        <!-- Recent Prescriptions -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Recent Prescriptions</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recent_prescriptions)): ?>
                        <div class="text-center text-muted py-4">
                            <span class="material-icons mb-2" style="font-size: 2rem;">medication</span>
                            <p class="mb-0">No recent prescriptions</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recent_prescriptions as $prescription): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($prescription['medication_name']); ?></h6>
                                            <p class="mb-1 text-muted small">
                                                Dr. <?php echo htmlspecialchars($prescription['doctor_last_name'] . ', ' . $prescription['doctor_first_name']); ?>
                                            </p>
                                            <p class="mb-0 text-muted small">
                                                <?php echo date('M d, Y', strtotime($prescription['created_at'])); ?>
                                            </p>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-info"><?php echo htmlspecialchars($prescription['dosage']); ?></span>
                                            <span class="badge bg-<?php echo $prescription['status'] === 'active' ? 'success' : ($prescription['status'] === 'completed' ? 'secondary' : 'warning'); ?>">
                                                <?php echo ucfirst($prescription['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-white">
                    <a href="?page=prescriptions" class="btn btn-primary">View All Prescriptions</a>
                </div>
            </div>
        </div>

        <!-- Recent Test Results -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Recent Medical Records</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recent_tests)): ?>
                        <div class="text-center text-muted py-4">
                            <span class="material-icons mb-2" style="font-size: 2rem;">science</span>
                            <p class="mb-0">No recent test results</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recent_tests as $test): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">Medical Record (<?php echo date('M d, Y', strtotime($test['created_at'])); ?>)</h6>
                                            <p class="mb-1 text-muted small">
                                                Dr. <?php echo htmlspecialchars($test['doctor_last_name'] . ', ' . $test['doctor_first_name']); ?>
                                            </p>
                                        </div>
                                        <div class="text-end">
                                            <?php if (!empty($test['blood_pressure_systolic'])): ?>
                                                <span class="badge bg-info">Vitals Recorded</span>
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-outline-primary view-medical-record mt-2"
                                                    data-medical-record-id="<?php echo $test['id']; ?>"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#medicalRecordDetailsModal">
                                                <span class="material-icons">visibility</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-white">
                    <a href="?page=medical-records" class="btn btn-primary">View All Records</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Appointment Details Modal -->
<div class="modal fade" id="appointmentDetailsModal" tabindex="-1" aria-labelledby="appointmentDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="appointmentDetailsModalLabel">Appointment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Doctor:</strong> <span id="modalDoctorName"></span></p>
                        <p><strong>Date:</strong> <span id="modalDate"></span></p>
                        <p><strong>Time:</strong> <span id="modalTime"></span></p>
                        <p><strong>Status:</strong> <span id="modalStatus"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Clinic:</strong> <span id="modalClinic"></span></p>
                        <p><strong>Address:</strong> <span id="modalAddress"></span></p>
                    </div>
                </div>
                <div class="mb-3">
                    <h6>Purpose</h6>
                    <p id="modalPurpose"></p>
                </div>
                <div class="mb-3">
                    <h6>Notes</h6>
                    <p id="modalNotes"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" id="cancelAppointmentBtn">Cancel Appointment</button>
                <button type="button" class="btn btn-primary" id="rescheduleAppointmentBtn">Reschedule</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Medical Record Details Modal (Similar to Appointment Details Modal) -->
<div class="modal fade" id="medicalRecordDetailsModal" tabindex="-1" aria-labelledby="medicalRecordDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="medicalRecordDetailsModalLabel">Medical Record Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Doctor:</strong> <span id="modalMRDoctorName"></span></p>
                        <p><strong>Record Date:</strong> <span id="modalMRDate"></span></p>
                        <p><strong>Record Time:</strong> <span id="modalMRTime"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Appointment:</strong> <span id="modalMRAppointment"></span></p>
                        <p><strong>Record Type:</strong> <span id="modalMRType"></span></p>
                    </div>
                </div>
                <hr>
                <div class="mb-3">
                    <h6>Chief Complaint</h6>
                    <p id="modalMRChiefComplaint"></p>
                </div>
                 <hr>
                 <div class="mb-3">
                    <h6>Vital Signs</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Blood Pressure:</strong> <span id="modalMRBP"></span></p>
                            <p><strong>Heart Rate:</strong> <span id="modalMRHR"></span></p>
                            <p><strong>Respiratory Rate:</strong> <span id="modalMRRR"></span></p>
                            <p><strong>Temperature:</strong> <span id="modalMRT"></span></p>
                        </div>
                        <div class="col-md-6">
                             <p><strong>Oxygen Saturation:</strong> <span id="modalMRO2"></span></p>
                             <p><strong>Weight:</strong> <span id="modalMRWeight"></span></p>
                             <p><strong>Height:</strong> <span id="modalMRHeight"></span></p>
                             <p><strong>BMI:</strong> <span id="modalMRBMI"></span></p>
                             <p><strong>Pain Scale:</strong> <span id="modalMRPain"></span></p>
                        </div>
                    </div>
                     <p><strong>Vitals Notes:</strong> <span id="modalMRVitalsNotes"></span></p>
                    <p><small class="text-muted">Recorded At: <span id="modalMRVitalsRecordedAt"></span></small></p>
                </div>
                <hr>
                <div class="mb-3">
                    <h6>Diagnosis</h6>
                    <p id="modalMRDiagnosis"></p>
                </div>
                <hr>
                <div class="mb-3">
                    <h6>Treatment Plan</h6>
                    <p id="modalMRTreatmentPlan"></p>
                </div>
                <hr>
                <div class="mb-3">
                    <h6>Prescription</h6>
                    <p id="modalMRPrescription"></p>
                </div>
                 <hr>
                 <div class="mb-3">
                    <h6>Notes</h6>
                    <p id="modalMRNotes"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

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

<script>
// Initialize date picker
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('selectedDate').addEventListener('change', function() {
        window.location.href = '?page=dashboard&date=' + this.value;
    });

    // View appointment details
    document.querySelectorAll('.view-appointment').forEach(button => {
        button.addEventListener('click', function() {
            console.log('View appointment button clicked!'); // Debug log
            const appointmentId = this.dataset.appointmentId;
            fetch(`../../api/get_appointment.php?id=${appointmentId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const appointment = data.appointment;
                        document.getElementById('modalDoctorName').textContent = 
                            `Dr. ${appointment.doctor_last_name || ''}, ${appointment.doctor_first_name || ''}`;
                        document.getElementById('modalDate').textContent = 
                            new Date(appointment.date).toLocaleDateString('en-US', { 
                                weekday: 'long', 
                                year: 'numeric', 
                                month: 'long', 
                                day: 'numeric' 
                            });
                        document.getElementById('modalTime').textContent = 
                            new Date(`2000-01-01T${appointment.time}`).toLocaleTimeString('en-US', {
                                hour: 'numeric',
                                minute: '2-digit'
                            });
                        document.getElementById('modalStatus').innerHTML = 
                            `<span class="badge bg-${appointment.status === 'scheduled' ? 'primary' : 
                                (appointment.status === 'completed' ? 'success' : 'danger')}">${
                                appointment.status.charAt(0).toUpperCase() + appointment.status.slice(1)}</span>`;
                        document.getElementById('modalClinic').textContent = appointment.clinic_name || '';
                        document.getElementById('modalAddress').textContent = appointment.clinic_address || '';
                        document.getElementById('modalPurpose').textContent = appointment.purpose;
                        document.getElementById('modalNotes').textContent = appointment.notes || 'No additional notes';

                        // Show/hide action buttons based on appointment status
                        const cancelBtn = document.getElementById('cancelAppointmentBtn');
                        const rescheduleBtn = document.getElementById('rescheduleAppointmentBtn');
                        
                        if (appointment.status === 'scheduled') {
                            cancelBtn.style.display = 'inline-block';
                            rescheduleBtn.style.display = 'inline-block';
                        } else {
                            cancelBtn.style.display = 'none';
                            rescheduleBtn.style.display = 'none';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load appointment details');
                });
        });
    });

    // Cancel appointment
    document.getElementById('cancelAppointmentBtn').addEventListener('click', function() {
        const appointmentId = this.dataset.appointmentId;
        if (confirm('Are you sure you want to cancel this appointment?')) {
            fetch('api/cancel_appointment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: appointmentId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Failed to cancel appointment');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to cancel appointment');
            });
        }
    });

    // Reschedule appointment
    document.getElementById('rescheduleAppointmentBtn').addEventListener('click', function() {
        const appointmentId = this.dataset.appointmentId;
        window.location.href = `?page=appointments&reschedule=${appointmentId}`;
    });

    // View medical record details
    document.querySelectorAll('.view-medical-record').forEach(button => {
        button.addEventListener('click', function() {
            console.log('View medical record button clicked!'); // Debug log
            const medicalRecordId = this.dataset.medicalRecordId;
            fetch(`../../api/get_medical_record.php?id=${medicalRecordId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const medicalRecord = data.medicalRecord;
                        document.getElementById('modalMRDoctorName').textContent = 
                            `Dr. ${medicalRecord.doctor_last_name || ''}, ${medicalRecord.doctor_first_name || ''}`;
                        document.getElementById('modalMRDate').textContent = 
                            new Date(medicalRecord.created_at).toLocaleDateString('en-US', { 
                                weekday: 'long', 
                                year: 'numeric', 
                                month: 'long', 
                                day: 'numeric' 
                            });
                        document.getElementById('modalMRTime').textContent = 
                            new Date(`2000-01-01T${medicalRecord.time}`).toLocaleTimeString('en-US', {
                                hour: 'numeric',
                                minute: '2-digit'
                            });
                        document.getElementById('modalMRAppointment').textContent = medicalRecord.appointment_purpose || 'No purpose specified';
                        document.getElementById('modalMRType').textContent = medicalRecord.record_type || 'Unknown type';
                        document.getElementById('modalMRChiefComplaint').textContent = medicalRecord.chief_complaint || 'No chief complaint';
                        document.getElementById('modalMRBP').textContent = medicalRecord.vital_signs.blood_pressure_systolic ? `${medicalRecord.vital_signs.blood_pressure_systolic}/${medicalRecord.vital_signs.blood_pressure_diastolic}` : 'N/A';
                        document.getElementById('modalMRHR').textContent = medicalRecord.vital_signs.heart_rate ? `${medicalRecord.vital_signs.heart_rate} bpm` : 'N/A';
                        document.getElementById('modalMRRR').textContent = medicalRecord.vital_signs.respiratory_rate ? `${medicalRecord.vital_signs.respiratory_rate} breaths/min` : 'N/A';
                        document.getElementById('modalMRT').textContent = medicalRecord.vital_signs.temperature ? `${medicalRecord.vital_signs.temperature}°C` : 'N/A';
                        document.getElementById('modalMRO2').textContent = medicalRecord.vital_signs.oxygen_saturation ? `${medicalRecord.vital_signs.oxygen_saturation}%` : 'N/A';
                        document.getElementById('modalMRWeight').textContent = medicalRecord.vital_signs.weight ? `${medicalRecord.vital_signs.weight} kg` : 'N/A';
                        document.getElementById('modalMRHeight').textContent = medicalRecord.vital_signs.height ? `${medicalRecord.vital_signs.height} cm` : 'N/A';
                        document.getElementById('modalMRBMI').textContent = medicalRecord.vital_signs.bmi ? `${medicalRecord.vital_signs.bmi} kg/m²` : 'N/A';
                        document.getElementById('modalMRPain').textContent = medicalRecord.vital_signs.pain_scale ? `${medicalRecord.vital_signs.pain_scale}/10` : 'N/A';
                        document.getElementById('modalMRVitalsNotes').textContent = medicalRecord.vital_signs.notes || 'No additional notes';
                        document.getElementById('modalMRVitalsRecordedAt').textContent = medicalRecord.vital_signs.recorded_at ? new Date(medicalRecord.vital_signs.recorded_at).toLocaleString('en-US') : 'N/A';
                        document.getElementById('modalMRDiagnosis').textContent = medicalRecord.diagnosis || 'No diagnosis';
                        document.getElementById('modalMRTreatmentPlan').textContent = medicalRecord.treatment_plan || 'No treatment plan';
                        document.getElementById('modalMRPrescription').textContent = medicalRecord.prescription || 'No prescription';
                        document.getElementById('modalMRNotes').textContent = medicalRecord.notes || 'No additional notes';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load medical record details');
                });
        });
    });
});
</script> 