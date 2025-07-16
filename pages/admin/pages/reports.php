<?php
// Check if this file is being included
if (!defined('ADMIN_ACCESS')) {
    header("Location: ../../index.php");
    exit();
}

require_once '../../config/database.php';
$db = new Database();
$conn = $db->getConnection();

// Handle clinic form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
    try {
        // Validate and sanitize input
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
        $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
        $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);

        // Validate required fields
        if (empty($name) || empty($address)) {
            throw new Exception("Please fill in all required fields.");
        }

        // Validate email format if provided
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please enter a valid email address.");
        }

        // Validate status
        if (!empty($status) && !in_array($status, ['active', 'inactive'])) {
            throw new Exception("Invalid status value.");
        }

        // Insert clinic into database
        $stmt = $conn->prepare("
            INSERT INTO clinics (name, address, phone, email, status, created_at)
            VALUES (:name, :address, :phone, :email, :status, NOW())
        ");

        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':status', $status);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Clinic added successfully!";
        } else {
            throw new Exception("Error adding clinic to database.");
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
}

// Handle specialization form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['specialization_name'])) {
    try {
        // Validate and sanitize input
        $name = filter_input(INPUT_POST, 'specialization_name', FILTER_SANITIZE_STRING);
        $description = filter_input(INPUT_POST, 'specialization_description', FILTER_SANITIZE_STRING);

        // Validate required fields
        if (empty($name)) {
            throw new Exception("Please enter the specialization name.");
        }

        // Insert specialization into database
        $stmt = $conn->prepare("
            INSERT INTO specializations (name, description, created_at)
            VALUES (:name, :description, NOW())
        ");

        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':description', $description);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Specialization added successfully!";
        } else {
            throw new Exception("Error adding specialization to database.");
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
}

// Define the API path
$api_path = dirname($_SERVER['PHP_SELF']) . '/../api/add_clinic.php';

// Initialize variables to prevent undefined warnings
$role_stats = [];
$status_stats = [];
$doctor_activity = [];
$clinic_metrics = [];
$appointment_trends = [];
$recent_registrations = [];
$monthly_trends = [];

// Handle date filter for clinic performance
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-6 months'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Handle PDF generation
if (isset($_GET['generate_pdf'])) {
    require_once '../../vendor/autoload.php';
    $action = isset($_GET['action']) ? $_GET['action'] : 'view';
    if ($_GET['generate_pdf'] === 'clinic_performance') {
        generateClinicPerformancePDF($conn, $start_date, $end_date, $action);
        exit();
    } elseif ($_GET['generate_pdf'] === 'doctor_performance') {
        generateDoctorPerformancePDF($conn, $start_date, $end_date, $action);
        exit();
    } elseif ($_GET['generate_pdf'] === 'single_doctor_performance' && isset($_GET['doctor_id'])) {
        $doctor_id = intval($_GET['doctor_id']);
        generateSingleDoctorPerformancePDF($conn, $doctor_id, $start_date, $end_date, $action);
        exit();
    } elseif ($_GET['generate_pdf'] === 'patients_report') {
        $filter_doctor = isset($_GET['filter_doctor']) ? intval($_GET['filter_doctor']) : '';
        $filter_diagnosis = isset($_GET['filter_diagnosis']) ? trim($_GET['filter_diagnosis']) : '';
        $filter_start_date = isset($_GET['filter_start_date']) ? $_GET['filter_start_date'] : '';
        $filter_end_date = isset($_GET['filter_end_date']) ? $_GET['filter_end_date'] : '';
        generatePatientsReportPDF($conn, $filter_doctor, $filter_diagnosis, $filter_start_date, $filter_end_date, $action);
        exit();
    }
}

