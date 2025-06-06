<?php
require_once '../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/index.php");
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Get user's messages
    // Get inbox messages
    $inbox_sql = "SELECT m.*, 
        u_sender.username as sender_username,
        u_sender.role as sender_role,
        CASE 
            WHEN u_sender.role = 'doctor' THEN d.first_name 
            WHEN u_sender.role = 'patient' THEN p.first_name 
            WHEN u_sender.role = 'staff' THEN s.first_name
            ELSE NULL 
        END as sender_first_name,
        CASE 
            WHEN u_sender.role = 'doctor' THEN d.last_name 
            WHEN u_sender.role = 'patient' THEN p.last_name 
            WHEN u_sender.role = 'staff' THEN s.last_name
            ELSE NULL 
        END as sender_last_name
        FROM messages m
        JOIN users u_sender ON m.sender_id = u_sender.id
        LEFT JOIN doctors d ON u_sender.id = d.user_id 
        LEFT JOIN patients p ON u_sender.id = p.user_id 
        LEFT JOIN staff s ON u_sender.id = s.user_id 
        WHERE m.receiver_id = ?
        ORDER BY m.created_at DESC";
    
    $inbox_stmt = $conn->prepare($inbox_sql);
    $inbox_stmt->execute([$_SESSION['user_id']]);
    $inbox_messages = $inbox_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get sent messages
    $sent_sql = "SELECT m.*, 
        u_receiver.username as receiver_username,
        u_receiver.role as receiver_role,
        CASE 
            WHEN u_receiver.role = 'doctor' THEN d.first_name 
            WHEN u_receiver.role = 'patient' THEN p.first_name 
            WHEN u_receiver.role = 'staff' THEN s.first_name
            ELSE NULL 
        END as receiver_first_name,
        CASE 
            WHEN u_receiver.role = 'doctor' THEN d.last_name 
            WHEN u_receiver.role = 'patient' THEN p.last_name 
            WHEN u_receiver.role = 'staff' THEN s.last_name
            ELSE NULL 
        END as receiver_last_name
        FROM messages m
        JOIN users u_receiver ON m.receiver_id = u_receiver.id
        LEFT JOIN doctors d ON u_receiver.id = d.user_id 
        LEFT JOIN patients p ON u_receiver.id = p.user_id 
        LEFT JOIN staff s ON u_receiver.id = s.user_id 
        WHERE m.sender_id = ?
        ORDER BY m.created_at DESC";
    
    $sent_stmt = $conn->prepare($sent_sql);
    $sent_stmt->execute([$_SESSION['user_id']]);
    $sent_messages = $sent_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get users for recipient selection
    $users_sql = "SELECT u.id, u.username, u.role,
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
        WHERE u.id != ? AND u.approval_status = 'approved'
        ORDER BY u.role, first_name, last_name";
    
    $users_stmt = $conn->prepare($users_sql);
    $users_stmt->execute([$_SESSION['user_id']]);
    $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Error fetching messages: " . $e->getMessage());
}
?>

<head>
    <!-- SweetAlert2 CSS and JS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
</head>

<body>
<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">Messages</h4>
        <button type="button" class="btn btn-primary" onclick="openComposeModal()">
            <i class="material-icons align-middle me-1">add</i>
            New Message
        </button>
    </div>

    <!-- Messages Tabs -->
    <div class="card">
        <div class="card-body">
            <ul class="nav nav-tabs" id="messagesTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="inbox-tab" data-bs-toggle="tab" data-bs-target="#inbox" type="button" role="tab">
                        Inbox
                        <?php if (count(array_filter($inbox_messages, fn($m) => !$m['is_read'])) > 0): ?>
                            <span class="badge bg-danger ms-1"><?php echo count(array_filter($inbox_messages, fn($m) => !$m['is_read'])); ?></span>
                        <?php endif; ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="sent-tab" data-bs-toggle="tab" data-bs-target="#sent" type="button" role="tab">Sent</button>
                </li>
            </ul>

            <div class="tab-content mt-3" id="messagesTabContent">
                <!-- Inbox Tab -->
                <div class="tab-pane fade show active" id="inbox" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>From</th>
                                    <th>Subject</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inbox_messages as $message): ?>
                                    <tr class="<?php echo !$message['is_read'] ? 'fw-bold' : ''; ?>">
                                        <td>
                                            <?php echo htmlspecialchars($message['sender_first_name'] . ' ' . $message['sender_last_name']); ?>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo ucfirst($message['sender_role']); ?>
                                            </small>
                                        </td>
                                        <td><?php echo htmlspecialchars($message['subject']); ?></td>
                                        <td><?php echo date('M d, Y h:i A', strtotime($message['created_at'])); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    onclick="viewMessage(<?php echo $message['id']; ?>)">
                                                <i class="material-icons">visibility</i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                    onclick="deleteMessage(<?php echo $message['id']; ?>)">
                                                <i class="material-icons">delete</i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Sent Tab -->
                <div class="tab-pane fade" id="sent" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>To</th>
                                    <th>Subject</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sent_messages as $message): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($message['receiver_first_name'] . ' ' . $message['receiver_last_name']); ?>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo ucfirst($message['receiver_role']); ?>
                                            </small>
                                        </td>
                                        <td><?php echo htmlspecialchars($message['subject']); ?></td>
                                        <td><?php echo date('M d, Y h:i A', strtotime($message['created_at'])); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    onclick="viewMessage(<?php echo $message['id']; ?>)">
                                                <i class="material-icons">visibility</i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                    onclick="deleteMessage(<?php echo $message['id']; ?>)">
                                                <i class="material-icons">delete</i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Compose Message Modal -->
