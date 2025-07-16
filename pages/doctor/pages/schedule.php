<?php
// Check if DOCTOR_ACCESS is defined
if (!defined('DOCTOR_ACCESS')) {
    header("Location: ../../auth/index.php");
    exit();
}

// Include database configuration
require_once __DIR__ . '/../../../config/database.php';

// Helper function to get day name
function getDayName($dayNumber) {
    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    return $days[$dayNumber - 1];
}

// Helper function to format time
function formatTime($time) {
    return date('h:i A', strtotime($time));
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

// Get schedules
$where = ['s.doctor_id = ?'];
$params = [$doctor_id];

if (!empty($_GET['clinic_id'])) {
    $where[] = 's.clinic_id = ?';
    $params[] = $_GET['clinic_id'];
}

if (!empty($_GET['day'])) {
    $where[] = 's.day_of_week = ?';
    $params[] = $_GET['day'];
}

$sql = "
    SELECT s.*, c.name as clinic_name
    FROM doctor_schedules s
    JOIN clinics c ON s.clinic_id = c.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY s.day_of_week, s.start_time
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get schedule for editing if edit parameter is set
$edit_schedule = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("
        SELECT * FROM doctor_schedules 
        WHERE id = ? AND doctor_id = ?
    ");
    $stmt->execute([$_GET['edit'], $doctor_id]);
    $edit_schedule = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!-- Display messages -->
<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($_GET['success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($_GET['error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

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
            <div class="text-center text-muted">
                <span class="material-icons">hourglass_empty</span>
                Loading assigned clinics...
            </div>
        </div>
    </div>
</div>

<!-- Schedule Filters -->
<div class="row mb-4">
    <div class="col-md-3">
        <select class="form-select" id="filterClinic" onchange="window.location.href='?clinic_id=' + this.value + '&day=' + document.getElementById('filterDay').value">
            <option value="">All Clinics</option>
            <?php foreach ($clinics as $clinic): ?>
                <option value="<?php echo $clinic['id']; ?>" <?php echo isset($_GET['clinic_id']) && $_GET['clinic_id'] == $clinic['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($clinic['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <select class="form-select" id="filterDay" onchange="window.location.href='?clinic_id=' + document.getElementById('filterClinic').value + '&day=' + this.value">
            <option value="">All Days</option>
            <option value="1" <?php echo isset($_GET['day']) && $_GET['day'] == '1' ? 'selected' : ''; ?>>Sunday</option>
            <option value="2" <?php echo isset($_GET['day']) && $_GET['day'] == '2' ? 'selected' : ''; ?>>Monday</option>
            <option value="3" <?php echo isset($_GET['day']) && $_GET['day'] == '3' ? 'selected' : ''; ?>>Tuesday</option>
            <option value="4" <?php echo isset($_GET['day']) && $_GET['day'] == '4' ? 'selected' : ''; ?>>Wednesday</option>
            <option value="5" <?php echo isset($_GET['day']) && $_GET['day'] == '5' ? 'selected' : ''; ?>>Thursday</option>
            <option value="6" <?php echo isset($_GET['day']) && $_GET['day'] == '6' ? 'selected' : ''; ?>>Friday</option>
            <option value="7" <?php echo isset($_GET['day']) && $_GET['day'] == '7' ? 'selected' : ''; ?>>Saturday</option>
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
        <tbody>
            <?php if (empty($schedules)): ?>
                <tr>
                    <td colspan="8" class="text-center">No schedules found</td>
                </tr>
            <?php else: ?>
                <?php foreach ($schedules as $schedule): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($schedule['clinic_name']); ?></td>
                        <td><?php echo getDayName($schedule['day_of_week']); ?></td>
                        <td><?php echo formatTime($schedule['start_time']); ?></td>
                        <td><?php echo formatTime($schedule['end_time']); ?></td>
                        <td><?php echo $schedule['break_start'] ? formatTime($schedule['break_start']) . ' - ' . formatTime($schedule['break_end']) : 'No break'; ?></td>
                        <td><?php echo ($schedule['duration_per_appointment'] ?? 30) . ' min'; ?></td>
                        <td><?php echo $schedule['max_appointments_per_slot']; ?></td>
                        <td>
                            <a href="?page=schedule&edit=<?php echo $schedule['id']; ?>" class="btn btn-sm btn-outline-primary me-1">
                                <span class="material-icons">edit</span>
                            </a>
                            <form action="../../api/schedules.php" method="POST" style="display: inline;">
                                <input type="hidden" name="_method" value="DELETE">
                                <input type="hidden" name="id" value="<?php echo $schedule['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="confirmDelete(event)">
                                    <span class="material-icons">delete</span>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
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
                            <div class="list-group-item text-center text-muted">
                                <span class="material-icons">hourglass_empty</span>
                                Loading available clinics...
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6>My Assigned Clinics</h6>
                        <div id="modalAssignedClinicsList" class="list-group">
                            <div class="list-group-item text-center text-muted">
                                <span class="material-icons">hourglass_empty</span>
                                Loading assigned clinics...
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-info me-2" onclick="doctorClinicManager.loadAllClinics()">
                    <span class="material-icons">refresh</span> Refresh Clinics
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Schedule Modal -->
<?php if (isset($_GET['edit'])): ?>
<div class="modal fade show" id="editScheduleModal" tabindex="-1" style="display: block;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Schedule</h5>
                <a href="?page=schedule" class="btn-close"></a>
            </div>
            <div class="modal-body">
                <form action="../../api/schedules.php" method="POST">
                    <input type="hidden" name="schedule_id" value="<?php echo $edit_schedule['id']; ?>">
                    <input type="hidden" name="_method" value="PUT">
                    <div class="mb-3">
                        <label class="form-label">Clinic</label>
                        <select class="form-select" name="clinic_id" required>
                            <option value="">Select Clinic</option>
                            <?php foreach ($clinics as $clinic): ?>
                                <option value="<?php echo $clinic['id']; ?>" <?php echo $edit_schedule['clinic_id'] == $clinic['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($clinic['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Day of Week</label>
                        <select class="form-select" name="day_of_week" required>
                            <option value="1" <?php echo $edit_schedule['day_of_week'] == 1 ? 'selected' : ''; ?>>Sunday</option>
                            <option value="2" <?php echo $edit_schedule['day_of_week'] == 2 ? 'selected' : ''; ?>>Monday</option>
                            <option value="3" <?php echo $edit_schedule['day_of_week'] == 3 ? 'selected' : ''; ?>>Tuesday</option>
                            <option value="4" <?php echo $edit_schedule['day_of_week'] == 4 ? 'selected' : ''; ?>>Wednesday</option>
                            <option value="5" <?php echo $edit_schedule['day_of_week'] == 5 ? 'selected' : ''; ?>>Thursday</option>
                            <option value="6" <?php echo $edit_schedule['day_of_week'] == 6 ? 'selected' : ''; ?>>Friday</option>
                            <option value="7" <?php echo $edit_schedule['day_of_week'] == 7 ? 'selected' : ''; ?>>Saturday</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Time</label>
                            <input type="time" class="form-control" name="start_time" value="<?php echo $edit_schedule['start_time']; ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Time</label>
                            <input type="time" class="form-control" name="end_time" value="<?php echo $edit_schedule['end_time']; ?>" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Break Start</label>
                            <input type="time" class="form-control" name="break_start" value="<?php echo $edit_schedule['break_start']; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Break End</label>
                            <input type="time" class="form-control" name="break_end" value="<?php echo $edit_schedule['break_end']; ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Duration per Appointment (minutes)</label>
                            <input type="number" class="form-control" name="duration_per_appointment" min="15" step="15" value="<?php echo $edit_schedule['duration_per_appointment'] ?? 30; ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Max Appointments per Slot</label>
                            <input type="number" class="form-control" name="max_appointments_per_slot" min="1" value="<?php echo $edit_schedule['max_appointments_per_slot']; ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="?page=schedule" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Update Schedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<div class="modal-backdrop fade show"></div>
<?php endif; ?>

<!-- Add Schedule Modal -->
<div class="modal fade" id="addScheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Schedule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form action="../../api/schedules.php" method="POST">
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
                            <option value="1">Sunday</option>
                            <option value="2">Monday</option>
                            <option value="3">Tuesday</option>
                            <option value="4">Wednesday</option>
                            <option value="5">Thursday</option>
                            <option value="6">Friday</option>
                            <option value="7">Saturday</option>
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
                            <input type="number" class="form-control" name="duration_per_appointment" min="15" step="15" value="30" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Max Appointments per Slot</label>
                            <input type="number" class="form-control" name="max_appointments_per_slot" min="1" value="1" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Schedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../../assets/js/doctor-clinics.js"></script>

<?php if (isset($_GET['success'])): ?>
<script>
    Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: '<?php echo htmlspecialchars($_GET['success']); ?>',
        timer: 3000,
        showConfirmButton: false
    });
</script>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
<script>
    Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: '<?php echo htmlspecialchars($_GET['error']); ?>'
    });
</script>
<?php endif; ?>

<script>
// Function to confirm delete
function confirmDelete(event) {
    event.preventDefault();
    const form = event.target.closest('form');
    
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            form.submit();
        }
    });
}
</script>
</body>
</html> 