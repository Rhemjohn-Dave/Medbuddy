<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Get posted data
$data = json_decode(file_get_contents("php://input"));

// Make sure data is not empty
if(
    !empty($data->firstName) &&
    !empty($data->lastName) &&
    !empty($data->email) &&
    !empty($data->password) &&
    !empty($data->role)
) {
    try {
        // Check if email already exists
        $check_query = "SELECT id FROM users WHERE email = ?";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute([$data->email]);
        
        if($check_stmt->rowCount() > 0) {
            http_response_code(400);
            echo json_encode(array("message" => "Email already exists."));
            exit();
        }

        // Check if contact number already exists (for all roles)
        if (!empty($data->contactNumber)) {
            $contact_exists = false;
            // Check in doctors
            $stmt = $db->prepare("SELECT id FROM doctors WHERE contact_number = ?");
            $stmt->execute([$data->contactNumber]);
            if ($stmt->rowCount() > 0) $contact_exists = true;
            // Check in patients
            $stmt = $db->prepare("SELECT id FROM patients WHERE contact_number = ?");
            $stmt->execute([$data->contactNumber]);
            if ($stmt->rowCount() > 0) $contact_exists = true;
            // Check in staff
            $stmt = $db->prepare("SELECT id FROM staff WHERE contact_number = ?");
            $stmt->execute([$data->contactNumber]);
            if ($stmt->rowCount() > 0) $contact_exists = true;
            if ($contact_exists) {
                http_response_code(400);
                echo json_encode(array("message" => "Contact number already exists."));
                exit();
            }
        }

        // Start transaction
        $db->beginTransaction();

        // Insert user
        $user_query = "INSERT INTO users (password, email, role, approval_status) VALUES (?, ?, ?, 'pending')";
        $user_stmt = $db->prepare($user_query);
        $hashed_password = password_hash($data->password, PASSWORD_DEFAULT);
        $user_stmt->execute([$hashed_password, $data->email, $data->role]);
        
        $user_id = $db->lastInsertId();

        // If role is doctor, insert doctor details
        if($data->role === 'doctor' && !empty($data->specialization) && !empty($data->licenseNumber)) {
            $doctor_query = "INSERT INTO doctors (user_id, specialization_id, first_name, last_name, license_number, contact_number, address) 
                           VALUES (?, ?, ?, ?, ?, ?, ?)";
            $doctor_stmt = $db->prepare($doctor_query);
            $doctor_stmt->execute([
                $user_id, 
                $data->specialization, 
                $data->firstName, 
                $data->lastName, 
                $data->licenseNumber,
                $data->contactNumber,
                $data->address
            ]);
        }
        // If role is patient, insert patient details
        else if($data->role === 'patient') {
            $patient_query = "INSERT INTO patients (user_id, first_name, last_name, date_of_birth, gender, blood_type, 
                           contact_number, address, emergency_contact_name, emergency_contact_number, medical_history, allergies) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $patient_stmt = $db->prepare($patient_query);
            $patient_stmt->execute([
                $user_id, 
                $data->firstName, 
                $data->lastName, 
                $data->dateOfBirth, 
                $data->gender,
                $data->bloodType,
                $data->contactNumber,
                $data->address,
                $data->emergencyContactName,
                $data->emergencyContactNumber,
                isset($data->medicalHistory) ? $data->medicalHistory : null,
                isset($data->allergies) ? $data->allergies : null
            ]);
        }
        // If role is staff, insert staff details
        else if($data->role === 'staff') {
            $staff_query = "INSERT INTO staff (user_id, first_name, last_name, contact_number, address) 
                           VALUES (?, ?, ?, ?, ?)";
            $staff_stmt = $db->prepare($staff_query);
            $staff_stmt->execute([
                $user_id, 
                $data->firstName, 
                $data->lastName, 
                $data->contactNumber,
                $data->address
            ]);
        }

        // Commit transaction
        $db->commit();

        // Set response code - 201 Created
        http_response_code(201);
        echo json_encode(array("message" => "Registration successful. Please wait for admin approval."));

    } catch(PDOException $e) {
        // Rollback transaction on error
        $db->rollBack();
        
        // Set response code - 500 Internal Server Error
        http_response_code(500);
        echo json_encode(array("message" => "Registration failed: " . $e->getMessage()));
    }
} else {
    // Set response code - 400 Bad Request
    http_response_code(400);
    echo json_encode(array("message" => "Unable to register. Data is incomplete."));
}
?> 