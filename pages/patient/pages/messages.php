<?php
require_once '../../config/database.php';

// Define the base URL for API endpoints
$base_url = '/Medbuddy';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/index.php");
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $user_id = $_SESSION['user_id'];

    // Fetch messages and group them by conversation partner
    // This query gets the latest message for each conversation thread
    $latest_messages_sql = "
        SELECT m.*,
        u_sender.role as sender_role,
        CASE 
            WHEN u_sender.role = 'doctor' THEN d.first_name 
            WHEN u_sender.role = 'patient' THEN p_sender.first_name 
            WHEN u_sender.role = 'staff' THEN s.first_name
            ELSE NULL 
        END as sender_first_name,
        CASE 
            WHEN u_sender.role = 'doctor' THEN d.last_name 
            WHEN u_sender.role = 'patient' THEN p_sender.last_name 
            WHEN u_sender.role = 'staff' THEN s.last_name
            ELSE NULL 
        END as sender_last_name,
        u_receiver.role as receiver_role,
        CASE 
            WHEN u_receiver.role = 'doctor' THEN d2.first_name 
            WHEN u_receiver.role = 'patient' THEN p_receiver.first_name 
            WHEN u_receiver.role = 'staff' THEN s2.first_name
            ELSE NULL 
        END as receiver_first_name,
        CASE 
            WHEN u_receiver.role = 'doctor' THEN d2.last_name 
            WHEN u_receiver.role = 'patient' THEN p_receiver.last_name 
            WHEN u_receiver.role = 'staff' THEN s2.last_name
            ELSE NULL 
        END as receiver_last_name
        FROM messages m
        JOIN users u_sender ON m.sender_id = u_sender.id
        JOIN users u_receiver ON m.receiver_id = u_receiver.id
        LEFT JOIN doctors d ON u_sender.id = d.user_id 
        LEFT JOIN patients p_sender ON u_sender.id = p_sender.user_id 
        LEFT JOIN staff s ON u_sender.id = s.user_id 
        LEFT JOIN doctors d2 ON u_receiver.id = d2.user_id 
        LEFT JOIN patients p_receiver ON u_receiver.id = p_receiver.user_id 
        LEFT JOIN staff s2 ON u_receiver.id = s2.user_id 
        WHERE m.id IN (
            SELECT MAX(id)
            FROM messages
            WHERE sender_id = ? OR receiver_id = ?
            GROUP BY LEAST(sender_id, receiver_id), GREATEST(sender_id, receiver_id)
        )
        ORDER BY m.created_at DESC";
    
    $latest_messages_stmt = $conn->prepare($latest_messages_sql);
    $latest_messages_stmt->execute([$user_id, $user_id]);
    $latest_messages = $latest_messages_stmt->fetchAll(PDO::FETCH_ASSOC);

     // Get users for recipient selection in compose modal (patients can only message doctors and staff)
    $users_sql = "SELECT u.id, u.role, u.email,
        CASE 
            WHEN u.role = 'doctor' THEN d.first_name 
            WHEN u.role = 'staff' THEN s.first_name
            ELSE NULL 
        END as first_name,
        CASE 
            WHEN u.role = 'doctor' THEN d.last_name 
            WHEN u.role = 'staff' THEN s.last_name
            ELSE NULL 
        END as last_name
        FROM users u
        LEFT JOIN doctors d ON u.id = d.user_id 
        LEFT JOIN staff s ON u.id = s.user_id 
        WHERE u.id != ? AND u.approval_status = 'approved' 
        AND (u.role = 'doctor' OR u.role = 'staff')
        ORDER BY u.role, first_name, last_name";
    
    $users_stmt = $conn->prepare($users_sql);
    $users_stmt->execute([$_SESSION['user_id']]);
    $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Error fetching messages: " . $e->getMessage());
    $latest_messages = [];
     $users = [];
}
?>

<head>
    <!-- SweetAlert2 CSS and JS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
</head>

