<?php
// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is a patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

// Include database connection
require_once '../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Start transaction
    $db->beginTransaction();

    // Get user ID from session
    $user_id = $_SESSION['user_id'];

    // Handle file upload if profile image is provided
    $profile_image = null;
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../uploads/profile_images/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // Generate unique filename
        $file_extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
        $filename = uniqid('profile_') . '.' . $file_extension;
        $target_path = $upload_dir . $filename;

        // Move uploaded file
        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_path)) {
            $profile_image = 'uploads/profile_images/' . $filename;
        }
    }

    // Update users table
    $query = "UPDATE users SET 
              email = :email 
              WHERE id = :user_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $_POST['email']);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();

    // Update patients table
    $query = "UPDATE patients SET 
              first_name = :first_name,
              last_name = :last_name,
              phone = :phone,
              date_of_birth = :date_of_birth,
              gender = :gender,
              blood_type = :blood_type,
              address = :address" .
              ($profile_image ? ", profile_image = :profile_image" : "") . "
              WHERE user_id = :user_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':first_name', $_POST['first_name']);
    $stmt->bindParam(':last_name', $_POST['last_name']);
    $stmt->bindParam(':phone', $_POST['phone']);
    $stmt->bindParam(':date_of_birth', $_POST['date_of_birth']);
    $stmt->bindParam(':gender', $_POST['gender']);
    $stmt->bindParam(':blood_type', $_POST['blood_type']);
    $stmt->bindParam(':address', $_POST['address']);
    $stmt->bindParam(':user_id', $user_id);
    
    if ($profile_image) {
        $stmt->bindParam(':profile_image', $profile_image);
    }
    
    $stmt->execute();

    // Commit transaction
    $db->commit();

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully'
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    // Log error
    error_log("Error updating patient profile: " . $e->getMessage());

    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error updating profile. Please try again.'
    ]);
} 