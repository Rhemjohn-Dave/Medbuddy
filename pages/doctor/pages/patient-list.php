<?php
// Check if DOCTOR_ACCESS is defined
if (!defined('DOCTOR_ACCESS')) {
    // If this is an API/AJAX request, return JSON error instead of redirect
    $isApi = (
        (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
        (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
    );
    if ($isApi) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit();
    } else {
        header("Location: ../../auth/index.php");
        exit();
    }
}

require_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get doctor ID from session
$user_id = $_SESSION['user_id'];
$query = "SELECT id FROM doctors WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $user_id);
$stmt->execute();
$doctor = $stmt->fetch();
$doctor_id = $doctor['id'];

// Get assigned clinics for this doctor
$query = "SELECT clinic_id FROM doctor_clinics WHERE doctor_id = :doctor_id";
$stmt = $db->prepare($query);
$stmt->bindParam(":doctor_id", $doctor_id);
$stmt->execute();
$assigned_clinics = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Cast all clinic IDs to integers to avoid SQL type mismatch
$assigned_clinics = array_map('intval', $assigned_clinics);

$patients = [];
$total_patients = 0;
if (!empty($assigned_clinics)) {
    $placeholders = implode(',', array_fill(0, count($assigned_clinics), '?'));
    $query = "SELECT DISTINCT p.*, CONCAT(p.first_name, ' ', p.last_name) as patient_name
              FROM patients p
              INNER JOIN appointments a ON p.id = a.patient_id
              WHERE a.doctor_id = ? AND a.clinic_id IN ($placeholders)
              ORDER BY p.first_name, p.last_name";
    $stmt = $db->prepare($query);
    $params = array_merge([$doctor_id], $assigned_clinics);
    $stmt->execute($params);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_patients = count($patients);
    // Debug output
    error_log('Doctor ID: ' . $doctor_id);
    error_log('Assigned clinics: ' . print_r($assigned_clinics, true));
    error_log('SQL: ' . $query);
    error_log('Params: ' . print_r($params, true));
    error_log('Patients found: ' . print_r($patients, true));
}


function renderPatientDetailsModal($db, $patient_id) {
    // Fetch patient details (all fields)
    $stmt = $db->prepare("SELECT * FROM patients WHERE id = :id");
    $stmt->bindParam(":id", $patient_id);
    $stmt->execute();
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch email from users table
    $email = '-';
    if (!empty($patient['user_id'])) {
        $stmt = $db->prepare("SELECT email FROM users WHERE id = :user_id");
        $stmt->bindParam(":user_id", $patient['user_id']);
    $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['email'])) {
            $email = $user['email'];
        }
    }

    // Fetch medical records
    $stmt = $db->prepare("SELECT * FROM medical_records WHERE patient_id = :id ORDER BY created_at DESC");
    $stmt->bindParam(":id", $patient_id);
    $stmt->execute();
    $medical_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all diagnoses for this patient (across all their medical records)
    $record_ids = array_column($medical_records, 'id');
    $diagnoses = [];
    $prescriptions = [];
    $vital_signs = null;
    if (!empty($record_ids)) {
        $in = str_repeat('?,', count($record_ids) - 1) . '?';
        // Diagnoses
        $sql = "SELECT d.*, mr.created_at as record_date FROM diagnoses d JOIN medical_records mr ON d.medical_record_id = mr.id WHERE d.medical_record_id IN ($in) ORDER BY d.created_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($record_ids);
        $diagnoses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Prescriptions
        $sql = "SELECT p.*, mr.created_at as record_date FROM prescriptions p JOIN medical_records mr ON p.medical_record_id = mr.id WHERE p.medical_record_id IN ($in) ORDER BY p.created_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($record_ids);
        $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Latest vital signs
        $sql = "SELECT * FROM vital_signs WHERE medical_record_id IN ($in) ORDER BY recorded_at DESC LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute($record_ids);
        $vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Fetch appointment history
    $stmt = $db->prepare("SELECT a.*, c.name as clinic_name FROM appointments a LEFT JOIN clinics c ON a.clinic_id = c.id WHERE a.patient_id = :id ORDER BY a.date DESC, a.time DESC");
    $stmt->bindParam(":id", $patient_id);
    $stmt->execute();
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch lab requests for this patient
    $stmt = $db->prepare("SELECT lr.*, lr.doctor_comment, d.first_name AS doctor_first, d.last_name AS doctor_last FROM lab_requests lr JOIN doctors d ON lr.doctor_id = d.id WHERE lr.patient_id = ? ORDER BY lr.requested_at DESC");
    $stmt->execute([$patient_id]);
    $lab_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch medications for this patient
    $stmt = $db->prepare("SELECT * FROM medications WHERE patient_id = ? ORDER BY created_at DESC");
    $stmt->execute([$patient_id]);
    $medications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $full_name = trim(($patient['first_name'] ?? '') . ' ' . ($patient['middle_name'] ?? '') . ' ' . ($patient['last_name'] ?? ''));
    ?>
    <div class="modal fade" id="viewPatientModal<?php echo $patient_id; ?>" tabindex="-1" aria-labelledby="viewPatientModalLabel<?php echo $patient_id; ?>" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white align-items-center">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-white d-flex justify-content-center align-items-center me-3" style="width:48px;height:48px;">
                            <span class="material-icons text-primary" style="font-size:2rem;">person</span>
                        </div>
                        <div>
                            <h5 class="modal-title mb-0" id="viewPatientModalLabel<?php echo $patient_id; ?>">
                                Patient Details: <?php echo htmlspecialchars($full_name); ?>
                            </h5>
                            <small class="text-white-50">Patient ID: <?php echo $patient_id; ?></small>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pb-0">
                    <ul class="nav nav-tabs mb-3" id="patientTab<?php echo $patient_id; ?>" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="personal-tab<?php echo $patient_id; ?>" data-bs-toggle="tab" data-bs-target="#personal<?php echo $patient_id; ?>" type="button" role="tab" aria-controls="personal<?php echo $patient_id; ?>" aria-selected="true">
                                <span class="material-icons align-middle me-1">badge</span> Personal Info
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="records-tab<?php echo $patient_id; ?>" data-bs-toggle="tab" data-bs-target="#records<?php echo $patient_id; ?>" type="button" role="tab" aria-controls="records<?php echo $patient_id; ?>" aria-selected="false">
                                <span class="material-icons align-middle me-1">assignment</span> Medical Records
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="diagnoses-tab<?php echo $patient_id; ?>" data-bs-toggle="tab" data-bs-target="#diagnoses<?php echo $patient_id; ?>" type="button" role="tab" aria-controls="diagnoses<?php echo $patient_id; ?>" aria-selected="false">
                                <span class="material-icons align-middle me-1">fact_check</span> Diagnoses
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="prescriptions-tab<?php echo $patient_id; ?>" data-bs-toggle="tab" data-bs-target="#prescriptions<?php echo $patient_id; ?>" type="button" role="tab" aria-controls="prescriptions<?php echo $patient_id; ?>" aria-selected="false">
                                <span class="material-icons align-middle me-1">medication</span> Prescriptions
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="history-tab<?php echo $patient_id; ?>" data-bs-toggle="tab" data-bs-target="#history<?php echo $patient_id; ?>" type="button" role="tab" aria-controls="history<?php echo $patient_id; ?>" aria-selected="false">
                                <span class="material-icons align-middle me-1">history</span> Appointment History
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="labresults-tab<?php echo $patient_id; ?>" data-bs-toggle="tab" data-bs-target="#labresults<?php echo $patient_id; ?>" type="button" role="tab" aria-controls="labresults<?php echo $patient_id; ?>" aria-selected="false">
                                <span class="material-icons align-middle me-1">science</span> Lab Results
                            </button>
                        </li>
                    </ul>
                    <div class="tab-content pt-3" id="patientTabContent<?php echo $patient_id; ?>">
                        <!-- Personal Info Tab -->
                        <div class="tab-pane fade show active" id="personal<?php echo $patient_id; ?>" role="tabpanel" aria-labelledby="personal-tab<?php echo $patient_id; ?>">
                            <div class="card shadow-sm border-0 mb-3" style="background:#f8f9fa;">
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <h6 class="text-primary mb-2"><span class="material-icons align-middle me-1">person</span> Basic Information</h6>
                                            <dl class="row mb-0">
                                                <dt class="col-5">Name:</dt><dd class="col-7"><?php echo htmlspecialchars($full_name); ?></dd>
                                                <dt class="col-5">Gender:</dt><dd class="col-7"><?php echo htmlspecialchars($patient['gender'] ?? '<span class=\'text-muted\'>-</span>'); ?></dd>
                                                <dt class="col-5">Date of Birth:</dt><dd class="col-7"><?php echo htmlspecialchars($patient['date_of_birth'] ?? '<span class=\'text-muted\'>-</span>'); ?></dd>
                                                <dt class="col-5">Blood Type:</dt><dd class="col-7"><?php echo htmlspecialchars($patient['blood_type'] ?? '<span class=\'text-muted\'>-</span>'); ?></dd>
                                            </dl>
                                        </div>
                                        <div class="col-md-6">
                                            <h6 class="text-primary mb-2"><span class="material-icons align-middle me-1">call</span> Contact</h6>
                                            <dl class="row mb-0">
                                                <dt class="col-5">Contact:</dt><dd class="col-7"><?php echo htmlspecialchars($patient['contact_number'] ?? '<span class=\'text-muted\'>-</span>'); ?></dd>
                                                <dt class="col-5">Email:</dt><dd class="col-7"><?php echo htmlspecialchars($email); ?></dd>
                                                <dt class="col-5">Address:</dt><dd class="col-7"><?php echo htmlspecialchars($patient['address'] ?? '<span class=\'text-muted\'>-</span>'); ?></dd>
                                            </dl>
                                        </div>
                                    </div>
                                    <hr>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <h6 class="text-primary mb-2"><span class="material-icons align-middle me-1">contacts</span> Emergency Contact</h6>
                                            <dl class="row mb-0">
                                                <dt class="col-5">Name:</dt><dd class="col-7"><?php echo htmlspecialchars($patient['emergency_contact_name'] ?? '<span class=\'text-muted\'>-</span>'); ?></dd>
                                                <dt class="col-5">Number:</dt><dd class="col-7"><?php echo htmlspecialchars($patient['emergency_contact_number'] ?? '<span class=\'text-muted\'>-</span>'); ?></dd>
                                            </dl>
                                        </div>
                                        <div class="col-md-6">
                                            <h6 class="text-primary mb-2"><span class="material-icons align-middle me-1">healing</span> Medical Info</h6>
                                            <dl class="row mb-0">
                                                <dt class="col-5">Medical History:</dt><dd class="col-7"><?php echo nl2br(htmlspecialchars($patient['medical_history'] ?? '<span class=\'text-muted\'>-</span>')); ?></dd>
                                                <dt class="col-5">Allergies:</dt><dd class="col-7"><?php echo nl2br(htmlspecialchars($patient['allergies'] ?? '<span class=\'text-muted\'>-</span>')); ?></dd>
                                            </dl>
                                        </div>
                                    </div>
                                    <hr>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <dl class="row mb-0">
                                                <dt class="col-5">Created At:</dt><dd class="col-7"><?php echo htmlspecialchars($patient['created_at'] ?? '<span class=\'text-muted\'>-</span>'); ?></dd>
                                            </dl>
                                        </div>
                                        <div class="col-md-6">
                                            <dl class="row mb-0">
                                                <dt class="col-5">Updated At:</dt><dd class="col-7"><?php echo htmlspecialchars($patient['updated_at'] ?? '<span class=\'text-muted\'>-</span>'); ?></dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Medical Records Tab -->
                        <div class="tab-pane fade" id="records<?php echo $patient_id; ?>" role="tabpanel" aria-labelledby="records-tab<?php echo $patient_id; ?>">
                            <?php if ($vital_signs): ?>
                                <div class="card shadow-sm border-0 mb-4" style="background:#f1f3f4;">
                                    <div class="card-body">
                                        <h6 class="text-primary mb-3"><span class="material-icons align-middle me-1">monitor_heart</span> Latest Vital Signs</h6>
                                        <div class="row g-3">
                                            <div class="col-md-4"><strong>BP:</strong> <?php
                                                $sys = $vital_signs['blood_pressure_systolic'] ?? '-';
                                                $dia = $vital_signs['blood_pressure_diastolic'] ?? '-';
                                                echo ($sys !== '-' && $dia !== '-') ? htmlspecialchars($sys . '/' . $dia . ' mmHg') : '-';
                                            ?></div>
                                            <div class="col-md-4"><strong>Temperature:</strong> <?php echo htmlspecialchars($vital_signs['temperature'] ?? '-') . ' °C'; ?></div>
                                            <div class="col-md-4"><strong>Heart Rate:</strong> <?php echo htmlspecialchars($vital_signs['heart_rate'] ?? '-') . ' bpm'; ?></div>
                                            <div class="col-md-4"><strong>Respiratory Rate:</strong> <?php echo htmlspecialchars($vital_signs['respiratory_rate'] ?? '-') . ' bpm'; ?></div>
                                            <div class="col-md-4"><strong>SpO2:</strong> <?php echo htmlspecialchars($vital_signs['oxygen_saturation'] ?? '-') . ' %'; ?></div>
                                            <div class="col-md-4"><strong>Weight:</strong> <?php echo htmlspecialchars($vital_signs['weight'] ?? '-') . ' kg'; ?></div>
                                            <div class="col-md-4"><strong>Height:</strong> <?php echo htmlspecialchars($vital_signs['height'] ?? '-') . ' cm'; ?></div>
                                            <div class="col-md-4"><strong>BMI:</strong> <?php echo htmlspecialchars($vital_signs['bmi'] ?? '-'); ?></div>
                                            <div class="col-md-4"><strong>Pain Scale:</strong> <?php echo htmlspecialchars($vital_signs['pain_scale'] ?? '-'); ?></div>
                                            <div class="col-md-4"><strong>Recorded At:</strong> <?php echo htmlspecialchars($vital_signs['recorded_at'] ?? '-'); ?></div>
                                            <div class="col-md-12"><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($vital_signs['notes'] ?? '-')); ?></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <?php if (empty($medical_records)): ?>
                                <p class="text-muted">No medical records found.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Date</th>
                                                <th>Diagnosis</th>
                                                <th>Prescription</th>
                                                <th>Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($medical_records as $record): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($record['created_at'] ?? '-'); ?></td>
                                                    <td><?php echo htmlspecialchars($record['diagnosis'] ?? '-'); ?></td>
                                                    <td><?php echo htmlspecialchars($record['prescription'] ?? '-'); ?></td>
                                                    <td><?php echo htmlspecialchars($record['notes'] ?? '-'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                        <!-- Diagnoses Tab -->
                        <div class="tab-pane fade" id="diagnoses<?php echo $patient_id; ?>" role="tabpanel" aria-labelledby="diagnoses-tab<?php echo $patient_id; ?>">
                            <?php if (empty($diagnoses)): ?>
                                <p class="text-muted">No diagnoses found.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Date</th>
                                                <th>Diagnosis</th>
                                                <th>Created By</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($diagnoses as $diag): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($diag['created_at'] ?? $diag['record_date'] ?? '-'); ?></td>
                                                    <td><?php echo htmlspecialchars($diag['diagnosis'] ?? '-'); ?></td>
                                                    <td><?php echo htmlspecialchars($diag['created_by'] ?? '-'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                        <!-- Prescriptions Tab -->
                        <div class="tab-pane fade" id="prescriptions<?php echo $patient_id; ?>" role="tabpanel" aria-labelledby="prescriptions-tab<?php echo $patient_id; ?>">
                            <form class="row g-2 align-items-end mb-2" id="medsPrintForm<?php echo $patient_id; ?>" action="../../api/print_medications.php" method="get" target="_blank">
                                <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                                <div class="col-auto">
                                    <label for="start_date_<?php echo $patient_id; ?>" class="form-label mb-0">From</label>
                                    <input type="date" class="form-control form-control-sm" name="start_date" id="start_date_<?php echo $patient_id; ?>">
                                </div>
                                <div class="col-auto">
                                    <label for="end_date_<?php echo $patient_id; ?>" class="form-label mb-0">To</label>
                                    <input type="date" class="form-control form-control-sm" name="end_date" id="end_date_<?php echo $patient_id; ?>">
                                </div>
                                <div class="col-auto">
                                    <button type="submit" class="btn btn-outline-primary btn-sm">
                                        <span class="material-icons align-middle me-1">print</span> Print/PDF Medications
                                    </button>
                                </div>
                            </form>
                            <?php if (empty($medications)): ?>
                                <p class="text-muted">No prescriptions found.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered prescription-table">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Medication</th>
                                                <th>Dosage</th>
                                                <th>Frequency</th>
                                                <th>Duration</th>
                                                <th>Instructions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($medications as $med): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($med['created_at']))); ?></td>
                                                    <td><?php echo htmlspecialchars($med['medication_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($med['dosage']); ?></td>
                                                    <td><?php echo htmlspecialchars($med['frequency']); ?></td>
                                                    <td><?php echo htmlspecialchars($med['start_date']) . ($med['end_date'] ? ' to ' . htmlspecialchars($med['end_date']) : ''); ?></td>
                                                    <td><?php echo htmlspecialchars($med['notes']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                        <!-- Appointment History Tab -->
                        <div class="tab-pane fade" id="history<?php echo $patient_id; ?>" role="tabpanel" aria-labelledby="history-tab<?php echo $patient_id; ?>">
                            <?php if (empty($appointments)): ?>
                                <p class="text-muted">No appointments found.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Date</th>
                                                <th>Time</th>
                                                <th>Clinic</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($appointments as $app): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($app['date'] ?? '-'); ?></td>
                                                    <td><?php echo htmlspecialchars($app['time'] ?? '-'); ?></td>
                                                    <td><?php echo htmlspecialchars($app['clinic_name'] ?? '-'); ?></td>
                                                    <td><?php echo htmlspecialchars($app['status'] ?? '-'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                        <!-- Lab Results Tab -->
                        <div class="tab-pane fade" id="labresults<?php echo $patient_id; ?>" role="tabpanel" aria-labelledby="labresults-tab<?php echo $patient_id; ?>">
                            <div class="card shadow-sm border-0 mb-3" style="background:#f8f9fa;">
                                <div class="card-body">
                                    <h6 class="text-primary mb-3"><span class="material-icons align-middle me-1">science</span> Lab Results</h6>
                                    <?php if (empty($lab_requests)): ?>
                                        <p class="text-muted">No lab requests found.</p>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th>Test Type</th>
                                                        <th>Notes</th>
                                                        <th>Status</th>
                                                        <th>Requested At</th>
                                                        <th>Requested By</th>
                                                        <th>Result</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                <?php foreach ($lab_requests as $req): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($req['test_type']) ?></td>
                                                        <td><?= htmlspecialchars($req['notes']) ?></td>
                                                        <td><span class="badge 
    <?php if ($req['status'] === 'requested') echo 'bg-warning text-dark';
          elseif ($req['status'] === 'in_progress') echo 'bg-info text-dark';
          else echo 'bg-secondary'; ?>
">
    <?= htmlspecialchars($req['status']) ?>
</span></td>
                                                        <td><?= htmlspecialchars($req['requested_at']) ?></td>
                                                        <td><?= htmlspecialchars($req['doctor_first'] . ' ' . $req['doctor_last']) ?></td>
                                                        <td>
                                                            <?php
                                                            if (!empty($req['result_file'])) {
                                                                echo '<a href="/Medbuddy/uploads/lab_results/' . htmlspecialchars($req['result_file']) . '" target="_blank">View PDF</a>';
                                                                if ($req['result']) {
                                                                    echo '<br>';
                                                                }
                                                            }
                                                            echo $req['result'] ? nl2br(htmlspecialchars($req['result'])) : '<span class="text-muted">Pending</span>';
                                                            ?>
                                                            <hr>
                                                            <form class="doctor-comment-form" data-lab-request-id="<?= $req['id'] ?>">
                                                                <label for="doctor_comment_<?= $req['id'] ?>" class="form-label">Doctor Comment:</label>
                                                                <textarea class="form-control mb-2" id="doctor_comment_<?= $req['id'] ?>" name="doctor_comment" rows="2"><?= htmlspecialchars($req['doctor_comment'] ?? '') ?></textarea>
                                                                <button type="submit" class="btn btn-sm btn-primary">Save Comment</button>
                                                                <span class="doctor-comment-status ms-2" style="display:none;"></span>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}
?>
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Total Patients</h6>
                    <h3 class="card-title mb-0"><?php echo $total_patients; ?></h3>
                </div>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Patient List</h5>
            <div>
                <input type="text" class="form-control d-inline-block" style="width:200px;" id="searchPatient" placeholder="Search patients...">
                <button class="btn btn-outline-primary btn-sm ms-2" onclick="exportTableToCSV('patient_list.csv')">Export</button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="patientListTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Age/Gender</th>
                            <th>Contact</th>
                            <th>Last Visit</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($patients)): ?>
                            <tr><td colspan="5" class="text-center text-muted">No patients found</td></tr>
                        <?php else: ?>
                            <?php foreach ($patients as $patient): 
                                $age = date_diff(date_create($patient['date_of_birth']), date_create('today'))->y;
                                // Fetch last visit for this patient (last appointment date with this doctor in assigned clinics)
                                $last_visit = null;
                                $query = "SELECT MAX(date) as last_visit FROM appointments WHERE patient_id = ? AND doctor_id = ? AND clinic_id IN (" . implode(',', array_fill(0, count($assigned_clinics), '?')) . ")";
                                $params = array_merge([$patient['id'], $doctor_id], $assigned_clinics);
                                $stmt = $db->prepare($query);
                                $stmt->execute($params);
                                $last_visit_row = $stmt->fetch(PDO::FETCH_ASSOC);
                                if ($last_visit_row && $last_visit_row['last_visit']) {
                                    $last_visit = $last_visit_row['last_visit'];
                                }
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($patient['patient_name']); ?></td>
                                <td><?php echo $age . '/' . htmlspecialchars($patient['gender']); ?></td>
                                <td><?php echo htmlspecialchars($patient['contact_number']); ?></td>
                                <td><?php echo $last_visit ? date('M d, Y', strtotime($last_visit)) : 'Never'; ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="#" class="btn btn-sm btn-outline-primary" title="View Patient" data-bs-toggle="modal" data-bs-target="#viewPatientModal<?php echo $patient['id']; ?>">
                                            <span class="material-icons">visibility</span>
                                        </a>
                                        <a href="#" class="btn btn-sm btn-outline-success" title="Schedule Appointment" onclick="openScheduleModal('<?php echo htmlspecialchars($patient['patient_name']); ?>', <?php echo $patient['id']; ?>); return false;">
                                            <span class="material-icons">event</span>
                                        </a>
                                        <a href="#" class="btn btn-sm btn-outline-info" title="Request Lab" onclick="openLabRequestModal(<?php echo $patient['id']; ?>, <?php echo $doctor_id; ?>); return false;">
                                            <span class="material-icons">science</span>
                                        </a>
                                        <?php if ($last_visit): ?>
                                        <a href="../../api/print_medications.php?patient_id=<?php echo $patient['id']; ?>&start_date=<?php echo $last_visit; ?>&end_date=<?php echo $last_visit; ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Print Medication for Last Visit">
                                            <span class="material-icons">print</span>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<!-- Schedule Appointment Modal (hidden by default, implement as needed) -->
<div class="modal fade" id="scheduleAppointmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Schedule Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="scheduleAppointmentForm">
                    <div class="mb-3">
                        <label class="form-label">Patient Name</label>
                        <input type="text" class="form-control" id="appointmentPatientName" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date</label>
                        <input type="date" class="form-control" id="appointmentDate" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Time</label>
                        <input type="time" class="form-control" id="appointmentTime" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Purpose</label>
                        <textarea class="form-control" id="appointmentPurpose" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="appointmentNotes" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveAppointment">Schedule</button>
            </div>
        </div>
    </div>
</div>
<!-- Lab Request Modal -->
<div class="modal fade" id="labRequestModal" tabindex="-1" aria-labelledby="labRequestModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="labRequestModalLabel">Request Lab Test</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="labRequestForm">
          <input type="hidden" name="patient_id" id="labRequestPatientId">
          <input type="hidden" name="doctor_id" id="labRequestDoctorId">
          <div class="mb-3">
            <label for="labRequestClinicId" class="form-label">Clinic</label>
            <select class="form-select" id="labRequestClinicId" name="clinic_id" required>
              <option value="">Select clinic...</option>
            </select>
          </div>
          <div class="mb-3">
            <label for="test_type" class="form-label">Lab Test Type</label>
            <select class="form-select" id="test_type" name="test_type" required>
              <option value="">Select test...</option>
              <option value="Ultrasound">Ultrasound</option>
              <option value="CBC">CBC</option>
              <option value="X-ray">X-ray</option>
              <option value="Urinalysis">Urinalysis</option>
              <option value="Blood Chemistry">Blood Chemistry</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <div class="mb-3">
            <label for="lab_notes" class="form-label">Notes</label>
            <textarea class="form-control" id="lab_notes" name="notes" rows="2"></textarea>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Submit Request</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<script>
// Simple search filter for patient table
const searchInput = document.getElementById('searchPatient');
if (searchInput) {
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const rows = document.querySelectorAll('#patientListTable tbody tr');
        rows.forEach(row => {
            const name = row.querySelector('td:first-child')?.textContent.toLowerCase() || '';
            row.style.display = name.includes(searchTerm) ? '' : 'none';
        });
    });
}
// Export table to CSV
function exportTableToCSV(filename) {
    const rows = document.querySelectorAll('#patientListTable tr');
    let csv = [];
    rows.forEach(row => {
        const cols = row.querySelectorAll('th, td');
        let rowData = [];
        cols.forEach(col => rowData.push('"' + col.innerText.replace(/"/g, '""') + '"'));
        csv.push(rowData.join(','));
    });
    const csvFile = new Blob([csv.join('\n')], { type: 'text/csv' });
    const downloadLink = document.createElement('a');
    downloadLink.download = filename;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}
// Open schedule modal
function openScheduleModal(patientName, patientId) {
    document.getElementById('appointmentPatientName').value = patientName;
    // You can set patientId in a hidden field if needed
    const modal = new bootstrap.Modal(document.getElementById('scheduleAppointmentModal'));
    modal.show();
}
function openLabRequestModal(patientId, doctorId) {
    document.getElementById('labRequestPatientId').value = patientId;
    document.getElementById('labRequestDoctorId').value = doctorId;
    document.getElementById('test_type').value = '';
    document.getElementById('lab_notes').value = '';
    // Fetch clinics for this patient and doctor
    const clinicSelect = document.getElementById('labRequestClinicId');
    clinicSelect.innerHTML = '<option value="">Loading...</option>';
    fetch(`../../api/clinics.php?patient_id=${patientId}&doctor_id=${doctorId}`)
      .then(res => res.json())
      .then(data => {
        clinicSelect.innerHTML = '<option value="">Select clinic...</option>';
        if (data.success && data.clinics && data.clinics.length > 0) {
          data.clinics.forEach(clinic => {
            const opt = document.createElement('option');
            opt.value = clinic.id;
            opt.textContent = clinic.name;
            clinicSelect.appendChild(opt);
          });
        } else {
          clinicSelect.innerHTML = '<option value="">No clinics found</option>';
        }
      });
    var modal = new bootstrap.Modal(document.getElementById('labRequestModal'));
    modal.show();
}
document.getElementById('labRequestForm').addEventListener('submit', function(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    if (!data.clinic_id) {
      alert('Please select a clinic for this lab request.');
      return;
    }
    fetch('../../api/lab_requests.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Lab request submitted successfully!');
            var modal = bootstrap.Modal.getInstance(document.getElementById('labRequestModal'));
            modal.hide();
        } else {
            alert('Error submitting lab request: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        alert('An error occurred while submitting lab request.');
    });
});
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.doctor-comment-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const labRequestId = this.getAttribute('data-lab-request-id');
            const comment = this.querySelector('textarea[name="doctor_comment"]').value;
            const statusSpan = this.querySelector('.doctor-comment-status');
            fetch('/Medbuddy/api/lab_requests.php', {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: labRequestId, doctor_comment: comment })
            })
            .then(res => res.json())
            .then(data => {
                statusSpan.textContent = data.success ? 'Saved!' : 'Error';
                statusSpan.style.display = 'inline';
                statusSpan.className = 'doctor-comment-status ms-2 ' + (data.success ? 'text-success' : 'text-danger');
                setTimeout(() => { statusSpan.style.display = 'none'; }, 2000);
            });
        });
    });
});
</script> 
<?php
// Render all modals after the table
if (!empty($patients)) {
    foreach ($patients as $patient) {
        renderPatientDetailsModal($db, $patient['id']);
    }
}
?> 