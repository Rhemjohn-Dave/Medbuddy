<?php
session_start();
require_once '../../../config/database.php';

// Check if user is logged in and is staff
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../../../login.php");
    exit();
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

$patients = [];
if (!empty($assigned_clinics)) {
    $placeholders = implode(',', array_fill(0, count($assigned_clinics), '?'));
    $sql = "SELECT DISTINCT u.id, u.first_name, u.last_name, u.email
            FROM users u
            JOIN appointments a ON u.id = a.patient_id
            WHERE a.clinic_id IN ($placeholders) AND u.role = 'patient'
            ORDER BY u.last_name, u.first_name";
    $stmt = $conn->prepare($sql);
    $stmt->execute($assigned_clinics);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get patient ID from URL if provided
$patient_id = isset($_GET['id']) ? $_GET['id'] : null;

// Get patient information if ID is provided and patient is in staff's clinics
$patient = null;
if ($patient_id && !empty($assigned_clinics)) {
    $sql = "SELECT u.* FROM users u
            JOIN appointments a ON u.id = a.patient_id
            WHERE u.id = ? AND a.clinic_id IN ($placeholders) AND u.role = 'patient' LIMIT 1";
    $stmt = $conn->prepare($sql);
    $params = array_merge([$patient_id], $assigned_clinics);
    $stmt->execute($params);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get patient's medical records if patient is selected and in staff's clinics
$medical_records = [];
if ($patient && !empty($assigned_clinics)) {
    $placeholders = implode(',', array_fill(0, count($assigned_clinics), '?'));
    $sql = "SELECT mr.*, d.first_name as doctor_first_name, d.last_name as doctor_last_name
            FROM medical_records mr
            JOIN users d ON mr.doctor_id = d.id
            JOIN appointments a ON mr.appointment_id = a.id
            WHERE mr.patient_id = ? AND a.clinic_id IN ($placeholders)
            ORDER BY mr.date DESC";
    $params = array_merge([$patient_id], $assigned_clinics);
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $medical_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient History - MedBuddy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <?php include '../components/sidebar.php'; ?>

        <div class="main-content">
            <!-- Navbar -->
            <?php include '../components/navbar.php'; ?>

            <!-- Main Content Area -->
            <div class="container-fluid py-4">
                <div class="row">
                    <div class="col-12">
                        <h1 class="h3 mb-4">Patient History</h1>
                    </div>
                </div>

                <!-- Patient Search -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <form method="GET" class="d-flex">
                                    <select name="id" class="form-select me-2" required>
                                        <option value="">Select Patient</option>
                                        <?php foreach ($patients as $p): ?>
                                        <option value="<?php echo $p['id']; ?>" <?php echo $patient_id == $p['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($p['last_name'] . ', ' . $p['first_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-primary">View History</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($patient): ?>
                <!-- Patient Information -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Patient Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Name:</strong> <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></p>
                                        <p><strong>Email:</strong> <?php echo htmlspecialchars($patient['email']); ?></p>
                                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($patient['phone']); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Date of Birth:</strong> <?php echo date('M d, Y', strtotime($patient['date_of_birth'])); ?></p>
                                        <p><strong>Gender:</strong> <?php echo ucfirst($patient['gender']); ?></p>
                                        <p><strong>Blood Type:</strong> <?php echo strtoupper($patient['blood_type']); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Medical Records -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Medical Records</h5>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newRecordModal">
                                    <i class="fas fa-plus"></i> Add Record
                                </button>
                            </div>
                            <div class="card-body">
                                <?php if (empty($medical_records)): ?>
                                <p class="text-muted">No medical records found for this patient.</p>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Doctor</th>
                                                <th>Diagnosis</th>
                                                <th>Treatment</th>
                                                <th>Notes</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($medical_records as $record): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($record['date'])); ?></td>
                                                <td><?php echo htmlspecialchars($record['doctor_first_name'] . ' ' . $record['doctor_last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($record['diagnosis']); ?></td>
                                                <td><?php echo htmlspecialchars($record['treatment']); ?></td>
                                                <td><?php echo htmlspecialchars($record['notes']); ?></td>
                                                <td>
                                                    <div class="btn-group">
                                                        <button type="button" class="btn btn-sm btn-primary" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#viewRecordModal<?php echo $record['id']; ?>">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-warning" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#editRecordModal<?php echo $record['id']; ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <form method="POST" action="api/delete-record.php" class="d-inline">
                                                            <input type="hidden" name="record_id" value="<?php echo $record['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger" 
                                                                    onclick="return confirm('Are you sure you want to delete this record?')">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
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
                <?php endif; ?>
            </div>

            <!-- Footer -->
            <?php include '../components/footer.php'; ?>
        </div>
    </div>

    <!-- New Record Modal -->
    <div class="modal fade" id="newRecordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">New Medical Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="newRecordForm" method="POST" action="api/create-record.php">
                        <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                        <div class="mb-3">
                            <label class="form-label">Doctor</label>
                            <select class="form-select" name="doctor_id" required>
                                <option value="">Select Doctor</option>
                                <!-- Will be populated via AJAX -->
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date</label>
                            <input type="date" class="form-control" name="date" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Diagnosis</label>
                            <textarea class="form-control" name="diagnosis" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Treatment</label>
                            <textarea class="form-control" name="treatment" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" form="newRecordForm" class="btn btn-primary">Create Record</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../../../assets/js/main.js"></script>
    <script>
        // Load doctors for the new record form
        $(document).ready(function() {
            $.get('api/get-doctors.php', function(data) {
                const doctorSelect = $('select[name="doctor_id"]');
                data.forEach(doctor => {
                    doctorSelect.append(`<option value="${doctor.id}">${doctor.first_name} ${doctor.last_name}</option>`);
                });
            });
        });
    </script>
</body>
</html> 