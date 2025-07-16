<?php
require_once '../../config/database.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$db = new Database();
$conn = $db->getConnection();

// Debug: Check if we can connect to database
error_log("Database connection successful");

// Get staff's assigned clinics
$staff_user_id = $_SESSION['user_id'];
error_log("Staff user ID: " . $staff_user_id);

$stmt = $conn->prepare("SELECT sc.clinic_id FROM staff_clinics sc 
                       JOIN staff s ON sc.staff_id = s.id 
                       WHERE s.user_id = ?");
$stmt->execute([$staff_user_id]);
$assigned_clinics = $stmt->fetchAll(PDO::FETCH_COLUMN);
error_log("Assigned clinics: " . print_r($assigned_clinics, true));

// Debug: Check if lab_requests table exists and has data
$stmt = $conn->prepare("SELECT COUNT(*) FROM lab_requests");
$stmt->execute();
$total_lab_requests = $stmt->fetchColumn();
error_log("Total lab requests in database: " . $total_lab_requests);

// Debug: Check lab_requests table structure
$stmt = $conn->prepare("DESCRIBE lab_requests");
$stmt->execute();
$table_structure = $stmt->fetchAll(PDO::FETCH_ASSOC);
error_log("Lab requests table structure: " . print_r($table_structure, true));

// Debug: Check a few sample lab requests
$stmt = $conn->prepare("SELECT * FROM lab_requests LIMIT 3");
$stmt->execute();
$sample_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
error_log("Sample lab requests: " . print_r($sample_requests, true));

$lab_requests = [];
if (!empty($assigned_clinics)) {
    $placeholders = implode(',', array_fill(0, count($assigned_clinics), '?'));
    
    // Try the original query first (without clinic restriction)
    $sql = "SELECT lr.*, p.first_name AS patient_first, p.last_name AS patient_last, d.first_name AS doctor_first, d.last_name AS doctor_last
            FROM lab_requests lr
            JOIN patients p ON lr.patient_id = p.id
            JOIN doctors d ON lr.doctor_id = d.id
            WHERE lr.status IN ('requested', 'in_progress')
            ORDER BY lr.requested_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $lab_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Lab requests found (no clinic restriction): " . count($lab_requests));
    
    // Now try with clinic restriction
    $sql = "SELECT lr.*, p.first_name AS patient_first, p.last_name AS patient_last, d.first_name AS doctor_first, d.last_name AS doctor_last
            FROM lab_requests lr
            JOIN patients p ON lr.patient_id = p.id
            JOIN doctors d ON lr.doctor_id = d.id
            LEFT JOIN appointments a ON lr.appointment_id = a.id
            WHERE lr.status IN ('requested', 'in_progress')
            AND (a.clinic_id IN ($placeholders) OR a.clinic_id IS NULL)
            ORDER BY lr.requested_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($assigned_clinics);
    $lab_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Lab requests found (with clinic restriction): " . count($lab_requests));
}

// If still no data, show all lab requests
if (empty($lab_requests)) {
    $sql = "SELECT lr.*, p.first_name AS patient_first, p.last_name AS patient_last, d.first_name AS doctor_first, d.last_name AS doctor_last
            FROM lab_requests lr
            JOIN patients p ON lr.patient_id = p.id
            JOIN doctors d ON lr.doctor_id = d.id
            WHERE lr.status IN ('requested', 'in_progress')
            ORDER BY lr.requested_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $lab_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Final lab requests found (fallback): " . count($lab_requests));
}
?>
<div class="container mt-4">
    <h2>Lab Requests</h2>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>ID</th>
                <th>Patient</th>
                <th>Doctor</th>
                <th>Test Type</th>
                <th>Notes</th>
                <th>Status</th>
                <th>Requested At</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($lab_requests as $req): ?>
            <tr>
                <td><?= htmlspecialchars($req['id']) ?></td>
                <td><?= htmlspecialchars($req['patient_first'] . ' ' . $req['patient_last']) ?></td>
                <td><?= htmlspecialchars($req['doctor_first'] . ' ' . $req['doctor_last']) ?></td>
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
                <td>
                    <button class="btn btn-success btn-sm" onclick="openResultModal(<?= $req['id'] ?>, '<?= htmlspecialchars(addslashes($req['test_type'])) ?>', '<?= htmlspecialchars(addslashes($req['notes'])) ?>', '<?= htmlspecialchars($req['result_file']) ?>')">Enter Result</button>
                    <?php if (!empty($req['request_slip'])): ?>
                        <a href="/Medbuddy/uploads/lab_requests/<?= htmlspecialchars($req['request_slip']) ?>" target="_blank" class="btn btn-primary btn-sm ms-1">Download Lab Request Slip</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Result Modal -->
<div class="modal fade" id="resultModal" tabindex="-1" aria-labelledby="resultModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="resultModalLabel">Enter Lab Result</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="resultForm" enctype="multipart/form-data">
          <input type="hidden" id="labRequestId" name="labRequestId">
          <div class="mb-3">
            <label for="testType" class="form-label">Test Type</label>
            <input type="text" class="form-control" id="testType" name="testType" readonly>
          </div>
          <div class="mb-3">
            <label for="notes" class="form-label">Notes</label>
            <textarea class="form-control" id="notes" name="notes" readonly></textarea>
          </div>
          <div class="mb-3">
            <label for="result" class="form-label">Result (Text)</label>
            <textarea class="form-control" id="result" name="result"></textarea>
          </div>
          <div class="mb-3">
            <label for="result_file" class="form-label">Result PDF (optional)</label>
            <input type="file" class="form-control" id="result_file" name="result_file" accept="application/pdf">
            <div id="existingResultFile" class="mt-2"></div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" onclick="submitResult()">Save Result</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let currentLabRequestId = null;
function openResultModal(id, testType, notes, resultFile = null) {
    document.getElementById('labRequestId').value = id;
    document.getElementById('testType').value = testType;
    document.getElementById('notes').value = notes;
    document.getElementById('result').value = '';
    document.getElementById('result_file').value = '';
    currentLabRequestId = id;
    // Show existing PDF link if available
    const existingDiv = document.getElementById('existingResultFile');
    if (resultFile) {
        existingDiv.innerHTML = `<a href="/uploads/lab_results/${resultFile}" target="_blank">View Existing PDF</a>`;
    } else {
        existingDiv.innerHTML = '';
    }
    var modal = new bootstrap.Modal(document.getElementById('resultModal'));
    modal.show();
}
function submitResult() {
    var id = document.getElementById('labRequestId').value;
    var result = document.getElementById('result').value;
    var fileInput = document.getElementById('result_file');
    // First, upload PDF if selected
    if (fileInput.files.length > 0) {
        var formData = new FormData();
        formData.append('lab_request_id', id);
        formData.append('result_file', fileInput.files[0]);
        fetch('/Medbuddy/api/upload_lab_result.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // After PDF upload, save text result if any
                saveTextResult(id, result);
            } else {
                Swal.fire('Error', data.message || 'Failed to upload PDF.', 'error');
            }
        });
    } else {
        // Only save text result
        saveTextResult(id, result);
    }
}
function saveTextResult(id, result) {
    fetch('/Medbuddy/api/lab_requests.php', {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, result: result, status: 'completed' })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            Swal.fire('Success', 'Result saved!', 'success').then(() => location.reload());
        } else {
            Swal.fire('Error', data.message || 'Failed to save result.', 'error');
        }
    });
}
</script> 