<body>
<div class="container-fluid py-4">
    <div class="row g-4">
        <!-- Left Message Panel -->
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Messages</h5>
                    <div>
                         <button type="button" class="btn btn-outline-secondary btn-sm me-2" onclick="location.reload()">
                             <i class="material-icons align-middle">refresh</i>
                         </button>
                        <button type="button" class="btn btn-primary btn-sm" id="composeNewBtn">
            <i class="material-icons align-middle me-1">add</i>
                            Compose New
        </button>
    </div>
                </div>
                 <div class="card-body p-2">
                    <div class="mb-3">
                        <input type="text" class="form-control" id="messageSearch" placeholder="Search messages...">
                    </div>
                    <div id="messageThreads" class="list-group list-group-flush">
                        <?php if (empty($latest_messages)): ?>
                            <div class="text-center text-muted py-4">
                                <span class="material-icons mb-2" style="font-size: 2rem;">mail_outline</span>
                                <p class="mb-0">No messages yet.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($latest_messages as $message): ?>
                                <?php
                                    // Determine the conversation partner (the user who is not the current user)
                                    $partner_id = ($message['sender_id'] == $user_id) ? $message['receiver_id'] : $message['sender_id'];
                                    $partner_name = ($message['sender_id'] == $user_id) ? 
                                                        htmlspecialchars($message['receiver_first_name'] . ' ' . $message['receiver_last_name']) : 
                                                        htmlspecialchars($message['sender_first_name'] . ' ' . $message['sender_last_name']);
                                     $partner_role = ($message['sender_id'] == $user_id) ? 
                                                        ucfirst($message['receiver_role']) : 
                                                        ucfirst($message['sender_role']);
                                    
                                    // Highlight if the latest message in the thread is unread and sent by the partner
                                     $is_unread_latest = ($message['receiver_id'] == $user_id && !$message['is_read']);
                                ?>
                                <a href="#" class="list-group-item list-group-item-action <?php echo $is_unread_latest ? 'bg-light fw-bold' : ''; ?>" 
                                   data-partner-id="<?php echo $partner_id; ?>">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1 <?php echo $is_unread_latest ? 'text-primary' : ''; ?>">
                                            <?php echo $partner_name; ?>
                                        </h6>
                                        <small class="text-muted"><?php echo date('M d, Y h:i A', strtotime($message['created_at'])); ?></small>
                                    </div>
                                    <p class="mb-1 text-muted"><?php echo htmlspecialchars(substr($message['message'], 0, 50)) . (strlen($message['message']) > 50 ? '...' : ''); ?></p>
                                     <small class="text-muted"><?php echo $partner_role; ?></small>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Chat Panel -->
        <div class="col-md-8">
            <div class="card h-100 d-flex flex-column">
                <div class="card-header bg-light">
                    <h5 class="mb-0" id="chatPartnerName">Select a message to read</h5>
                    <small class="text-muted" id="chatPartnerRole"></small>
                </div>
                <div class="card-body overflow-auto flex-grow-1" id="chatHistory">
                    <!-- Chat messages will be loaded here -->
                    <div class="text-center text-muted py-5">
                        <span class="material-icons mb-2" style="font-size: 3rem;">forum</span>
                        <p>Select a conversation from the left panel to view messages.</p>
                    </div>
                </div>
                <div class="card-footer bg-light">
                    <div class="input-group">
                        <input type="text" class="form-control" id="messageInput" placeholder="Type your message here...">
                        <button class="btn btn-primary" type="button" id="sendMessageBtn">
                            <i class="material-icons">send</i>
                                            </button>
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
                                    <?php
                                    $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                                    if (empty(trim($name))) {
                                        $name = $user['email'];
                                    }
                                    echo htmlspecialchars($name . ' (' . ucfirst($user['role']) . ')');
                                    ?>
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
                <button type="button" class="btn btn-primary" id="sendComposeBtn">Send Message</button>
            </div>
        </div>
    </div>
</div>


<style>
.card {
    transition: transform 0.2s;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border: none;
    margin-bottom: 1rem;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.card-header {
    background-color: #f8f9fa;
}

.list-group-item-action {
    cursor: pointer;
}

.list-group-item-action:hover {
    background-color: #e9ecef;
}

.list-group-flush > .list-group-item {
    border-width: 0 0 1px 0;
}

#messageThreads {
    max-height: calc(100vh - 250px); /* Adjust based on header/search height */
    overflow-y: auto;
}

#chatHistory {
    max-height: calc(100vh - 250px); /* Adjust based on header/input height */
     min-height: 400px; /* Minimum height */
}

.message-bubble {
    padding: 10px 15px;
    border-radius: 15px;
    margin-bottom: 10px;
    max-width: 80%;
    word-wrap: break-word;
}

