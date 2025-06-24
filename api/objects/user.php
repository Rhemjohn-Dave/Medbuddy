<?php
class User {
    // Database connection and table name
    private $conn;
    private $table_name = "users";

    // Object properties
    public $id;
    public $email;
    public $password;
    public $role;
    public $approval_status;
    public $created_at;

    // Constructor with $db as database connection
    public function __construct($db) {
        $this->conn = $db;
    }

    // Login user
    public function login() {
        // Query to check if email exists
        $query = "SELECT id, password, role, email, approval_status 
                FROM " . $this->table_name . " 
                WHERE email = ? 
                LIMIT 0,1";

        // Prepare the query
        $stmt = $this->conn->prepare($query);

        // Sanitize input
        $this->email = htmlspecialchars(strip_tags($this->email));

        // Bind the email parameter
        $stmt->bindParam(1, $this->email);

        // Execute the query
        $stmt->execute();

        return $stmt;
    }

    // Create new user
    public function create() {
        // Query to insert record
        $query = "INSERT INTO " . $this->table_name . "
                SET
                    email = :email,
                    password = :password,
                    role = :role,
                    approval_status = :approval_status";

        // Prepare query
        $stmt = $this->conn->prepare($query);

        // Sanitize input
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->role = htmlspecialchars(strip_tags($this->role));
        $this->approval_status = htmlspecialchars(strip_tags($this->approval_status));

        // Hash the password
        $this->password = password_hash($this->password, PASSWORD_DEFAULT);

        // Bind values
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":password", $this->password);
        $stmt->bindParam(":role", $this->role);
        $stmt->bindParam(":approval_status", $this->approval_status);

        // Execute query
        if($stmt->execute()) {
            return true;
        }

        return false;
    }

    // Check if email exists
    public function emailExists() {
        // Query to check if email exists
        $query = "SELECT id, password, role, email, approval_status
                FROM " . $this->table_name . "
                WHERE email = ?
                LIMIT 0,1";

        // Prepare the query
        $stmt = $this->conn->prepare($query);

        // Sanitize input
        $this->email = htmlspecialchars(strip_tags($this->email));

        // Bind the email parameter
        $stmt->bindParam(1, $this->email);

        // Execute the query
        $stmt->execute();

        // Get number of rows
        $num = $stmt->rowCount();

        // If email exists, assign values to object properties
        if($num > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->id = $row['id'];
            $this->password = $row['password'];
            $this->role = $row['role'];
            $this->approval_status = $row['approval_status'];
            return true;
        }

        return false;
    }
}
?> 