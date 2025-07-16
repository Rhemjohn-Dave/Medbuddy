<?php
require_once '../../config/database.php';
// Get user_id from session
$user_id = $_SESSION['user_id'] ?? null;
$patient_id = null;
if ($user_id) {
    $db = new Database();
    $conn = $db->getConnection();
    $stmt = $conn->prepare("SELECT id FROM patients WHERE user_id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $patient_id = $row['id'];
    }
}
if (!$patient_id) {
    echo '<div class="alert alert-danger">Not logged in as patient.</div>';
    exit;
}
// Fetch lab requests with doctor name
$stmt = $conn->prepare("SELECT lr.*, d.first_name AS doctor_first, d.last_name AS doctor_last FROM lab_requests lr JOIN doctors d ON lr.doctor_id = d.id WHERE lr.patient_id = ? ORDER BY lr.requested_at DESC");
$stmt->execute([$patient_id]);
$lab_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="container mt-4">
    <h2>My Lab Requests</h2>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Test Type</th>
                <th>Notes</th>
                <th>Status</th>
                <th>Requested At</th>
                <th>Requested By</th>
                <th>Doctor's Comment</th>
                <th>Result</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($lab_requests as $req): ?>
            <tr>
                <td><?= htmlspecialchars($req['test_type']) ?></td>
                <td><?= htmlspecialchars($req['notes']) ?></td>
                <td><span class="badge bg-info text-dark"><?= htmlspecialchars($req['status']) ?></span></td>
                <td><?= htmlspecialchars($req['requested_at']) ?></td>
                <td><?= htmlspecialchars($req['doctor_first'] . ' ' . $req['doctor_last']) ?></td>
                <td><?= !empty($req['doctor_comment']) ? nl2br(htmlspecialchars($req['doctor_comment'])) : '<span class="text-muted">No comment</span>' ?></td>
                <td>
                    <?php
                    // Remove lab request slip download for patients
                    if (!empty($req['result_file'])) {
                        echo '<a href="/Medbuddy/uploads/lab_results/' . htmlspecialchars($req['result_file']) . '" target="_blank">View PDF</a>';
                        if ($req['result']) {
                            echo '<br>';
                        }
                    }
                    echo $req['result'] ? nl2br(htmlspecialchars($req['result'])) : '<span class="text-muted">Pending</span>';
                    ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div> 