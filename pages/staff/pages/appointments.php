<?php
// Check if STAFF_ACCESS is defined
if (!defined('STAFF_ACCESS')) {
    header("Location: ../../auth/index.php");
    exit();
}

require_once(__DIR__ . '/../../../config/database.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get staff's assigned clinics
$staff_user_id = $_SESSION['user_id'];
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->prepare("SELECT sc.clinic_id FROM staff_clinics sc 
                       JOIN staff s ON sc.staff_id = s.id 
                       WHERE s.user_id = ?");
$stmt->execute([$staff_user_id]);
$assigned_clinics = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($assigned_clinics)) {
    $appointments = [];
} else {
    $placeholders = implode(',', array_fill(0, count($assigned_clinics), '?'));
    $sql = "SELECT a.*, 
                   CONCAT(p.first_name, ' ', IFNULL(p.middle_name, ''), ' ', p.last_name) AS patient_name, 
                   p.contact_number AS patient_contact, 
                   CONCAT(d.first_name, ' ', IFNULL(d.middle_name, ''), ' ', d.last_name) AS doctor_name
            FROM appointments a
            JOIN patients p ON a.patient_id = p.id
            JOIN doctors d ON a.doctor_id = d.id
            WHERE a.clinic_id IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    $stmt->execute($assigned_clinics);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get all doctors for filter
$stmt = $conn->prepare("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM doctors ORDER BY first_name");
$stmt->execute();
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">Appointments</h4>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAppointmentModal">
            <i class="material-icons align-middle me-1">add</i> New Appointment
        </button>
    </div>

    <!-- Filters -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="row g-3">
                <input type="hidden" name="page" value="appointments">
                <div class="col-md-3">
                    <label class="form-label">Date</label>
                    <input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($date); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="scheduled" <?php echo $status === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                        <option value="confirmed" <?php echo $status === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Doctor</label>
                    <select class="form-select" name="doctor_id">
                        <option value="">All Doctors</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?php echo $doctor['id']; ?>" <?php echo $doctor_id == $doctor['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($doctor['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2" name="filter">Filter</button>
                    <a href="?page=appointments" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Appointments List -->
    <?php if (empty($appointments)): ?>
        <div class="alert alert-info">
            <i class="material-icons align-middle me-2">info</i>
            No appointments found for the selected filters.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Date & Time</th>
                        <th>Patient</th>
                        <th>Contact</th>
                        <th>Doctor</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $appointment): ?>
                        <tr>
                            <td>
                                <div class="fw-bold"><?php echo date('M d, Y', strtotime($appointment['date'])); ?></div>
                                <small class="text-muted"><?php echo date('h:i A', strtotime($appointment['date'])); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($appointment['patient_name']); ?></td>
                            <td><?php echo htmlspecialchars($appointment['patient_contact']); ?></td>
                            <td><?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                            <td>
                                <?php
                                $statusClass = [
                                    'scheduled' => 'bg-warning',
                                    'confirmed' => 'bg-info',
                                    'completed' => 'bg-success',
                                    'cancelled' => 'bg-danger'
                                ][$appointment['status']] ?? 'bg-secondary';
                                ?>
                                <span class="badge <?php echo $statusClass; ?>">
                                    <?php echo ucfirst($appointment['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            onclick="viewAppointment(this)"
                                            data-appointment='<?php echo json_encode($appointment); ?>'>
                                        <i class="material-icons">visibility</i>
                                    </button>
                                    <?php if ($appointment['status'] === 'scheduled' || $appointment['status'] === 'no-show'): ?>
                                        <?php
                                            if ($appointment['status'] === 'scheduled') {
                                                $apptDateTime = strtotime($appointment['date'] . ' ' . $appointment['time']);
                                                $now = time();
                                                $canNoShow = $now >= $apptDateTime + 5 * 60;
                                            }
                                        ?>
                                        <?php if ($appointment['status'] === 'scheduled'): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                onclick="markNoShow(<?php echo $appointment['id']; ?>)"
                                                <?php if (!$canNoShow) echo 'disabled'; ?>
                                                title="Mark as No-Show (enabled 5 min after scheduled time)">
                                                <i class="material-icons">block</i>
                                            </button>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                            onclick="cancelAppointment(<?php echo $appointment['id']; ?>)"
                                            title="Cancel Appointment">
                                            <i class="material-icons">cancel</i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Add Appointment Modal -->
<div class="modal fade" id="addAppointmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addAppointmentForm">
                    <div class="mb-3">
                        <label class="form-label">Patient</label>
                        <select class="form-select" name="patient_id" required>
                            <option value="">Select Patient</option>
                            <!-- Will be populated via AJAX -->
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Doctor</label>
                        <select class="form-select" name="doctor_id" required>
                            <option value="">Select Doctor</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['id']; ?>">
                                    <?php echo htmlspecialchars($doctor['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date & Time</label>
                        <input type="datetime-local" class="form-control" name="date" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveAppointment()">Save Appointment</button>
            </div>
        </div>
    </div>
</div>

<!-- Appointment Details Modal -->
<div class="modal fade" id="viewAppointmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Appointment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Date & Time</dt>
                    <dd class="col-sm-8" id="modalDateTime"></dd>
                    <dt class="col-sm-4">Patient</dt>
                    <dd class="col-sm-8" id="modalPatient"></dd>
                    <dt class="col-sm-4">Contact</dt>
                    <dd class="col-sm-8" id="modalContact"></dd>
                    <dt class="col-sm-4">Doctor</dt>
                    <dd class="col-sm-8" id="modalDoctor"></dd>
                    <dt class="col-sm-4">Status</dt>
                    <dd class="col-sm-8" id="modalStatus"></dd>
                    <dt class="col-sm-4">Notes</dt>
                    <dd class="col-sm-8" id="modalNotes"></dd>
                </dl>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Function to load patients for the select dropdown
function loadPatients() {
    fetch('../../api/patients.php')
        .then(response => response.json())
        .then(data => {
            const select = document.querySelector('select[name="patient_id"]');
            select.innerHTML = '<option value="">Select Patient</option>';
            data.forEach(patient => {
                select.innerHTML += `<option value="${patient.id}">${patient.first_name} ${patient.last_name}</option>`;
            });
        })
        .catch(error => console.error('Error loading patients:', error));
}

// Function to update appointment status
function updateStatus(appointmentId, status) {
    if (!confirm('Are you sure you want to update this appointment\'s status?')) return;
    
    fetch('../../api/appointments.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            id: appointmentId,
            status: status
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error updating appointment: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating appointment');
    });
}

// Function to save new appointment
function saveAppointment() {
    const form = document.getElementById('addAppointmentForm');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    fetch('../../api/appointments.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error creating appointment: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error creating appointment');
    });
}

// Function to show appointment details in modal
function viewAppointment(btn) {
    var appointment = btn.getAttribute('data-appointment');
    if (appointment) {
        appointment = JSON.parse(appointment);
        document.getElementById('modalDateTime').textContent = 
            new Date(appointment.date).toLocaleString();
        document.getElementById('modalPatient').textContent = appointment.patient_name;
        document.getElementById('modalContact').textContent = appointment.patient_contact;
        document.getElementById('modalDoctor').textContent = appointment.doctor_name;
        document.getElementById('modalStatus').textContent = appointment.status.charAt(0).toUpperCase() + appointment.status.slice(1);
        document.getElementById('modalNotes').textContent = appointment.notes || '-';
        var modal = new bootstrap.Modal(document.getElementById('viewAppointmentModal'));
        modal.show();
    }
}

// Function to mark appointment as No-Show
function markNoShow(appointmentId) {
    if (!confirm('Are you sure you want to mark this patient as No-Show?')) return;
    fetch('../../api/update_appointment_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            appointment_id: appointmentId,
            status: 'no-show',
            notes: 'Patient did not arrive within 5 minutes.'
        })
    })
    .then(response => response.text())
    .then(text => {
        try {
            const data = JSON.parse(text);
            if (data.success) {
                alert('Appointment marked as No-Show.');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        } catch (e) {
            console.error('Non-JSON response:', text);
            alert('Server error: ' + text);
        }
    })
    .catch(error => {
        alert('Error: ' + error);
    });
}

// Function to cancel appointment
function cancelAppointment(appointmentId) {
    if (!confirm('Are you sure you want to cancel this appointment?')) return;
    fetch('../../api/update_appointment_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            appointment_id: appointmentId,
            status: 'cancelled',
            notes: 'Cancelled by staff.'
        })
    })
    .then(response => response.text())
    .then(text => {
        try {
            const data = JSON.parse(text);
            if (data.success) {
                alert('Appointment cancelled.');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        } catch (e) {
            console.error('Non-JSON response:', text);
            alert('Server error: ' + text);
        }
    })
    .catch(error => {
        alert('Error: ' + error);
    });
}

// Load patients when modal is opened
document.getElementById('addAppointmentModal').addEventListener('show.bs.modal', loadPatients);
</script>

<style>
.table th {
    white-space: nowrap;
}
.btn-group .btn {
    padding: 0.25rem 0.5rem;
}
.btn-group .material-icons {
    font-size: 1.1rem;
}
</style> 