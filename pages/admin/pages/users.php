<?php
// Check if this file is being included
if (!defined('ADMIN_ACCESS')) {
    header("Location: ../../index.php");
    exit();
}

// Handle AJAX requests for modal content
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    // Only output modal content for AJAX requests
    ob_start();
}

require_once '../../config/database.php';
$db = new Database();
$conn = $db->getConnection();

// Get filter parameters
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$role = isset($_GET['role']) ? $_GET['role'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query
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

        CASE 
            WHEN u.role = 'staff' THEN CONCAT(d.first_name, ' ', d.last_name)
            ELSE NULL 
        END as assigned_doctor
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
            CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR 
            u.role LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
}

$sql .= " ORDER BY u.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch user details if view_user_id is set
$view_user = null;
if (isset($_GET['view_user_id'])) {
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        // First get the user's role
        $role_sql = "SELECT role FROM users WHERE id = ?";
        $role_stmt = $conn->prepare($role_sql);
        $role_stmt->execute([$_GET['view_user_id']]);
        $user_role = $role_stmt->fetch(PDO::FETCH_ASSOC);

        if ($user_role) {
            // Base query for user information
            $sql = "SELECT u.*, ";
            
            // Add role-specific fields based on the user's role
            switch ($user_role['role']) {
                case 'doctor':
                    $sql .= "d.first_name, d.middle_name, d.last_name, 
                            s.name as specialization, d.license_number, d.contact_number, d.address as doctor_address,
                            NULL as date_of_birth, NULL as gender,
                            NULL as position, NULL as assigned_doctor
                            FROM users u 
                            LEFT JOIN doctors d ON u.id = d.user_id 
                            LEFT JOIN specializations s ON d.specialization_id = s.id
                            WHERE u.id = ?";
                    break;
                    
                case 'patient':
                    $sql .= "p.first_name, p.middle_name, p.last_name,
                            NULL as specialization, NULL as license_number,
                            p.date_of_birth, p.gender, p.address as patient_address,
                            NULL as position, NULL as assigned_doctor
                            FROM users u 
                            LEFT JOIN patients p ON u.id = p.user_id 
                            WHERE u.id = ?";
                    break;
                    
                case 'staff':
                    $sql .= "s.first_name, s.middle_name, s.last_name,
                            NULL as specialization, NULL as license_number,
                            NULL as date_of_birth, NULL as gender,
                            s.contact_number as staff_contact_number, s.address as staff_address
                            FROM users u 
                            LEFT JOIN staff s ON u.id = s.user_id 
                            WHERE u.id = ?";
                    break;
                    
                default:
                    $sql .= "NULL as first_name, NULL as middle_name, NULL as last_name,
                            NULL as specialization, NULL as license_number,
                            NULL as date_of_birth, NULL as gender, NULL as address,
                            NULL as position, NULL as assigned_doctor
                            FROM users u 
                            WHERE u.id = ?";
            }
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([$_GET['view_user_id']]);
            $view_user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($view_user) {
                // Format date fields
                if ($view_user['date_of_birth']) {
                    $view_user['date_of_birth'] = date('Y-m-d', strtotime($view_user['date_of_birth']));
                }
                if ($view_user['created_at']) {
                    $view_user['created_at'] = date('Y-m-d H:i:s', strtotime($view_user['created_at']));
                }
                if ($view_user['updated_at']) {
                    $view_user['updated_at'] = date('Y-m-d H:i:s', strtotime($view_user['updated_at']));
                }
                
                // Remove sensitive information
                unset($view_user['password']);
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching user details: " . $e->getMessage());
    }
}

// Fetch user details for editing if edit_user_id is set
$edit_user = null;
if (isset($_GET['edit_user_id'])) {
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        // First get the user's role and basic info
        $base_sql = "SELECT u.*, 
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
            END as last_name
            FROM users u 
            LEFT JOIN doctors d ON u.id = d.user_id 
            LEFT JOIN patients p ON u.id = p.user_id 
            LEFT JOIN staff s ON u.id = s.user_id 
            WHERE u.id = ?";
            
        $base_stmt = $conn->prepare($base_sql);
        $base_stmt->execute([$_GET['edit_user_id']]);
        $edit_user = $base_stmt->fetch(PDO::FETCH_ASSOC);

        if ($edit_user) {
            // Get role-specific information
            switch ($edit_user['role']) {
                case 'doctor':
                    $role_sql = "SELECT s.name as specialization, d.license_number, d.contact_number, d.address
                                FROM doctors d
                                LEFT JOIN specializations s ON d.specialization_id = s.id
                                WHERE d.user_id = ?";
                    break;
                    
                case 'patient':
                    $role_sql = "SELECT date_of_birth, gender, address 
                                FROM patients 
                                WHERE user_id = ?";
                    break;
                    
                case 'staff':
                    $role_sql = "SELECT contact_number, address 
                                FROM staff 
                                WHERE user_id = ?";
                    break;
            }
            
            if (isset($role_sql)) {
                $role_stmt = $conn->prepare($role_sql);
                $role_stmt->execute([$_GET['edit_user_id']]);
                $role_data = $role_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($role_data) {
                    $edit_user = array_merge($edit_user, $role_data);
                }
            }
            
            // Format date fields
            if (isset($edit_user['date_of_birth'])) {
                $edit_user['date_of_birth'] = date('Y-m-d', strtotime($edit_user['date_of_birth']));
            }
            if (isset($edit_user['created_at'])) {
                $edit_user['created_at'] = date('Y-m-d H:i:s', strtotime($edit_user['created_at']));
            }
            if (isset($edit_user['updated_at'])) {
                $edit_user['updated_at'] = date('Y-m-d H:i:s', strtotime($edit_user['updated_at']));
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching user details for editing: " . $e->getMessage());
    }
}

// Handle staff clinic assignment on form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id']) && isset($_POST['role']) && $_POST['role'] === 'staff') {
    $user_id = intval($_POST['user_id']);
    $assigned_clinics = isset($_POST['assigned_clinics']) ? $_POST['assigned_clinics'] : [];
    // Get staff_id for this user
    $staff_stmt = $conn->prepare("SELECT id FROM staff WHERE user_id = ?");
    $staff_stmt->execute([$user_id]);
    $staff = $staff_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($staff) {
        $staff_id = $staff['id'];
        // Remove all current assignments
        $conn->prepare("DELETE FROM staff_clinics WHERE staff_id = ?")->execute([$staff_id]);
        // Add new assignments
        if (!empty($assigned_clinics)) {
            $insert_stmt = $conn->prepare("INSERT INTO staff_clinics (staff_id, clinic_id) VALUES (?, ?)");
            foreach ($assigned_clinics as $clinic_id) {
                $insert_stmt->execute([$staff_id, $clinic_id]);
            }
        }
    }
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">User Management</h4>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-primary" onclick="printUsers()">
                <i class="material-icons align-middle me-1">print</i>
                Print
            </button>
            <button type="button" class="btn btn-outline-success" onclick="downloadUsers()">
                <i class="material-icons align-middle me-1">download</i>
                Download
            </button>
        <button type="button" class="btn btn-primary" onclick="openAddUserModal()">
            <i class="material-icons align-middle me-1">add</i>
            Add New User
        </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form id="filterForm" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status" onchange="this.form.submit()">
                        <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Role</label>
                    <select class="form-select" name="role" onchange="this.form.submit()">
                        <option value="all" <?php echo $role === 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="doctor" <?php echo $role === 'doctor' ? 'selected' : ''; ?>>Doctors</option>
                        <option value="patient" <?php echo $role === 'patient' ? 'selected' : ''; ?>>Patients</option>
                        <option value="staff" <?php echo $role === 'staff' ? 'selected' : ''; ?>>Staff</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Search</label>
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" placeholder="Search by name, email, or role" value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="material-icons">search</i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card">
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
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr data-user-id="<?php echo $user['id']; ?>">
                                <td><?php echo htmlspecialchars($user['first_name'] ?? 'N/A'); ?></td>
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
                                <td>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                onclick="viewUser(<?php echo $user['id']; ?>)">
                                            <i class="material-icons">visibility</i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                onclick="editUser(<?php echo $user['id']; ?>)">
                                            <i class="material-icons">edit</i>
                                        </button>
                                        <?php if ($user['approval_status'] === 'pending'): ?>
                                            <button type="button" class="btn btn-sm btn-outline-success" 
                                                    onclick="showApproveUserModal(<?php echo $user['id']; ?>)">
                                                <i class="material-icons">check</i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                    onclick="openRejectModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['email']); ?>')">
                                                <i class="material-icons">close</i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- View User Modal -->
<div class="modal fade" id="viewUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">User Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if (isset($view_user) && $view_user): ?>
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="material-icons align-middle me-1">account_circle</i> Basic Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label text-muted small">First Name</label>
                                        <p class="mb-0 fw-bold"><?php echo htmlspecialchars($view_user['first_name'] ?? 'N/A'); ?></p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label text-muted small">Middle Name</label>
                                        <p class="mb-0 fw-bold"><?php echo htmlspecialchars($view_user['middle_name'] ?? 'N/A'); ?></p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label text-muted small">Last Name</label>
                                        <p class="mb-0 fw-bold"><?php echo htmlspecialchars($view_user['last_name'] ?? 'N/A'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="material-icons align-middle me-1">contact_mail</i> Account Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="row mb-2">
                                <div class="col-md-4 text-muted">Email:</div>
                                <div class="col-md-8" id="viewUserEmail"><?php echo htmlspecialchars($view_user['email']); ?></div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label text-muted small">Role</label>
                                        <p class="mb-0">
                                            <span class="badge bg-<?php 
                                                echo match($view_user['role']) {
                                                    'doctor' => 'primary',
                                                    'patient' => 'success',
                                                    'staff' => 'warning',
                                                    'admin' => 'danger',
                                                    default => 'secondary'
                                                };
                                            ?>">
                                                <?php echo ucfirst($view_user['role']); ?>
                                            </span>
                                        </p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label text-muted small">Status</label>
                                        <p class="mb-0">
                                            <span class="badge bg-<?php 
                                                echo match($view_user['approval_status']) {
                                                    'approved' => 'success',
                                                    'pending' => 'warning',
                                                    'rejected' => 'danger',
                                                    default => 'secondary'
                                                };
                                            ?>">
                                                <?php echo ucfirst($view_user['approval_status']); ?>
                                            </span>
                                        </p>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label class="form-label text-muted small">Registered On</label>
                                        <p class="mb-0 fw-bold"><?php echo date('F d, Y h:i A', strtotime($view_user['created_at'])); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($view_user['role'] === 'doctor'): ?>
                        <div class="card mb-3">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><i class="material-icons align-middle me-1">medical_services</i> Professional Information</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label text-muted small">Specialization</label>
                                            <p class="mb-0 fw-bold"><?php echo htmlspecialchars($view_user['specialization'] ?? 'N/A'); ?></p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label text-muted small">License Number</label>
                                            <p class="mb-0 fw-bold"><?php echo htmlspecialchars($view_user['license_number'] ?? 'N/A'); ?></p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label text-muted small">Contact Number</label>
                                            <p class="mb-0 fw-bold"><?php echo htmlspecialchars($view_user['contact_number'] ?? 'N/A'); ?></p>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="mb-3">
                                            <label class="form-label text-muted small">Address</label>
                                            <p class="mb-0 fw-bold"><?php echo htmlspecialchars($view_user['doctor_address'] ?? 'N/A'); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($view_user['role'] === 'patient'): ?>
                        <div class="card mb-3">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0"><i class="material-icons align-middle me-1">person</i> Patient Information</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label text-muted small">Date of Birth</label>
                                            <p class="mb-0 fw-bold"><?php echo $view_user['date_of_birth'] ?? 'N/A'; ?></p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label text-muted small">Gender</label>
                                            <p class="mb-0 fw-bold"><?php echo $view_user['gender'] ? ucfirst($view_user['gender']) : 'N/A'; ?></p>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="mb-3">
                                            <label class="form-label text-muted small">Address</label>
                                            <p class="mb-0 fw-bold"><?php echo htmlspecialchars($view_user['patient_address'] ?? 'N/A'); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($view_user['role'] === 'staff'): ?>
                        <div class="card mb-3">
                            <div class="card-header bg-warning text-dark">
                                <h6 class="mb-0"><i class="material-icons align-middle me-1">badge</i> Staff Information</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label text-muted small">Contact Number</label>
                                            <p class="mb-0 fw-bold"><?php echo htmlspecialchars($view_user['staff_contact_number'] ?? 'N/A'); ?></p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label text-muted small">Address</label>
                                            <p class="mb-0 fw-bold"><?php echo htmlspecialchars($view_user['staff_address'] ?? 'N/A'); ?></p>
                                        </div>
                                    </div>

                                </div>
                                <?php
                                // Fetch assigned clinics for this staff
                                $assigned_stmt = $conn->prepare("SELECT c.name, c.address FROM staff_clinics sc 
                                                               JOIN staff s ON sc.staff_id = s.id 
                                                               JOIN clinics c ON sc.clinic_id = c.id 
                                                               WHERE s.user_id = ?");
                                $assigned_stmt->execute([$view_user['id']]);
                                $assigned_clinics = $assigned_stmt->fetchAll(PDO::FETCH_ASSOC);
                                ?>
                                <div class="mb-3">
                                    <label class="form-label text-muted small">Assigned Clinics</label>
                                    <?php if (count($assigned_clinics) > 0): ?>
                                        <ul class="mb-0">
                                            <?php foreach ($assigned_clinics as $clinic): ?>
                                                <li><strong><?php echo htmlspecialchars($clinic['name']); ?></strong> <small class="text-muted"><?php echo htmlspecialchars($clinic['address']); ?></small></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p class="mb-0 text-muted">No clinics assigned.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-danger">
                        User not found or error occurred while fetching user details.
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if (isset($edit_user) && $edit_user): ?>
                <form id="editUserForm" method="POST">
                    <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
                    <input type="hidden" name="role" value="<?php echo $edit_user['role']; ?>">

                    <!-- Basic Information -->
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="material-icons align-middle me-1">account_circle</i> Basic Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" name="first_name" value="<?php echo htmlspecialchars($edit_user['first_name'] ?? ''); ?>" required class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Middle Name</label>
                                    <input type="text" name="middle_name" value="<?php echo htmlspecialchars($edit_user['middle_name'] ?? ''); ?>" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" name="last_name" value="<?php echo htmlspecialchars($edit_user['last_name'] ?? ''); ?>" required class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Account Information -->
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="material-icons align-middle me-1">contact_mail</i> Account Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" name="email" value="<?php echo htmlspecialchars($edit_user['email']); ?>" required class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Status</label>
                                    <select name="approval_status" class="form-select">
                                        <option value="pending" <?php echo $edit_user['approval_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="approved" <?php echo $edit_user['approval_status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                        <option value="rejected" <?php echo $edit_user['approval_status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Role-specific Fields -->
                    <?php if ($edit_user['role'] === 'doctor'): ?>
                        <div class="card mb-3">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><i class="material-icons align-middle me-1">medical_services</i> Doctor Information</h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Specialization</label>
                                        <input type="text" name="specialization" value="<?php echo htmlspecialchars($edit_user['specialization'] ?? ''); ?>" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">License Number</label>
                                        <input type="text" name="license_number" value="<?php echo htmlspecialchars($edit_user['license_number'] ?? ''); ?>" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Contact Number</label>
                                        <input type="text" name="contact_number" value="<?php echo htmlspecialchars($edit_user['contact_number'] ?? ''); ?>" class="form-control">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Address</label>
                                        <textarea name="address" rows="3" class="form-control"><?php echo htmlspecialchars($edit_user['address'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($edit_user['role'] === 'patient'): ?>
                        <div class="card mb-3">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0"><i class="material-icons align-middle me-1">person</i> Patient Information</h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Date of Birth</label>
                                        <input type="date" name="date_of_birth" value="<?php echo $edit_user['date_of_birth'] ?? ''; ?>" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Gender</label>
                                        <select name="gender" class="form-select">
                                            <option value="">Select Gender</option>
                                            <option value="male" <?php echo ($edit_user['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                            <option value="female" <?php echo ($edit_user['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                            <option value="other" <?php echo ($edit_user['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Address</label>
                                        <textarea name="address" rows="3" class="form-control"><?php echo htmlspecialchars($edit_user['address'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($edit_user['role'] === 'staff'): ?>
                        <div class="card mb-3">
                            <div class="card-header bg-warning text-dark">
                                <h6 class="mb-0"><i class="material-icons align-middle me-1">badge</i> Staff Information</h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Contact Number</label>
                                        <input type="tel" name="contact_number" value="<?php echo htmlspecialchars($edit_user['contact_number'] ?? ''); ?>" class="form-control">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Address</label>
                                        <textarea name="address" class="form-control" rows="2"><?php echo htmlspecialchars($edit_user['address'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Staff Clinic Assignment PHP-only UI -->
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="material-icons align-middle me-1">local_hospital</i> Assigned Clinics</h6>
                            </div>
                            <div class="card-body">
                                <?php
                                // Fetch all clinics
                                $clinics_stmt = $conn->prepare("SELECT id, name, address FROM clinics WHERE status = 'active'");
                                $clinics_stmt->execute();
                                $all_clinics = $clinics_stmt->fetchAll(PDO::FETCH_ASSOC);
                                // Fetch assigned clinics for this staff
                                $assigned_stmt = $conn->prepare("SELECT sc.clinic_id FROM staff_clinics sc 
                                                               JOIN staff s ON sc.staff_id = s.id 
                                                               WHERE s.user_id = ?");
                                $assigned_stmt->execute([$edit_user['id']]);
                                $assigned_clinics = array_column($assigned_stmt->fetchAll(PDO::FETCH_ASSOC), 'clinic_id');
                                ?>
                                <div class="row">
                                    <?php foreach ($all_clinics as $clinic): ?>
                                        <div class="col-md-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="assigned_clinics[]" value="<?php echo $clinic['id']; ?>" id="clinic_<?php echo $clinic['id']; ?>" <?php echo in_array($clinic['id'], $assigned_clinics) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="clinic_<?php echo $clinic['id']; ?>">
                                                    <strong><?php echo htmlspecialchars($clinic['name']); ?></strong><br>
                                                    <small class='text-muted'><?php echo htmlspecialchars($clinic['address']); ?></small>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                </form>
                <?php else: ?>
                    <div class="alert alert-danger">
                        User not found or error occurred while fetching user details.
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitEditUserForm()">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addUserForm">
                    <div class="row g-3">
                        <!-- Basic Information -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" name="email" required class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password <span class="text-danger">*</span></label>
                                <input type="password" name="password" required class="form-control" autocomplete="new-password">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Role <span class="text-danger">*</span></label>
                                <select name="role" required onchange="toggleRoleFields()" class="form-select">
                                    <option value="">Select Role</option>
                                    <option value="doctor">Doctor</option>
                                    <option value="patient">Patient</option>
                                    <option value="staff">Staff</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Name Fields -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">First Name <span class="text-danger">*</span></label>
                                <input type="text" name="first_name" required class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Middle Name</label>
                                <input type="text" name="middle_name" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                <input type="text" name="last_name" required class="form-control">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Role-specific Fields -->
                    <div id="doctorFields" class="d-none">
                        <hr>
                        <h6 class="mb-3">Doctor Information</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Specialization</label>
                                    <input type="text" name="specialization" class="form-control" placeholder="e.g., Cardiology, Pediatrics">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">License Number</label>
                                    <input type="text" name="license_number" class="form-control" placeholder="Enter license number">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Contact Number</label>
                                    <input type="tel" name="contact_number" class="form-control" placeholder="Enter contact number">
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label">Address</label>
                                    <textarea name="address" rows="3" class="form-control" placeholder="Enter address"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="patientFields" class="d-none">
                        <hr>
                        <h6 class="mb-3">Patient Information</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Date of Birth</label>
                                    <input type="date" name="date_of_birth" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Gender</label>
                                    <select name="gender" class="form-select">
                                        <option value="">Select Gender</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label">Address</label>
                                    <textarea name="address" rows="3" class="form-control"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="staffFields" class="d-none">
                        <hr>
                        <h6 class="mb-3">Staff Information</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Contact Number</label>
                                    <input type="tel" name="contact_number" class="form-control" placeholder="Enter contact number">
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label">Address</label>
                                    <textarea name="address" rows="3" class="form-control" placeholder="Enter address"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitAddUserForm()">Add User</button>
            </div>
        </div>
    </div>
</div>

<!-- Approve User Modal -->
<div class="modal fade" id="approveUserModal" tabindex="-1" aria-labelledby="approveUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="approveUserModalLabel">Approve User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to approve this user?</p>
                <input type="hidden" id="approveUserId">
                <div class="mb-3">
                    <label for="approveNotes" class="form-label">Notes (Optional)</label>
                    <textarea class="form-control" id="approveNotes" rows="3" placeholder="Add any notes for the user..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="submitApproveUser()">Approve User</button>
            </div>
        </div>
    </div>
</div>

<!-- Reject User Modal -->
<div class="modal fade" id="rejectUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to reject <span id="rejectUsername" class="fw-bold"></span>?</p>
                <form id="rejectUserForm">
                    <input type="hidden" id="rejectUserId" name="userId">
                    <div class="mb-3">
                        <label class="form-label">Reason for Rejection <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="rejectReason" name="reason" rows="3" required
                                placeholder="Please provide a reason for rejecting this user"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="submitRejectUser()">Reject User</button>
            </div>
        </div>
    </div>
</div>

<!-- Show/Hide Password Toggle -->
<script src="/Medbuddy/assets/js/common.js"></script>

<script>
// Initialize Bootstrap tooltips and popovers
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Add role change event listener if the element exists
    const roleSelect = document.querySelector('select[name="role"]');
    if (roleSelect) {
        roleSelect.addEventListener('change', toggleRoleFields);
    }

    // Remove the JS submit handler for editUserForm
    // const editUserForm = document.getElementById('editUserForm');
    // if (editUserForm) {
    //     editUserForm.addEventListener('submit', function(e) {
    //         e.preventDefault();
    //         submitEditUserForm();
    //     });
    // }
});

function viewUser(userId) {
    // Show loading state in modal
    const modal = document.getElementById('viewUserModal');
    const modalBody = modal.querySelector('.modal-body');
    modalBody.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading user details...</p>
        </div>
    `;
    
    // Show the modal first
    const viewModal = new bootstrap.Modal(modal);
    viewModal.show();
    
    // Fetch user details via AJAX
    fetch(`?page=users&view_user_id=${userId}&ajax=1`)
        .then(response => response.text())
        .then(html => {
            // Extract the modal content from the response
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newModalBody = doc.querySelector('#viewUserModal .modal-body');
            
            if (newModalBody) {
                modalBody.innerHTML = newModalBody.innerHTML;
            } else {
                modalBody.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="material-icons align-middle me-1">error</i>
                        User not found or error occurred while fetching user details.
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error fetching user details:', error);
            modalBody.innerHTML = `
                <div class="alert alert-danger">
                    <i class="material-icons align-middle me-1">error</i>
                    An error occurred while fetching user details. Please try again.
                </div>
            `;
        });
    
    // Update URL without reloading
    const url = new URL(window.location.href);
    const params = new URLSearchParams(url.search);
    params.set('view_user_id', userId);
    window.history.pushState({}, '', `${url.pathname}?${params.toString()}`);
}

function editUser(userId) {
    // Hide any open modals before showing the edit modal
    document.querySelectorAll('.modal.show').forEach(modal => {
        const instance = bootstrap.Modal.getInstance(modal);
        if (instance) instance.hide();
    });

    // Show loading state in modal
    const modal = document.getElementById('editUserModal');
    const modalBody = modal.querySelector('.modal-body');
    modalBody.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading user details for editing...</p>
        </div>
    `;
    
    // Show the modal first
    const editModal = new bootstrap.Modal(modal);
    editModal.show();
    
    // Fetch user details via AJAX
    fetch(`?page=users&edit_user_id=${userId}&ajax=1`)
        .then(response => response.text())
        .then(html => {
            // Extract the modal content from the response
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newModalBody = doc.querySelector('#editUserModal .modal-body');
            
            if (newModalBody) {
                modalBody.innerHTML = newModalBody.innerHTML;
                
                // Re-initialize form elements and event listeners
                initializeEditForm();
            } else {
                modalBody.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="material-icons align-middle me-1">error</i>
                        User not found or error occurred while fetching user details.
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error fetching user details for editing:', error);
            modalBody.innerHTML = `
                <div class="alert alert-danger">
                    <i class="material-icons align-middle me-1">error</i>
                    An error occurred while fetching user details. Please try again.
                </div>
            `;
        });
    
    // Update URL without reloading
    const url = new URL(window.location.href);
    const params = new URLSearchParams(url.search);
    params.set('edit_user_id', userId);
    window.history.pushState({}, '', `${url.pathname}?${params.toString()}`);
}

// Function to initialize edit form elements
function initializeEditForm() {
    // Re-initialize role field toggle
    const roleSelect = document.querySelector('#editUserModal select[name="role"]');
    if (roleSelect) {
        roleSelect.addEventListener('change', toggleRoleFields);
        // Trigger initial toggle based on current value
        toggleRoleFields();
    }
    
    // Re-initialize form submission
    const editForm = document.getElementById('editUserForm');
    if (editForm) {
        // Remove existing event listeners
        const newForm = editForm.cloneNode(true);
        editForm.parentNode.replaceChild(newForm, editForm);
        
        // Add new event listener
        newForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitEditUserForm();
        });
    }
}

function submitEditUserForm() {
    const form = document.getElementById('editUserForm');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    // Ensure assigned_clinics is always an array
    data.assigned_clinics = Array.from(form.querySelectorAll('input[name="assigned_clinics[]"]:checked')).map(cb => cb.value);
    // Show loading state
    const submitButton = document.querySelector('#editUserModal .btn-primary');
    const originalContent = submitButton.innerHTML;
    submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
    submitButton.disabled = true;
    
    fetch('/Medbuddy/api/admin/edit-user.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(result => {
        if (result.success) {
            // Show success message
            Swal.fire({
                title: 'Success!',
                text: 'User updated successfully',
                icon: 'success',
                confirmButtonText: 'OK',
                confirmButtonColor: '#28a745',
                timer: 2000,
                timerProgressBar: true,
                showConfirmButton: false
            }).then(() => {
                // Close modal and reload page
            const modal = bootstrap.Modal.getInstance(document.getElementById('editUserModal'));
            modal.hide();
            location.reload();
            });
        } else {
            throw new Error(result.error || 'Failed to update user');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            title: 'Error!',
            text: error.message || 'Failed to update user',
            icon: 'error',
            confirmButtonText: 'OK',
            confirmButtonColor: '#dc3545'
        });
    })
    .finally(() => {
        // Reset button state
        submitButton.innerHTML = originalContent;
        submitButton.disabled = false;
    });
}

function openAddUserModal() {
    const modal = new bootstrap.Modal(document.getElementById('addUserModal'));
    modal.show();
}

function toggleRoleFields() {
    const role = document.querySelector('select[name="role"]').value;
    document.getElementById('doctorFields').classList.add('d-none');
    document.getElementById('patientFields').classList.add('d-none');
    document.getElementById('staffFields').classList.add('d-none');
    
    if (role === 'doctor') {
        document.getElementById('doctorFields').classList.remove('d-none');
    } else if (role === 'patient') {
        document.getElementById('patientFields').classList.remove('d-none');
    } else if (role === 'staff') {
        document.getElementById('staffFields').classList.remove('d-none');
    }
}

function submitAddUserForm() {
    const form = document.getElementById('addUserForm');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    fetch('/Medbuddy/api/admin/add-user.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('addUserModal'));
            modal.hide();
            location.reload();
        } else {
            alert(result.error || 'Failed to add user');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding the user');
    });
}

function showApproveUserModal(userId) {
    document.getElementById('approveUserId').value = userId;
    new bootstrap.Modal(document.getElementById('approveUserModal')).show();
}

function openRejectModal(userId, email) {
    document.getElementById('rejectUserId').value = userId;
    document.getElementById('rejectUsername').textContent = email;
    const modal = new bootstrap.Modal(document.getElementById('rejectUserModal'));
    modal.show();
}

function submitRejectUser() {
    const userId = document.getElementById('rejectUserId').value;
    const reason = document.getElementById('rejectReason').value;
    const submitButton = document.querySelector('#rejectUserModal .btn-danger');
    
    if (!reason.trim()) {
        Swal.fire({
            title: 'Warning!',
            text: 'Please provide a reason for rejection',
            icon: 'warning',
            confirmButtonText: 'OK',
            confirmButtonColor: '#ffc107'
        });
        return;
    }
    
    // Show loading state
    const originalContent = submitButton.innerHTML;
    submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Rejecting...';
    submitButton.disabled = true;

    fetch('../../api/admin/reject-user.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ 
            userId: userId,
            reason: reason
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('rejectUserModal'));
            modal.hide();
            
            // Show success message with SweetAlert2
            Swal.fire({
                title: 'Success!',
                text: 'User has been rejected successfully',
                icon: 'success',
                confirmButtonText: 'OK',
                confirmButtonColor: '#28a745',
                timer: 2000,
                timerProgressBar: true,
                showConfirmButton: false
            }).then(() => {
                // Reload the page
                location.reload();
            });
        } else {
            throw new Error(data.error || 'Failed to reject user');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // Show error message with SweetAlert2
        Swal.fire({
            title: 'Error!',
            text: error.message,
            icon: 'error',
            confirmButtonText: 'OK',
            confirmButtonColor: '#dc3545'
        });
    })
    .finally(() => {
        // Reset button state
        submitButton.innerHTML = originalContent;
        submitButton.disabled = false;
    });
}

function submitApproveUser() {
    const userId = document.getElementById('approveUserId').value;
    const notes = document.getElementById('approveNotes').value;
    const submitButton = document.querySelector('#approveUserModal .btn-success');
    
    // Show loading state
    const originalContent = submitButton.innerHTML;
    submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Approving...';
    submitButton.disabled = true;

    // Create form data
    const formData = new FormData();
    formData.append('userId', userId);
    formData.append('notes', notes);

    // Send the request
    fetch('/Medbuddy/api/admin/approve-user.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('approveUserModal'));
            modal.hide();
            
            // Show success message with SweetAlert2
            Swal.fire({
                title: 'Success!',
                text: 'User has been approved successfully',
                icon: 'success',
                confirmButtonText: 'OK',
                confirmButtonColor: '#28a745',
                timer: 2000,
                timerProgressBar: true,
                showConfirmButton: false
            }).then(() => {
                // Update the status badge in the table
                const row = document.querySelector(`tr[data-user-id="${userId}"]`);
                if (row) {
                    const statusCell = row.querySelector('td:nth-child(7)');
                    if (statusCell) {
                        statusCell.innerHTML = '<span class="badge bg-success">Approved</span>';
                    }
                    
                    // Remove approve/reject buttons
                    const actionCell = row.querySelector('td:last-child .btn-group');
                    if (actionCell) {
                        const approveBtn = actionCell.querySelector('.btn-outline-success');
                        const rejectBtn = actionCell.querySelector('.btn-outline-danger');
                        if (approveBtn) approveBtn.remove();
                        if (rejectBtn) rejectBtn.remove();
                    }
                }
                
                // Reload the page to ensure all data is in sync
                location.reload();
            });
        } else {
            throw new Error(data.error || 'Failed to approve user');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // Show error message with SweetAlert2
        Swal.fire({
            title: 'Error!',
            text: error.message,
            icon: 'error',
            confirmButtonText: 'OK',
            confirmButtonColor: '#dc3545'
        });
    })
    .finally(() => {
        // Reset button state
        submitButton.innerHTML = originalContent;
        submitButton.disabled = false;
    });
}

// Add event listeners when the document is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all modals
    var modals = document.querySelectorAll('.modal');
    modals.forEach(function(modal) {
        new bootstrap.Modal(modal);
    });
});

// Add this to your existing JavaScript code
function exportUsers(format) {
    // Get current filter values
    const status = document.querySelector('select[name="status"]').value;
    const role = document.querySelector('select[name="role"]').value;
    const search = document.querySelector('input[name="search"]').value;

    // Build the export URL with current filters
    const exportUrl = `../../api/admin/export-users.php?format=${format}&status=${status}&role=${role}&search=${encodeURIComponent(search)}`;

    // Create a temporary link and trigger the download
    const link = document.createElement('a');
    link.href = exportUrl;
    link.download = `users_${format}_${new Date().toISOString().split('T')[0]}.${format}`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Add these functions to your existing JavaScript code
function printUsers() {
    // Get current filter values
    const status = document.querySelector('select[name="status"]').value;
    const role = document.querySelector('select[name="role"]').value;
    const search = document.querySelector('input[name="search"]').value;

    // Open print window with filtered data
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Users List - MedBuddy</title>
            <style>
                @page {
                    size: A4;
                    margin: 2cm;
                }
                body { 
                    font-family: Arial, sans-serif;
                    margin: 0;
                    padding: 20px;
                    color: #333;
                }
                .header {
                    text-align: center;
                    margin-bottom: 30px;
                    border-bottom: 2px solid #007bff;
                    padding-bottom: 10px;
                }
                .header h1 {
                    color: #007bff;
                    margin: 0;
                    font-size: 24px;
                }
                .header p {
                    margin: 5px 0;
                    color: #666;
                }
                .filters {
                    margin-bottom: 20px;
                    padding: 10px;
                    background-color: #f8f9fa;
                    border-radius: 5px;
                }
                .filters p {
                    margin: 5px 0;
                    font-size: 14px;
                }
                table { 
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 20px;
                    font-size: 12px;
                }
                th, td { 
                    border: 1px solid #dee2e6;
                    padding: 12px 8px;
                    text-align: left;
                }
                th { 
                    background-color: #007bff;
                    color: white;
                    font-weight: bold;
                }
                tr:nth-child(even) {
                    background-color: #f8f9fa;
                }
                .badge { 
                    padding: 4px 8px;
                    border-radius: 4px;
                    color: white;
                    font-size: 11px;
                    font-weight: bold;
                }
                .badge-success { background-color: #28a745; }
                .badge-warning { background-color: #ffc107; color: #000; }
                .badge-danger { background-color: #dc3545; }
                .badge-primary { background-color: #007bff; }
                .badge-secondary { background-color: #6c757d; }
                .footer {
                    margin-top: 30px;
                    text-align: center;
                    font-size: 12px;
                    color: #666;
                    border-top: 1px solid #dee2e6;
                    padding-top: 10px;
                }
                .no-print {
                    display: none;
                }
                @media print {
                    .no-print { display: none; }
                    body { padding: 0; }
                    .header { margin-bottom: 20px; }
                    table { margin-top: 10px; }
                }
            </style>
        </head>
        <body>
            <div class="no-print" style="margin-bottom: 20px;">
                <button onclick="window.print()" style="padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">Print</button>
                <button onclick="window.close()" style="padding: 8px 16px; background: #6c757d; color: white; border: none; border-radius: 4px; margin-left: 10px; cursor: pointer;">Close</button>
            </div>

            <div class="header">
                <h1>MedBuddy - Users List</h1>
                <p>Generated on: ${new Date().toLocaleString()}</p>
            </div>

            <div class="filters">
                <p><strong>Filters Applied:</strong></p>
                <p>Status: ${status === 'all' ? 'All' : status.charAt(0).toUpperCase() + status.slice(1)}</p>
                <p>Role: ${role === 'all' ? 'All' : role.charAt(0).toUpperCase() + role.slice(1)}</p>
                ${search ? `<p>Search: ${search}</p>` : ''}
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Registered</th>
                    </tr>
                </thead>
                <tbody>
                    ${Array.from(document.querySelectorAll('tbody tr')).map(row => `
                        <tr>
                            <td>${row.cells[0].textContent} ${row.cells[1].textContent} ${row.cells[2].textContent}</td>
                            <td>${row.cells[3].textContent}</td>
                            <td>${row.cells[4].textContent}</td>
                            <td>${row.cells[5].innerHTML}</td>
                            <td>${row.cells[6].textContent}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>

            <div class="footer">
                <p>This document was generated from the MedBuddy System</p>
                <p>Page 1 of 1</p>
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
}

function downloadUsers() {
    // Get current filter values
    const status = document.querySelector('select[name="status"]').value;
    const role = document.querySelector('select[name="role"]').value;
    const search = document.querySelector('input[name="search"]').value;

    // Create CSV content
    const headers = ['Name', 'Email', 'Role', 'Status', 'Registered'];
    const rows = Array.from(document.querySelectorAll('tbody tr')).map(row => [
        `${row.cells[0].textContent} ${row.cells[1].textContent} ${row.cells[2].textContent}`.trim(),
        row.cells[3].textContent,
        row.cells[4].textContent,
        row.cells[5].textContent,
        row.cells[6].textContent
    ]);

    // Convert to CSV
    const csvContent = [
        headers.join(','),
        ...rows.map(row => row.map(cell => `"${cell.replace(/"/g, '""')}"`).join(','))
    ].join('\n');

    // Create and trigger download
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', `users_${new Date().toISOString().split('T')[0]}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Show modal if view_user_id is present in URL
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    
    // Show view modal if view_user_id is present
    if (urlParams.has('view_user_id')) {
        const viewModal = new bootstrap.Modal(document.getElementById('viewUserModal'));
        viewModal.show();
    }
    
    // Show edit modal if edit_user_id is present
    if (urlParams.has('edit_user_id')) {
        const editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
        editModal.show();
    }
    
    // Add modal cleanup event listeners
    const viewModal = document.getElementById('viewUserModal');
    const editModal = document.getElementById('editUserModal');
    
    if (viewModal) {
        viewModal.addEventListener('hidden.bs.modal', function() {
            // Clear URL parameter when modal is closed
            const url = new URL(window.location.href);
            const params = new URLSearchParams(url.search);
            params.delete('view_user_id');
            window.history.pushState({}, '', `${url.pathname}?${params.toString()}`);
        });
    }
    
    if (editModal) {
        editModal.addEventListener('hidden.bs.modal', function() {
            // Clear URL parameter when modal is closed
            const url = new URL(window.location.href);
            const params = new URLSearchParams(url.search);
            params.delete('edit_user_id');
            window.history.pushState({}, '', `${url.pathname}?${params.toString()}`);
        });
    }
});
</script> 

<?php
// Handle AJAX response
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    $output = ob_get_clean();
    echo $output;
    exit();
}
?> 