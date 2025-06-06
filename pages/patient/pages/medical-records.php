<?php
// Check if PATIENT_ACCESS is defined
if (!defined('PATIENT_ACCESS')) {
    header("Location: ../../auth/index.php");
    exit();
}

require_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get patient info from session
$user_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT * FROM patients WHERE user_id = :user_id");
$stmt->bindParam(":user_id", $user_id);
$stmt->execute();
$patient = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$patient) {
    echo '<div class="alert alert-danger">Patient record not found.</div>';
    exit();
}
$patient_id = $patient['id'];
$full_name = trim(($patient['first_name'] ?? '') . ' ' . ($patient['middle_name'] ?? '') . ' ' . ($patient['last_name'] ?? ''));

// Fetch email from users table
$email = '-';
$stmt = $db->prepare("SELECT email FROM users WHERE id = :user_id");
$stmt->bindParam(":user_id", $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if ($user && !empty($user['email'])) {
    $email = $user['email'];
}

// Fetch medical records with all related information
$stmt = $db->prepare("
    SELECT mr.*, 
           d.first_name as doctor_first_name, 
           d.last_name as doctor_last_name,
           a.date as appointment_date,
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
           GROUP_CONCAT(DISTINCT pr.prescription_text SEPARATOR '||') as prescriptions,
           GROUP_CONCAT(DISTINCT di.diagnosis SEPARATOR '||') as diagnoses
    FROM medical_records mr
    LEFT JOIN doctors d ON mr.doctor_id = d.id
    LEFT JOIN appointments a ON mr.appointment_id = a.id
    LEFT JOIN vital_signs vs ON mr.id = vs.medical_record_id
    LEFT JOIN prescriptions pr ON mr.id = pr.medical_record_id
    LEFT JOIN diagnoses di ON mr.id = di.medical_record_id
    WHERE mr.patient_id = :id
    GROUP BY mr.id
    ORDER BY mr.created_at DESC
");
$stmt->bindParam(":id", $patient_id);
$stmt->execute();
$medical_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch allergies
$stmt = $db->prepare("SELECT * FROM allergies WHERE patient_id = :id ORDER BY created_at DESC");
$stmt->bindParam(":id", $patient_id);
$stmt->execute();
$allergies = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch immunizations
$stmt = $db->prepare("SELECT * FROM immunizations WHERE patient_id = :id ORDER BY created_at DESC");
$stmt->bindParam(":id", $patient_id);
$stmt->execute();
$immunizations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-primary d-flex justify-content-center align-items-center me-4" style="width:64px;height:64px;">
                        <span class="material-icons text-white" style="font-size:2.5rem;">person</span>
                    </div>
                    <div>
                        <h4 class="mb-0"><?php echo htmlspecialchars($full_name); ?></h4>
                        <div class="text-muted small mb-1"><?php echo htmlspecialchars($email); ?></div>
                        <div class="text-muted small">Patient ID: <?php echo $patient_id; ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <ul class="nav nav-tabs mb-3" id="patientRecordTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="summary-tab" data-bs-toggle="tab" data-bs-target="#summary" type="button" role="tab">Summary</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="records-tab" data-bs-toggle="tab" data-bs-target="#records" type="button" role="tab">Medical Records</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="vitals-tab" data-bs-toggle="tab" data-bs-target="#vitals" type="button" role="tab">Current Vital Signs</button>
        </li>
    </ul>
    <div class="tab-content" id="patientRecordTabsContent">
        <!-- Summary Tab -->
        <div class="tab-pane fade show active" id="summary" role="tabpanel">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header bg-light">Personal Information</div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-5">Name:</dt><dd class="col-7"><?php echo htmlspecialchars($full_name); ?></dd>
                                <dt class="col-5">Gender:</dt><dd class="col-7"><?php echo htmlspecialchars($patient['gender'] ?? '-'); ?></dd>
                                <dt class="col-5">Date of Birth:</dt><dd class="col-7"><?php echo htmlspecialchars($patient['date_of_birth'] ?? '-'); ?></dd>
                                <dt class="col-5">Blood Type:</dt><dd class="col-7"><?php echo htmlspecialchars($patient['blood_type'] ?? '-'); ?></dd>
                                <dt class="col-5">Contact:</dt><dd class="col-7"><?php echo htmlspecialchars($patient['contact_number'] ?? '-'); ?></dd>
                                <dt class="col-5">Address:</dt><dd class="col-7"><?php echo htmlspecialchars($patient['address'] ?? '-'); ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header bg-light">Emergency Contact</div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-5">Name:</dt><dd class="col-7"><?php echo htmlspecialchars($patient['emergency_contact_name'] ?? '-'); ?></dd>
                                <dt class="col-5">Number:</dt><dd class="col-7"><?php echo htmlspecialchars($patient['emergency_contact_number'] ?? '-'); ?></dd>
                            </dl>
                        </div>
                    </div>
                    <div class="card mb-3">
                        <div class="card-header bg-light">Medical Info</div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-5">Medical History:</dt><dd class="col-7"><?php echo nl2br(htmlspecialchars($patient['medical_history'] ?? '-')); ?></dd>
                                <dt class="col-5">Allergies:</dt><dd class="col-7"><?php echo nl2br(htmlspecialchars($patient['allergies'] ?? '-')); ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Medical Records Tab -->
        <div class="tab-pane fade" id="records" role="tabpanel">
            <?php if (empty($medical_records)): ?>
                <div class="text-center py-5">
                    <span class="material-icons text-muted" style="font-size: 48px;">medical_services</span>
                    <p class="text-muted mt-3">No medical records found.</p>
                </div>
            <?php else: ?>
                <div class="timeline">
                    <?php foreach ($medical_records as $record): ?>
                        <div class="timeline-item mb-4">
                            <div class="timeline-header d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h6 class="mb-1 d-flex align-items-center">
                                        <span class="material-icons me-2 text-primary">person</span>
                                        Dr. <?php echo htmlspecialchars($record['doctor_first_name'] . ' ' . $record['doctor_last_name']); ?>
                                    </h6>
                                    <small class="text-muted d-flex align-items-center">
                                        <span class="material-icons me-1" style="font-size: 16px;">event</span>
                                        <?php echo date('F d, Y', strtotime($record['created_at'])); ?>
                                    </small>
                                </div>
                                <span class="badge bg-primary rounded-pill px-3 py-2">
                                    <?php echo ucfirst($record['record_type']); ?>
                                </span>
                            </div>
                            
                            <div class="timeline-content">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body">
                                        <?php if ($record['chief_complaint']): ?>
                                            <div class="mb-4">
                                                <h6 class="d-flex align-items-center mb-3 text-primary">
                                                    <span class="material-icons me-2">medical_information</span>
                                                    Chief Complaint
                                                </h6>
                                                <div class="ps-4">
                                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($record['chief_complaint'])); ?></p>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($record['diagnoses']): ?>
                                            <div class="mb-4">
                                                <h6 class="d-flex align-items-center mb-3 text-primary">
                                                    <span class="material-icons me-2">science</span>
                                                    Diagnoses
                                                </h6>
                                                <div class="ps-4">
                                                    <?php 
                                                    $diagnoses = explode('||', $record['diagnoses']);
                                                    foreach ($diagnoses as $diagnosis): 
                                                    ?>
                                                        <div class="diagnosis-item mb-2">
                                                            <p class="mb-1"><?php echo nl2br(htmlspecialchars($diagnosis)); ?></p>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($record['prescriptions']): ?>
                                            <div class="mb-4">
                                                <h6 class="d-flex align-items-center mb-3 text-primary">
                                                    <span class="material-icons me-2">medication</span>
                                                    Prescriptions
                                                </h6>
                                                <div class="ps-4">
                                                    <?php 
                                                    $prescriptions = explode('||', $record['prescriptions']);
                                                    foreach ($prescriptions as $prescription): 
                                                        $parts = explode('|', $prescription);
                                                        if (count($parts) >= 5):
                                                    ?>
                                                        <div class="prescription-item mb-3">
                                                            <div class="d-flex align-items-center mb-2">
                                                                <span class="material-icons text-primary me-2">medication_liquid</span>
                                                                <strong class="text-primary"><?php echo htmlspecialchars($parts[0]); ?></strong>
                                                            </div>
                                                            <div class="ps-4">
                                                                <div class="row g-2">
                                                                    <div class="col-md-6">
                                                                        <small class="text-muted d-block">Dosage</small>
                                                                        <span><?php echo htmlspecialchars($parts[1]); ?></span>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <small class="text-muted d-block">Frequency</small>
                                                                        <span><?php echo htmlspecialchars($parts[2]); ?></span>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <small class="text-muted d-block">Duration</small>
                                                                        <span><?php echo htmlspecialchars($parts[3]); ?></span>
                                                                    </div>
                                                                    <div class="col-12">
                                                                        <small class="text-muted d-block">Instructions</small>
                                                                        <span><?php echo htmlspecialchars($parts[4]); ?></span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php 
                                                        endif;
                                                    endforeach; 
                                                    ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($record['notes']): ?>
                                            <div class="mb-4">
                                                <h6 class="d-flex align-items-center mb-3 text-primary">
                                                    <span class="material-icons me-2">note</span>
                                                    Notes
                                                </h6>
                                                <div class="ps-4">
                                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($record['notes'])); ?></p>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <!-- Current Vital Signs Tab -->
        <div class="tab-pane fade" id="vitals" role="tabpanel">
            <?php
            // Get the most recent vital signs
            $stmt = $db->prepare("
                SELECT vs.*, mr.created_at as record_date, 
                       d.first_name as doctor_first_name, 
                       d.last_name as doctor_last_name
                FROM vital_signs vs
                JOIN medical_records mr ON vs.medical_record_id = mr.id
                JOIN doctors d ON mr.doctor_id = d.id
                WHERE mr.patient_id = :patient_id
                ORDER BY vs.recorded_at DESC
                LIMIT 1
            ");
            $stmt->bindParam(":patient_id", $patient_id);
            $stmt->execute();
            $latest_vitals = $stmt->fetch(PDO::FETCH_ASSOC);
            ?>

            <?php if (!$latest_vitals): ?>
                <p class="text-muted">No vital signs recorded.</p>
            <?php else: ?>
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Latest Vital Signs</h6>
                        <small class="text-muted">
                            Recorded on <?php echo date('F d, Y', strtotime($latest_vitals['recorded_at'])); ?> by 
                            Dr. <?php echo htmlspecialchars($latest_vitals['doctor_first_name'] . ' ' . $latest_vitals['doctor_last_name']); ?>
                        </small>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <div class="col-md-4">
                                <div class="vital-sign-card">
                                    <div class="vital-sign-icon">
                                        <span class="material-icons">favorite</span>
                                    </div>
                                    <div class="vital-sign-info">
                                        <h6>Blood Pressure</h6>
                                        <p class="mb-0">
                                            <?php 
                                            if ($latest_vitals['blood_pressure_systolic'] && $latest_vitals['blood_pressure_diastolic']) {
                                                echo $latest_vitals['blood_pressure_systolic'] . '/' . $latest_vitals['blood_pressure_diastolic'] . ' mmHg';
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="vital-sign-card">
                                    <div class="vital-sign-icon">
                                        <span class="material-icons">monitor_heart</span>
                                    </div>
                                    <div class="vital-sign-info">
                                        <h6>Heart Rate</h6>
                                        <p class="mb-0"><?php echo $latest_vitals['heart_rate'] ? $latest_vitals['heart_rate'] . ' bpm' : '-'; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="vital-sign-card">
                                    <div class="vital-sign-icon">
                                        <span class="material-icons">thermostat</span>
                                    </div>
                                    <div class="vital-sign-info">
                                        <h6>Temperature</h6>
                                        <p class="mb-0"><?php echo $latest_vitals['temperature'] ? $latest_vitals['temperature'] . ' °C' : '-'; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="vital-sign-card">
                                    <div class="vital-sign-icon">
                                        <span class="material-icons">air</span>
                                    </div>
                                    <div class="vital-sign-info">
                                        <h6>Respiratory Rate</h6>
                                        <p class="mb-0"><?php echo $latest_vitals['respiratory_rate'] ? $latest_vitals['respiratory_rate'] . ' bpm' : '-'; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="vital-sign-card">
                                    <div class="vital-sign-icon">
                                        <span class="material-icons">water_drop</span>
                                    </div>
                                    <div class="vital-sign-info">
                                        <h6>Oxygen Saturation</h6>
                                        <p class="mb-0"><?php echo $latest_vitals['oxygen_saturation'] ? $latest_vitals['oxygen_saturation'] . '%' : '-'; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="vital-sign-card">
                                    <div class="vital-sign-icon">
                                        <span class="material-icons">scale</span>
                                    </div>
                                    <div class="vital-sign-info">
                                        <h6>Weight</h6>
                                        <p class="mb-0"><?php echo $latest_vitals['weight'] ? $latest_vitals['weight'] . ' kg' : '-'; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="vital-sign-card">
                                    <div class="vital-sign-icon">
                                        <span class="material-icons">height</span>
                                    </div>
                                    <div class="vital-sign-info">
                                        <h6>Height</h6>
                                        <p class="mb-0"><?php echo $latest_vitals['height'] ? $latest_vitals['height'] . ' cm' : '-'; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="vital-sign-card">
                                    <div class="vital-sign-icon">
                                        <span class="material-icons">calculate</span>
                                    </div>
                                    <div class="vital-sign-info">
                                        <h6>BMI</h6>
                                        <p class="mb-0"><?php echo $latest_vitals['bmi'] ?: '-'; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="vital-sign-card">
                                    <div class="vital-sign-icon">
                                        <span class="material-icons">sentiment_satisfied</span>
                                    </div>
                                    <div class="vital-sign-info">
                                        <h6>Pain Scale</h6>
                                        <p class="mb-0"><?php echo $latest_vitals['pain_scale'] ?: '-'; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding: 20px 0;
}

.timeline-item {
    position: relative;
    padding-left: 20px;
    border-left: 2px solid #e9ecef;
}

.timeline-item:last-child {
    border-left: none;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -6px;
    top: 0;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #0d6efd;
}

.prescription-item {
    background-color: #f8f9fa;
    border-radius: 8px;
    padding: 1rem;
    border-left: 3px solid #0d6efd;
}

.diagnosis-item {
    background-color: #f8f9fa;
    border-radius: 8px;
    padding: 1rem;
    border-left: 3px solid #198754;
}

.nav-tabs .nav-link {
    color: #495057;
}

.nav-tabs .nav-link.active {
    color: #0d6efd;
    font-weight: 500;
}

.vital-sign-card {
    display: flex;
    align-items: center;
    padding: 1rem;
    background-color: #f8f9fa;
    border-radius: 8px;
    height: 100%;
}

.vital-sign-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    background-color: #e9ecef;
    border-radius: 8px;
    margin-right: 1rem;
}

.vital-sign-icon .material-icons {
    color: #0d6efd;
    font-size: 24px;
}

.vital-sign-info h6 {
    margin-bottom: 0.25rem;
    color: #495057;
}

.vital-sign-info p {
    color: #6c757d;
    font-size: 1.1rem;
}

.timeline-header .badge {
    font-size: 0.875rem;
}

.timeline-content .card {
    transition: transform 0.2s;
}

.timeline-content .card:hover {
    transform: translateY(-2px);
}
</style> 