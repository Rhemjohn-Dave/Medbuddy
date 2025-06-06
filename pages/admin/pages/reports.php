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

    // Clinic performance metrics
    $clinic_metrics = $conn->query("
        SELECT c.name as clinic_name,
               COUNT(DISTINCT a.id) as total_appointments,
               COUNT(DISTINCT CASE WHEN a.status = 'completed' THEN a.id END) as completed_appointments,
               COUNT(DISTINCT d.id) as total_doctors,
               AVG(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) * 100 as completion_rate
        FROM clinics c
        LEFT JOIN appointments a ON c.id = a.clinic_id
        LEFT JOIN doctors d ON c.id = d.clinic_id
        GROUP BY c.id, c.name
        ORDER BY total_appointments DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

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

} catch (Exception $e) {
    error_log("Error fetching report data: " . $e->getMessage());
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

    <!-- Main Analytics Section -->
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

        <!-- Clinic Performance -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Clinic Performance</h5>
                </div>
                <div class="card-body">
                    <canvas id="clinicPerformanceChart"></canvas>
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
                            <th>Username</th>
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
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
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
                        label: 'Completion Rate (%)',
                        data: <?php echo json_encode(array_column($clinic_metrics, 'completion_rate')); ?>,
                        backgroundColor: 'rgba(255, 99, 132, 0.5)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 1,
                        yAxisID: 'y1'
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Count'
                            }
                        },
                        y1: {
                            beginAtZero: true,
                            max: 100,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Completion Rate (%)'
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
</script> 