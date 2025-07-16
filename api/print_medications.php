<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/fpdf/fpdf.php';

if (!isset($_GET['patient_id']) || !is_numeric($_GET['patient_id'])) {
    die('Invalid patient ID');
}
$patient_id = (int)$_GET['patient_id'];

$db = new Database();
$conn = $db->getConnection();

// Fetch patient info
$stmt = $conn->prepare("SELECT first_name, last_name, address, date_of_birth FROM patients WHERE id = ?");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$patient) {
    die('Patient not found');
}
$patient_name = $patient['first_name'] . ' ' . $patient['last_name'];
$patient_address = $patient['address'] ?? '';
$patient_dob = $patient['date_of_birth'] ?? '';

// Date filter
$where = "WHERE patient_id = ?";
$params = [$patient_id];
if (!empty($_GET['start_date'])) {
    $where .= " AND created_at >= ?";
    $params[] = $_GET['start_date'] . ' 00:00:00';
}
if (!empty($_GET['end_date'])) {
    $where .= " AND created_at <= ?";
    $params[] = $_GET['end_date'] . ' 23:59:59';
}
$stmt = $conn->prepare("SELECT * FROM medications $where ORDER BY created_at DESC");
$stmt->execute($params);
$medications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pdf = new FPDF('P', 'mm', array(216, 279)); // A4 size
$pdf->AddPage();
// Professional MedBuddy header
$pdf->SetFont('Arial', 'B', 18);
$pdf->SetTextColor(40, 75, 99);
$pdf->Cell(0, 12, 'MedBuddy Medical Center', 0, 1, 'C');
$pdf->SetFont('Arial', '', 12);
$pdf->SetTextColor(60, 60, 60);
$pdf->Cell(0, 7, '123 Medical Center Dr, Suite 100, City, Country', 0, 1, 'C');
$pdf->Cell(0, 7, 'Phone: (555) 0101   |   Email: info@medbuddy.com', 0, 1, 'C');
$pdf->Ln(2);
$pdf->SetDrawColor(40, 75, 99);
$pdf->SetLineWidth(0.7);
$pdf->Line(15, $pdf->GetY(), 201, $pdf->GetY());
$pdf->Ln(4);
// Large Rx symbol
$pdf->SetFont('Arial', 'B', 40);
$pdf->SetTextColor(0,0,0);
$pdf->SetXY(15, $pdf->GetY());
$pdf->Cell(25, 20, 'Rx', 0, 0, 'L');
// Patient info to the right
$pdf->SetFont('Arial', '', 13);
$pdf->SetXY(40, $pdf->GetY() + 2);
$pdf->Cell(0, 9, 'Patient: ' . $patient_name, 0, 1);
$pdf->SetX(40);
$pdf->Cell(0, 9, 'Address: ' . $patient_address, 0, 1);
if ($patient_dob) {
    $pdf->SetX(40);
    $pdf->Cell(0, 9, 'Date of Birth: ' . $patient_dob, 0, 1);
}
$pdf->Ln(2);
// Horizontal line
$pdf->SetDrawColor(180,180,180);
$pdf->SetLineWidth(0.3);
$pdf->Line(15, $pdf->GetY(), 201, $pdf->GetY());
$pdf->Ln(5);
// Medications section
$pdf->SetFont('Arial', 'B', 13);
$pdf->SetTextColor(40, 75, 99);
$pdf->Cell(0, 10, 'Medications:', 0, 1);
$pdf->SetFont('Arial', '', 12);
$pdf->SetTextColor(0,0,0);
// Table header
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetFillColor(230, 240, 255);
$pdf->Cell(28, 10, 'Date', 1, 0, 'C', true);
$pdf->Cell(40, 10, 'Medication', 1, 0, 'C', true);
$pdf->Cell(25, 10, 'Dosage', 1, 0, 'C', true);
$pdf->Cell(28, 10, 'Frequency', 1, 0, 'C', true);
$pdf->Cell(40, 10, 'Duration', 1, 0, 'C', true);
$pdf->Cell(40, 10, 'Instructions', 1, 1, 'C', true);
$pdf->SetFont('Arial', '', 11);
foreach ($medications as $med) {
    $date = date('Y-m-d', strtotime($med['created_at']));
    $medication = $med['medication_name'];
    $dosage = $med['dosage'];
    $frequency = $med['frequency'];
    // Only show the duration value, not the date range
    $duration = $med['duration'] ?? '';
    $instructions = $med['notes'];
    $pdf->Cell(28, 9, $date, 1);
    $pdf->Cell(40, 9, $medication, 1);
    $pdf->Cell(25, 9, $dosage, 1);
    $pdf->Cell(28, 9, $frequency, 1);
    $pdf->Cell(40, 9, $duration, 1);
    $pdf->Cell(40, 9, $instructions, 1);
    $pdf->Ln();
}
// Signature and date lines at the bottom
$pdf->SetY(240);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(100, 10, 'Signature: ______________________', 0, 0, 'L');
$pdf->Cell(0, 10, 'Date: ' . date('Y-m-d'), 0, 1, 'R');
$pdf->Output('D', 'medications_' . $patient_id . '.pdf'); 