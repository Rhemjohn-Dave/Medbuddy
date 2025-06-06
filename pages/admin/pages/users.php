<?php
// Check if this file is being included
if (!defined('ADMIN_ACCESS')) {
    header("Location: ../../index.php");
    exit();
}

// Fetch users with their details
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
            WHEN u.role = 'staff' THEN s.position
            ELSE NULL 
        END as position,
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
    $sql .= " AND (u.username LIKE ? OR u.email LIKE ? OR 
             CONCAT(COALESCE(d.first_name, p.first_name, s.first_name), ' ', 
                   COALESCE(d.middle_name, p.middle_name, s.middle_name), ' ',
                   COALESCE(d.last_name, p.last_name, s.last_name)) LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
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
                            d.specialization, d.license_number,
                            NULL as date_of_birth, NULL as gender, NULL as address,
                            NULL as position, NULL as assigned_doctor
                            FROM users u 
                            LEFT JOIN doctors d ON u.id = d.user_id 
                            WHERE u.id = ?";
                    break;
                    
                case 'patient':
                    $sql .= "p.first_name, p.middle_name, p.last_name,
                            NULL as specialization, NULL as license_number,
                            p.date_of_birth, p.gender, p.address,
                            NULL as position, NULL as assigned_doctor
                            FROM users u 
                            LEFT JOIN patients p ON u.id = p.user_id 
                            WHERE u.id = ?";
                    break;
                    
                case 'staff':
                    $sql .= "s.first_name, s.middle_name, s.last_name,
                            NULL as specialization, NULL as license_number,
                            NULL as date_of_birth, NULL as gender, NULL as address,
                            s.position, s.contact_number, s.address
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
                    $role_sql = "SELECT specialization, license_number 
                                FROM doctors 
                                WHERE user_id = ?";
                    break;
                    
                case 'patient':
                    $role_sql = "SELECT date_of_birth, gender, address 
                                FROM patients 
                                WHERE user_id = ?";
                    break;
                    
                case 'staff':
                    $role_sql = "SELECT position, assigned_doctor_id 
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
                        <input type="text" class="form-control" name="search" placeholder="Search by name, email, or username" value="<?php echo htmlspecialchars($search); ?>">
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
                            <th>First Name</th>
                            <th>Middle Name</th>
                            <th>Last Name</th>
                            <th>Username</th>
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
                                <td><?php echo htmlspecialchars($user['middle_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($user['last_name'] ?? 'N/A'); ?></td>
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
                                                    onclick="openRejectModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
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
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label text-muted small">Username</label>
                                        <p class="mb-0 fw-bold"><?php echo htmlspecialchars($view_user['username']); ?></p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label text-muted small">Email</label>
                                        <p class="mb-0 fw-bold"><?php echo htmlspecialchars($view_user['email']); ?></p>
                                    </div>
                                </div>
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
                                            <p class="mb-0 fw-bold"><?php echo htmlspecialchars($view_user['address'] ?? 'N/A'); ?></p>
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
                                            <label class="form-label text-muted small">Position</label>
                                            <p class="mb-0 fw-bold"><?php echo htmlspecialchars($view_user['position'] ?? 'N/A'); ?></p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label text-muted small">Assigned Doctor</label>
                                            <p class="mb-0 fw-bold"><?php echo htmlspecialchars($view_user['assigned_doctor'] ?? 'N/A'); ?></p>
                                        </div>
                                    </div>
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
                <form id="editUserForm">
                        <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
                        <input type="hidden" name="role" value="<?php echo $edit_user['role']; ?>">
                        
                    <div class="row g-3">
                        <!-- Name Fields -->
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" name="first_name" value="<?php echo htmlspecialchars($edit_user['first_name'] ?? ''); ?>" required class="form-control">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Middle Name</label>
                                    <input type="text" name="middle_name" value="<?php echo htmlspecialchars($edit_user['middle_name'] ?? ''); ?>" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" name="last_name" value="<?php echo htmlspecialchars($edit_user['last_name'] ?? ''); ?>" required class="form-control">
                                </div>
                            </div>
                        </div>

                        <!-- Account Information -->
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Username <span class="text-danger">*</span></label>
                                    <input type="text" name="username" value="<?php echo htmlspecialchars($edit_user['username']); ?>" required class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" name="email" value="<?php echo htmlspecialchars($edit_user['email']); ?>" required class="form-control">
                            </div>
                        </div>
                    </div>

                    <!-- Role-specific Fields -->
                        <?php if ($edit_user['role'] === 'doctor'): ?>
                        <hr>
                        <h6 class="mb-3">Doctor Information</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Specialization</label>
                                        <input type="text" name="specialization" value="<?php echo htmlspecialchars($edit_user['specialization'] ?? ''); ?>" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">License Number</label>
                                        <input type="text" name="license_number" value="<?php echo htmlspecialchars($edit_user['license_number'] ?? ''); ?>" class="form-control">
                                </div>
                            </div>
                        </div>
                        <?php elseif ($edit_user['role'] === 'patient'): ?>
                        <hr>
                        <h6 class="mb-3">Patient Information</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Date of Birth</label>
                                        <input type="date" name="date_of_birth" value="<?php echo $edit_user['date_of_birth'] ?? ''; ?>" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Gender</label>
                                        <select name="gender" class="form-select">
                                        <option value="">Select Gender</option>
                                            <option value="male" <?php echo ($edit_user['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                            <option value="female" <?php echo ($edit_user['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                            <option value="other" <?php echo ($edit_user['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label">Address</label>
                                        <textarea name="address" rows="3" class="form-control"><?php echo htmlspecialchars($edit_user['address'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                        <?php elseif ($edit_user['role'] === 'staff'): ?>
                            <hr>
                            <h6 class="mb-3">Staff Information</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Position</label>
                                        <input type="text" name="position" value="<?php echo htmlspecialchars($edit_user['position'] ?? ''); ?>" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Contact Number</label>
                                        <input type="tel" name="contact_number" value="<?php echo htmlspecialchars($edit_user['contact_number'] ?? ''); ?>" class="form-control">
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label class="form-label">Address</label>
                                        <textarea name="address" class="form-control" rows="2"><?php echo htmlspecialchars($edit_user['address'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label">Status</label>
                            <select name="approval_status" class="form-select">
                                <option value="pending" <?php echo $edit_user['approval_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="approved" <?php echo $edit_user['approval_status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="rejected" <?php echo $edit_user['approval_status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
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
                                <label class="form-label">Username <span class="text-danger">*</span></label>
                                <input type="text" name="username" required class="form-control">
                            </div>
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
                                    <input type="text" name="specialization" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">License Number</label>
                                    <input type="text" name="license_number" class="form-control">
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

<!-- Add this in the head section or before closing body tag -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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

    // Add form submit event listener if the form exists
    const editUserForm = document.getElementById('editUserForm');
    if (editUserForm) {
        editUserForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitEditUserForm();
        });
    }
});

function viewUser(userId) {
    // Get the current URL and parameters
    const url = new URL(window.location.href);
    const params = new URLSearchParams(url.search);
    
    // Update or add the view_user_id parameter
    params.set('view_user_id', userId);
            
    // Update the URL without reloading the page
    window.history.pushState({}, '', `${url.pathname}?${params.toString()}`);
            
    // Show the modal
    const viewModal = new bootstrap.Modal(document.getElementById('viewUserModal'));
    viewModal.show();

    // Reload the page to refresh the data
    location.reload();
}

function editUser(userId) {
    // Get the current URL and parameters
    const url = new URL(window.location.href);
    const params = new URLSearchParams(url.search);
    
    // Update or add the edit_user_id parameter
    params.set('edit_user_id', userId);
            
    // Update the URL without reloading the page
    window.history.pushState({}, '', `${url.pathname}?${params.toString()}`);
            
            // Show the modal
            const editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
            editModal.show();
}

function submitEditUserForm() {
    const form = document.getElementById('editUserForm');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
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
    
    if (role === 'doctor') {
        document.getElementById('doctorFields').classList.remove('d-none');
    } else if (role === 'patient') {
        document.getElementById('patientFields').classList.remove('d-none');
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

function openRejectModal(userId, username) {
    document.getElementById('rejectUserId').value = userId;
    document.getElementById('rejectUsername').textContent = username;
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
                        <th>Username</th>
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
                            <td>${row.cells[6].innerHTML}</td>
                            <td>${row.cells[7].textContent}</td>
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
    const headers = ['Name', 'Username', 'Email', 'Role', 'Status', 'Registered'];
    const rows = Array.from(document.querySelectorAll('tbody tr')).map(row => [
        `${row.cells[0].textContent} ${row.cells[1].textContent} ${row.cells[2].textContent}`.trim(),
        row.cells[3].textContent,
        row.cells[4].textContent,
        row.cells[5].textContent,
        row.cells[6].textContent,
        row.cells[7].textContent
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
    if (urlParams.has('view_user_id')) {
        const viewModal = new bootstrap.Modal(document.getElementById('viewUserModal'));
        viewModal.show();
    }
});
</script> 