<div class="modal fade" id="composeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="composeForm">
                    <div class="mb-3">
                        <label class="form-label">To</label>
                        <select name="receiver_id" required class="form-select">
                            <option value="">Select Recipient</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?> 
                                    (<?php echo ucfirst($user['role']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <input type="text" name="subject" required class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message</label>
                        <textarea name="message" required rows="5" class="form-control"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="sendMessage()">Send Message</button>
            </div>
        </div>
    </div>
</div>

<!-- View Message Modal -->
<div class="modal fade" id="viewMessageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="messageContent">
                    <!-- Message content will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="replyToMessage()">Reply</button>
            </div>
        </div>
    </div>
</div>

<script>
// Define base URL for API endpoints - using absolute path
const BASE_URL = window.location.origin + '/Medbuddy';

function openComposeModal() {
    const modal = new bootstrap.Modal(document.getElementById('composeModal'));
    modal.show();
}

function viewMessage(messageId) {
    // Show loading state
    const modal = new bootstrap.Modal(document.getElementById('viewMessageModal'));
    document.getElementById('messageContent').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2 text-muted">Loading message...</p>
        </div>
    `;
    modal.show();

    // Log the URL for debugging
    const url = `${BASE_URL}/api/get-message.php?id=${messageId}`;
    console.log('Fetching from:', url);

    fetch(url)
        .then(async response => {
            const data = await response.json();
            console.log('Response data:', data); // Debug log
            
            if (!response.ok) {
                if (data && data.error) {
                    throw new Error(data.error);
                }
                throw new Error(`Server error (${response.status}): ${data.error || 'Unknown error'}`);
            }
            return data;
        })
        .then(data => {
            // Check if we have a valid message object
            if (data.success && data.message) {
                const message = data.message;
                // Format the date if it's not already formatted
                const messageDate = new Date(message.created_at).toLocaleString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: 'numeric',
                    hour12: true
                });
                
                const content = `
                    <div class="mb-3">
                        <label class="form-label text-muted small">From</label>
                        <p class="mb-0 fw-bold">${message.sender_name || `${message.sender_first_name} ${message.sender_last_name}`}</p>
                        <small class="text-muted">${message.sender_role}</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Subject</label>
                        <p class="mb-0 fw-bold">${message.subject}</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Date</label>
                        <p class="mb-0">${messageDate}</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Message</label>
                        <p class="mb-0">${message.message}</p>
                    </div>
                `;
                document.getElementById('messageContent').innerHTML = content;
            } else {
                throw new Error('Invalid message data received from server');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('messageContent').innerHTML = `
                <div class="alert alert-danger">
                    <i class="material-icons align-middle me-2">error</i>
                    <strong>Error loading message:</strong><br>
                    ${error.message}
                </div>
            `;
        });
}

function sendMessage() {
    const form = document.getElementById('composeForm');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    // Validate recipient
    if (!data.receiver_id) {
        Swal.fire({
            title: 'Error!',
            text: 'Please select a recipient',
            icon: 'error',
            confirmButtonText: 'OK',
            confirmButtonColor: '#dc3545'
        });
        return;
    }
    
    // Show loading state
    const submitButton = document.querySelector('#composeModal .btn-primary');
    const originalContent = submitButton.innerHTML;
    submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Sending...';
    submitButton.disabled = true;

    fetch(`${BASE_URL}/api/send-message.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(async response => {
        const data = await response.json();
        if (!response.ok) {
            throw new Error(data.error || 'Failed to send message');
        }
        return data;
    })
    .then(result => {
        if (result.success) {
            // Close modal and show success message
            const modal = bootstrap.Modal.getInstance(document.getElementById('composeModal'));
            modal.hide();
            
            // Reset form
            form.reset();
            
            Swal.fire({
                title: 'Success!',
                text: 'Message sent successfully',
                icon: 'success',
                confirmButtonText: 'OK',
                confirmButtonColor: '#28a745',
                timer: 2000,
                timerProgressBar: true,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        } else {
            throw new Error(result.error || 'Failed to send message');
        }
    })
    .catch(error => {
        console.error('Error:', error);
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

function deleteMessage(messageId) {
    Swal.fire({
        title: 'Are you sure?',
        text: "This message will be permanently deleted!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`${BASE_URL}/api/delete-message.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ message_id: messageId })
            })
            .then(async response => {
                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.error || 'Failed to delete message');
                }
                return data;
            })
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Deleted!',
                        text: 'Message has been deleted.',
                        icon: 'success',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#28a745',
                        timer: 2000,
                        timerProgressBar: true,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    throw new Error(data.error || 'Failed to delete message');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    title: 'Error!',
                    text: error.message,
                    icon: 'error',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#dc3545'
                });
            });
        }
    });
}

function replyToMessage() {
    const messageContent = document.getElementById('messageContent');
    if (!messageContent) {
        console.error('Message content element not found');
        return;
    }

    const senderElement = messageContent.querySelector('p.mb-0.fw-bold');
    const subjectElement = messageContent.querySelectorAll('p.mb-0.fw-bold')[1];
    
    if (!senderElement || !subjectElement) {
        console.error('Required message elements not found');
        return;
    }

    const senderName = senderElement.textContent;
    const subject = subjectElement.textContent;
    
    // Close view modal
    const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewMessageModal'));
    if (viewModal) {
        viewModal.hide();
    }
    
    // Open compose modal
    const composeModal = new bootstrap.Modal(document.getElementById('composeModal'));
    
    // Set reply subject
    const subjectInput = document.querySelector('#composeForm input[name="subject"]');
    if (subjectInput) {
        subjectInput.value = `Re: ${subject}`;
    }
    
    // Show compose modal
    composeModal.show();
}
</script> 
</body>
</html> 