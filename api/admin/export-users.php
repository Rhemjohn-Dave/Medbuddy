<?php
// Check if ADMIN_ACCESS is defined
if (!defined('ADMIN_ACCESS')) {
    header("Location: ../../index.php");
    exit();
}

require_once '../../config/database.php';
require_once '../../vendor/autoload.php'; // Make sure you have TCPDF and PhpSpreadsheet installed

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Get filter parameters
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$role = isset($_GET['role']) ? $_GET['role'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$format = isset($_GET['format']) ? $_GET['format'] : 'pdf';

// Initialize database connection
$db = new Database();
$conn = $db->getConnection();

// Build query (same as in users.php)
$sql = "SELECT u.*, 
        CASE 
            WHEN u.role = 'doctor' THEN d.first_name 
            WHEN u.role = 'patient' THEN p.first_name 
            WHEN u.role = 'staff' THEN s.first_name
            ELSE NULL 
        END as first_name,
        CASE 
            WHEN u.role = 'doctor' THEN d.middle_name 
            WHEN u.role = 'patient' THEN p.middle_name 
            WHEN u.role = 'staff' THEN s.middle_name
            ELSE NULL 
        END as middle_name,
        CASE 
            WHEN u.role = 'doctor' THEN d.last_name 
            WHEN u.role = 'patient' THEN p.last_name 
            WHEN u.role = 'staff' THEN s.last_name
            ELSE NULL 
        END as last_name,

        FROM users u 
        LEFT JOIN doctors d ON u.id = d.user_id 
        LEFT JOIN patients p ON u.id = p.user_id 
        LEFT JOIN staff s ON u.id = s.user_id 
        WHERE 1=1";

$params = [];

if ($status !== 'all') {
    $sql .= " AND u.approval_status = ?";
    $params[] = $status;
}

if ($role !== 'all') {
    $sql .= " AND u.role = ?";
    $params[] = $role;
}

if ($search) {
    $sql .= " AND (u.email LIKE ? OR 
             CONCAT(COALESCE(d.first_name, p.first_name, s.first_name), ' ', 
                   COALESCE(d.middle_name, p.middle_name, s.middle_name), ' ',
                   COALESCE(d.last_name, p.last_name, s.last_name)) LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$sql .= " ORDER BY u.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($format === 'pdf') {
    // Create PDF
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('MedBuddy Admin');
    $pdf->SetTitle('Users List');

    // Set default header data
    $pdf->SetHeaderData('', 0, 'Users List', 'Generated on ' . date('Y-m-d H:i:s'));

    // Set margins
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);

    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, 15);

    // Add a page
    $pdf->AddPage();

    // Set font
    $pdf->SetFont('helvetica', '', 10);

    // Create the table content
    $html = '<table border="1" cellpadding="4">
        <thead>
            <tr style="background-color: #f8f9fa;">
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Registered</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($users as $user) {
        $name = trim(($user['first_name'] ?? '') . ' ' . ($user['middle_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        $html .= '<tr>
            <td>' . htmlspecialchars($name) . '</td>
            <td>' . htmlspecialchars($user['email']) . '</td>
            <td>' . ucfirst($user['role']) . '</td>
            <td>' . ucfirst($user['approval_status']) . '</td>
            <td>' . date('M d, Y', strtotime($user['created_at'])) . '</td>
        </tr>';
    }

    $html .= '</tbody></table>';

    // Output the table
    $pdf->writeHTML($html, true, false, true, false, '');

    // Close and output PDF document
    $pdf->Output('users.pdf', 'D');
} else {
    // Create Excel
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Set headers
    $sheet->setCellValue('A1', 'Name');
    $sheet->setCellValue('B1', 'Email');
    $sheet->setCellValue('C1', 'Role');
    $sheet->setCellValue('D1', 'Status');
    $sheet->setCellValue('E1', 'Registered');

    // Style the header row
    $headerStyle = [
        'font' => ['bold' => true],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'F8F9FA']
        ]
    ];
    $sheet->getStyle('A1:E1')->applyFromArray($headerStyle);

    // Add data
    $row = 2;
    foreach ($users as $user) {
        $name = trim(($user['first_name'] ?? '') . ' ' . ($user['middle_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        $sheet->setCellValue('A' . $row, $name);
        $sheet->setCellValue('B' . $row, $user['email']);
        $sheet->setCellValue('C' . $row, ucfirst($user['role']));
        $sheet->setCellValue('D' . $row, ucfirst($user['approval_status']));
        $sheet->setCellValue('E' . $row, date('M d, Y', strtotime($user['created_at'])));
        $row++;
    }

    // Auto-size columns
    foreach (range('A', 'E') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Create Excel file
    $writer = new Xlsx($spreadsheet);
    
    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="users.xlsx"');
    header('Cache-Control: max-age=0');

    // Save file to PHP output
    $writer->save('php://output');
} 