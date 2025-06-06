<?php
// Check if DOCTOR_ACCESS is defined
if (!defined('DOCTOR_ACCESS')) {
    header("Location: ../../auth/index.php");
    exit();
}

require_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get appointment ID from URL
$appointment_id = isset($_GET['appointment_id']) ? $_GET['appointment_id'] : die('ERROR: Appointment ID not specified.');

// Get doctor ID from session and database
if (!isset($_SESSION['user_id'])) {
     echo "<div class=\"alert alert-danger\">User not logged in.</div>";
     exit();
}
$user_id = $_SESSION['user_id'];

try {
    $stmt = $db->prepare("SELECT id FROM doctors WHERE user_id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$doctor) {
        echo "<div class=\"alert alert-danger\">Doctor record not found.</div>";
        exit();
    }
    $doctor_id = $doctor['id'];

    // Fetch appointment details
    $query = "SELECT a.*, 
                    p.first_name as patient_first_name, 
                    p.last_name as patient_last_name, 
                    p.date_of_birth as patient_dob,
                    p.gender as patient_gender,
                    c.name as clinic_name,
                    c.address as clinic_address
              FROM appointments a
              JOIN patients p ON a.patient_id = p.id
              JOIN clinics c ON a.clinic_id = c.id
              WHERE a.id = ? AND a.doctor_id = ? LIMIT 1";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$appointment_id, $doctor_id]); // Use the fetched doctor_id
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        echo "<div class=\"alert alert-danger\">Appointment not found or you don't have access.</div>";
        exit();
    }

} catch (PDOException $e) {
    die("ERROR: Could not fetch appointment details." . $e->getMessage());
}

// Calculate patient age
$dob = new DateTime($appointment['patient_dob']);
$now = new DateTime();
$age = $now->diff($dob)->y;
?>

