<?php
// Check if DOCTOR_ACCESS is defined
if (!defined('DOCTOR_ACCESS')) {
    header("Location: ../../auth/index.php");
    exit();
}

// Include database configuration
require_once '../../config/database.php';

try {
    // Initialize database connection
    $database = new Database();
    $db = $database->getConnection();
    
    // Get doctor's ID from session
    $user_id = $_SESSION['user_id'];
    
    // First, get the doctor's ID from the doctors table
    $query = "SELECT id FROM doctors WHERE user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->execute();
    $doctor = $stmt->fetch();
    $doctor_id = $doctor['id'];
    
    // Get all medical records for this doctor with patient details
    $query = "SELECT mr.*, 
                     p.first_name, p.last_name, p.contact_number,
                     CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                     a.appointment_date, a.appointment_time
              FROM medical_records mr
              JOIN patients p ON mr.patient_id = p.id
              LEFT JOIN appointments a ON mr.appointment_id = a.id
              WHERE mr.doctor_id = :doctor_id
              ORDER BY mr.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":doctor_id", $doctor_id);
    $stmt->execute();
    $medical_records = $stmt->fetchAll();
    
    // Format dates and add status colors
    foreach ($medical_records as &$record) {
        $record['created_date'] = date('M d, Y', strtotime($record['created_at']));
        $record['status_color'] = $record['diagnosis'] ? 'success' : 'warning';
        $record['status'] = $record['diagnosis'] ? 'Completed' : 'Pending';
    }
    
} catch (Exception $e) {
    // Log the error
    error_log("Medical Records Error: " . $e->getMessage());
    $medical_records = [];
}
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">Medical Records</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newRecordModal">
        <span class="material-icons align-text-bottom">add</span>
        New Record
    </button>
</div>

