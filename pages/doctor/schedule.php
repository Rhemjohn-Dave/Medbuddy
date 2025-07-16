<?php
require_once '../../config/database.php';
require_once '../../includes/auth_check.php';

// Check if user is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: ../../index.php');
    exit();
}

// Get doctor ID
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->prepare("SELECT id FROM doctors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);
$doctor_id = $doctor['id'];

// Get doctor's clinics
$stmt = $conn->prepare("
    SELECT c.* 
    FROM clinics c
    JOIN doctor_clinics dc ON c.id = dc.clinic_id
    WHERE dc.doctor_id = ? AND c.status = 'active'
    ORDER BY c.name
");
$stmt->execute([$doctor_id]);
$clinics = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Management - MedBuddy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-3 border-bottom">
                    <h1 class="h2">Schedule Management</h1>
                    <div>
                        <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#assignClinicModal">
                            <span class="material-icons">add_location</span> Assign Clinic
                        </button>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                            <span class="material-icons">add</span> Add Schedule
                        </button>
                    </div>
                </div>

                <!-- Clinic Assignment Section -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <span class="material-icons">location_on</span>
                            My Assigned Clinics
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="assignedClinicsList">
                            <!-- Assigned clinics will be loaded here -->
                        </div>
                    </div>
                </div>

                <!-- Schedule Filters -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <select class="form-select" id="filterClinic">
                            <option value="">All Clinics</option>
                            <?php foreach ($clinics as $clinic): ?>
                                <option value="<?php echo $clinic['id']; ?>"><?php echo htmlspecialchars($clinic['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="filterDay">
                            <option value="">All Days</option>
                            <option value="2">Monday</option>
                            <option value="3">Tuesday</option>
                            <option value="4">Wednesday</option>
                            <option value="5">Thursday</option>
                            <option value="6">Friday</option>
                            <option value="7">Saturday</option>
                            <option value="1">Sunday</option>
                        </select>
                    </div>
                </div>

                <!-- Schedule Table -->
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Clinic</th>
                                <th>Day</th>
                                <th>Start Time</th>
                                <th>End Time</th>
                                <th>Break Time</th>
                                <th>Duration</th>
                                <th>Max Appointments</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="scheduleTable">
                            <!-- Schedule data will be loaded here -->
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>

    <!-- Assign Clinic Modal -->
    <div class="modal fade" id="assignClinicModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Assign Clinics</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Available Clinics</h6>
                            <div id="availableClinicsList" class="list-group">
                                <!-- Available clinics will be loaded here -->
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6>My Assigned Clinics</h6>
                            <div id="modalAssignedClinicsList" class="list-group">
                                <!-- Assigned clinics will be loaded here -->
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Schedule Modal -->
    <div class="modal fade" id="addScheduleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Schedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addScheduleForm">
                        <div class="mb-3">
                            <label class="form-label">Clinic</label>
                            <select class="form-select" name="clinic_id" required>
                                <option value="">Select Clinic</option>
                                <?php foreach ($clinics as $clinic): ?>
                                    <option value="<?php echo $clinic['id']; ?>"><?php echo htmlspecialchars($clinic['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Day of Week</label>
                            <select class="form-select" name="day_of_week" required>
                                <option value="2">Monday</option>
                                <option value="3">Tuesday</option>
                                <option value="4">Wednesday</option>
                                <option value="5">Thursday</option>
                                <option value="6">Friday</option>
                                <option value="7">Saturday</option>
                                <option value="1">Sunday</option>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Start Time</label>
                                <input type="time" class="form-control" name="start_time" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">End Time</label>
                                <input type="time" class="form-control" name="end_time" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Break Start</label>
                                <input type="time" class="form-control" name="break_start">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Break End</label>
                                <input type="time" class="form-control" name="break_end">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Duration per Appointment (minutes)</label>
                                <input type="number" class="form-control" name="duration_per_appointment" min="15" step="15" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Max Appointments per Slot</label>
                                <input type="number" class="form-control" name="max_appointments_per_slot" min="1" required>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveSchedule">Save Schedule</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Schedule Modal -->
    <div class="modal fade" id="editScheduleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Schedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editScheduleForm">
                        <input type="hidden" name="schedule_id">
                        <div class="mb-3">
                            <label class="form-label">Clinic</label>
                            <select class="form-select" name="clinic_id" required>
                                <option value="">Select Clinic</option>
                                <?php foreach ($clinics as $clinic): ?>
                                    <option value="<?php echo $clinic['id']; ?>"><?php echo htmlspecialchars($clinic['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Day of Week</label>
                            <select class="form-select" name="day_of_week" required>
                                <option value="2">Monday</option>
                                <option value="3">Tuesday</option>
                                <option value="4">Wednesday</option>
                                <option value="5">Thursday</option>
                                <option value="6">Friday</option>
                                <option value="7">Saturday</option>
                                <option value="1">Sunday</option>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Start Time</label>
                                <input type="time" class="form-control" name="start_time" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">End Time</label>
                                <input type="time" class="form-control" name="end_time" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Break Start</label>
                                <input type="time" class="form-control" name="break_start">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Break End</label>
                                <input type="time" class="form-control" name="break_end">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Duration per Appointment (minutes)</label>
                                <input type="number" class="form-control" name="duration_per_appointment" min="15" step="15" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Max Appointments per Slot</label>
                                <input type="number" class="form-control" name="max_appointments_per_slot" min="1" required>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="updateSchedule">Update Schedule</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/doctor-clinics.js"></script>
    <script src="../../assets/js/schedule.js"></script>
</body>
</html> 