<div class="container py-4">
    <h4>Consultation for Appointment #<?php echo htmlspecialchars($appointment_id); ?></h4>
    
    <div class="card mb-4">
        <div class="card-header bg-light">
            Appointment & Patient Details
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Patient:</strong> <?php echo htmlspecialchars($appointment['patient_last_name'] . ', ' . $appointment['patient_first_name']); ?></p>
                    <p><strong>Date of Birth:</strong> <?php echo date('M d, Y', strtotime($appointment['patient_dob'])); ?> (<?php echo $age; ?> years old)</p>
                    <p><strong>Gender:</strong> <?php echo htmlspecialchars(ucfirst($appointment['patient_gender'])); ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Date:</strong> <?php echo date('M d, Y', strtotime($appointment['date'])); ?></p>
                    <p><strong>Time:</strong> <?php echo date('h:i A', strtotime($appointment['time'])); ?></p>
                    <p><strong>Clinic:</strong> <?php echo htmlspecialchars($appointment['clinic_name']); ?></p>
                    <p><strong>Reason for Visit:</strong> <?php echo htmlspecialchars($appointment['purpose']); ?></p>
                </div>
            </div>
        </div>
    </div>

    <form id="consultationForm">
        <input type="hidden" name="appointment_id" value="<?php echo htmlspecialchars($appointment_id); ?>">
        <input type="hidden" name="patient_id" value="<?php echo htmlspecialchars($appointment['patient_id']); ?>">
        <input type="hidden" name="doctor_id" value="<?php echo htmlspecialchars($doctor_id); ?>">

        <!-- Vital Signs Section -->
        <div class="card mb-4">
            <div class="card-header bg-light">Vital Signs</div>
            <div class="card-body">
                 <div class="row g-3">
                    <div class="col-md-4">
                        <label for="blood_pressure" class="form-label">Blood Pressure (mmHg)</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="blood_pressure_systolic" name="vital_signs[blood_pressure_systolic]" placeholder="Systolic">
                            <span class="input-group-text">/</span>
                            <input type="text" class="form-control" id="blood_pressure_diastolic" name="vital_signs[blood_pressure_diastolic]" placeholder="Diastolic">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label for="heart_rate" class="form-label">Heart Rate (bpm)</label>
                        <input type="text" class="form-control" id="heart_rate" name="vital_signs[heart_rate]">
                    </div>
                    <div class="col-md-4">
                        <label for="respiratory_rate" class="form-label">Respiratory Rate (breaths/min)</label>
                        <input type="text" class="form-control" id="respiratory_rate" name="vital_signs[respiratory_rate]">
                    </div>
                    <div class="col-md-4">
                        <label for="temperature" class="form-label">Temperature (°C)</label>
                        <input type="text" class="form-control" id="temperature" name="vital_signs[temperature]">
                    </div>
                     <div class="col-md-4">
                        <label for="oxygen_saturation" class="form-label">Oxygen Saturation (%)</label>
                        <input type="text" class="form-control" id="oxygen_saturation" name="vital_signs[oxygen_saturation]">
                    </div>
                    <div class="col-md-4">
                        <label for="weight" class="form-label">Weight (kg)</label>
                        <input type="text" class="form-control" id="weight" name="vital_signs[weight]">
                    </div>
                    <div class="col-md-4">
                        <label for="height" class="form-label">Height (cm)</label>
                        <input type="text" class="form-control" id="height" name="vital_signs[height]">
                    </div>
                     <div class="col-md-4">
                        <label for="bmi" class="form-label">BMI (kg/m²)</label>
                        <input type="text" class="form-control" id="bmi" name="vital_signs[bmi]" readonly>
                    </div>
                     <div class="col-md-4">
                        <label for="pain_scale" class="form-label">Pain Scale (0-10)</label>
                        <input type="number" class="form-control" id="pain_scale" name="vital_signs[pain_scale]" min="0" max="10">
                    </div>
                    <div class="col-12">
                        <label for="vitals_notes" class="form-label">Additional Vitals Notes</label>
                        <textarea class="form-control" id="vitals_notes" name="vital_signs[notes]" rows="2"></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Consultation Details Section -->
        <div class="card mb-4">
            <div class="card-header bg-light">Consultation Details</div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="chief_complaint" class="form-label">Chief Complaint</label>
                    <textarea class="form-control" id="chief_complaint" name="chief_complaint" rows="3" required></textarea>
                </div>
                <div class="mb-3">
                    <label for="diagnosis" class="form-label">Diagnosis</label>
                    <textarea class="form-control" id="diagnosis" name="diagnosis" rows="3"></textarea>
                </div>
                <div class="mb-3">
                    <label for="treatment_plan" class="form-label">Treatment Plan</label>
                    <textarea class="form-control" id="treatment_plan" name="treatment_plan" rows="3"></textarea>
                </div>
                 <div class="mb-3">
                    <label for="notes" class="form-label">General Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                </div>
            </div>
        </div>

        <!-- Prescription Section (Simplified for now) -->
         <div class="card mb-4">
            <div class="card-header bg-light">Prescription</div>
            <div class="card-body">
                 <div id="medicationsContainer">
                    <!-- Medication inputs will be added here by JavaScript -->
                    <div class="row g-3 medication-item mb-3">
                         <h6>Medication 1</h6>
                        <div class="col-md-6">
                            <label for="medication_name_1" class="form-label">Medication Name</label>
                            <input type="text" class="form-control" id="medication_name_1" name="medications[0][medication_name]">
                        </div>
                        <div class="col-md-3">
                            <label for="dosage_1" class="form-label">Dosage</label>
                            <input type="text" class="form-control" id="dosage_1" name="medications[0][dosage]">
                        </div>
                        <div class="col-md-3">
                            <label for="frequency_1" class="form-label">Frequency</label>
                            <input type="text" class="form-control" id="frequency_1" name="medications[0][frequency]">
                        </div>
                        <div class="col-md-3">
                            <label for="duration_1" class="form-label">Duration</label>
                            <input type="text" class="form-control" id="duration_1" name="medications[0][duration]">
                        </div>
                         <div class="col-md-3">
                            <label for="status_1" class="form-label">Status</label>
                             <select class="form-select" id="status_1" name="medications[0][status]">
                                <option value="active">Active</option>
                                <option value="completed">Completed</option>
                                 <option value="discontinued">Discontinued</option>
                             </select>
                        </div>
                        <div class="col-md-6">
                            <label for="instructions_1" class="form-label">Instructions</label>
                            <textarea class="form-control" id="instructions_1" name="medications[0][instructions]" rows="2"></textarea>
                        </div>
                    </div>
                 </div>
                 <button type="button" class="btn btn-secondary btn-sm" id="addMedicationBtn">+ Add Another Medication</button>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Save Consultation</button>
    </form>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const consultationForm = document.getElementById('consultationForm');
    const medicationsContainer = document.getElementById('medicationsContainer');
    const addMedicationBtn = document.getElementById('addMedicationBtn');
    let medicationCount = 1;

    // Function to calculate BMI
    function calculateBMI() {
        const weight = parseFloat(document.getElementById('weight').value);
        const height = parseFloat(document.getElementById('height').value);
        const bmiInput = document.getElementById('bmi');

        if (weight > 0 && height > 0) {
            const heightInMeters = height / 100;
            const bmi = weight / (heightInMeters * heightInMeters);
            bmiInput.value = bmi.toFixed(2);
        } else {
            bmiInput.value = '';
        }
    }

    // Add event listeners to weight and height inputs for BMI calculation
    document.getElementById('weight').addEventListener('input', calculateBMI);
    document.getElementById('height').addEventListener('input', calculateBMI);


    // Function to add new medication fields
    function addMedicationFields() {
        const newMedicationHtml = `
            <div class="row g-3 medication-item mb-3">
                 <h6>Medication ${medicationCount + 1}</h6>
                <div class="col-md-6">
                    <label for="medication_name_${medicationCount + 1}" class="form-label">Medication Name</label>
                    <input type="text" class="form-control" id="medication_name_${medicationCount + 1}" name="medications[${medicationCount}][medication_name]">
                </div>
                <div class="col-md-3">
                    <label for="dosage_${medicationCount + 1}" class="form-label">Dosage</label>
                    <input type="text" class="form-control" id="dosage_${medicationCount + 1}" name="medications[${medicationCount}][dosage]">
                </div>
                <div class="col-md-3">
                    <label for="frequency_${medicationCount + 1}" class="form-label">Frequency</label>
                    <input type="text" class="form-control" id="frequency_${medicationCount + 1}" name="medications[${medicationCount}][frequency]">
                </div>
                <div class="col-md-3">
                    <label for="duration_${medicationCount + 1}" class="form-label">Duration</label>
                    <input type="text" class="form-control" id="duration_${medicationCount + 1}" name="medications[${medicationCount}][duration]">
                </div>
                 <div class="col-md-3">
                    <label for="status_${medicationCount + 1}" class="form-label">Status</label>
                     <select class="form-select" id="status_${medicationCount + 1}" name="medications[${medicationCount}][status]">
                        <option value="active">Active</option>
                        <option value="completed">Completed</option>
                         <option value="discontinued">Discontinued</option>
                     </select>
                </div>
                <div class="col-md-6">
                    <label for="instructions_${medicationCount + 1}" class="form-label">Instructions</label>
                    <textarea class="form-control" id="instructions_${medicationCount + 1}" name="medications[${medicationCount}][instructions]" rows="2"></textarea>
                </div>
            </div>
        `;
        medicationsContainer.insertAdjacentHTML('beforeend', newMedicationHtml);
        medicationCount++;
    }

    addMedicationBtn.addEventListener('click', addMedicationFields);

    // Handle form submission
    consultationForm.addEventListener('submit', function(event) {
        event.preventDefault();

        // Collect form data
        const formData = new FormData(consultationForm);
        const jsonData = {};

        // Manually handle medications to group them correctly
        const medications = [];
        // Iterate through all input names to find medication fields
        for (let pair of formData.entries()) {
            const [key, value] = pair;
            // Check if the key matches the pattern for medication fields (e.g., medications[0][medication_name])
            const medicationMatch = key.match(/^medications\[(\d+)\]\[(\w+)\]$/);
            if (medicationMatch) {
                const index = parseInt(medicationMatch[1]);
                const field = medicationMatch[2];
                // Initialize the medication object if it doesn't exist
                if (!medications[index]) {
                    medications[index] = {};
                }
                // Assign the value to the correct field
                medications[index][field] = value;
            } else {
                 // Add other form data to jsonData
                jsonData[key] = value;
            }
        }
         // Filter out empty medication objects (e.g., if the last added row was empty)
        jsonData.medications = medications.filter(med => Object.keys(med).length > 0);


        // Send data to API (replace with your actual API endpoint)
        fetch('../../api/doctor/save_consultation.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(jsonData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Consultation saved successfully!');
                // Redirect or update UI as needed
                 window.location.href = '?page=appointments'; // Example redirect
            } else {
                alert('Error saving consultation: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while saving consultation.');
        });
    });
});
</script> 