<?php
// Check if STAFF_ACCESS is defined
if (!defined('STAFF_ACCESS')) {
    header("Location: ../../auth/index.php");
    exit();
}

require_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get staff's assigned clinics
$staff_user_id = $_SESSION['user_id'];
$conn = $db;
$stmt = $conn->prepare("SELECT sc.clinic_id FROM staff_clinics sc 
                       JOIN staff s ON sc.staff_id = s.id 
                       WHERE s.user_id = ?");
$stmt->execute([$staff_user_id]);
$assigned_clinics = $stmt->fetchAll(PDO::FETCH_COLUMN);

$appointments = [];
if (!empty($assigned_clinics)) {
    $placeholders = implode(',', array_fill(0, count($assigned_clinics), '?'));
    $sql = "SELECT a.*, 
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            p.contact_number as patient_contact,
            CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
            p.id as patient_id,
            p.date_of_birth,
            vs.blood_pressure_systolic,
            vs.blood_pressure_diastolic,
            vs.heart_rate,
            vs.respiratory_rate,
            vs.temperature,
            vs.oxygen_saturation,
            vs.weight,
            vs.height,
            vs.bmi,
            vs.pain_scale,
            vs.notes as vitals_notes,
            vs.recorded_at as vitals_recorded_at,
            CASE WHEN vs.id IS NOT NULL THEN 1 ELSE 0 END as has_vitals
            FROM appointments a
            JOIN patients p ON a.patient_id = p.id
            JOIN doctors d ON a.doctor_id = d.id
            LEFT JOIN medical_records mr ON a.id = mr.appointment_id
            LEFT JOIN vital_signs vs ON mr.id = vs.medical_record_id
            WHERE DATE(a.date) = CURDATE()
            AND a.status = 'scheduled'
            AND a.clinic_id IN ($placeholders)
            ORDER BY a.date ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute($assigned_clinics);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">Record Vital Signs</h4>
    </div>

    <?php if (empty($appointments)): ?>
        <div class="alert alert-info">
            <i class="material-icons align-middle me-2">info</i>
            No scheduled appointments for today.
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($appointments as $appointment): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100 <?php echo $appointment['has_vitals'] ? 'border-success' : ''; ?>">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <?php echo htmlspecialchars($appointment['patient_name']); ?>
                            </h5>
                            <?php if ($appointment['has_vitals']): ?>
                                <span class="badge bg-success">
                                    <i class="material-icons align-middle" style="font-size: 1rem;">check_circle</i>
                                    Vitals Recorded
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>Appointment Time:</strong><br>
                                <?php echo date('h:i A', strtotime($appointment['date'])); ?>
                            </div>
                            <div class="mb-3">
                                <strong>Doctor:</strong><br>
                                Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?>
                            </div>
                            <div class="mb-3">
                                <strong>Contact:</strong><br>
                                <?php echo htmlspecialchars($appointment['patient_contact']); ?>
                            </div>
                            <div class="mb-3">
                                <strong>Age:</strong><br>
                                <?php 
                                    $dob = new DateTime($appointment['date_of_birth']);
                                    $now = new DateTime();
                                    $age = $dob->diff($now)->y;
                                    echo $age . ' years old';
                                ?>
                            </div>
                            
                            <?php if ($appointment['has_vitals']): ?>
                                <hr>
                                <h6>Recorded Vitals:</h6>
                                <p class="mb-1"><strong>BP:</strong> <?php echo htmlspecialchars($appointment['blood_pressure_systolic'] . '/' . $appointment['blood_pressure_diastolic']); ?> mmHg</p>
                                <p class="mb-1"><strong>Temp:</strong> <?php echo htmlspecialchars($appointment['temperature']); ?> °C</p>
                                <p class="mb-1"><strong>HR:</strong> <?php echo htmlspecialchars($appointment['heart_rate']); ?> bpm</p>
                                <p class="mb-1"><strong>RR:</strong> <?php echo htmlspecialchars($appointment['respiratory_rate']); ?> breaths/min</p>
                                <p class="mb-1"><strong>SpO2:</strong> <?php echo htmlspecialchars($appointment['oxygen_saturation']); ?> %</p>
                                <p class="mb-1"><strong>Weight:</strong> <?php echo htmlspecialchars($appointment['weight']); ?> kg</p>
                                <?php if (!empty($appointment['height'])): ?>
                                    <p class="mb-1"><strong>Height:</strong> <?php echo htmlspecialchars($appointment['height']); ?> cm</p>
                                <?php endif; ?>
                                <?php if (!empty($appointment['bmi'])): ?>
                                    <p class="mb-1"><strong>BMI:</strong> <?php echo htmlspecialchars($appointment['bmi']); ?> kg/m²</p>
                                <?php endif; ?>
                                <?php if (!empty($appointment['pain_scale'])): ?>
                                    <p class="mb-1"><strong>Pain:</strong> <?php echo htmlspecialchars($appointment['pain_scale']); ?>/10</p>
                                <?php endif; ?>
                                <?php if (!empty($appointment['vitals_notes'])): ?>
                                     <p class="mb-1"><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($appointment['vitals_notes'])); ?></p>
                                <?php endif; ?>
                                <p><small class="text-muted">Recorded at: <?php echo date('M d, Y h:i A', strtotime($appointment['vitals_recorded_at'])); ?></small></p>
                                <hr>
                            <?php endif; ?>

                            <button type="button" class="btn <?php echo $appointment['has_vitals'] ? 'btn-success' : 'btn-primary'; ?> w-100" 
                                    onclick="openVitalSignsModal(<?php echo htmlspecialchars(json_encode($appointment)); ?>)">
                                <?php echo $appointment['has_vitals'] ? 'Update Vital Signs' : 'Record Vital Signs'; ?>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Vital Signs Modal -->
<div class="modal fade" id="vitalSignsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Record Vital Signs</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="vitalSignsForm">
                    <input type="hidden" name="appointment_id" id="appointment_id">
                    <input type="hidden" name="patient_id" id="patient_id">
                    
                    <div class="row g-3">
                        <!-- Blood Pressure -->
                        <div class="col-md-6">
                            <label class="form-label">Blood Pressure (mmHg)</label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="systolic" placeholder="Systolic" required>
                                <span class="input-group-text">/</span>
                                <input type="number" class="form-control" name="diastolic" placeholder="Diastolic" required>
                            </div>
                        </div>
                        
                        <!-- Temperature -->
                        <div class="col-md-6">
                            <label class="form-label">Temperature (°C)</label>
                            <input type="number" class="form-control" name="temperature" step="0.1" required>
                        </div>
                        
                        <!-- Heart Rate -->
                        <div class="col-md-6">
                            <label class="form-label">Heart Rate (bpm)</label>
                            <input type="number" class="form-control" name="pulse_rate" required>
                        </div>
                        
                        <!-- Respiratory Rate -->
                        <div class="col-md-6">
                            <label class="form-label">Respiratory Rate (bpm)</label>
                            <input type="number" class="form-control" name="respiratory_rate" required>
                        </div>
                        
                        <!-- Oxygen Saturation -->
                        <div class="col-md-6">
                            <label class="form-label">Oxygen Saturation (%)</label>
                            <input type="number" class="form-control" name="oxygen_saturation" required>
                        </div>
                        
                        <!-- Weight -->
                        <div class="col-md-6">
                            <label class="form-label">Weight (kg)</label>
                            <input type="number" class="form-control" name="weight" step="0.1" required>
                        </div>
                        
                        <!-- Height -->
                        <div class="col-md-6">
                            <label class="form-label">Height (cm)</label>
                            <input type="number" class="form-control" name="height" step="0.1">
                        </div>
                        
                        <!-- Pain Scale -->
                        <div class="col-md-6">
                            <label class="form-label">Pain Scale (0-10)</label>
                            <input type="number" class="form-control" name="pain_scale" min="0" max="10">
                        </div>
                        
                        <!-- Notes -->
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveVitalSigns()">Save Vital Signs</button>
            </div>
        </div>
    </div>
</div>

<script>
function openVitalSignsModal(appointment) {
    document.getElementById('appointment_id').value = appointment.id;
    document.getElementById('patient_id').value = appointment.patient_id;
    
    const form = document.getElementById('vitalSignsForm');
    form.reset(); // Always reset first

    // If vitals are already recorded, populate the form fields
    // The data is already available in the appointment object due to the updated query
    if (appointment.has_vitals > 0) { 
        // Populate form fields with existing vitals
        form.querySelector('input[name="systolic"]').value = appointment.blood_pressure_systolic || '';
        form.querySelector('input[name="diastolic"]').value = appointment.blood_pressure_diastolic || '';
        form.querySelector('input[name="temperature"]').value = appointment.temperature || '';
        form.querySelector('input[name="pulse_rate"]').value = appointment.heart_rate || '';
        form.querySelector('input[name="respiratory_rate"]').value = appointment.respiratory_rate || '';
        form.querySelector('input[name="oxygen_saturation"]').value = appointment.oxygen_saturation || '';
        form.querySelector('input[name="weight"]').value = appointment.weight || '';
        form.querySelector('input[name="height"]').value = appointment.height || '';
        form.querySelector('input[name="pain_scale"]').value = appointment.pain_scale || '';
        form.querySelector('textarea[name="notes"]').value = appointment.vitals_notes || ''; // Use vitals_notes

        // Trigger BMI calculation if height and weight are populated
        const weightInput = form.querySelector('input[name="weight"]');
        const heightInput = form.querySelector('input[name="height"]');
        if (weightInput && heightInput) {
            weightInput.dispatchEvent(new Event('input'));
            heightInput.dispatchEvent(new Event('input'));
        }
    }
    
    // Show modal
    var modal = new bootstrap.Modal(document.getElementById('vitalSignsModal'));
    modal.show();
}

function saveVitalSigns() {
    const form = document.getElementById('vitalSignsForm');
    const formData = new FormData(form);
    
    // Re-add appointment_id and patient_id to formData as they are needed by the API
    formData.append('appointment_id', document.getElementById('appointment_id').value);
    formData.append('patient_id', document.getElementById('patient_id').value);

    fetch('../../api/record_vitals.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: data.message
            }).then(() => {
                // Close modal and refresh page
                const modal = bootstrap.Modal.getInstance(document.getElementById('vitalSignsModal'));
                modal.hide();
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: data.message
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'An error occurred while saving vital signs.'
        });
    });
}
</script>

<style>
.card {
    transition: transform 0.2s;
}

.card:hover {
    transform: translateY(-5px);
}

.input-group-text {
    background-color: #f8f9fa;
}

.badge {
    font-size: 0.8rem;
    padding: 0.5em 0.8em;
}

.badge .material-icons {
    margin-right: 0.2rem;
}

/* Style for displaying vitals */
.card-body hr {
    margin-top: 1rem;
    margin-bottom: 1rem;
}

.card-body h6 {
    margin-bottom: 0.75rem;
}
</style> 