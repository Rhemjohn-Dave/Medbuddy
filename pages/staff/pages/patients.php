<?php
// Check if STAFF_ACCESS is defined
if (!defined('STAFF_ACCESS')) {
    header("Location: ../../auth/index.php");
    exit();
}

require_once(__DIR__ . '/../../../config/database.php');
require_once(__DIR__ . '/../../../config/database.php');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$search = isset($_GET['search']) ? $_GET['search'] : '';
$gender = isset($_GET['gender']) ? $_GET['gender'] : '';

// Get staff's assigned clinics
$staff_user_id = $_SESSION['user_id'];
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->prepare("SELECT sc.clinic_id FROM staff_clinics sc 
                       JOIN staff s ON sc.staff_id = s.id 
                       WHERE s.user_id = ?");
$stmt->execute([$staff_user_id]);
$assigned_clinics = $stmt->fetchAll(PDO::FETCH_COLUMN);

$patients = [];
if (!empty($assigned_clinics)) {
    $placeholders = implode(',', array_fill(0, count($assigned_clinics), '?'));
    $where = [];
    $params = $assigned_clinics;
    if ($search) {
        $where[] = "(p.first_name LIKE ? OR p.last_name LIKE ? OR u.email LIKE ? OR p.contact_number LIKE ?)";
        $params = array_merge($params, array_fill(0, 4, "%$search%"));
    }
    if ($gender) {
        $where[] = "p.gender = ?";
        $params[] = $gender;
    }
    $where_sql = $where ? " AND " . implode(" AND ", $where) : "";
    $sql = "SELECT DISTINCT p.*, u.email,
                (SELECT COUNT(*) FROM appointments WHERE patient_id = p.user_id) as total_appointments,
                (SELECT COUNT(*) FROM medical_records WHERE patient_id = p.user_id) as total_records
            FROM patients p
            JOIN appointments a ON p.user_id = a.patient_id
            LEFT JOIN users u ON p.user_id = u.id
            WHERE a.clinic_id IN ($placeholders) $where_sql
            ORDER BY p.first_name ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Add this right after your existing PHP code at the top of the file
if (isset($_GET['view_patient'])) {
    $patient_id = $_GET['view_patient'];
    
    // Get patient details
    $stmt = $conn->prepare("
        SELECT p.*, u.email 
        FROM patients p 
        LEFT JOIN users u ON p.user_id = u.id 
        WHERE p.id = ?
    ");
    $stmt->execute([$patient_id]);
    $view_patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($view_patient) {
        // Get medical records with vital signs
        $stmt = $conn->prepare("
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
                   vs.pain_scale
            FROM medical_records mr
            LEFT JOIN doctors d ON mr.doctor_id = d.id
            LEFT JOIN appointments a ON mr.appointment_id = a.id
            LEFT JOIN vital_signs vs ON mr.id = vs.medical_record_id
            WHERE mr.patient_id = ?
            ORDER BY mr.created_at DESC
        ");
        $stmt->execute([$patient_id]);
        $medical_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get latest medical record with vital signs
        $stmt = $conn->prepare("
            SELECT mr.*,
                   vs.blood_pressure_systolic,
                   vs.blood_pressure_diastolic,
                   vs.heart_rate,
                   vs.respiratory_rate,
                   vs.temperature,
                   vs.oxygen_saturation,
                   vs.weight,
                   vs.height,
                   vs.bmi,
                   vs.pain_scale
            FROM medical_records mr
            LEFT JOIN vital_signs vs ON mr.id = vs.medical_record_id
            WHERE mr.patient_id = ? 
            ORDER BY mr.created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$patient_id]);
        $latest_record = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Add this for medical records view
if (isset($_GET['view_records'])) {
    $patient_id = $_GET['view_records'];
    
    // Get patient details
    $stmt = $conn->prepare("
        SELECT p.*, u.email 
        FROM patients p 
        LEFT JOIN users u ON p.user_id = u.id 
        WHERE p.id = ?
    ");
    $stmt->execute([$patient_id]);
    $view_records_patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($view_records_patient) {
        // Get medical records with all related information
        $stmt = $conn->prepare("
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
            WHERE mr.patient_id = ?
            GROUP BY mr.id
            ORDER BY mr.created_at DESC
        ");
        $stmt->execute([$patient_id]);
        $view_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">Patients</h4>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPatientModal">
            <i class="material-icons align-middle me-1">add</i> New Patient
        </button>
    </div>

    <!-- Search and Filters -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="row g-3">
                <input type="hidden" name="page" value="patients">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="material-icons">search</i>
                        </span>
                        <input type="text" class="form-control" name="search" 
                               placeholder="Search by name, email, or contact number" 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="gender">
                        <option value="">All Genders</option>
                        <option value="male" <?php echo $gender === 'male' ? 'selected' : ''; ?>>Male</option>
                        <option value="female" <?php echo $gender === 'female' ? 'selected' : ''; ?>>Female</option>
                        <option value="other" <?php echo $gender === 'other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Search</button>
                    <a href="?page=patients" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Patients List -->
    <?php if (empty($patients)): ?>
        <div class="alert alert-info">
            <i class="material-icons align-middle me-2">info</i>
            No patients found matching your search criteria.
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php foreach ($patients as $patient): ?>
                <div class="col">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="flex-shrink-0">
                                    <div class="avatar-circle bg-primary text-white">
                                        <?php echo strtoupper(substr($patient['first_name'], 0, 1) . substr($patient['last_name'], 0, 1)); ?>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h5 class="card-title mb-1">
                                        <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                                    </h5>
                                    <p class="text-muted small mb-0">
                                        <?php echo htmlspecialchars($patient['email']); ?>
                                    </p>
                                </div>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <div class="d-flex align-items-center">
                                        <i class="material-icons text-muted me-2" style="font-size: 1.1rem;">phone</i>
                                        <span class="small"><?php echo htmlspecialchars($patient['contact_number']); ?></span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="d-flex align-items-center">
                                        <i class="material-icons text-muted me-2" style="font-size: 1.1rem;">person</i>
                                        <span class="small"><?php echo ucfirst($patient['gender']); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <div class="d-flex align-items-center">
                                        <i class="material-icons text-muted me-2" style="font-size: 1.1rem;">event</i>
                                        <span class="small"><?php echo $patient['total_appointments']; ?> Appointments</span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="d-flex align-items-center">
                                        <i class="material-icons text-muted me-2" style="font-size: 1.1rem;">medical_services</i>
                                        <span class="small"><?php echo $patient['total_records']; ?> Records</span>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-sm btn-outline-primary me-2" 
                                        onclick="window.location.href='?page=patients&view_patient=<?php echo $patient['id']; ?>'">
                                    <i class="material-icons" style="font-size: 1.1rem;">visibility</i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-success" 
                                        onclick="window.location.href='?page=patients&view_records=<?php echo $patient['id']; ?>'">
                                    <i class="material-icons" style="font-size: 1.1rem;">medical_services</i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Add Patient Modal -->
<div class="modal fade" id="addPatientModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Patient</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addPatientForm">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control" name="first_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" name="last_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact Number</label>
                            <input type="tel" class="form-control" name="contact_number" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Gender</label>
                            <select class="form-select" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" class="form-control" name="date_of_birth" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="2"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="savePatient()">Save Patient</button>
            </div>
        </div>
    </div>
</div>

<!-- View Patient Modal -->
<div class="modal fade" id="viewPatientModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Patient Information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if (isset($view_patient) && $view_patient): ?>
                    <!-- Patient Information -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0">Personal Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Name:</strong> <?php echo htmlspecialchars($view_patient['first_name'] . ' ' . $view_patient['last_name']); ?></p>
                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($view_patient['email']); ?></p>
                                    <p><strong>Contact:</strong> <?php echo htmlspecialchars($view_patient['contact_number']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Gender:</strong> <?php echo ucfirst($view_patient['gender']); ?></p>
                                    <p><strong>Date of Birth:</strong> <?php echo date('F d, Y', strtotime($view_patient['date_of_birth'])); ?></p>
                                    <p><strong>Address:</strong> <?php echo htmlspecialchars($view_patient['address']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Latest Medical Information -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0">Latest Medical Information</h6>
                        </div>
                        <div class="card-body">
                            <?php if ($latest_record): ?>
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <p><strong>Chief Complaint:</strong><br><?php echo nl2br(htmlspecialchars($latest_record['chief_complaint'])); ?></p>
                                    </div>
                                </div>
                                <?php if ($latest_record['blood_pressure_systolic'] || $latest_record['temperature'] || $latest_record['heart_rate'] || $latest_record['weight']): ?>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <?php if ($latest_record['blood_pressure_systolic']): ?>
                                                <p><strong>Blood Pressure:</strong><br><?php echo htmlspecialchars($latest_record['blood_pressure_systolic'] . '/' . $latest_record['blood_pressure_diastolic']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-3">
                                            <?php if ($latest_record['temperature']): ?>
                                                <p><strong>Temperature:</strong><br><?php echo htmlspecialchars($latest_record['temperature']); ?>°C</p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-3">
                                            <?php if ($latest_record['heart_rate']): ?>
                                                <p><strong>Heart Rate:</strong><br><?php echo htmlspecialchars($latest_record['heart_rate']); ?> bpm</p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-3">
                                            <?php if ($latest_record['weight']): ?>
                                                <p><strong>Weight:</strong><br><?php echo htmlspecialchars($latest_record['weight']); ?> kg</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <small class="text-muted">Recorded on: <?php echo date('F d, Y h:i A', strtotime($latest_record['created_at'])); ?></small>
                            <?php else: ?>
                                <p class="text-muted mb-0">No medical information recorded yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Medical History -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Medical History</h6>
                            <span class="badge bg-primary"><?php echo count($medical_records); ?> Records</span>
                        </div>
                        <div class="card-body">
                            <?php if ($medical_records): ?>
                                <div class="timeline">
                                    <?php foreach ($medical_records as $index => $record): ?>
                                        <div class="timeline-item mb-3">
                                            <div class="timeline-header d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-1">
                                                        Dr. <?php echo htmlspecialchars($record['doctor_first_name'] . ' ' . $record['doctor_last_name']); ?>
                                                    </h6>
                                                    <small class="text-muted">
                                                        <?php echo date('F d, Y', strtotime($record['appointment_date'])); ?>
                                                    </small>
                                                </div>
                                                <button class="btn btn-sm btn-outline-primary" type="button" 
                                                        data-bs-toggle="collapse" 
                                                        data-bs-target="#record<?php echo $index; ?>" 
                                                        aria-expanded="false" 
                                                        aria-controls="record<?php echo $index; ?>">
                                                    <i class="material-icons">expand_more</i>
                                                </button>
                                            </div>
                                            
                                            <div class="collapse" id="record<?php echo $index; ?>">
                                                <div class="card card-body mt-2">
                                                    <!-- Vital Signs -->
                                                    <?php if ($record['blood_pressure_systolic'] || $record['temperature'] || $record['heart_rate'] || $record['weight']): ?>
                                                        <div class="mb-3">
                                                            <strong>Vital Signs:</strong>
                                                            <div class="row">
                                                                <?php if ($record['blood_pressure_systolic']): ?>
                                                                    <div class="col-md-3">
                                                                        <small>BP: <?php echo htmlspecialchars($record['blood_pressure_systolic'] . '/' . $record['blood_pressure_diastolic']); ?></small>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <?php if ($record['temperature']): ?>
                                                                    <div class="col-md-3">
                                                                        <small>Temp: <?php echo htmlspecialchars($record['temperature']); ?>°C</small>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <?php if ($record['heart_rate']): ?>
                                                                    <div class="col-md-3">
                                                                        <small>HR: <?php echo htmlspecialchars($record['heart_rate']); ?> bpm</small>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <?php if ($record['respiratory_rate']): ?>
                                                                    <div class="col-md-3">
                                                                        <small>RR: <?php echo htmlspecialchars($record['respiratory_rate']); ?> bpm</small>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <?php if ($record['oxygen_saturation']): ?>
                                                                    <div class="col-md-3">
                                                                        <small>SpO2: <?php echo htmlspecialchars($record['oxygen_saturation']); ?>%</small>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <?php if ($record['weight']): ?>
                                                                    <div class="col-md-3">
                                                                        <small>Weight: <?php echo htmlspecialchars($record['weight']); ?> kg</small>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <?php if ($record['height']): ?>
                                                                    <div class="col-md-3">
                                                                        <small>Height: <?php echo htmlspecialchars($record['height']); ?> cm</small>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <?php if ($record['bmi']): ?>
                                                                    <div class="col-md-3">
                                                                        <small>BMI: <?php echo htmlspecialchars($record['bmi']); ?></small>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <?php if ($record['pain_scale']): ?>
                                                                    <div class="col-md-3">
                                                                        <small>Pain Scale: <?php echo htmlspecialchars($record['pain_scale']); ?>/10</small>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>

                                                    <!-- Chief Complaint -->
                                                    <?php if ($record['chief_complaint']): ?>
                                                        <div class="mb-3">
                                                            <strong>Chief Complaint:</strong>
                                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($record['chief_complaint'])); ?></p>
                                                        </div>
                                                    <?php endif; ?>

                                                    <!-- Diagnosis -->
                                                    <?php if ($record['diagnosis']): ?>
                                                        <div class="mb-2">
                                                            <strong>Diagnosis:</strong>
                                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($record['diagnosis'])); ?></p>
                                                        </div>
                                                    <?php endif; ?>

                                                    <!-- Prescription -->
                                                    <?php if ($record['prescription']): ?>
                                                        <div class="mb-3">
                                                            <strong>Prescriptions:</strong>
                                                            <?php 
                                                            $prescriptions = explode('||', $record['prescription']);
                                                            foreach ($prescriptions as $prescription): 
                                                            ?>
                                                                <div class="mb-2">
                                                                    <p class="mb-1">
                                                                        <?php echo nl2br(htmlspecialchars($prescription)); ?>
                                                                    </p>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>

                                                    <!-- Notes -->
                                                    <?php if ($record['notes']): ?>
                                                        <div>
                                                            <strong>Notes:</strong>
                                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($record['notes'])); ?></p>
                                                        </div>
                                                    <?php endif; ?>

                                                    <small class="text-muted d-block mt-2">
                                                        Recorded on: <?php echo date('F d, Y h:i A', strtotime($record['created_at'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted mb-0">No medical records found.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger">Patient not found.</div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Medical Records Modal -->
<div class="modal fade" id="medicalRecordsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Medical Records</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if (isset($view_records_patient) && $view_records_patient): ?>
                    <!-- Patient Information -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0">Patient Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Name:</strong> <?php echo htmlspecialchars($view_records_patient['first_name'] . ' ' . $view_records_patient['last_name']); ?></p>
                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($view_records_patient['email']); ?></p>
                                    <p><strong>Contact:</strong> <?php echo htmlspecialchars($view_records_patient['contact_number']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Gender:</strong> <?php echo ucfirst($view_records_patient['gender']); ?></p>
                                    <p><strong>Date of Birth:</strong> <?php echo date('F d, Y', strtotime($view_records_patient['date_of_birth'])); ?></p>
                                    <p><strong>Address:</strong> <?php echo htmlspecialchars($view_records_patient['address']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Medical Records -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Medical Records</h6>
                            <span class="badge bg-primary"><?php echo count($view_records); ?> Records</span>
                        </div>
                        <div class="card-body">
                            <?php if ($view_records): ?>
                                <div class="timeline">
                                    <?php foreach ($view_records as $index => $record): ?>
                                        <div class="timeline-item mb-3">
                                            <div class="timeline-header d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-1">
                                                        Dr. <?php echo htmlspecialchars($record['doctor_first_name'] . ' ' . $record['doctor_last_name']); ?>
                                                    </h6>
                                                    <small class="text-muted">
                                                        <?php echo date('F d, Y', strtotime($record['appointment_date'])); ?>
                                                    </small>
                                                </div>
                                                <div class="d-flex gap-2">
                                                    <button class="btn btn-sm btn-outline-primary" type="button" 
                                                            data-bs-toggle="collapse" 
                                                            data-bs-target="#record<?php echo $index; ?>" 
                                                            aria-expanded="false" 
                                                            aria-controls="record<?php echo $index; ?>">
                                                        <i class="material-icons">expand_more</i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-secondary" type="button"
                                                            onclick="printRecord(<?php echo $record['id']; ?>)">
                                                        <i class="material-icons">print</i>
                                                    </button>
                                                </div>
                                            </div>
                                            
                                            <div class="collapse" id="record<?php echo $index; ?>">
                                                <div class="card card-body mt-2">
                                                    <!-- Vital Signs -->
                                                    <?php if ($record['blood_pressure_systolic'] || $record['temperature'] || $record['heart_rate'] || $record['weight']): ?>
                                                        <div class="mb-3">
                                                            <strong>Vital Signs:</strong>
                                                            <div class="row">
                                                                <?php if ($record['blood_pressure_systolic']): ?>
                                                                    <div class="col-md-3">
                                                                        <small>BP: <?php echo htmlspecialchars($record['blood_pressure_systolic'] . '/' . $record['blood_pressure_diastolic']); ?></small>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <?php if ($record['temperature']): ?>
                                                                    <div class="col-md-3">
                                                                        <small>Temp: <?php echo htmlspecialchars($record['temperature']); ?>°C</small>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <?php if ($record['heart_rate']): ?>
                                                                    <div class="col-md-3">
                                                                        <small>HR: <?php echo htmlspecialchars($record['heart_rate']); ?> bpm</small>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <?php if ($record['respiratory_rate']): ?>
                                                                    <div class="col-md-3">
                                                                        <small>RR: <?php echo htmlspecialchars($record['respiratory_rate']); ?> bpm</small>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <?php if ($record['oxygen_saturation']): ?>
                                                                    <div class="col-md-3">
                                                                        <small>SpO2: <?php echo htmlspecialchars($record['oxygen_saturation']); ?>%</small>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <?php if ($record['weight']): ?>
                                                                    <div class="col-md-3">
                                                                        <small>Weight: <?php echo htmlspecialchars($record['weight']); ?> kg</small>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <?php if ($record['height']): ?>
                                                                    <div class="col-md-3">
                                                                        <small>Height: <?php echo htmlspecialchars($record['height']); ?> cm</small>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <?php if ($record['bmi']): ?>
                                                                    <div class="col-md-3">
                                                                        <small>BMI: <?php echo htmlspecialchars($record['bmi']); ?></small>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <?php if ($record['pain_scale']): ?>
                                                                    <div class="col-md-3">
                                                                        <small>Pain Scale: <?php echo htmlspecialchars($record['pain_scale']); ?>/10</small>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>

                                                    <!-- Chief Complaint -->
                                                    <?php if ($record['chief_complaint']): ?>
                                                        <div class="mb-3">
                                                            <strong>Chief Complaint:</strong>
                                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($record['chief_complaint'])); ?></p>
                                                        </div>
                                                    <?php endif; ?>

                                                    <!-- Diagnoses -->
                                                    <?php if ($record['diagnoses']): ?>
                                                        <div class="mb-3">
                                                            <strong>Diagnoses:</strong>
                                                            <?php 
                                                            $diagnoses = explode('||', $record['diagnoses']);
                                                            foreach ($diagnoses as $diagnosis): 
                                                            ?>
                                                                <p class="mb-1"><?php echo nl2br(htmlspecialchars($diagnosis)); ?></p>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>

                                                    <!-- Prescriptions -->
                                                    <?php if ($record['prescriptions']): ?>
                                                        <div class="mb-3">
                                                            <strong>Prescriptions:</strong>
                                                            <?php 
                                                            $prescriptions = explode('||', $record['prescriptions']);
                                                            foreach ($prescriptions as $prescription): 
                                                            ?>
                                                                <div class="mb-2">
                                                                    <p class="mb-1">
                                                                        <?php echo nl2br(htmlspecialchars($prescription)); ?>
                                                                    </p>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>

                                                    <!-- Notes -->
                                                    <?php if ($record['notes']): ?>
                                                        <div>
                                                            <strong>Notes:</strong>
                                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($record['notes'])); ?></p>
                                                        </div>
                                                    <?php endif; ?>

                                                    <small class="text-muted d-block mt-2">
                                                        Recorded on: <?php echo date('F d, Y h:i A', strtotime($record['created_at'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted mb-0">No medical records found.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger">Patient not found.</div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Define formatting functions first
function formatVitalSigns(vitalSigns) {
    if (!vitalSigns) return '';
    
    const vitalSignsData = {
        'Blood Pressure': vitalSigns.blood_pressure_systolic ? `${vitalSigns.blood_pressure_systolic}/${vitalSigns.blood_pressure_diastolic}` : '',
        'Temperature': vitalSigns.temperature ? `${vitalSigns.temperature}°C` : '',
        'Heart Rate': vitalSigns.heart_rate ? `${vitalSigns.heart_rate} bpm` : '',
        'Respiratory Rate': vitalSigns.respiratory_rate ? `${vitalSigns.respiratory_rate} bpm` : '',
        'Oxygen Saturation': vitalSigns.oxygen_saturation ? `${vitalSigns.oxygen_saturation}%` : '',
        'Weight': vitalSigns.weight ? `${vitalSigns.weight} kg` : '',
        'Height': vitalSigns.height ? `${vitalSigns.height} cm` : '',
        'BMI': vitalSigns.bmi || '',
        'Pain Scale': vitalSigns.pain_scale ? `${vitalSigns.pain_scale}/10` : ''
    };

    // Filter out empty values
    const validSigns = Object.entries(vitalSignsData).filter(([_, value]) => value !== '');

    if (validSigns.length === 0) return '';

    // Split into two columns
    const midPoint = Math.ceil(validSigns.length / 2);
    const leftColumn = validSigns.slice(0, midPoint);
    const rightColumn = validSigns.slice(midPoint);

    return `
        <div class="vital-signs-section">
            <div class="section-header">Vital Signs</div>
            <div class="row">
                <div class="col-6">
                    <table class="table table-bordered vital-signs-table">
                        <tbody>
                            ${leftColumn.map(([label, value]) => `
                                <tr>
                                    <td style="width: 40%"><strong>${label}:</strong></td>
                                    <td style="width: 60%">${value}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
                <div class="col-6">
                    <table class="table table-bordered vital-signs-table">
                        <tbody>
                            ${rightColumn.map(([label, value]) => `
                                <tr>
                                    <td style="width: 40%"><strong>${label}:</strong></td>
                                    <td style="width: 60%">${value}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    `;
}

function formatDiagnosis(diagnosisText) {
    if (!diagnosisText) return '';
    const diagnoses = diagnosisText.split('||').filter(d => d.trim());
    if (diagnoses.length === 0) return '';
    
    return `
        <div class="diagnosis-section">
            <div class="section-header">Diagnoses</div>
            <table class="table table-bordered diagnosis-table">
                <thead>
                    <tr>
                        <th style="width: 5%">#</th>
                        <th style="width: 25%">Diagnosis</th>
                        <th style="width: 10%">ICD-10</th>
                        <th style="width: 15%">Type</th>
                        <th style="width: 15%">Status</th>
                        <th style="width: 30%">Notes</th>
                    </tr>
                </thead>
                <tbody>
                    ${diagnoses.map((d, index) => {
                        // Parse diagnosis components
                        const parts = d.split(' - ');
                        const diagnosis = parts[0] || '';
                        const icd10 = diagnosis.match(/\(ICD-10:\s*([^)]+)\)/)?.[1] || '';
                        const type = diagnosis.match(/\[Type:\s*([^\]]+)\]/)?.[1] || '';
                        const status = parts.find(p => p.startsWith('Status:'))?.replace('Status:', '').trim() || '';
                        const notes = parts.filter(p => !p.startsWith('Status:')).join(' - ').replace(/\(ICD-10:[^)]+\)/, '').replace(/\[Type:[^\]]+\]/, '').trim();

                        return `
                            <tr>
                                <td>${index + 1}</td>
                                <td>${diagnosis.replace(/\(ICD-10:[^)]+\)/, '').replace(/\[Type:[^\]]+\]/, '').trim()}</td>
                                <td>${icd10}</td>
                                <td>${type}</td>
                                <td>${status}</td>
                                <td>${notes}</td>
                            </tr>
                        `;
                    }).join('')}
                </tbody>
            </table>
        </div>
    `;
}

function formatPrescription(prescriptionText) {
    if (!prescriptionText) return '';
    const prescriptions = prescriptionText.split('||').filter(p => p.trim());
    if (prescriptions.length === 0) return '';
    
    return `
        <div class="prescription-section">
            <div class="section-header">Prescriptions</div>
            <table class="table table-bordered prescription-table">
                <thead>
                    <tr>
                        <th style="width: 5%">#</th>
                        <th style="width: 20%">Medication</th>
                        <th style="width: 10%">Dosage</th>
                        <th style="width: 15%">Frequency</th>
                        <th style="width: 15%">Duration</th>
                        <th style="width: 35%">Instructions</th>
                    </tr>
                </thead>
                <tbody>
                    ${prescriptions.map((p, index) => {
                        const [medication, dosage, frequency, duration, instructions] = p.split('|').map(item => item.trim());
                        return `
                            <tr>
                                <td>${index + 1}</td>
                                <td>${medication || ''}</td>
                                <td>${dosage || ''}</td>
                                <td>${frequency || ''}</td>
                                <td>${duration || ''}</td>
                                <td>${instructions || ''}</td>
                            </tr>
                        `;
                    }).join('')}
                </tbody>
            </table>
        </div>
    `;
}

function formatChiefComplaint(complaintText) {
    if (!complaintText) return '';
    return `
        <div class="complaint-section">
            <div class="section-header">Chief Complaint</div>
            <div class="complaint-content">
                ${complaintText.split('\n').map(line => `<p class="mb-1">${line}</p>`).join('')}
            </div>
        </div>
    `;
}

function printRecord(recordId) {
    setTimeout(() => {
        try {
            const printButton = document.querySelector(`button[onclick="printRecord(${recordId})"]`);
            if (!printButton) {
                console.error('Print button not found');
                return;
            }

            const timelineItem = printButton.closest('.timeline-item');
            if (!timelineItem) {
                console.error('Timeline item not found');
                return;
            }

            const recordContent = timelineItem.querySelector('.card-body');
            if (!recordContent) {
                console.error('Record content not found');
                return;
            }

            // Get patient information from the modal
            const patientInfoCard = document.querySelector('#medicalRecordsModal .card-body');
            if (!patientInfoCard) {
                console.error('Patient information not found');
                return;
            }

            // Extract patient details
            const patientName = patientInfoCard.querySelector('p:first-child strong')?.nextSibling?.textContent.trim() || 'N/A';
            const patientEmail = patientInfoCard.querySelector('p:nth-child(2) strong')?.nextSibling?.textContent.trim() || 'N/A';
            const patientContact = patientInfoCard.querySelector('p:nth-child(3) strong')?.nextSibling?.textContent.trim() || 'N/A';
            const patientGender = patientInfoCard.querySelector('p:nth-child(4) strong')?.nextSibling?.textContent.trim() || 'N/A';
            const patientDOB = patientInfoCard.querySelector('p:nth-child(5) strong')?.nextSibling?.textContent.trim() || 'N/A';
            const patientAddress = patientInfoCard.querySelector('p:nth-child(6) strong')?.nextSibling?.textContent.trim() || 'N/A';

            // Get doctor and date information
            const doctorInfo = timelineItem.querySelector('h6')?.textContent || 'Doctor Information';
            const dateInfo = timelineItem.querySelector('small')?.textContent || 'Date Information';

            // Create a new print container
            const printContainer = document.createElement('div');
            printContainer.className = 'print-container';
            printContainer.style.display = 'block';
            
            // Create print content
            const printContent = document.createElement('div');
            printContent.className = 'print-content';
            
            // Get the original content
            const originalContent = recordContent.innerHTML;
            
            // Find and replace sections
            let formattedContent = originalContent;

            // Find and replace vital signs section
            const vitalSignsMatch = originalContent.match(/<strong>Vital Signs:<\/strong>([\s\S]*?)(?=<strong>|$)/);
            if (vitalSignsMatch) {
                const vitalSignsText = vitalSignsMatch[1].trim();
                const vitalSigns = {
                    blood_pressure_systolic: vitalSignsText.match(/BP:\s*(\d+)\/\d+/)?.[1],
                    blood_pressure_diastolic: vitalSignsText.match(/BP:\s*\d+\/(\d+)/)?.[1],
                    temperature: vitalSignsText.match(/Temp:\s*([\d.]+)/)?.[1],
                    heart_rate: vitalSignsText.match(/HR:\s*(\d+)/)?.[1],
                    respiratory_rate: vitalSignsText.match(/RR:\s*(\d+)/)?.[1],
                    oxygen_saturation: vitalSignsText.match(/SpO2:\s*([\d.]+)/)?.[1],
                    weight: vitalSignsText.match(/Weight:\s*([\d.]+)/)?.[1],
                    height: vitalSignsText.match(/Height:\s*([\d.]+)/)?.[1],
                    bmi: vitalSignsText.match(/BMI:\s*([\d.]+)/)?.[1],
                    pain_scale: vitalSignsText.match(/Pain Scale:\s*(\d+)/)?.[1]
                };
                formattedContent = formattedContent.replace(
                    vitalSignsMatch[0],
                    formatVitalSigns(vitalSigns)
                );
            }

            // Find and replace chief complaint section
            const chiefComplaintMatch = originalContent.match(/<strong>Chief Complaint:<\/strong>([\s\S]*?)(?=<strong>|$)/);
            if (chiefComplaintMatch) {
                const complaintText = chiefComplaintMatch[1].trim();
                formattedContent = formattedContent.replace(
                    chiefComplaintMatch[0],
                    formatChiefComplaint(complaintText)
                );
            }

            // Find and replace diagnosis section
            const diagnosisMatch = originalContent.match(/<strong>Diagnoses:<\/strong>([\s\S]*?)(?=<strong>|$)/);
            if (diagnosisMatch) {
                const diagnosisText = diagnosisMatch[1].trim();
                formattedContent = formattedContent.replace(
                    diagnosisMatch[0],
                    formatDiagnosis(diagnosisText)
                );
            }

            // Find and replace prescription section
            const prescriptionMatch = originalContent.match(/<strong>Prescriptions:<\/strong>([\s\S]*?)(?=<strong>|$)/);
            if (prescriptionMatch) {
                const prescriptionText = prescriptionMatch[1].trim();
                formattedContent = formattedContent.replace(
                    prescriptionMatch[0],
                    formatPrescription(prescriptionText)
                );
            }
            
            // Set the content
            printContent.innerHTML = `
                <div class="print-header text-center">
                    <img src="../../assets/images/logo.png" alt="MedBuddy Logo">
                    <h2>MedBuddy Medical Center</h2>
                    <p>123 Medical Plaza, Healthcare City</p>
                    <p>Phone: (123) 456-7890 | Email: info@medbuddy.com</p>
                    <hr>
                </div>

                <div class="patient-info">
                    <div class="row">
                        <div class="col-6">
                            <p class="mb-1"><strong>Name:</strong> ${patientName}</p>
                            <p class="mb-1"><strong>Email:</strong> ${patientEmail}</p>
                            <p class="mb-1"><strong>Contact:</strong> ${patientContact}</p>
                        </div>
                        <div class="col-6">
                            <p class="mb-1"><strong>Gender:</strong> ${patientGender}</p>
                            <p class="mb-1"><strong>Date of Birth:</strong> ${patientDOB}</p>
                            <p class="mb-1"><strong>Address:</strong> ${patientAddress}</p>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-6">
                            <p class="mb-1"><strong>Doctor:</strong> ${doctorInfo}</p>
                        </div>
                        <div class="col-6">
                            <p class="mb-1"><strong>Date:</strong> ${dateInfo}</p>
                        </div>
                    </div>
                    <hr>
                </div>

                ${formattedContent}

                <div class="print-footer">
                    <hr>
                    <div class="row">
                        <div class="col-6">
                            <p class="mb-1"><strong>Doctor's Signature:</strong></p>
                            <div style="height: 40px;"></div>
                            <p class="mb-1">${doctorInfo}</p>
                        </div>
                        <div class="col-6 text-end">
                            <p class="mb-1"><strong>Date:</strong></p>
                            <p class="mb-1">${new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</p>
                        </div>
                    </div>
                </div>
            `;
            
            // Add the content to the print container
            printContainer.appendChild(printContent);
            document.body.appendChild(printContainer);
            
            // Print the document
            window.print();
            
            // Remove the print container after printing
            setTimeout(() => {
                if (printContainer.parentNode) {
                    document.body.removeChild(printContainer);
                }
            }, 1000);
        } catch (error) {
            console.error('Error in printRecord:', error);
            alert('An error occurred while trying to print the record. Please try again.');
        }
    }, 100);
}

function savePatient() {
    const form = document.getElementById('addPatientForm');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    fetch('../../api/patients.php', {
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
            alert('Error creating patient: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error creating patient');
    });
}

function viewPatient(patientId) {
    window.location.href = `?page=patient-details&id=${patientId}`;
}

function viewMedicalRecords(patientId) {
    window.location.href = `?page=medical-records&patient_id=${patientId}`;
}

document.addEventListener('DOMContentLoaded', function() {
    <?php if (isset($_GET['view_patient'])): ?>
    var viewPatientModal = new bootstrap.Modal(document.getElementById('viewPatientModal'));
    viewPatientModal.show();
    <?php endif; ?>

    <?php if (isset($_GET['view_records'])): ?>
    var medicalRecordsModal = new bootstrap.Modal(document.getElementById('medicalRecordsModal'));
    medicalRecordsModal.show();
    <?php endif; ?>
});
</script>

<style>
.avatar-circle {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 500;
    font-size: 1.2rem;
}

.card {
    transition: transform 0.2s;
}

.card:hover {
    transform: translateY(-5px);
}

.btn-group .btn {
    padding: 0.25rem 0.5rem;
}

.btn-group .material-icons {
    font-size: 1.1rem;
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

.modal-lg {
    max-width: 800px;
}

/* Add print-specific styles */
@media print {
    @page {
        margin: 1cm;
    }

    body * {
        visibility: hidden;
    }

    .print-container, .print-container * {
        visibility: visible;
    }

    .print-container {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }

    .print-content {
        padding: 0;
    }

    .no-print {
        display: none !important;
    }

    /* Header styles */
    .print-header {
        margin-bottom: 20px;
    }

    .print-header img {
        height: 60px;
        margin-bottom: 5px;
    }

    .print-header h2 {
        font-size: 18px;
        margin-bottom: 2px;
    }

    .print-header p {
        font-size: 12px;
        margin-bottom: 2px;
    }

    /* Patient info styles */
    .patient-info {
        margin-bottom: 15px;
    }

    .patient-info h4 {
        font-size: 16px;
        margin-bottom: 10px;
    }

    .patient-info p {
        font-size: 12px;
        margin-bottom: 3px;
    }

    /* Table styles */
    .diagnosis-table, .prescription-table {
        width: 100%;
        margin: 10px 0;
        border-collapse: collapse;
        font-size: 11px;
    }

    .diagnosis-table th, .diagnosis-table td,
    .prescription-table th, .prescription-table td {
        border: 1px solid #000;
        padding: 4px 6px;
        text-align: left;
    }

    .diagnosis-table th, .prescription-table th {
        background-color: #f8f9fa !important;
        font-weight: bold;
    }

    /* Section headers */
    .section-header {
        font-size: 14px;
        font-weight: bold;
        margin: 15px 0 8px 0;
        color: #000;
        border-bottom: 1px solid #000;
        padding-bottom: 3px;
    }

    /* Footer styles */
    .print-footer {
        margin-top: 20px;
    }

    .print-footer p {
        font-size: 12px;
        margin-bottom: 3px;
    }

    /* Utility classes */
    .text-small {
        font-size: 11px;
    }

    .mb-1 {
        margin-bottom: 3px !important;
    }

    .mb-2 {
        margin-bottom: 6px !important;
    }

    .mb-3 {
        margin-bottom: 9px !important;
    }

    .mt-2 {
        margin-top: 6px !important;
    }

    .mt-3 {
        margin-top: 9px !important;
    }

    /* Vital Signs styles */
    .vital-signs-section {
        margin: 10px 0;
    }

    .vital-signs-table {
        width: 100%;
        margin: 0;
        border-collapse: collapse;
        font-size: 11px;
    }

    .vital-signs-table td {
        border: none;
        padding: 3px 6px;
        line-height: 1.2;
    }

    .vital-signs-table td:first-child {
        background-color: transparent !important;
        font-weight: bold;
    }

    /* Diagnosis section */
    .diagnosis-section {
        margin: 10px 0;
    }

    .diagnosis-table {
        width: 100%;
        margin: 0;
        border-collapse: collapse;
        font-size: 11px;
    }

    .diagnosis-table th,
    .diagnosis-table td {
        border: none;
        padding: 4px 6px;
        line-height: 1.2;
    }

    .diagnosis-table th {
        background-color: transparent !important;
        font-weight: bold;
        border-bottom: 1px solid #000;
    }

    /* Prescription section */
    .prescription-section {
        margin: 10px 0;
    }

    .prescription-table {
        width: 100%;
        margin: 0;
        border-collapse: collapse;
        font-size: 11px;
    }

    .prescription-table th,
    .prescription-table td {
        border: none;
        padding: 4px 6px;
        line-height: 1.2;
    }

    .prescription-table th {
        background-color: transparent !important;
        font-weight: bold;
        border-bottom: 1px solid #000;
    }

    /* Complaint section */
    .complaint-section {
        margin: 10px 0;
    }

    .complaint-content {
        font-size: 12px;
        line-height: 1.4;
        padding: 5px 0;
    }

    .complaint-content p {
        margin-bottom: 3px;
    }
}
</style>

<?php
function printMedicalRecord($record) {
    ?>
    <div class="print-container" style="display: none;">
        <div class="print-content">
            <!-- Header -->
            <div class="text-center mb-4">
                <img src="../../assets/images/logo.png" alt="MedBuddy Logo" style="height: 80px; margin-bottom: 10px;">
                <h2 class="mb-1">MedBuddy Medical Center</h2>
                <p class="mb-1">123 Medical Plaza, Healthcare City</p>
                <p class="mb-1">Phone: (123) 456-7890 | Email: info@medbuddy.com</p>
                <hr class="my-3">
            </div>

            <!-- Patient Information -->
            <div class="row mb-4">
                <div class="col-12">
                    <h4 class="mb-3">Medical Record</h4>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Patient Name:</strong> <?php echo htmlspecialchars($record['patient_name']); ?></p>
                            <p><strong>Date of Birth:</strong> <?php echo htmlspecialchars($record['date_of_birth']); ?></p>
                            <p><strong>Gender:</strong> <?php echo htmlspecialchars($record['gender']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Record Date:</strong> <?php echo date('F d, Y', strtotime($record['record_date'])); ?></p>
                            <p><strong>Record Time:</strong> <?php echo date('h:i A', strtotime($record['record_time'])); ?></p>
                            <p><strong>Record Type:</strong> <?php echo ucfirst(htmlspecialchars($record['record_type'])); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Visit Information -->
            <div class="row mb-4">
                <div class="col-12">
                    <h5 class="mb-3">Visit Information</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Doctor:</strong> Dr. <?php echo htmlspecialchars($record['doctor_first_name'] . ' ' . $record['doctor_last_name']); ?></p>
                            <p><strong>Appointment Date:</strong> <?php echo date('F d, Y', strtotime($record['appointment_date'])); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chief Complaint -->
            <?php if (!empty($record['chief_complaint'])): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <h5 class="mb-3">Chief Complaint</h5>
                    <p><?php echo nl2br(htmlspecialchars($record['chief_complaint'])); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Present Illness -->
            <?php if (!empty($record['present_illness'])): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <h5 class="mb-3">Present Illness</h5>
                    <p><?php echo nl2br(htmlspecialchars($record['present_illness'])); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Vital Signs -->
            <?php if (!empty($record['vital_signs'])): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <h5 class="mb-3">Vital Signs</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <tbody>
                                <?php 
                                $vital_signs = json_decode($record['vital_signs'], true);
                                if ($vital_signs): 
                                    foreach ($vital_signs as $key => $value):
                                        if (!empty($value)):
                                ?>
                                <tr>
                                    <th style="width: 30%"><?php echo ucwords(str_replace('_', ' ', $key)); ?></th>
                                    <td><?php echo htmlspecialchars($value); ?></td>
                                </tr>
                                <?php 
                                        endif;
                                    endforeach;
                                endif; 
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Physical Examination -->
            <?php if (!empty($record['physical_examination'])): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <h5 class="mb-3">Physical Examination</h5>
                    <p><?php echo nl2br(htmlspecialchars($record['physical_examination'])); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Diagnosis -->
            <?php if (!empty($record['diagnosis'])): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <h5 class="mb-3">Diagnosis</h5>
                    <p><?php echo nl2br(htmlspecialchars($record['diagnosis'])); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Treatment Plan -->
            <?php if (!empty($record['treatment_plan'])): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <h5 class="mb-3">Treatment Plan</h5>
                    <p><?php echo nl2br(htmlspecialchars($record['treatment_plan'])); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Prescription -->
            <?php if (!empty($record['prescription'])): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <h5 class="mb-3">Prescription</h5>
                    <p><?php echo nl2br(htmlspecialchars($record['prescription'])); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Notes -->
            <?php if (!empty($record['notes'])): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <h5 class="mb-3">Additional Notes</h5>
                    <p><?php echo nl2br(htmlspecialchars($record['notes'])); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Footer -->
            <div class="row mt-5">
                <div class="col-12">
                    <hr>
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Doctor's Signature:</strong></p>
                            <div style="height: 50px;"></div>
                            <p class="mb-1">Dr. <?php echo htmlspecialchars($record['doctor_first_name'] . ' ' . $record['doctor_last_name']); ?></p>
                        </div>
                        <div class="col-6 text-end">
                            <p class="mb-1"><strong>Date:</strong></p>
                            <p class="mb-1">${new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        @media print {
            body * {
                visibility: hidden;
            }
            .print-container, .print-container * {
                visibility: visible;
            }
            .print-container {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .print-content {
                padding: 20px;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>

    <script>
    function printRecord(recordId) {
        // Get the record data from the collapsed content
        const recordContent = document.querySelector(`#record${recordId}`).querySelector('.card-body');
        
        // Create a new print container
        const printContainer = document.createElement('div');
        printContainer.className = 'print-container';
        printContainer.style.display = 'block';
        
        // Clone the record content
        const printContent = document.createElement('div');
        printContent.className = 'print-content';
        
        // Get patient information
        const patientInfo = document.querySelector('.card-header h6').textContent;
        const doctorInfo = recordElement.querySelector('h6').textContent;
        const dateInfo = recordElement.querySelector('small').textContent;
        
        printContent.innerHTML = `
            <div class="text-center mb-4">
                <img src="../../assets/images/logo.png" alt="MedBuddy Logo" style="height: 80px; margin-bottom: 10px;">
                <h2 class="mb-1">MedBuddy Medical Center</h2>
                <p class="mb-1">123 Medical Plaza, Healthcare City</p>
                <p class="mb-1">Phone: (123) 456-7890 | Email: info@medbuddy.com</p>
                <hr class="my-3">
            </div>
            <div class="patient-info mb-4">
                <h4>${patientInfo}</h4>
                <p><strong>Doctor:</strong> ${doctorInfo}</p>
                <p><strong>Date:</strong> ${dateInfo}</p>
            </div>
            ${recordContent.innerHTML}
            <div class="mt-5">
                <hr>
                <div class="row">
                    <div class="col-6">
                        <p><strong>Doctor's Signature:</strong></p>
                        <div style="height: 50px;"></div>
                        <p>${doctorInfo}</p>
                    </div>
                    <div class="col-6 text-end">
                        <p><strong>Date:</strong></p>
                        <p>${new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</p>
                    </div>
                </div>
            </div>
        `;
        
        // Add the content to the print container
        printContainer.appendChild(printContent);
        document.body.appendChild(printContainer);
        
        // Print the document
        window.print();
        
        // Remove the print container after printing
        setTimeout(() => {
            if (printContainer.parentNode) {
                document.body.removeChild(printContainer);
            }
        }, 1000);
    }
    </script>
    <?php
} 