<!-- Search and Filter -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text">
                        <span class="material-icons">search</span>
                    </span>
                    <input type="text" class="form-control" id="searchInput" placeholder="Search patient name...">
                </div>
            </div>
            <div class="col-md-3">
                <select class="form-select" id="statusFilter">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="completed">Completed</option>
                </select>
            </div>
            <div class="col-md-3">
                <input type="date" class="form-control" id="dateFilter">
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-secondary w-100" id="resetFilters">
                    Reset Filters
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Medical Records List -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Patient Name</th>
                        <th>Date</th>
                        <th>Chief Complaint</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($medical_records)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">
                            No medical records found
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($medical_records as $record): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-circle bg-primary text-white me-2">
                                        <?php echo strtoupper(substr($record['patient_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="fw-medium"><?php echo htmlspecialchars($record['patient_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($record['contact_number']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div><?php echo htmlspecialchars($record['created_date']); ?></div>
                                <?php if ($record['appointment_date']): ?>
                                <small class="text-muted">
                                    Appointment: <?php echo date('M d, Y', strtotime($record['appointment_date'])); ?>
                                </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="text-truncate" style="max-width: 200px;">
                                    <?php echo htmlspecialchars($record['chief_complaint']); ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $record['status_color']; ?>">
                                    <?php echo $record['status']; ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-outline-primary view-record" 
                                            data-record-id="<?php echo $record['id']; ?>">
                                        <span class="material-icons">visibility</span>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-primary edit-record"
                                            data-record-id="<?php echo $record['id']; ?>">
                                        <span class="material-icons">edit</span>
                                    </button>
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

<!-- New Record Modal -->
<div class="modal fade" id="newRecordModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Medical Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="newRecordForm">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Patient</label>
                            <select class="form-select" name="patient_id" required>
                                <option value="">Select Patient</option>
                                <!-- Will be populated via AJAX -->
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Appointment</label>
                            <select class="form-select" name="appointment_id">
                                <option value="">Select Appointment (Optional)</option>
                                <!-- Will be populated via AJAX -->
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Chief Complaint</label>
                        <textarea class="form-control" name="chief_complaint" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">History of Present Illness</label>
                        <textarea class="form-control" name="history_of_illness" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Physical Examination</label>
                        <textarea class="form-control" name="physical_examination" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Diagnosis</label>
                        <textarea class="form-control" name="diagnosis" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Treatment Plan</label>
                        <textarea class="form-control" name="treatment_plan" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveRecordBtn">Save Record</button>
            </div>
        </div>
    </div>
</div>

<!-- View/Edit Record Modal -->
<div class="modal fade" id="recordModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Medical Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="recordForm">
                    <input type="hidden" name="record_id">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Patient</label>
                            <input type="text" class="form-control" name="patient_name" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date</label>
                            <input type="text" class="form-control" name="created_date" readonly>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Chief Complaint</label>
                        <textarea class="form-control" name="chief_complaint" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">History of Present Illness</label>
                        <textarea class="form-control" name="history_of_illness" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Physical Examination</label>
                        <textarea class="form-control" name="physical_examination" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Diagnosis</label>
                        <textarea class="form-control" name="diagnosis" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Treatment Plan</label>
                        <textarea class="form-control" name="treatment_plan" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="updateRecordBtn">Update Record</button>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-circle {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 500;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Search functionality
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const dateFilter = document.getElementById('dateFilter');
    const resetFilters = document.getElementById('resetFilters');
    
    function filterRecords() {
        const searchTerm = searchInput.value.toLowerCase();
        const statusValue = statusFilter.value.toLowerCase();
        const dateValue = dateFilter.value;
        
        const rows = document.querySelectorAll('tbody tr');
        rows.forEach(row => {
            const patientName = row.querySelector('td:first-child').textContent.toLowerCase();
            const status = row.querySelector('.badge').textContent.toLowerCase();
            const date = row.querySelector('td:nth-child(2)').textContent;
            
            const matchesSearch = patientName.includes(searchTerm);
            const matchesStatus = !statusValue || status === statusValue;
            const matchesDate = !dateValue || date.includes(dateValue);
            
            row.style.display = matchesSearch && matchesStatus && matchesDate ? '' : 'none';
        });
    }
    
    searchInput.addEventListener('input', filterRecords);
    statusFilter.addEventListener('change', filterRecords);
    dateFilter.addEventListener('change', filterRecords);
    
    resetFilters.addEventListener('click', function() {
        searchInput.value = '';
        statusFilter.value = '';
        dateFilter.value = '';
        filterRecords();
    });
    
    // Load patients for new record
    fetch('../../api/patients.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.querySelector('select[name="patient_id"]');
                data.patients.forEach(patient => {
                    const option = document.createElement('option');
                    option.value = patient.id;
                    option.textContent = `${patient.first_name} ${patient.last_name}`;
                    select.appendChild(option);
                });
            }
        })
        .catch(error => console.error('Error loading patients:', error));
    
    // Load appointments for new record
    fetch('../../api/appointments.php?doctor_id=<?php echo $doctor_id; ?>')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.querySelector('select[name="appointment_id"]');
                data.appointments.forEach(appointment => {
                    const option = document.createElement('option');
                    option.value = appointment.id;
                    option.textContent = `${appointment.patient_name} - ${appointment.appointment_date} ${appointment.appointment_time}`;
                    select.appendChild(option);
                });
            }
        })
        .catch(error => console.error('Error loading appointments:', error));
    
    // Save new record
    document.getElementById('saveRecordBtn').addEventListener('click', function() {
        const form = document.getElementById('newRecordForm');
        const formData = new FormData(form);
        formData.append('doctor_id', '<?php echo $doctor_id; ?>');
        
        fetch('../../api/medical-records.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error saving record: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error saving record. Please try again.');
        });
    });
    
    // View/Edit record
    document.querySelectorAll('.view-record, .edit-record').forEach(button => {
        button.addEventListener('click', function() {
            const recordId = this.dataset.recordId;
            const isEdit = this.classList.contains('edit-record');
            
            fetch(`../../api/medical-records.php?id=${recordId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const record = data.record;
                        const form = document.getElementById('recordForm');
                        
                        form.querySelector('[name="record_id"]').value = record.id;
                        form.querySelector('[name="patient_name"]').value = record.patient_name;
                        form.querySelector('[name="created_date"]').value = record.created_date;
                        form.querySelector('[name="chief_complaint"]').value = record.chief_complaint;
                        form.querySelector('[name="history_of_illness"]').value = record.history_of_illness;
                        form.querySelector('[name="physical_examination"]').value = record.physical_examination;
                        form.querySelector('[name="diagnosis"]').value = record.diagnosis;
                        form.querySelector('[name="treatment_plan"]').value = record.treatment_plan;
                        
                        // Enable/disable form fields based on view/edit mode
                        const inputs = form.querySelectorAll('input, textarea');
                        inputs.forEach(input => {
                            input.readOnly = !isEdit;
                        });
                        
                        document.getElementById('updateRecordBtn').style.display = isEdit ? 'block' : 'none';
                        
                        const modal = new bootstrap.Modal(document.getElementById('recordModal'));
                        modal.show();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading record. Please try again.');
                });
        });
    });
    
    // Update record
    document.getElementById('updateRecordBtn').addEventListener('click', function() {
        const form = document.getElementById('recordForm');
        const formData = new FormData(form);
        formData.append('doctor_id', '<?php echo $doctor_id; ?>');
        
        fetch('../../api/medical-records.php', {
            method: 'PUT',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error updating record: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error updating record. Please try again.');
        });
    });
});
</script> 