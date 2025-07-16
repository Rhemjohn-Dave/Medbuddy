<?php
class Database {
    // Database credentials
    private $host = "localhost";
    private $db_name = "medbuddy";
    private $username = "root";
    private $password = "";
    private $conn;

    // Get the database connection
    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password,
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                )
            );
        } catch(PDOException $e) {
            // Log the error for debugging
            error_log("Database Connection Error: " . $e->getMessage());
            throw new Exception("Database connection failed. Please check your database configuration.");
        }

        return $this->conn;
    }
}
?> 