// Get statistics
try {
    // Total users by role
    $role_stats = $conn->query("
        SELECT role, COUNT(*) as count 
        FROM users 
        WHERE role != 'admin' 
        GROUP BY role
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Approval status statistics
    $status_stats = $conn->query("
        SELECT approval_status, COUNT(*) as count 
        FROM users 
        WHERE role != 'admin' 
        GROUP BY approval_status
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Doctor activity by specialization
    $doctor_activity = $conn->query("
        SELECT s.name as specialization, COUNT(DISTINCT d.id) as doctor_count,
               COUNT(DISTINCT a.id) as appointment_count,
               COUNT(DISTINCT mr.id) as consultation_count
        FROM specializations s
        LEFT JOIN doctors d ON s.id = d.specialization_id
        LEFT JOIN appointments a ON d.id = a.doctor_id
        LEFT JOIN medical_records mr ON d.id = mr.doctor_id
        GROUP BY s.id, s.name
        ORDER BY doctor_count DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Clinic performance metrics with date filter
    $stmt = $conn->prepare("
        SELECT c.name as clinic_name,
               COUNT(DISTINCT a.id) as total_appointments,
               COUNT(DISTINCT CASE WHEN a.status = 'completed' THEN a.id END) as completed_appointments,
               COUNT(DISTINCT CASE WHEN a.status = 'cancelled' THEN a.id END) as cancelled_appointments,
               COUNT(DISTINCT CASE WHEN a.status = 'no-show' THEN a.id END) as no_show_appointments,
               COUNT(DISTINCT dc.doctor_id) as total_doctors,
               CASE 
                   WHEN COUNT(DISTINCT a.id) > 0 
                   THEN ROUND((COUNT(DISTINCT CASE WHEN a.status = 'completed' THEN a.id END) * 100.0 / COUNT(DISTINCT a.id)), 2)
                   ELSE 0 
               END as completion_rate,
               CASE 
                   WHEN COUNT(DISTINCT a.id) > 0 
                   THEN ROUND((COUNT(DISTINCT CASE WHEN a.status = 'cancelled' THEN a.id END) * 100.0 / COUNT(DISTINCT a.id)), 2)
                   ELSE 0 
               END as cancellation_rate,
               CASE 
                   WHEN COUNT(DISTINCT a.id) > 0 
                   THEN ROUND((COUNT(DISTINCT CASE WHEN a.status = 'no-show' THEN a.id END) * 100.0 / COUNT(DISTINCT a.id)), 2)
                   ELSE 0 
               END as no_show_rate
        FROM clinics c
        LEFT JOIN appointments a ON c.id = a.clinic_id AND a.date BETWEEN :start_date AND :end_date
        LEFT JOIN doctor_clinics dc ON c.id = dc.clinic_id
        WHERE c.status = 'active'
        GROUP BY c.id, c.name
        ORDER BY total_appointments DESC
    ");
    
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    $stmt->execute();
    $clinic_metrics = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Appointment trends (last 6 months)
    $appointment_trends = $conn->query("
        SELECT 
            DATE_FORMAT(date, '%Y-%m') as month,
            COUNT(*) as total_appointments,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_appointments,
            COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_appointments
        FROM appointments 
        WHERE date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(date, '%Y-%m')
        ORDER BY month ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Recent registrations (last 7 days)
    $recent_registrations = $conn->query("
        SELECT u.*, 
            CASE 
                WHEN u.role = 'doctor' THEN d.first_name 
                WHEN u.role = 'patient' THEN p.first_name 
                WHEN u.role = 'staff' THEN s.first_name
                ELSE NULL 
            END as first_name,
            CASE 
                WHEN u.role = 'doctor' THEN d.last_name 
                WHEN u.role = 'patient' THEN p.last_name 
                WHEN u.role = 'staff' THEN s.last_name
                ELSE NULL 
            END as last_name
        FROM users u 
        LEFT JOIN doctors d ON u.id = d.user_id 
        LEFT JOIN patients p ON u.id = p.user_id 
        LEFT JOIN staff s ON u.id = s.user_id 
        WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        AND u.role != 'admin'
        ORDER BY u.created_at DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Monthly registration trends
    $monthly_trends = $conn->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as count
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        AND role != 'admin'
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Doctor overall performance (all doctors combined)
    $stmt = $conn->prepare("
        SELECT
            COUNT(*) AS total_appointments,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) AS completed_appointments,
            COUNT(CASE WHEN status = 'cancelled' THEN 1 END) AS cancelled_appointments,
            COUNT(CASE WHEN status = 'no-show' THEN 1 END) AS no_show_appointments,
            ROUND(
                CASE WHEN COUNT(*) > 0
                    THEN (COUNT(CASE WHEN status = 'completed' THEN 1 END) * 100.0 / COUNT(*))
                    ELSE 0 END, 2
            ) AS completion_rate
        FROM appointments
        WHERE date BETWEEN :start_date AND :end_date
    ");
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    $stmt->execute();
    $doctor_overall_performance = $stmt->fetch(PDO::FETCH_ASSOC);

    // Per-doctor performance
    $stmt = $conn->prepare("
        SELECT
            d.id AS doctor_id,
            CONCAT(d.first_name, ' ', d.last_name) AS doctor_name,
            s.name AS specialization,
            COUNT(a.id) AS total_appointments,
            COUNT(CASE WHEN a.status = 'completed' THEN 1 END) AS completed_appointments,
            COUNT(CASE WHEN a.status = 'cancelled' THEN 1 END) AS cancelled_appointments,
            COUNT(CASE WHEN a.status = 'no-show' THEN 1 END) AS no_show_appointments,
            ROUND(
                CASE WHEN COUNT(a.id) > 0
                    THEN (COUNT(CASE WHEN a.status = 'completed' THEN 1 END) * 100.0 / COUNT(a.id))
                    ELSE 0 END, 2
            ) AS completion_rate
        FROM doctors d
        LEFT JOIN specializations s ON d.specialization_id = s.id
        LEFT JOIN appointments a ON d.id = a.doctor_id AND a.date BETWEEN :start_date AND :end_date
        GROUP BY d.id, doctor_name, s.name
        ORDER BY doctor_name
    ");
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    $stmt->execute();
    $doctor_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Diagnosis statistics for pie chart
    $diagnosis_stats = $conn->query("
        SELECT 
            CASE 
                WHEN dg.diagnosis IS NOT NULL AND dg.diagnosis != '' THEN 
                    CASE 
                        WHEN dg.diagnosis LIKE '%(%' THEN 
                            SUBSTRING_INDEX(dg.diagnosis, ' (', 1)
                        ELSE 
                            dg.diagnosis
                    END
                ELSE 'No Diagnosis'
            END as diagnosis_type,
            COUNT(DISTINCT p.id) as patient_count
        FROM patients p
        LEFT JOIN diagnoses dg ON dg.id = (
            SELECT d2.id FROM diagnoses d2
            JOIN medical_records mr2 ON d2.medical_record_id = mr2.id
            WHERE mr2.patient_id = p.id
            ORDER BY d2.created_at DESC, d2.id DESC
            LIMIT 1
        )
        GROUP BY 
            CASE 
                WHEN dg.diagnosis IS NOT NULL AND dg.diagnosis != '' THEN 
                    CASE 
                        WHEN dg.diagnosis LIKE '%(%' THEN 
                            SUBSTRING_INDEX(dg.diagnosis, ' (', 1)
                        ELSE 
                            dg.diagnosis
                    END
                ELSE 'No Diagnosis'
            END
        ORDER BY patient_count DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Error fetching report data: " . $e->getMessage());
}

// Patients Report Filters
$filter_doctor = isset($_GET['filter_doctor']) ? intval($_GET['filter_doctor']) : '';
$filter_diagnosis = isset($_GET['filter_diagnosis']) ? trim($_GET['filter_diagnosis']) : '';
$filter_start_date = isset($_GET['filter_start_date']) ? $_GET['filter_start_date'] : '';
$filter_end_date = isset($_GET['filter_end_date']) ? $_GET['filter_end_date'] : '';

// Fetch all doctors for dropdown
$all_doctors = $conn->query("SELECT d.id, CONCAT(d.first_name, ' ', d.last_name) AS name FROM doctors d ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
// Fetch all diagnoses for dropdown (distinct diagnosis from diagnoses table)
$all_diagnoses = $conn->query("SELECT DISTINCT diagnosis FROM diagnoses ORDER BY diagnosis")->fetchAll(PDO::FETCH_COLUMN);

// Build filtered patients query
$where = [];
$params = [];
if ($filter_doctor) {
    $where[] = 'a.doctor_id = :filter_doctor';
    $params[':filter_doctor'] = $filter_doctor;
}
if ($filter_diagnosis) {
    // Use extractDiagnosisAndICD to filter by primary diagnosis or ICD-10 code
    $where[] = "(dg.diagnosis LIKE :filter_diagnosis_primary OR dg.diagnosis LIKE :filter_diagnosis_icd)";
    // Try to extract primary/icd from filter value
    list($primary, $icd) = extractDiagnosisAndICD($filter_diagnosis);
    $params[':filter_diagnosis_primary'] = '%' . $primary . '%';
    $params[':filter_diagnosis_icd'] = $icd ? ('%ICD-10: ' . $icd . '%') : '%';
}
if ($filter_start_date) {
    $where[] = 'u.created_at >= :filter_start_date';
    $params[':filter_start_date'] = $filter_start_date;
}
if ($filter_end_date) {
    $where[] = 'u.created_at <= :filter_end_date';
    $params[':filter_end_date'] = $filter_end_date;
}
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$filtered_patients = [];
// Always run the query, with or without filters
$sql = "
    SELECT p.id, CONCAT(p.first_name, ' ', p.last_name) AS patient_name, p.gender, p.date_of_birth, p.contact_number, u.created_at,
           d.id AS doctor_id, CONCAT(d.first_name, ' ', d.last_name) AS doctor_name,
           dg.diagnosis
    FROM patients p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN appointments a ON p.id = a.patient_id
    LEFT JOIN doctors d ON a.doctor_id = d.id
    LEFT JOIN diagnoses dg ON dg.id = (
        SELECT d2.id FROM diagnoses d2
        JOIN medical_records mr2 ON d2.medical_record_id = mr2.id
        WHERE mr2.patient_id = p.id
        ORDER BY d2.created_at DESC, d2.id DESC
        LIMIT 1
    )
    $where_sql
    GROUP BY p.id, doctor_id, dg.diagnosis
    ORDER BY patient_name
";
$stmt = $conn->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->execute();
$filtered_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

function extractDiagnosisAndICD($diagnosis) {
    $primary = '';
    $icd = '';
    if (preg_match('/^([^(]+)\s*\(ICD-10:\s*([^\)]+)\)/', $diagnosis, $matches)) {
        $primary = trim($matches[1]);
        $icd = trim($matches[2]);
    } else {
        $primary = $diagnosis;
    }
    return [$primary, $icd];
}

function generatePatientsReportPDF($conn, $filter_doctor, $filter_diagnosis, $filter_start_date, $filter_end_date, $action = 'view') {
    require_once '../../vendor/tecnickcom/tcpdf/tcpdf.php';
    // Build filtered patients query (same as UI, but repeat here for PDF)
    $where = [];
    $params = [];
    if ($filter_doctor) {
        $where[] = 'a.doctor_id = :filter_doctor';
        $params[':filter_doctor'] = $filter_doctor;
    }
    if ($filter_diagnosis) {
        list($primary, $icd) = extractDiagnosisAndICD($filter_diagnosis);
        $where[] = "(dg.diagnosis LIKE :filter_diagnosis_primary OR dg.diagnosis LIKE :filter_diagnosis_icd)";
        $params[':filter_diagnosis_primary'] = '%' . $primary . '%';
        $params[':filter_diagnosis_icd'] = $icd ? ('%ICD-10: ' . $icd . '%') : '%';
    }
    if ($filter_start_date) {
        $where[] = 'u.created_at >= :filter_start_date';
        $params[':filter_start_date'] = $filter_start_date;
    }
    if ($filter_end_date) {
        $where[] = 'u.created_at <= :filter_end_date';
        $params[':filter_end_date'] = $filter_end_date;
    }
    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "
        SELECT p.id, CONCAT(p.first_name, ' ', p.last_name) AS patient_name, p.gender, p.date_of_birth, p.contact_number, u.created_at,
               d.id AS doctor_id, CONCAT(d.first_name, ' ', d.last_name) AS doctor_name,
               dg.diagnosis
        FROM patients p
        JOIN users u ON p.user_id = u.id
        LEFT JOIN appointments a ON p.id = a.patient_id
        LEFT JOIN doctors d ON a.doctor_id = d.id
        LEFT JOIN diagnoses dg ON dg.id = (
            SELECT d2.id FROM diagnoses d2
            JOIN medical_records mr2 ON d2.medical_record_id = mr2.id
            WHERE mr2.patient_id = p.id
            ORDER BY d2.created_at DESC, d2.id DESC
            LIMIT 1
        )
        $where_sql
        GROUP BY p.id, doctor_id, dg.diagnosis
        ORDER BY patient_name
    ";
    $stmt = $conn->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->execute();
    $filtered_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create PDF using TCPDF with LETTER (short bond paper) size
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, 'LETTER', true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator('Medbuddy Healthcare System');
    $pdf->SetAuthor('Medbuddy Admin');
    $pdf->SetTitle('Patients Report');
    $pdf->SetSubject('Patient Information Report');

    // Set default header data
    $pdf->SetHeaderData('', 0, 'Medbuddy Healthcare System', 'Patient Information Report', array(0,0,0), array(0,0,0));
    $pdf->setFooterData(array(0,0,0), array(0,0,0));

    // Set header and footer fonts
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

    // Set default monospaced font
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

    // Set margins
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

    // Set image scale factor
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

    // Add a page
    $pdf->AddPage();

    // Set font
    $pdf->SetFont('helvetica', '', 12);

    // Report Header (centered)
    $pdf->SetFont('helvetica', 'B', 18);
    $pdf->Cell(0, 15, 'PATIENT INFORMATION REPORT', 0, 1, 'C');
    $pdf->Ln(5);

    // Report Details (centered)
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Generated on: ' . date('F d, Y \a\t g:i A'), 0, 1, 'C');
    $pdf->Ln(3);

    // Filter Summary
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, 'FILTER SUMMARY:', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    
    // Get filter details for display
    $filter_summary = [];
    if ($filter_doctor) {
        $doctor_stmt = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) AS name FROM doctors WHERE id = ?");
        $doctor_stmt->execute([$filter_doctor]);
        $doctor_name = $doctor_stmt->fetchColumn();
        $filter_summary[] = "Doctor: " . $doctor_name;
    } else {
        $filter_summary[] = "Doctor: All Doctors";
    }
    
    if ($filter_diagnosis) {
        $filter_summary[] = "Diagnosis: " . $filter_diagnosis;
    } else {
        $filter_summary[] = "Diagnosis: All Diagnoses";
    }
    
    if ($filter_start_date && $filter_end_date) {
        $filter_summary[] = "Date Range: " . date('M d, Y', strtotime($filter_start_date)) . " to " . date('M d, Y', strtotime($filter_end_date));
    } elseif ($filter_start_date) {
        $filter_summary[] = "From Date: " . date('M d, Y', strtotime($filter_start_date));
    } elseif ($filter_end_date) {
        $filter_summary[] = "To Date: " . date('M d, Y', strtotime($filter_end_date));
    } else {
        $filter_summary[] = "Date Range: All Dates";
    }
    
    foreach ($filter_summary as $summary) {
        $pdf->Cell(0, 6, $summary, 0, 1, 'L');
    }
    
    $pdf->Ln(5);

    // Results Summary
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, 'RESULTS SUMMARY:', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, 'Total Patients Found: ' . count($filtered_patients), 0, 1, 'L');
    $pdf->Ln(5);

    // Table Header
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(240, 240, 240);
    $headers = array('Patient Name', 'Gender', 'Age', 'Contact', 'Doctor', 'Diagnosis', 'Reg. Date');
    $widths = array(35, 15, 12, 25, 30, 40, 25);
    $pdf->SetX(($pdf->getPageWidth() - array_sum($widths)) / 2); // Center the table
    for($i = 0; $i < count($headers); $i++) {
        $pdf->Cell($widths[$i], 10, $headers[$i], 1, 0, 'C', true);
    }
    $pdf->Ln();

    // Table data
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetFillColor(255, 255, 255);
    foreach($filtered_patients as $pat) {
        // Calculate age
        $age = '';
        if (!empty($pat['date_of_birth'])) {
            $dob = new DateTime($pat['date_of_birth']);
            $now = new DateTime();
            $age = $now->diff($dob)->y;
        }
        
        // Extract diagnosis
        list($primary, $icd) = extractDiagnosisAndICD($pat['diagnosis']);
        $diagnosis_display = $primary;
        if ($icd) $diagnosis_display .= "\nICD-10: " . $icd;
        
        // Handle null doctor name
        $doctor_name = $pat['doctor_name'] ?: 'Not Assigned';
        
        $pdf->SetX(($pdf->getPageWidth() - array_sum($widths)) / 2); // Center the table
        $pdf->Cell($widths[0], 8, $pat['patient_name'], 1, 0, 'L', true);
        $pdf->Cell($widths[1], 8, ucfirst($pat['gender']), 1, 0, 'C', true);
        $pdf->Cell($widths[2], 8, $age, 1, 0, 'C', true);
        $pdf->Cell($widths[3], 8, $pat['contact_number'], 1, 0, 'C', true);
        $pdf->Cell($widths[4], 8, $doctor_name, 1, 0, 'L', true);
        
        // MultiCell for diagnosis to handle line breaks
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $pdf->MultiCell($widths[5], 8, $diagnosis_display, 1, 'L', true, 0, $x, $y, true, 0, false, true, 8, 'M');
        
        $pdf->Cell($widths[6], 8, date('M d, Y', strtotime($pat['created_at'])), 1, 1, 'C', true);
    }

    // Summary section
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'REPORT SUMMARY', 0, 1, 'C');
    $pdf->Ln(3);

    // Calculate statistics
    $total_patients = count($filtered_patients);
    $male_count = 0;
    $female_count = 0;
    $with_doctor = 0;
    $without_doctor = 0;
    $with_diagnosis = 0;
    $without_diagnosis = 0;

    foreach($filtered_patients as $pat) {
        if (strtolower($pat['gender']) === 'male') $male_count++;
        if (strtolower($pat['gender']) === 'female') $female_count++;
        if ($pat['doctor_name']) $with_doctor++;
        else $without_doctor++;
        if ($pat['diagnosis']) $with_diagnosis++;
        else $without_diagnosis++;
    }

    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, 'Total Patients: ' . $total_patients, 0, 1, 'C');
    $pdf->Cell(0, 6, 'Male: ' . $male_count . ' | Female: ' . $female_count, 0, 1, 'C');
    $pdf->Cell(0, 6, 'With Doctor: ' . $with_doctor . ' | Without Doctor: ' . $without_doctor, 0, 1, 'C');
    $pdf->Cell(0, 6, 'With Diagnosis: ' . $with_diagnosis . ' | Without Diagnosis: ' . $without_diagnosis, 0, 1, 'C');

    // Output PDF based on action
    $filename = 'patients_report_' . date('Y-m-d_H-i-s') . '.pdf';
    if ($action === 'download') {
        $pdf->Output($filename, 'D'); // Download
    } else {
        $pdf->Output($filename, 'I'); // View in browser
    }
    exit();
}
?>

<!-- Add custom CSS for admin reports -->
<style>
.reports-container {
    padding: 20px;
}

.stat-card {
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.chart-container {
    position: relative;
    margin: auto;
    height: 300px;
}

.material-icons {
    font-size: 24px;
}

.table th {
    background-color: #f8f9fa;
}

.badge {
    font-size: 0.85em;
    padding: 0.5em 0.75em;
}

.btn-group .btn {
    display: flex;
    align-items: center;
    gap: 5px;
}

.list-group-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 20px;
}

.list-group-item i {
    font-size: 20px;
}

@media print {
    .btn-group, .no-print {
        display: none !important;
    }
    .card {
        break-inside: avoid;
    }
    .container-fluid {
        width: 100%;
        padding: 0;
        margin: 0;
    }
}

.nav-tabs .nav-link {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
}

.nav-tabs .material-icons {
    font-size: 1.25rem;
}

.form-label {
    font-weight: 500;
}

.text-danger {
    font-size: 0.875em;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

/* Date filter styling */
.date-filter-form {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border: 1px solid #dee2e6;
}

.date-filter-form .form-control {
    border: 1px solid #ced4da;
    border-radius: 4px;
}

.date-filter-form .form-control:focus {
    border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

/* PDF download button styling */
.btn-success {
    background-color: #28a745;
    border-color: #28a745;
}

.btn-success:hover {
    background-color: #218838;
    border-color: #1e7e34;
}

/* Alert styling */
.alert-info {
    background-color: #d1ecf1;
    border-color: #bee5eb;
    color: #0c5460;
}

/* Table responsive improvements */
.table-responsive {
    border-radius: 8px;
    overflow: hidden;
}

.table th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
}

/* Badge improvements */
.badge {
    font-size: 0.85em;
    padding: 0.5em 0.75em;
    border-radius: 6px;
}

/* PDF Modal styling */
.modal-xl {
    max-width: 95%;
}

#pdfPreviewModal .modal-body {
    padding: 20px;
    min-height: 400px;
}

#pdfIframe {
    background: #f8f9fa;
}

/* Loading spinner styling */
.spinner-border {
    width: 3rem;
    height: 3rem;
}

/* Button spacing in modal footer */
.modal-footer .btn {
    margin-left: 5px;
}

/* PDF preview content */
#pdfPreviewContent {
    background: white;
    border-radius: 8px;
    overflow: hidden;
}
</style>

<div class="container-fluid reports-container">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">System Analytics & Reports</h4>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-primary" onclick="printReports()">
                <i class="material-icons align-middle me-1">print</i>
                Print
            </button>
            <button type="button" class="btn btn-outline-success" onclick="downloadReports()">
                <i class="material-icons align-middle me-1">download</i>
                Download
            </button>
            <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#addEntityModal">
                <i class="material-icons align-middle me-1">add</i>
                Add New
            </button>
        </div>
    </div>

    <!-- Reports Tabs -->
    <ul class="nav nav-tabs mb-4" id="reportsTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="analytics-tab" data-bs-toggle="tab" data-bs-target="#analytics-tab-pane" type="button" role="tab" aria-controls="analytics-tab-pane" aria-selected="true">
                <i class="material-icons align-middle me-1">insights</i> System Analytics
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="patients-tab" data-bs-toggle="tab" data-bs-target="#patients-report-tab" type="button" role="tab" aria-controls="patients-report-tab" aria-selected="false">
                <i class="material-icons align-middle me-1">groups</i> Patients Report
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="doctors-tab" data-bs-toggle="tab" data-bs-target="#doctors-report-tab" type="button" role="tab" aria-controls="doctors-report-tab" aria-selected="false">
                <i class="material-icons align-middle me-1">medical_services</i> Doctor Analytics
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="clinics-tab" data-bs-toggle="tab" data-bs-target="#clinics-report-tab" type="button" role="tab" aria-controls="clinics-report-tab" aria-selected="false">
                <i class="material-icons align-middle me-1">local_hospital</i> Clinic Performance
            </button>
        </li>
    </ul>
    <div class="tab-content" id="reportsTabContent">
        <!-- System Analytics Tab (default) -->
        <div class="tab-pane fade" id="analytics-tab-pane" role="tabpanel" aria-labelledby="analytics-tab">
            <!-- Statistics Cards -->
            <div class="row g-4 mb-4">
                <?php foreach ($role_stats as $stat): ?>
                    <div class="col-md-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-2 text-muted"><?php echo ucfirst($stat['role']); ?>s</h6>
                                        <h2 class="card-title mb-0"><?php echo $stat['count']; ?></h2>
                                    </div>
                                    <div class="bg-<?php 
                                        echo match($stat['role']) {
                                            'doctor' => 'primary',
                                            'patient' => 'success',
                                            'staff' => 'warning',
                                            default => 'secondary'
                                        };
                                    ?> bg-opacity-10 p-3 rounded">
                                        <i class="material-icons text-<?php 
                                            echo match($stat['role']) {
                                                'doctor' => 'primary',
                                                'patient' => 'success',
                                                'staff' => 'warning',
                                                default => 'secondary'
                                            };
                                        ?>">
                                            <?php 
                                            echo match($stat['role']) {
                                                'doctor' => 'medical_services',
                                                'patient' => 'person',
                                                'staff' => 'badge',
                                                default => 'people'
                                            };
                                            ?>
                                        </i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <!-- Main Analytics Section (Graphs) -->
            <div class="row mb-4">
                <!-- Doctor Activity by Specialization -->
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Doctor Activity by Specialization</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="doctorActivityChart"></canvas>
                        </div>
                    </div>
                </div>
                <!-- Clinic Performance Chart -->
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Clinic Performance Chart</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="clinicPerformanceChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Diagnosis Statistics Pie Chart -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Patient Diagnosis Status</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="diagnosisPieChart"></canvas>
                        </div>
                    </div>
                </div>
                <!-- Empty column for balance -->
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Diagnosis Statistics</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php 
                                $total_patients = 0;
                                $diagnosis_data = [];
                                foreach ($diagnosis_stats as $stat) {
                                    $total_patients += $stat['patient_count'];
                                    $diagnosis_data[] = $stat;
                                }
                                
                                // Show top 4 diagnosis types
                                $top_diagnoses = array_slice($diagnosis_data, 0, 4);
                                $colors = ['success', 'primary', 'warning', 'info', 'secondary', 'danger'];
                                ?>
                                
                                <?php foreach ($top_diagnoses as $index => $diagnosis): ?>
                                    <?php 
                                    $percentage = $total_patients > 0 ? round(($diagnosis['patient_count'] / $total_patients) * 100, 1) : 0;
                                    $color = $colors[$index % count($colors)];
                                    ?>
                                    <div class="col-6 mb-3">
                                        <div class="text-center">
                                            <div class="bg-<?php echo $color; ?> bg-opacity-10 p-3 rounded mb-2">
                                                <i class="material-icons text-<?php echo $color; ?>" style="font-size: 1.5rem;">medical_services</i>
                                            </div>
                                            <h5 class="text-<?php echo $color; ?> mb-1"><?php echo $diagnosis['patient_count']; ?></h5>
                                            <p class="text-muted mb-0 small"><?php echo htmlspecialchars($diagnosis['diagnosis_type']); ?></p>
                                            <small class="text-muted"><?php echo $percentage; ?>%</small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <?php if (count($diagnosis_data) > 4): ?>
                                <hr>
                                <div class="text-center">
                                    <small class="text-muted">
                                        +<?php echo count($diagnosis_data) - 4; ?> more diagnosis types
                                    </small>
                                </div>
                            <?php endif; ?>
                            
                            <hr>
                            <div class="text-center">
                                <h5 class="text-primary">Total Patients: <?php echo $total_patients; ?></h5>
                                <p class="text-muted mb-0">Top diagnosis types by patient count</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Appointment Trends -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Appointment Trends (Last 6 Months)</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="appointmentTrendsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Recent Registrations -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Recent Registrations (Last 7 Days)</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Registered</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_registrations as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo match($user['role']) {
                                                    'doctor' => 'primary',
                                                    'patient' => 'success',
                                                    'staff' => 'warning',
                                                    default => 'secondary'
                                                };
                                            ?>">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo match($user['approval_status']) {
                                                    'approved' => 'success',
                                                    'pending' => 'warning',
                                                    'rejected' => 'danger',
                                                    default => 'secondary'
                                                };
                                            ?>">
                                                <?php echo ucfirst($user['approval_status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <!-- Patients Report Tab -->
        <div class="tab-pane fade" id="patients-report-tab" role="tabpanel" aria-labelledby="patients-tab">
            <!-- Patients Report Section -->
            <div class="row mb-4" id="patients-report-section">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Patients Report</h5>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Filter Form -->
                            <form method="GET" class="row g-3 align-items-end mb-4" action="?page=reports#patients-report-tab">
                                <input type="hidden" name="page" value="reports">
                                <div class="col-md-3">
                                    <label for="filter_doctor" class="form-label">Doctor</label>
                                    <select class="form-select" id="filter_doctor" name="filter_doctor">
                                        <option value="">All Doctors</option>
                                        <?php foreach ($all_doctors as $doc): ?>
                                            <option value="<?php echo $doc['id']; ?>" <?php if ($filter_doctor == $doc['id']) echo 'selected'; ?>><?php echo htmlspecialchars($doc['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="filter_diagnosis" class="form-label">Diagnosis</label>
                                    <select class="form-select" id="filter_diagnosis" name="filter_diagnosis">
                                        <option value="">All Diagnoses</option>
                                        <?php
                                        $unique_diag_values = [];
                                        foreach ($all_diagnoses as $diag):
                                            list($primary, $icd) = extractDiagnosisAndICD($diag);
                                            $value = $primary;
                                            if ($icd) $value .= " (ICD-10: $icd)";
                                            // Avoid duplicates
                                            if (isset($unique_diag_values[$value])) continue;
                                            $unique_diag_values[$value] = true;
                                            $display = $primary;
                                            if ($icd) $display .= " (ICD-10: $icd)";
                                        ?>
                                            <option value="<?php echo htmlspecialchars($value); ?>" <?php if ($filter_diagnosis == $value) echo 'selected'; ?>><?php echo htmlspecialchars($display); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="filter_start_date" class="form-label">From</label>
                                    <input type="date" class="form-control" id="filter_start_date" name="filter_start_date" value="<?php echo htmlspecialchars($filter_start_date); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label for="filter_end_date" class="form-label">To</label>
                                    <input type="date" class="form-control" id="filter_end_date" name="filter_end_date" value="<?php echo htmlspecialchars($filter_end_date); ?>">
                                </div>
                                <div class="col-md-2 d-flex gap-2">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="material-icons align-middle me-1">filter_list</i> Filter
                                    </button>
                                    <a href="?page=reports#patients-report-tab" class="btn btn-outline-secondary w-100">
                                        <i class="material-icons align-middle me-1">refresh</i> Reset
                                    </a>
                                </div>
                            </form>
                            <!-- Results Table and PDF Buttons -->
                            <?php if (!empty($filtered_patients)): ?>
                                <div class="mb-3 d-flex gap-2">
                                    <a href="?page=reports&generate_pdf=patients_report&action=view&filter_doctor=<?php echo urlencode($filter_doctor); ?>&filter_diagnosis=<?php echo urlencode($filter_diagnosis); ?>&filter_start_date=<?php echo urlencode($filter_start_date); ?>&filter_end_date=<?php echo urlencode($filter_end_date); ?>" class="btn btn-info btn-sm" target="_blank">
                                        <i class="material-icons align-middle me-1">visibility</i> View PDF
                                    </a>
                                    <a href="?page=reports&generate_pdf=patients_report&action=download&filter_doctor=<?php echo urlencode($filter_doctor); ?>&filter_diagnosis=<?php echo urlencode($filter_diagnosis); ?>&filter_start_date=<?php echo urlencode($filter_start_date); ?>&filter_end_date=<?php echo urlencode($filter_end_date); ?>" class="btn btn-success btn-sm">
                                        <i class="material-icons align-middle me-1">download</i> Download PDF
                                    </a>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Patient Name</th>
                                                <th>Gender</th>
                                                <th>Age</th>
                                                <th>Contact</th>
                                                <th>Doctor</th>
                                                <th>Diagnosis</th>
                                                <th>Registration Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($filtered_patients as $pat): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($pat['patient_name']); ?></td>
                                                    <td><?php echo htmlspecialchars(ucfirst($pat['gender'])); ?></td>
                                                    <td>
                                                        <?php
                                                            $age = '';
                                                            if (!empty($pat['date_of_birth'])) {
                                                                $dob = new DateTime($pat['date_of_birth']);
                                                                $now = new DateTime();
                                                                $age = $now->diff($dob)->y;
                                                            }
                                                            echo $age;
                                                        ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($pat['contact_number']); ?></td>
                                                    <td><?php echo htmlspecialchars($pat['doctor_name']); ?></td>
                                                    <?php list($primary, $icd) = extractDiagnosisAndICD($pat['diagnosis']); ?>
                                                    <td>
                                                        <?php echo htmlspecialchars($primary); ?>
                                                        <?php if ($icd): ?>
                                                            <br><small class="text-muted">ICD-10: <?php echo htmlspecialchars($icd); ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo date('M d, Y', strtotime($pat['created_at'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && ($filter_doctor || $filter_diagnosis || $filter_start_date || $filter_end_date)): ?>
                                <div class="alert alert-warning mt-3">No patients found for the selected filters.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Doctor Analytics Tab -->
        <div class="tab-pane fade" id="doctors-report-tab" role="tabpanel" aria-labelledby="doctors-tab">
            <!-- Doctor Analytics Section -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Doctor Analytics & Performance</h5>
                                <div class="d-flex gap-2">
                                    <!-- Date Filter Form (reuse clinic filter) -->
                                    <form method="GET" class="d-flex gap-2 align-items-center" action="?page=reports#doctors-report-tab">
                                        <input type="hidden" name="page" value="reports">
                                        <div class="d-flex gap-2 align-items-center">
                                            <label for="doctor_start_date" class="form-label mb-0">From:</label>
                                            <input type="date" class="form-control form-control-sm" id="doctor_start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" style="width: 150px;">
                                        </div>
                                        <div class="d-flex gap-2 align-items-center">
                                            <label for="doctor_end_date" class="form-label mb-0">To:</label>
                                            <input type="date" class="form-control form-control-sm" id="doctor_end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" style="width: 150px;">
                                        </div>
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="material-icons align-middle me-1">filter_list</i>
                                            Filter
                                        </button>
                                        <a href="?page=reports#doctors-report-tab" class="btn btn-outline-secondary btn-sm">
                                            <i class="material-icons align-middle me-1">refresh</i>
                                            Reset
                                        </a>
                                    </form>
                                    <!-- PDF Buttons -->
                                    <a href="?page=reports&generate_pdf=doctor_performance&action=view&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="btn btn-info btn-sm" target="_blank">
                                        <i class="material-icons align-middle me-1">visibility</i>
                                        View PDF
                                    </a>
                                    <a href="?page=reports&generate_pdf=doctor_performance&action=download&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="btn btn-success btn-sm">
                                        <i class="material-icons align-middle me-1">download</i>
                                        Download PDF
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Date Range Display -->
                            <div class="alert alert-info mb-3">
                                <i class="material-icons align-middle me-1">info</i>
                                Showing doctor performance data from <strong><?php echo date('F d, Y', strtotime($start_date)); ?></strong> to <strong><?php echo date('F d, Y', strtotime($end_date)); ?></strong>
                            </div>
                            <!-- Overall Summary Card -->
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <div class="card bg-light mb-2">
                                        <div class="card-body text-left">
                                            <h6 class="mb-2">Overall Doctor Performance</h6>
                                            <div class="row justify-content-left">
                                                <div class="col-auto"><span class="badge bg-primary">Total Appointments: <?php echo $doctor_overall_performance['total_appointments'] ?? 0; ?></span></div>
                                                <div class="col-auto"><span class="badge bg-success">Completed: <?php echo $doctor_overall_performance['completed_appointments'] ?? 0; ?></span></div>
                                                <div class="col-auto"><span class="badge bg-warning">Cancelled: <?php echo $doctor_overall_performance['cancelled_appointments'] ?? 0; ?></span></div>
                                                <div class="col-auto"><span class="badge bg-danger">No-Show: <?php echo $doctor_overall_performance['no_show_appointments'] ?? 0; ?></span></div>
                                                <div class="col-auto"><span class="badge bg-info">Completion Rate: <?php echo $doctor_overall_performance['completion_rate'] ?? 0; ?>%</span></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Per-Doctor Table -->
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Doctor Name</th>
                                            <th>Specialization</th>
                                            <th>Total Appointments</th>
                                            <th>Completed</th>
                                            <th>Cancelled</th>
                                            <th>No-Show</th>
                                            <th>Completion Rate</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($doctor_performance)): ?>
                                            <tr>
                                                <td colspan="8" class="text-center text-muted">
                                                    <i class="material-icons align-middle me-1">info</i>
                                                    No doctor performance data found for the selected date range.
                                                </td>
                                    <?php else: ?>
                                        <?php foreach ($doctor_performance as $doc): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($doc['doctor_name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($doc['specialization']); ?></td>
                                                <td><span class="badge bg-primary"><?php echo $doc['total_appointments']; ?></span></td>
                                                <td><span class="badge bg-success"><?php echo $doc['completed_appointments']; ?></span></td>
                                                <td><span class="badge bg-warning"><?php echo $doc['cancelled_appointments']; ?></span></td>
                                                <td><span class="badge bg-danger"><?php echo $doc['no_show_appointments']; ?></span></td>
                                                <td><span class="badge bg-info"><?php echo $doc['completion_rate']; ?>%</span></td>
                                                <td>
                                                    <div class="d-flex gap-1">
                                                        <a href="?page=reports&generate_pdf=single_doctor_performance&doctor_id=<?php echo $doc['doctor_id']; ?>&action=view&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="btn btn-xs btn-info btn-sm" target="_blank" title="View PDF"><i class="material-icons align-middle">visibility</i></a>
                                                        <a href="?page=reports&generate_pdf=single_doctor_performance&doctor_id=<?php echo $doc['doctor_id']; ?>&action=download&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="btn btn-xs btn-success btn-sm" title="Download PDF"><i class="material-icons align-middle">download</i></a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                            </div>
                            <!-- Chart Placeholder -->
                            <div class="mt-4">
                                <canvas id="doctorPerformanceChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Clinic Performance Tab -->
        <div class="tab-pane fade" id="clinics-report-tab" role="tabpanel" aria-labelledby="clinics-tab">
            <!-- Clinic Performance Details Table -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Clinic Performance Details</h5>
                                <div class="d-flex gap-2">
                                    <!-- Date Filter Form -->
                                    <form method="GET" class="d-flex gap-2 align-items-center" action="?page=reports#clinics-report-tab">
                                        <input type="hidden" name="page" value="reports">
                                        <div class="d-flex gap-2 align-items-center">
                                            <label for="start_date" class="form-label mb-0">From:</label>
                                            <input type="date" class="form-control form-control-sm" id="start_date" name="start_date" 
                                                   value="<?php echo htmlspecialchars($start_date); ?>" style="width: 150px;">
                                        </div>
                                        <div class="d-flex gap-2 align-items-center">
                                            <label for="end_date" class="form-label mb-0">To:</label>
                                            <input type="date" class="form-control form-control-sm" id="end_date" name="end_date" 
                                                   value="<?php echo htmlspecialchars($end_date); ?>" style="width: 150px;">
                                        </div>
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="material-icons align-middle me-1">filter_list</i>
                                            Filter
                                        </button>
                                        <a href="?page=reports#clinics-report-tab" class="btn btn-outline-secondary btn-sm">
                                            <i class="material-icons align-middle me-1">refresh</i>
                                            Reset
                                        </a>
                                    </form>
                                    
                                    <!-- PDF View Button -->
                                    <button type="button" class="btn btn-info btn-sm" onclick="viewPDF('<?php echo urlencode($start_date); ?>', '<?php echo urlencode($end_date); ?>')">
                                        <i class="material-icons align-middle me-1">visibility</i>
                                        View PDF
                                    </button>
                                    
                                    <!-- PDF Download Button -->
                                    <a href="?page=reports&generate_pdf=clinic_performance&action=download&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" 
                                       class="btn btn-success btn-sm">
                                        <i class="material-icons align-middle me-1">download</i>
                                        Download PDF
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Date Range Display -->
                            <div class="alert alert-info mb-3">
                                <i class="material-icons align-middle me-1">info</i>
                                Showing clinic performance data from <strong><?php echo date('F d, Y', strtotime($start_date)); ?></strong> 
                                to <strong><?php echo date('F d, Y', strtotime($end_date)); ?></strong>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Clinic Name</th>
                                            <th>Total Appointments</th>
                                            <th>Completed</th>
                                            <th>Cancelled</th>
                                            <th>No-Show</th>
                                            <th>Completion Rate</th>
                                            <th>Cancellation Rate</th>
                                            <th>No-Show Rate</th>
                                            <th>Total Doctors</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($clinic_metrics)): ?>
                                            <tr>
                                                <td colspan="9" class="text-center text-muted">
                                                    <i class="material-icons align-middle me-1">info</i>
                                                    No clinic performance data found for the selected date range.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($clinic_metrics as $clinic): ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars($clinic['clinic_name']); ?></strong></td>
                                                    <td>
                                                        <span class="badge bg-primary"><?php echo $clinic['total_appointments']; ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-success"><?php echo $clinic['completed_appointments']; ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-warning"><?php echo $clinic['cancelled_appointments']; ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-danger"><?php echo $clinic['no_show_appointments']; ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info"><?php echo $clinic['completion_rate']; ?>%</span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-secondary"><?php echo $clinic['cancellation_rate']; ?>%</span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-warning"><?php echo $clinic['no_show_rate']; ?>%</span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-dark"><?php echo $clinic['total_doctors']; ?></span>
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
            </div>
        </div>
    </div>
</div>

<!-- PDF Preview Modal -->
<div class="modal fade" id="pdfPreviewModal" tabindex="-1" aria-labelledby="pdfPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pdfPreviewModalLabel">
                    <i class="material-icons align-middle me-1">picture_as_pdf</i>
                    Clinic Performance Report Preview
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <div class="spinner-border text-primary" role="status" id="pdfLoadingSpinner">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="text-muted">Generating PDF preview...</p>
                </div>
                <div id="pdfPreviewContent" style="display: none;">
                    <iframe id="pdfIframe" style="width: 100%; height: 600px; border: 1px solid #dee2e6; border-radius: 8px;"></iframe>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="#" class="btn btn-outline-primary" id="openInNewTabBtn" target="_blank" style="display: none;">
                    <i class="material-icons align-middle me-1">open_in_new</i>
                    Open in New Tab
                </a>
                <button type="button" class="btn btn-success" id="downloadPdfBtn" style="display: none;">
                    <i class="material-icons align-middle me-1">download</i>
                    Download PDF
                </button>
                <button type="button" class="btn btn-primary" id="printPdfBtn" style="display: none;">
                    <i class="material-icons align-middle me-1">print</i>
                    Print PDF
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add New Entity Modal -->
<div class="modal fade" id="addEntityModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Entity</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs" id="addEntityTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="clinic-tab" data-bs-toggle="tab" data-bs-target="#clinic" type="button" role="tab">
                            <i class="material-icons align-middle me-1">local_hospital</i>
                            Add Clinic
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="doctor-tab" data-bs-toggle="tab" data-bs-target="#doctor" type="button" role="tab">
                            <i class="material-icons align-middle me-1">medical_services</i>
                            Add Doctor
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="specialization-tab" data-bs-toggle="tab" data-bs-target="#specialization" type="button" role="tab">
                            <i class="material-icons align-middle me-1">category</i>
                            Add Specialization
                        </button>
                    </li>
                </ul>

                <div class="tab-content pt-3" id="addEntityTabContent">
                    <!-- Clinic Form Tab -->
                    <div class="tab-pane fade show active" id="clinic" role="tabpanel">
                        <form id="addClinicForm" method="POST" action="index.php?page=reports" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="clinic_name" class="form-label">Clinic Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="clinic_name" name="name" maxlength="100" required>
                                <div class="invalid-feedback">Please enter the clinic name.</div>
                            </div>

                            <div class="mb-3">
                                <label for="clinic_address" class="form-label">Address <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="clinic_address" name="address" rows="3" required></textarea>
                                <div class="invalid-feedback">Please enter the clinic address.</div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="clinic_phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="clinic_phone" name="phone" maxlength="20">
                                    <div class="invalid-feedback">Please enter a valid phone number.</div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="clinic_email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="clinic_email" name="email" maxlength="100">
                                    <div class="invalid-feedback">Please enter a valid email address.</div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="clinic_status" class="form-label">Status</label>
                                <select class="form-select" id="clinic_status" name="status">
                                    <option value="active" selected>Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="material-icons align-middle me-1">add</i>
                                    Add Clinic
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Doctor Form Tab -->
                    <div class="tab-pane fade" id="doctor" role="tabpanel">
                        <div class="text-center py-4">
                            <a href="?page=add-doctor" class="btn btn-primary">
                                <i class="material-icons align-middle me-1">medical_services</i>
                                Go to Add Doctor Page
                            </a>
                        </div>
                    </div>

                    <!-- Specialization Form Tab -->
                    <div class="tab-pane fade" id="specialization" role="tabpanel">
                        <form id="addSpecializationForm" method="POST" action="index.php?page=reports" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="specialization_name" class="form-label">Specialization Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="specialization_name" name="specialization_name" maxlength="100" required>
                                <div class="invalid-feedback">Please enter the specialization name.</div>
                            </div>

                            <div class="mb-3">
                                <label for="specialization_description" class="form-label">Description</label>
                                <textarea class="form-control" id="specialization_description" name="specialization_description" rows="3"></textarea>
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="material-icons align-middle me-1">add</i>
                                    Add Specialization
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add success message display -->
<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php 
        echo $_SESSION['success_message'];
        unset($_SESSION['success_message']);
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Add SweetAlert2 CDN before the closing body tag -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Initialize charts when the document is ready
document.addEventListener('DOMContentLoaded', function() {
    // Add error handling for chart initialization
    try {
        // Doctor Activity Chart
        const doctorActivityCtx = document.getElementById('doctorActivityChart');
        if (doctorActivityCtx) {
            new Chart(doctorActivityCtx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($doctor_activity, 'specialization')); ?>,
                    datasets: [{
                        label: 'Doctors',
                        data: <?php echo json_encode(array_column($doctor_activity, 'doctor_count')); ?>,
                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }, {
                        label: 'Appointments',
                        data: <?php echo json_encode(array_column($doctor_activity, 'appointment_count')); ?>,
                        backgroundColor: 'rgba(255, 206, 86, 0.5)',
                        borderColor: 'rgba(255, 206, 86, 1)',
                        borderWidth: 1
                    }, {
                        label: 'Consultations',
                        data: <?php echo json_encode(array_column($doctor_activity, 'consultation_count')); ?>,
                        backgroundColor: 'rgba(75, 192, 192, 0.5)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        // Clinic Performance Chart
        const clinicPerformanceCtx = document.getElementById('clinicPerformanceChart');
        if (clinicPerformanceCtx) {
            new Chart(clinicPerformanceCtx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($clinic_metrics, 'clinic_name')); ?>,
                    datasets: [{
                        label: 'Total Appointments',
                        data: <?php echo json_encode(array_column($clinic_metrics, 'total_appointments')); ?>,
                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }, {
                        label: 'Completed Appointments',
                        data: <?php echo json_encode(array_column($clinic_metrics, 'completed_appointments')); ?>,
                        backgroundColor: 'rgba(75, 192, 192, 0.5)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    }, {
                        label: 'Cancelled Appointments',
                        data: <?php echo json_encode(array_column($clinic_metrics, 'cancelled_appointments')); ?>,
                        backgroundColor: 'rgba(255, 99, 132, 0.5)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 1
                    }, {
                        label: 'No-Show Appointments',
                        data: <?php echo json_encode(array_column($clinic_metrics, 'no_show_appointments')); ?>,
                        backgroundColor: 'rgba(255, 159, 64, 0.5)',
                        borderColor: 'rgba(255, 159, 64, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                afterBody: function(context) {
                                    const dataIndex = context[0].dataIndex;
                                    const completionRate = <?php echo json_encode(array_column($clinic_metrics, 'completion_rate')); ?>[dataIndex];
                                    const cancellationRate = <?php echo json_encode(array_column($clinic_metrics, 'cancellation_rate')); ?>[dataIndex];
                                    const noShowRate = <?php echo json_encode(array_column($clinic_metrics, 'no_show_rate')); ?>[dataIndex];
                                    const totalDoctors = <?php echo json_encode(array_column($clinic_metrics, 'total_doctors')); ?>[dataIndex];
                                    return [
                                        `Completion Rate: ${completionRate}%`,
                                        `Cancellation Rate: ${cancellationRate}%`,
                                        `No-Show Rate: ${noShowRate}%`,
                                        `Total Doctors: ${totalDoctors}`
                                    ];
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Appointments'
                            }
                        }
                    }
                }
            });
        }

        // Appointment Trends Chart
        const appointmentTrendsCtx = document.getElementById('appointmentTrendsChart');
        if (appointmentTrendsCtx) {
            new Chart(appointmentTrendsCtx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column($appointment_trends, 'month')); ?>,
                    datasets: [{
                        label: 'Total Appointments',
                        data: <?php echo json_encode(array_column($appointment_trends, 'total_appointments')); ?>,
                        borderColor: 'rgba(54, 162, 235, 1)',
                        backgroundColor: 'rgba(54, 162, 235, 0.1)',
                        fill: true
                    }, {
                        label: 'Completed Appointments',
                        data: <?php echo json_encode(array_column($appointment_trends, 'completed_appointments')); ?>,
                        borderColor: 'rgba(75, 192, 192, 1)',
                        backgroundColor: 'rgba(75, 192, 192, 0.1)',
                        fill: true
                    }, {
                        label: 'Cancelled Appointments',
                        data: <?php echo json_encode(array_column($appointment_trends, 'cancelled_appointments')); ?>,
                        borderColor: 'rgba(255, 99, 132, 1)',
                        backgroundColor: 'rgba(255, 99, 132, 0.1)',
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        // Diagnosis Pie Chart
        const diagnosisPieCtx = document.getElementById('diagnosisPieChart');
        if (diagnosisPieCtx) {
            // Generate colors for multiple diagnosis types
            const diagnosisColors = [
                'rgba(75, 192, 192, 0.8)',   // Teal
                'rgba(255, 99, 132, 0.8)',   // Red
                'rgba(54, 162, 235, 0.8)',   // Blue
                'rgba(255, 206, 86, 0.8)',   // Yellow
                'rgba(153, 102, 255, 0.8)',  // Purple
                'rgba(255, 159, 64, 0.8)',   // Orange
                'rgba(199, 199, 199, 0.8)',  // Gray
                'rgba(83, 102, 255, 0.8)',   // Indigo
                'rgba(255, 99, 132, 0.8)',   // Pink
                'rgba(75, 192, 192, 0.8)'    // Green
            ];
            
            const diagnosisBorderColors = diagnosisColors.map(color => color.replace('0.8', '1'));
            
            new Chart(diagnosisPieCtx.getContext('2d'), {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode(array_column($diagnosis_stats, 'diagnosis_type')); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($diagnosis_stats, 'patient_count')); ?>,
                        backgroundColor: diagnosisColors.slice(0, <?php echo count($diagnosis_stats); ?>),
                        borderColor: diagnosisBorderColors.slice(0, <?php echo count($diagnosis_stats); ?>),
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                usePointStyle: true,
                                font: {
                                    size: 11
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                title: function(context) {
                                    return context[0].label;
                                },
                                label: function(context) {
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return `Patients: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }
    } catch (error) {
        console.error('Error initializing charts:', error);
    }
});

// Print reports function
function printReports() {
    window.print();
}

// Download reports function
function downloadReports() {
    // Implement PDF generation and download
    alert('Report download functionality will be implemented soon.');
}

// Form validation
(function () {
    'use strict'
    var forms = document.querySelectorAll('.needs-validation')
    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
            }
            form.classList.add('was-validated')
        }, false)
    })
})()

// Phone number formatting
document.getElementById('clinic_phone').addEventListener('input', function (e) {
    let x = e.target.value.replace(/\D/g, '').match(/(\d{0,3})(\d{0,3})(\d{0,4})/);
    e.target.value = !x[2] ? x[1] : '(' + x[1] + ') ' + x[2] + (x[3] ? '-' + x[3] : '');
});

// Date filter validation
document.addEventListener('DOMContentLoaded', function() {
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    
    if (startDate && endDate) {
        // Set max date for start date
        startDate.max = new Date().toISOString().split('T')[0];
        
        // Set max date for end date
        endDate.max = new Date().toISOString().split('T')[0];
        
        // Validate date range
        startDate.addEventListener('change', function() {
            if (endDate.value && startDate.value > endDate.value) {
                alert('Start date cannot be after end date');
                startDate.value = '';
            }
        });
        
        endDate.addEventListener('change', function() {
            if (startDate.value && endDate.value < startDate.value) {
                alert('End date cannot be before start date');
                endDate.value = '';
            }
        });
    }
});

// PDF viewing function
function viewPDF(startDate, endDate) {
    const modal = new bootstrap.Modal(document.getElementById('pdfPreviewModal'));
    const loadingSpinner = document.getElementById('pdfLoadingSpinner');
    const pdfContent = document.getElementById('pdfPreviewContent');
    const pdfIframe = document.getElementById('pdfIframe');
    const openInNewTabBtn = document.getElementById('openInNewTabBtn');
    const downloadBtn = document.getElementById('downloadPdfBtn');
    const printBtn = document.getElementById('printPdfBtn');
    
    // Show modal and loading spinner
    modal.show();
    loadingSpinner.style.display = 'block';
    pdfContent.style.display = 'none';
    openInNewTabBtn.style.display = 'none';
    downloadBtn.style.display = 'none';
    printBtn.style.display = 'none';
    
    // Generate PDF URL
    const pdfUrl = `?page=reports&generate_pdf=clinic_performance&action=view&start_date=${startDate}&end_date=${endDate}`;
    
    // Load PDF in iframe
    pdfIframe.src = pdfUrl;
    
    // Handle iframe load
    pdfIframe.onload = function() {
        loadingSpinner.style.display = 'none';
        pdfContent.style.display = 'block';
        openInNewTabBtn.style.display = 'inline-block';
        downloadBtn.style.display = 'inline-block';
        printBtn.style.display = 'inline-block';
        
        // Set up open in new tab button
        openInNewTabBtn.href = pdfUrl;
        
        // Set up download button
        downloadBtn.onclick = function() {
            const downloadUrl = `?page=reports&generate_pdf=clinic_performance&action=download&start_date=${startDate}&end_date=${end_date}`;
            window.open(downloadUrl, '_blank');
        };
        
        // Set up print button
        printBtn.onclick = function() {
            pdfIframe.contentWindow.print();
        };
    };
    
    // Handle iframe error
    pdfIframe.onerror = function() {
        loadingSpinner.style.display = 'none';
        pdfContent.innerHTML = '<div class="alert alert-danger">Error loading PDF preview. Please try again.</div>';
        pdfContent.style.display = 'block';
    };
}

// Show SweetAlert messages if they exist
<?php if (isset($_SESSION['success_message'])): ?>
    Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: '<?php echo $_SESSION['success_message']; ?>',
        showConfirmButton: false,
        timer: 1500
    }).then(() => {
        // Close modal if it's open
        const modal = bootstrap.Modal.getInstance(document.getElementById('addEntityModal'));
        if (modal) {
            modal.hide();
        }
        // Reset form
        document.getElementById('addClinicForm').reset();
    });
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: '<?php echo $_SESSION['error_message']; ?>'
    });
    <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

// Auto-activate Bootstrap tab based on URL hash (robust version)
window.onload = function() {
    function activateTabFromHash() {
        var hash = window.location.hash;
        var tabBtn = hash && document.querySelector('button[data-bs-target="' + hash + '"]');
        
        // First, remove active classes from all tab panes and nav links
        document.querySelectorAll('.tab-pane').forEach(function(pane) {
            pane.classList.remove('show', 'active');
        });
        document.querySelectorAll('#reportsTab .nav-link').forEach(function(link) {
            link.classList.remove('active');
            link.setAttribute('aria-selected', 'false');
        });
        
        if (tabBtn) {
            // Activate the target tab
            tabBtn.classList.add('active');
            tabBtn.setAttribute('aria-selected', 'true');
            var targetPane = document.querySelector(tabBtn.getAttribute('data-bs-target'));
            if (targetPane) {
                targetPane.classList.add('show', 'active');
            }
        } else {
            // Default to first tab if hash is missing/invalid
            var firstTabBtn = document.querySelector('#reportsTab button[data-bs-toggle="tab"]');
            if (firstTabBtn) {
                firstTabBtn.classList.add('active');
                firstTabBtn.setAttribute('aria-selected', 'true');
                var firstPane = document.querySelector(firstTabBtn.getAttribute('data-bs-target'));
                if (firstPane) {
                    firstPane.classList.add('show', 'active');
                }
            }
        }
    }
    activateTabFromHash();
    // Fallback: re-activate after short delay in case of slow DOM
    setTimeout(activateTabFromHash, 100);
    // Update hash when tab is changed
    var tabButtons = document.querySelectorAll('#reportsTab button[data-bs-toggle="tab"]');
    tabButtons.forEach(function(btn) {
        btn.addEventListener('shown.bs.tab', function(e) {
            history.replaceState(null, '', e.target.getAttribute('data-bs-target'));
        });
    });
};
</script> 

<?php
// PDF Generation Function
function generateClinicPerformancePDF($conn, $start_date, $end_date, $action = 'view') {
    // Get clinic performance data for the specified date range
    $stmt = $conn->prepare("
        SELECT c.name as clinic_name,
               COUNT(DISTINCT a.id) as total_appointments,
               COUNT(DISTINCT CASE WHEN a.status = 'completed' THEN a.id END) as completed_appointments,
               COUNT(DISTINCT CASE WHEN a.status = 'cancelled' THEN a.id END) as cancelled_appointments,
               COUNT(DISTINCT CASE WHEN a.status = 'no-show' THEN a.id END) as no_show_appointments,
               COUNT(DISTINCT dc.doctor_id) as total_doctors,
               CASE 
                   WHEN COUNT(DISTINCT a.id) > 0 
                   THEN ROUND((COUNT(DISTINCT CASE WHEN a.status = 'completed' THEN a.id END) * 100.0 / COUNT(DISTINCT a.id)), 2)
                   ELSE 0 
               END as completion_rate,
               CASE 
                   WHEN COUNT(DISTINCT a.id) > 0 
                   THEN ROUND((COUNT(DISTINCT CASE WHEN a.status = 'cancelled' THEN a.id END) * 100.0 / COUNT(DISTINCT a.id)), 2)
                   ELSE 0 
               END as cancellation_rate,
               CASE 
                   WHEN COUNT(DISTINCT a.id) > 0 
                   THEN ROUND((COUNT(DISTINCT CASE WHEN a.status = 'no-show' THEN a.id END) * 100.0 / COUNT(DISTINCT a.id)), 2)
                   ELSE 0 
               END as no_show_rate
        FROM clinics c
        LEFT JOIN appointments a ON c.id = a.clinic_id AND a.date BETWEEN :start_date AND :end_date
        LEFT JOIN doctor_clinics dc ON c.id = dc.clinic_id
        WHERE c.status = 'active'
        GROUP BY c.id, c.name
        ORDER BY total_appointments DESC
    ");
    
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    $stmt->execute();
    $clinic_metrics = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Create PDF using TCPDF with LETTER (short bond paper) size
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, 'LETTER', true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator('Medbuddy System');
    $pdf->SetAuthor('Medbuddy Admin');
    $pdf->SetTitle('Clinic Performance Report');
    $pdf->SetSubject('Clinic Performance Analysis');

    // Set default header data
    $pdf->SetHeaderData('', 0, 'Medbuddy Healthcare System', 'Clinic Performance Report', array(0,0,0), array(0,0,0));
    $pdf->setFooterData(array(0,0,0), array(0,0,0));

    // Set header and footer fonts
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

    // Set default monospaced font
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

    // Set margins
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

    // Set image scale factor
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

    // Add a page
    $pdf->AddPage();

    // Set font
    $pdf->SetFont('helvetica', '', 12);

    // Report Header (centered)
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'CLINIC PERFORMANCE REPORT', 0, 1, 'C');
    $pdf->Ln(2);

    // Date Range (centered)
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Report Period: ' . date('F d, Y', strtotime($start_date)) . ' to ' . date('F d, Y', strtotime($end_date)), 0, 1, 'C');
    $pdf->Ln(2);

    // Generated Date (centered)
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 10, 'Generated on: ' . date('F d, Y \a\t g:i A'), 0, 1, 'C');
    $pdf->Ln(2);

    // Table Header (centered)
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(240, 240, 240);
    $headers = array('Clinic Name', 'Total Appts', 'Completed', 'Cancelled', 'No-Show', 'Completion %', 'Cancellation %', 'No-Show %', 'Doctors');
    $widths = array(40, 20, 20, 20, 20, 25, 25, 25, 20);
    $pdf->SetX(($pdf->getPageWidth() - array_sum($widths)) / 2); // Center the table
    for($i = 0; $i < count($headers); $i++) {
        $pdf->Cell($widths[$i], 10, $headers[$i], 1, 0, 'C', true);
    }
    $pdf->Ln();

    // Table data (centered)
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetFillColor(255, 255, 255);
    foreach($clinic_metrics as $clinic) {
        $pdf->SetX(($pdf->getPageWidth() - array_sum($widths)) / 2); // Center the table
        $pdf->Cell($widths[0], 8, $clinic['clinic_name'], 1, 0, 'C', true);
        $pdf->Cell($widths[1], 8, $clinic['total_appointments'], 1, 0, 'C', true);
        $pdf->Cell($widths[2], 8, $clinic['completed_appointments'], 1, 0, 'C', true);
        $pdf->Cell($widths[3], 8, $clinic['cancelled_appointments'], 1, 0, 'C', true);
        $pdf->Cell($widths[4], 8, $clinic['no_show_appointments'], 1, 0, 'C', true);
        $pdf->Cell($widths[5], 8, $clinic['completion_rate'] . '%', 1, 0, 'C', true);
        $pdf->Cell($widths[6], 8, $clinic['cancellation_rate'] . '%', 1, 0, 'C', true);
        $pdf->Cell($widths[7], 8, $clinic['no_show_rate'] . '%', 1, 0, 'C', true);
        $pdf->Cell($widths[8], 8, $clinic['total_doctors'], 1, 0, 'C', true);
        $pdf->Ln();
    }

    // Summary section (centered)
    $pdf->Ln(8);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'SUMMARY', 0, 1, 'C');
    $pdf->Ln(2);

    // Calculate totals
    $total_clinics = count($clinic_metrics);
    $total_appointments = array_sum(array_column($clinic_metrics, 'total_appointments'));
    $total_completed = array_sum(array_column($clinic_metrics, 'completed_appointments'));
    $total_cancelled = array_sum(array_column($clinic_metrics, 'cancelled_appointments'));
    $total_no_show = array_sum(array_column($clinic_metrics, 'no_show_appointments'));
    $total_doctors = array_sum(array_column($clinic_metrics, 'total_doctors'));

    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 8, 'Total Clinics: ' . $total_clinics, 0, 1, 'C');
    $pdf->Cell(0, 8, 'Total Appointments: ' . $total_appointments, 0, 1, 'C');
    $pdf->Cell(0, 8, 'Total Completed: ' . $total_completed, 0, 1, 'C');
    $pdf->Cell(0, 8, 'Total Cancelled: ' . $total_cancelled, 0, 1, 'C');
    $pdf->Cell(0, 8, 'Total No-Show: ' . $total_no_show, 0, 1, 'C');
    $pdf->Cell(0, 8, 'Total Doctors: ' . $total_doctors, 0, 1, 'C');

    if ($total_appointments > 0) {
        $overall_completion_rate = round(($total_completed * 100.0 / $total_appointments), 2);
        $overall_cancellation_rate = round(($total_cancelled * 100.0 / $total_appointments), 2);
        $overall_no_show_rate = round(($total_no_show * 100.0 / $total_appointments), 2);
        $pdf->Ln(2);
        $pdf->Cell(0, 8, 'Overall Completion Rate: ' . $overall_completion_rate . '%', 0, 1, 'C');
        $pdf->Cell(0, 8, 'Overall Cancellation Rate: ' . $overall_cancellation_rate . '%', 0, 1, 'C');
        $pdf->Cell(0, 8, 'Overall No-Show Rate: ' . $overall_no_show_rate . '%', 0, 1, 'C');
    }

    // Output PDF based on action
    $filename = 'clinic_performance_report_' . date('Y-m-d_H-i-s') . '.pdf';
    if ($action === 'download') {
        $pdf->Output($filename, 'D'); // Download
    } else {
        $pdf->Output($filename, 'I'); // View in browser
    }
}

function generateDoctorPerformancePDF($conn, $start_date, $end_date, $action = 'view') {
    // Get overall doctor performance
    $stmt = $conn->prepare("
        SELECT
            COUNT(*) AS total_appointments,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) AS completed_appointments,
            COUNT(CASE WHEN status = 'cancelled' THEN 1 END) AS cancelled_appointments,
            COUNT(CASE WHEN status = 'no-show' THEN 1 END) AS no_show_appointments,
            ROUND(
                CASE WHEN COUNT(*) > 0
                    THEN (COUNT(CASE WHEN status = 'completed' THEN 1 END) * 100.0 / COUNT(*))
                    ELSE 0 END, 2
            ) AS completion_rate
        FROM appointments
        WHERE date BETWEEN :start_date AND :end_date
    ");
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    $stmt->execute();
    $overall = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get per-doctor performance
    $stmt = $conn->prepare("
        SELECT
            d.id AS doctor_id,
            CONCAT(d.first_name, ' ', d.last_name) AS doctor_name,
            s.name AS specialization,
            COUNT(a.id) AS total_appointments,
            COUNT(CASE WHEN a.status = 'completed' THEN 1 END) AS completed_appointments,
            COUNT(CASE WHEN a.status = 'cancelled' THEN 1 END) AS cancelled_appointments,
            COUNT(CASE WHEN a.status = 'no-show' THEN 1 END) AS no_show_appointments,
            ROUND(
                CASE WHEN COUNT(a.id) > 0
                    THEN (COUNT(CASE WHEN a.status = 'completed' THEN 1 END) * 100.0 / COUNT(a.id))
                    ELSE 0 END, 2
            ) AS completion_rate
        FROM doctors d
        LEFT JOIN specializations s ON d.specialization_id = s.id
        LEFT JOIN appointments a ON d.id = a.doctor_id AND a.date BETWEEN :start_date AND :end_date
        GROUP BY d.id, doctor_name, s.name
        ORDER BY doctor_name
    ");
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    $stmt->execute();
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Create PDF using TCPDF with LETTER (short bond paper) size
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, 'LETTER', true, 'UTF-8', false);
    $pdf->SetCreator('Medbuddy System');
    $pdf->SetAuthor('Medbuddy Admin');
    $pdf->SetTitle('Doctor Performance Report');
    $pdf->SetSubject('Doctor Performance Analysis');
    $pdf->SetHeaderData('', 0, 'Medbuddy Healthcare System', 'Doctor Performance Report', array(0,0,0), array(0,0,0));
    $pdf->setFooterData(array(0,0,0), array(0,0,0));
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 12);
    // Header
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'DOCTOR PERFORMANCE REPORT', 0, 1, 'C');
    $pdf->Ln(2);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Report Period: ' . date('F d, Y', strtotime($start_date)) . ' to ' . date('F d, Y', strtotime($end_date)), 0, 1, 'C');
    $pdf->Ln(2);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 10, 'Generated on: ' . date('F d, Y \a\t g:i A'), 0, 1, 'C');
    $pdf->Ln(2);
    // Overall summary
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'OVERALL DOCTOR PERFORMANCE', 0, 1, 'C');
    $pdf->Ln(2);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 8, 'Total Appointments: ' . ($overall['total_appointments'] ?? 0), 0, 1, 'L');
    $pdf->Cell(0, 8, 'Completed: ' . ($overall['completed_appointments'] ?? 0), 0, 1, 'L');
    $pdf->Cell(0, 8, 'Cancelled: ' . ($overall['cancelled_appointments'] ?? 0), 0, 1, 'L');
    $pdf->Cell(0, 8, 'No-Show: ' . ($overall['no_show_appointments'] ?? 0), 0, 1, 'L');
    $pdf->Cell(0, 8, 'Completion Rate: ' . ($overall['completion_rate'] ?? 0) . '%', 0, 1, 'L');
    $pdf->Ln(8);
    // Per-doctor table
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(240, 240, 240);
    $headers = array('Doctor Name', 'Specialization', 'Total Appts', 'Completed', 'Cancelled', 'No-Show', 'Completion %');
    $widths = array(40, 35, 22, 18, 18, 18, 22);
    $pdf->SetX(($pdf->getPageWidth() - array_sum($widths)) / 2);
    foreach ($headers as $i => $header) {
        $pdf->Cell($widths[$i], 10, $header, 1, 0, 'C', true);
    }
    $pdf->Ln();
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetFillColor(255, 255, 255);
    foreach ($doctors as $doc) {
        $pdf->SetX(($pdf->getPageWidth() - array_sum($widths)) / 2);
        $pdf->Cell($widths[0], 8, $doc['doctor_name'], 1, 0, 'C', true);
        $pdf->Cell($widths[1], 8, $doc['specialization'], 1, 0, 'C', true);
        $pdf->Cell($widths[2], 8, $doc['total_appointments'], 1, 0, 'C', true);
        $pdf->Cell($widths[3], 8, $doc['completed_appointments'], 1, 0, 'C', true);
        $pdf->Cell($widths[4], 8, $doc['cancelled_appointments'], 1, 0, 'C', true);
        $pdf->Cell($widths[5], 8, $doc['no_show_appointments'], 1, 0, 'C', true);
        $pdf->Cell($widths[6], 8, $doc['completion_rate'] . '%', 1, 0, 'C', true);
        $pdf->Ln();
    }
    // Summary section
    $pdf->Ln(8);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'SUMMARY', 0, 1, 'C');
    $pdf->Ln(2);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 8, 'Total Doctors: ' . count($doctors), 0, 1, 'L');
    $pdf->Cell(0, 8, 'Total Appointments: ' . ($overall['total_appointments'] ?? 0), 0, 1, 'L');
    $pdf->Cell(0, 8, 'Total Completed: ' . ($overall['completed_appointments'] ?? 0), 0, 1, 'L');
    $pdf->Cell(0, 8, 'Total Cancelled: ' . ($overall['cancelled_appointments'] ?? 0), 0, 1, 'L');
    $pdf->Cell(0, 8, 'Total No-Show: ' . ($overall['no_show_appointments'] ?? 0), 0, 1, 'L');
    if (($overall['total_appointments'] ?? 0) > 0) {
        $pdf->Ln(2);
        $pdf->Cell(0, 8, 'Overall Completion Rate: ' . ($overall['completion_rate'] ?? 0) . '%', 0, 1, 'L');
    }
    $filename = 'doctor_performance_report_' . date('Y-m-d_H-i-s') . '.pdf';
    if ($action === 'download') {
        $pdf->Output($filename, 'D');
    } else {
        $pdf->Output($filename, 'I');
    }
}

function generateSingleDoctorPerformancePDF($conn, $doctor_id, $start_date, $end_date, $action = 'view') {
    // Get doctor details
    $stmt = $conn->prepare("
        SELECT d.*, s.name AS specialization
        FROM doctors d
        LEFT JOIN specializations s ON d.specialization_id = s.id
        WHERE d.id = :doctor_id
        LIMIT 1
    ");
    $stmt->bindParam(':doctor_id', $doctor_id);
    $stmt->execute();
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$doctor) {
        die('Doctor not found.');
    }

    // Get doctor's appointment stats for the date range
    $stmt = $conn->prepare("
        SELECT
            COUNT(*) AS total_appointments,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) AS completed_appointments,
            COUNT(CASE WHEN status = 'cancelled' THEN 1 END) AS cancelled_appointments,
            COUNT(CASE WHEN status = 'no-show' THEN 1 END) AS no_show_appointments,
            ROUND(
                CASE WHEN COUNT(*) > 0
                    THEN (COUNT(CASE WHEN status = 'completed' THEN 1 END) * 100.0 / COUNT(*))
                    ELSE 0 END, 2
            ) AS completion_rate
        FROM appointments
        WHERE doctor_id = :doctor_id AND date BETWEEN :start_date AND :end_date
    ");
    $stmt->bindParam(':doctor_id', $doctor_id);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Create PDF using TCPDF with LETTER (short bond paper) size
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, 'LETTER', true, 'UTF-8', false);
    $pdf->SetCreator('Medbuddy System');
    $pdf->SetAuthor('Medbuddy Admin');
    $pdf->SetTitle('Doctor Individual Performance Report');
    $pdf->SetSubject('Doctor Performance Analysis');
    $pdf->SetHeaderData('', 0, 'Medbuddy Healthcare System', 'Doctor Individual Performance Report', array(0,0,0), array(0,0,0));
    $pdf->setFooterData(array(0,0,0), array(0,0,0));
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 12);
    // Header
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'DOCTOR INDIVIDUAL PERFORMANCE REPORT', 0, 1, 'C');
    $pdf->Ln(2);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Report Period: ' . date('F d, Y', strtotime($start_date)) . ' to ' . date('F d, Y', strtotime($end_date)), 0, 1, 'C');
    $pdf->Ln(2);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 10, 'Generated on: ' . date('F d, Y \a\t g:i A'), 0, 1, 'C');
    $pdf->Ln(4);
    // Doctor Details
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'DOCTOR DETAILS', 0, 1, 'C');
    $pdf->Ln(2);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 8, 'Name: ' . $doctor['first_name'] . ' ' . $doctor['middle_name'] . ' ' . $doctor['last_name'], 0, 1, 'L');
    $pdf->Cell(0, 8, 'Specialization: ' . ($doctor['specialization'] ?? 'N/A'), 0, 1, 'L');
    $pdf->Cell(0, 8, 'License Number: ' . $doctor['license_number'], 0, 1, 'L');
    $pdf->Cell(0, 8, 'Contact Number: ' . ($doctor['contact_number'] ?? 'N/A'), 0, 1, 'L');
    $pdf->Cell(0, 8, 'Status: ' . ucfirst($doctor['status']), 0, 1, 'L');
    $pdf->Ln(8);
    // Performance Stats
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'PERFORMANCE SUMMARY', 0, 1, 'C');
    $pdf->Ln(2);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 8, 'Total Appointments: ' . ($stats['total_appointments'] ?? 0), 0, 1, 'L');
    $pdf->Cell(0, 8, 'Completed: ' . ($stats['completed_appointments'] ?? 0), 0, 1, 'L');
    $pdf->Cell(0, 8, 'Cancelled: ' . ($stats['cancelled_appointments'] ?? 0), 0, 1, 'L');
    $pdf->Cell(0, 8, 'No-Show: ' . ($stats['no_show_appointments'] ?? 0), 0, 1, 'L');
    $pdf->Cell(0, 8, 'Completion Rate: ' . ($stats['completion_rate'] ?? 0) . '%', 0, 1, 'L');
    $pdf->Ln(8);
    // Summary Section
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'SUMMARY', 0, 1, 'C');
    $pdf->Ln(2);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 8, 'Doctor: ' . $doctor['first_name'] . ' ' . $doctor['middle_name'] . ' ' . $doctor['last_name'], 0, 1, 'L');
    $pdf->Cell(0, 8, 'Total Appointments: ' . ($stats['total_appointments'] ?? 0), 0, 1, 'L');
    $pdf->Cell(0, 8, 'Total Completed: ' . ($stats['completed_appointments'] ?? 0), 0, 1, 'L');
    $pdf->Cell(0, 8, 'Total Cancelled: ' . ($stats['cancelled_appointments'] ?? 0), 0, 1, 'L');
    $pdf->Cell(0, 8, 'Total No-Show: ' . ($stats['no_show_appointments'] ?? 0), 0, 1, 'L');
    if (($stats['total_appointments'] ?? 0) > 0) {
        $pdf->Ln(2);
        $pdf->Cell(0, 8, 'Overall Completion Rate: ' . ($stats['completion_rate'] ?? 0) . '%', 0, 1, 'L');
    }
    $filename = 'doctor_individual_performance_report_' . $doctor_id . '_' . date('Y-m-d_H-i-s') . '.pdf';
    if ($action === 'download') {
        $pdf->Output($filename, 'D');
    } else {
        $pdf->Output($filename, 'I');
    }
}
?>