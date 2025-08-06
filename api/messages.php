<?php
// Turn off error display to prevent HTML output
ini_set('display_errors', 0);
error_reporting(0);

// Start output buffering to catch any unwanted output
ob_start();

session_start();
header('Content-Type: application/json');

require_once '../config/database.php';
// require_once '../models/Message.php'; // Remove this line as the model doesn't exist

// Check for session (basic auth check)
if (!isset($_SESSION['user_id'])) {
    ob_end_clean(); // Clear any output buffer
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];
$database = new Database();
$db = $database->getConnection();
// $message_model = new Message($db); // Remove this line

$request_method = $_SERVER['REQUEST_METHOD'];

switch ($request_method) {
    case 'GET':
        // Handle fetching messages for a conversation
        if (isset($_GET['partner_id'])) {
            $partner_id = filter_var($_GET['partner_id'], FILTER_SANITIZE_NUMBER_INT);
            if ($partner_id) {
                try {
                    // Fetch all messages between the current user and the partner
                    // This query selects messages where sender is user_id and receiver is partner_id OR sender is partner_id and receiver is user_id
                    $sql = "SELECT m.*,
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
                            WHERE (m.sender_id = ? AND m.receiver_id = ?)
                               OR (m.sender_id = ? AND m.receiver_id = ?)
                            ORDER BY m.created_at ASC"; // Order by time for chat history

                    $stmt = $db->prepare($sql);
                    $stmt->execute([$user_id, $partner_id, $partner_id, $user_id]);
                    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    ob_end_clean(); // Clear any output buffer
                    echo json_encode(['success' => true, 'messages' => $messages]);
                } catch (Exception $e) {
                    ob_end_clean(); // Clear any output buffer
                    http_response_code(500); // Internal Server Error
                    echo json_encode(['success' => false, 'message' => 'Error fetching messages: ' . $e->getMessage()]);
                     error_log("API Error fetching messages: " . $e->getMessage());
                }
            } else {
                ob_end_clean(); // Clear any output buffer
                http_response_code(400); // Bad Request
                echo json_encode(['success' => false, 'message' => 'Invalid partner_id']);
            }
        } else {
            ob_end_clean(); // Clear any output buffer
            http_response_code(400); // Bad Request
            echo json_encode(['success' => false, 'message' => 'Missing partner_id parameter']);
        }
        break;

    case 'POST':
        // Handle sending a new message
        $data = json_decode(file_get_contents("php://input"), true);

        if (isset($data['receiver_id'], $data['message'])) {
            $receiver_id = filter_var($data['receiver_id'], FILTER_SANITIZE_NUMBER_INT);
            // Subject is less relevant for chat view, use a default or derive if needed
            $subject = filter_var($data['subject'] ?? 'New Message', FILTER_SANITIZE_STRING);
            $message_content = filter_var($data['message'], FILTER_SANITIZE_STRING);

            if ($receiver_id && $message_content) {
                try {
                    // Check if receiver_id exists and is approved
                    $user_check_sql = "SELECT id, role FROM users WHERE id = ? AND approval_status = 'approved' LIMIT 1";
                    $user_check_stmt = $db->prepare($user_check_sql);
                    $user_check_stmt->execute([$receiver_id]);
                    $receiver = $user_check_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$receiver) {
                         ob_end_clean(); // Clear any output buffer
                         http_response_code(404); // Not Found
                         echo json_encode(['success' => false, 'message' => 'Recipient not found or not approved.']);
                         exit();
                    }
                    
                    // Get sender's role to check permissions
                    $sender_check_sql = "SELECT role FROM users WHERE id = ? LIMIT 1";
                    $sender_check_stmt = $db->prepare($sender_check_sql);
                    $sender_check_stmt->execute([$user_id]);
                    $sender = $sender_check_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Prevent patients from messaging other patients
                    if ($sender && $sender['role'] === 'patient' && $receiver['role'] === 'patient') {
                         ob_end_clean(); // Clear any output buffer
                         http_response_code(403); // Forbidden
                         echo json_encode(['success' => false, 'message' => 'Patients cannot message other patients.']);
                         exit();
                    }

                    // Insert message into database
                    $insert_sql = "INSERT INTO messages (sender_id, receiver_id, subject, message, is_read) VALUES (?, ?, ?, ?, 0)";
                    $insert_stmt = $db->prepare($insert_sql);

                    if ($insert_stmt->execute([$user_id, $receiver_id, $subject, $message_content])) {
                        // Optional: Get the newly inserted message to return in the response
                        // $last_insert_id = $db->lastInsertId();
                        // $new_message = $message_model->getMessageById($last_insert_id); // Would need this method if using a model

                        ob_end_clean(); // Clear any output buffer
                        echo json_encode(['success' => true, 'message' => 'Message sent successfully.']);
                    } else {
                        ob_end_clean(); // Clear any output buffer
                        http_response_code(500); // Internal Server Error
                        echo json_encode(['success' => false, 'message' => 'Failed to send message.']);
                        error_log("API Error sending message: Insert failed.");
                    }
                } catch (Exception $e) {
                    ob_end_clean(); // Clear any output buffer
                    http_response_code(500); // Internal Server Error
                    echo json_encode(['success' => false, 'message' => 'Error sending message: ' . $e->getMessage()]);
                    error_log("API Error sending message: " . $e->getMessage());
                }
            } else {
                ob_end_clean(); // Clear any output buffer
                http_response_code(400); // Bad Request
                echo json_encode(['success' => false, 'message' => 'Missing required fields: receiver_id or message']);
            }
        } else {
            ob_end_clean(); // Clear any output buffer
            http_response_code(400); // Bad Request
            echo json_encode(['success' => false, 'message' => 'Invalid input data']);
        }
        break;

    case 'PUT':
        // Handle marking messages as read
        // Expecting partner_id as a query parameter
        if (isset($_GET['mark_read'])) {
            $partner_id = filter_var($_GET['mark_read'], FILTER_SANITIZE_NUMBER_INT);

            if ($partner_id) {
                try {
                    // Mark messages sent by the partner to the current user as read
                    $update_sql = "UPDATE messages SET is_read = TRUE
                                 WHERE sender_id = ? AND receiver_id = ? AND is_read = FALSE";
                    $update_stmt = $db->prepare($update_sql);

                    if ($update_stmt->execute([$partner_id, $user_id])) {
                         // Check how many rows were affected
                         $rows_affected = $update_stmt->rowCount();
                         if ($rows_affected > 0) {
                            echo json_encode(['success' => true, 'message' => $rows_affected . ' messages marked as read.']);
                         } else {
                             // This means no unread messages were found from this partner for this user
                             echo json_encode(['success' => true, 'message' => 'No unread messages from this partner.']);
                         }
                    } else {
                         http_response_code(500); // Internal Server Error
                         echo json_encode(['success' => false, 'message' => 'Failed to mark messages as read.']);
                         error_log("API Error marking messages as read: Update failed.");
                    }
                } catch (Exception $e) {
                     http_response_code(500); // Internal Server Error
                     echo json_encode(['success' => false, 'message' => 'Error marking messages as read: ' . $e->getMessage()]);
                     error_log("API Error marking messages as read: " . $e->getMessage());
                }
            } else {
                http_response_code(400); // Bad Request
                echo json_encode(['success' => false, 'message' => 'Invalid partner_id for marking as read.']);
            }
        } else {
            http_response_code(400); // Bad Request
            echo json_encode(['success' => false, 'message' => 'Missing partner_id parameter for marking as read.']);
        }
        break;

    default:
        // Invalid request method
        http_response_code(405); // Method Not Allowed
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        break;
} 