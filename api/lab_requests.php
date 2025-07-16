<?php
session_start();
error_log('API DEBUG: method=' . $_SERVER['REQUEST_METHOD'] . ' session=' . print_r($_SESSION, true) . ' cookies=' . print_r($_COOKIE, true));
require_once '../config/database.php';
header('Content-Type: application/json');

// Ensure user is authenticated
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$db = new Database();
$conn = $db->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // List or get single lab request
        $where = [];
        $params = [];
        if (isset($_GET['id'])) {
            $where[] = 'lr.id = ?';
            $params[] = $_GET['id'];
        }
        if (isset($_GET['patient_id'])) {
            $where[] = 'lr.patient_id = ?';
            $params[] = $_GET['patient_id'];
        }
        if (isset($_GET['doctor_id'])) {
            $where[] = 'lr.doctor_id = ?';
            $params[] = $_GET['doctor_id'];
        }
        $sql = "SELECT lr.*, p.first_name as patient_first_name, p.last_name as patient_last_name, d.first_name as doctor_first_name, d.last_name as doctor_last_name FROM lab_requests lr JOIN patients p ON lr.patient_id = p.id JOIN doctors d ON lr.doctor_id = d.id";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY lr.requested_at DESC';
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'lab_requests' => $results]);
        break;
    case 'POST':
        // Create new lab request
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        error_log('DEBUG: Incoming POST data: ' . print_r($data, true));
        $required = ['patient_id', 'doctor_id', 'test_type', 'clinic_id'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                error_log("DEBUG: Missing required field: $field");
                echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
                exit();
            }
        }
        try {
            $stmt = $conn->prepare("INSERT INTO lab_requests (patient_id, doctor_id, clinic_id, test_type, notes) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['patient_id'],
                $data['doctor_id'],
                $data['clinic_id'],
                $data['test_type'],
                $data['notes'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log('DEBUG: SQL Error on INSERT: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'SQL Error: ' . $e->getMessage()]);
            exit();
        }
        // After insert, generate PDF slip
        $lab_request_id = $conn->lastInsertId();
        error_log('DEBUG: Inserted lab_request_id: ' . $lab_request_id);
        // Fetch details for the slip
        $stmt = $conn->prepare("SELECT lr.*, p.first_name AS patient_first, p.last_name AS patient_last, d.first_name AS doctor_first, d.last_name AS doctor_last FROM lab_requests lr JOIN patients p ON lr.patient_id = p.id JOIN doctors d ON lr.doctor_id = d.id WHERE lr.id = ?");
        $stmt->execute([$lab_request_id]);
        $req = $stmt->fetch(PDO::FETCH_ASSOC);
        error_log('DEBUG: Lab request details for PDF: ' . print_r($req, true));
        // Generate PDF
        $pdf_filename = 'labrequest_' . $lab_request_id . '_' . time() . '.pdf';
        $pdf_path = __DIR__ . '/../uploads/lab_requests/' . $pdf_filename;
        if (!is_dir(__DIR__ . '/../uploads/lab_requests/')) {
            mkdir(__DIR__ . '/../uploads/lab_requests/', 0777, true);
        }
        // Use FPDF for PDF generation
        require_once __DIR__ . '/../vendor/fpdf/fpdf.php';
        // Set custom page size: 120mm x 180mm (about 4.7 x 7 inches)
        $pdf = new FPDF('P', 'mm', array(120, 180));
        $pdf->AddPage();
        // MedBuddy header
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->SetTextColor(40, 75, 99);
        $pdf->Cell(0, 8, 'MedBuddy Medical Center', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->Cell(0, 5, '123 Medical Center Dr, Suite 100, City, Country', 0, 1, 'C');
        $pdf->Cell(0, 5, 'Phone: (555) 0101   |   Email: info@medbuddy.com', 0, 1, 'C');
        $pdf->Ln(1);
        $pdf->SetDrawColor(40, 75, 99);
        $pdf->SetLineWidth(0.5);
        $pdf->Line(10, $pdf->GetY(), 110, $pdf->GetY());
        $pdf->Ln(2);
        // Large Rx symbol
        $pdf->SetFont('Arial', 'B', 32);
        $pdf->SetTextColor(0,0,0);
        $pdf->SetXY(10, $pdf->GetY());
        $pdf->Cell(18, 18, 'Rx', 0, 0, 'L');
        // Patient info to the right
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetXY(30, $pdf->GetY() + 2);
        $pdf->Cell(0, 7, 'Patient: ' . $req['patient_first'] . ' ' . $req['patient_last'], 0, 1);
        $pdf->SetX(30);
        $pdf->Cell(0, 7, 'Address: ' . ($req['address'] ?? ''), 0, 1);
        $pdf->Ln(2);
        // Horizontal line
        $pdf->SetDrawColor(180,180,180);
        $pdf->SetLineWidth(0.2);
        $pdf->Line(10, $pdf->GetY(), 110, $pdf->GetY());
        $pdf->Ln(3);
        // Lab Request section
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(40, 75, 99);
        $pdf->Cell(0, 7, 'Lab Request:', 0, 1);
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(0,0,0);
        $pdf->MultiCell(0, 6, 'Test Type: ' . $req['test_type']);
        if (!empty($req['notes'])) {
            $pdf->MultiCell(0, 6, 'Notes: ' . $req['notes']);
        }
        // Signature and date lines at the bottom
        $pdf->SetY(150);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(60, 7, 'Signature: ______________________', 0, 0, 'L');
        $pdf->Cell(0, 7, 'Date: ' . date('Y-m-d', strtotime($req['requested_at'])), 0, 1, 'R');
        $pdf->Output('F', $pdf_path);
        // Update lab_requests row
        $stmt = $conn->prepare("UPDATE lab_requests SET request_slip = ? WHERE id = ?");
        $stmt->execute([$pdf_filename, $lab_request_id]);
        echo json_encode(['success' => true, 'message' => 'Lab request created']);
        break;
    case 'PATCH':
        // Update lab request (status/result)
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['id'])) {
            echo json_encode(['success' => false, 'message' => 'Missing lab request ID']);
            exit();
        }
        $fields = [];
        $params = [];
        if (isset($data['status'])) {
            $fields[] = 'status = ?';
            $params[] = $data['status'];
        }
        if (isset($data['result'])) {
            $fields[] = 'result = ?';
            $params[] = $data['result'];
            if ($data['status'] === 'completed') {
                $fields[] = 'completed_at = NOW()';
            }
        }
        if (isset($data['doctor_comment'])) {
            $fields[] = 'doctor_comment = ?';
            $params[] = $data['doctor_comment'];
        }
        if (!$fields) {
            echo json_encode(['success' => false, 'message' => 'No fields to update']);
            exit();
        }
        $params[] = $data['id'];
        $sql = 'UPDATE lab_requests SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['success' => true, 'message' => 'Lab request updated']);
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
} 