.message-sent {
    background-color: #dcf8c6; /* Light green */
    align-self: flex-end;
}

.message-received {
    background-color: #e9e9eb; /* Light grey */
    align-self: flex-start;
}

.message-timestamp {
    font-size: 0.75rem;
    color: #6c757d;
    margin-top: 5px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let currentChatPartnerId = null;
    let composeModal = null;

    // Initialize the compose modal
    function initComposeModal() {
        const modalElement = document.getElementById('composeModal');
        if (modalElement) {
            composeModal = new bootstrap.Modal(modalElement);
}
    }

    // Initialize modal on page load
    initComposeModal();

    // Add click handler for compose button
    const composeNewBtn = document.getElementById('composeNewBtn');
    if (composeNewBtn) {
        composeNewBtn.addEventListener('click', function() {
            if (!composeModal) {
                initComposeModal();
            }
            if (composeModal) {
                composeModal.show();
            }
        });
    }

    // Handle sending new message from compose modal
    const sendComposeBtn = document.getElementById('sendComposeBtn');
    if (sendComposeBtn) {
        sendComposeBtn.addEventListener('click', function() {
            const form = document.getElementById('composeForm');
            if (!form) return;

            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            
            // Basic validation
            if (!data.receiver_id || !data.subject || !data.message) {
                alert('Please fill in all fields.');
                return;
            }
            
            // Show loading indicator
            const sendButton = this;
            sendButton.disabled = true;
            sendButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sending...';

            fetch('../../api/messages.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                sendButton.disabled = false;
                sendButton.innerHTML = 'Send Message';
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Message sent successfully!',
                        showConfirmButton: false,
                        timer: 1500
                    });
                    if (composeModal) {
                        composeModal.hide();
                    }
                    form.reset();
                    location.reload();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'Error sending message: ' + (data.message || 'Unknown error')
                    });
                }
            })
            .catch(error => {
                sendButton.disabled = false;
                sendButton.innerHTML = 'Send Message';
                console.error('Error sending message:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'An error occurred while sending the message: ' + error.message
                });
            });
        });
    }

    // Handle clicking on a message thread
    document.querySelectorAll('#messageThreads .list-group-item-action').forEach(item => {
        item.addEventListener('click', function(event) {
            event.preventDefault();
            const partnerId = this.dataset.partnerId;
            const partnerName = this.querySelector('h6').textContent.trim();
            const partnerRole = this.querySelector('small').textContent.trim();

            // Remove active class from all threads and add to the clicked one
            document.querySelectorAll('#messageThreads .list-group-item-action').forEach(thread => {
                thread.classList.remove('active', 'bg-light', 'fw-bold');
                thread.querySelector('h6').classList.remove('text-primary');
            });
            this.classList.add('active');
            // Remove highlighting after clicking an unread message
            this.classList.remove('bg-light', 'fw-bold');
            this.querySelector('h6').classList.remove('text-primary');

            // Update chat header
            document.getElementById('chatPartnerName').textContent = partnerName;
            document.getElementById('chatPartnerRole').textContent = partnerRole;

            // Load chat history
            currentChatPartnerId = partnerId;
            loadChatHistory(partnerId);
            
            // Mark messages as read
            markMessagesAsRead(partnerId);
        });
    });

    // Function to load chat history
    function loadChatHistory(partnerId) {
        const chatHistoryDiv = document.getElementById('chatHistory');
        chatHistoryDiv.innerHTML = '<div class="text-center text-muted py-5"><span class="spinner-border text-primary"></span> Loading messages...</div>';

        fetch(`../../api/messages.php?partner_id=${partnerId}`)
            .then(response => {
            if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                chatHistoryDiv.innerHTML = '';
                if (data.success && data.messages.length > 0) {
                    data.messages.forEach(message => {
                        const messageElement = document.createElement('div');
                        messageElement.classList.add('d-flex', message.sender_id == <?php echo $user_id; ?> ? 'justify-content-end' : 'justify-content-start');
                        
                        messageElement.innerHTML = `
                            <div>
                                <div class="message-bubble ${message.sender_id == <?php echo $user_id; ?> ? 'message-sent' : 'message-received'}">
                                    ${htmlspecialchars(message.message)}
                                </div>
                                <div class="message-timestamp text-end">
                                    ${formatTimestamp(message.created_at)}
                                </div>
                            </div>
                        `;
                        chatHistoryDiv.appendChild(messageElement);
                    });
                    chatHistoryDiv.scrollTop = chatHistoryDiv.scrollHeight;
                } else {
                    chatHistoryDiv.innerHTML = '<div class="text-center text-muted py-5"><span class="material-icons mb-2" style="font-size: 3rem;">forum</span><p>No messages in this conversation.</p></div>';
                }
            })
            .catch(error => {
                console.error('Error loading chat history:', error);
                chatHistoryDiv.innerHTML = '<div class="text-center text-danger py-5"><span class="material-icons mb-2" style="font-size: 3rem;">error_outline</span><p>Error loading messages.</p></div>';
            });
    }

    // Function to mark messages as read
    function markMessagesAsRead(partnerId) {
        fetch(`../../api/messages.php?mark_read=${partnerId}`, {
            method: 'PUT'
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(data => { throw new Error(data.message || `HTTP error! status: ${response.status}`); });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                console.log('Messages marked as read:', data.message);
            } else {
                console.error('Failed to mark messages as read.', data.message);
            }
        })
        .catch(error => {
            console.error('Error marking messages as read:', error);
        });
    }

    // Helper function to format timestamp
    function formatTimestamp(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diffInSeconds = Math.floor((now - date) / 1000);

        if (diffInSeconds < 60) {
            return diffInSeconds + ' seconds ago';
        } else if (diffInSeconds < 3600) {
            return Math.floor(diffInSeconds / 60) + ' minutes ago';
        } else if (diffInSeconds < 86400) {
            return Math.floor(diffInSeconds / 3600) + ' hours ago';
        } else if (diffInSeconds < 2592000) {
            return Math.floor(diffInSeconds / 86400) + ' days ago';
        } else {
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}
    }

    // Helper function for HTML escaping
    function htmlspecialchars(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>'"]/g, function(m) { return map[m]; });
    }

    // Handle message input
    const messageInput = document.getElementById('messageInput');
    const sendMessageBtn = document.getElementById('sendMessageBtn');

    if (messageInput && sendMessageBtn) {
        // Send message on Enter key
        messageInput.addEventListener('keypress', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                sendMessageBtn.click();
            }
        });

        // Send message on button click
        sendMessageBtn.addEventListener('click', function() {
            const messageText = messageInput.value.trim();
            if (messageText === '' || currentChatPartnerId === null) {
                return;
            }

            const sendButton = this;
            sendButton.disabled = true;
            sendButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

            fetch('../../api/messages.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    receiver_id: currentChatPartnerId,
                    subject: 'Regarding our conversation',
                    message: messageText
                })
            })
            .then(response => {
                sendButton.disabled = false;
                sendButton.innerHTML = '<i class="material-icons">send</i>';
                if (!response.ok) {
                    return response.json().then(data => { throw new Error(data.message || `HTTP error! status: ${response.status}`); });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    const chatHistoryDiv = document.getElementById('chatHistory');
                    const messageElement = document.createElement('div');
                    messageElement.classList.add('d-flex', 'justify-content-end');
                    messageElement.innerHTML = `
                        <div>
                            <div class="message-bubble message-sent">
                                ${htmlspecialchars(messageText)}
                            </div>
                            <div class="message-timestamp text-end">
                                ${formatTimestamp(new Date())}
                            </div>
                        </div>
                    `;
                    if (chatHistoryDiv.querySelector('.text-center.text-muted')) {
                        chatHistoryDiv.innerHTML = '';
                    }
                    chatHistoryDiv.appendChild(messageElement);
                    chatHistoryDiv.scrollTop = chatHistoryDiv.scrollHeight;
                    messageInput.value = '';

                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Message sent successfully!',
                        showConfirmButton: false,
                        timer: 1500
                    });

                } else {
                     Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'Error sending message: ' + (data.message || 'Unknown error')
                    });
                }
            })
            .catch(error => {
                sendButton.disabled = false;
                sendButton.innerHTML = '<i class="material-icons">send</i>';
                console.error('Error sending message:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'An error occurred while sending message: ' + error.message
                });
            });
    });
}

    // Handle message search
    const messageSearch = document.getElementById('messageSearch');
    if (messageSearch) {
        messageSearch.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            document.querySelectorAll('#messageThreads .list-group-item-action').forEach(item => {
                const textContent = item.textContent.toLowerCase();
                if (textContent.includes(searchTerm)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });
}
});
</script> 