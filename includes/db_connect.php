<?php
/**
 * A modern, robust, and efficient Singleton database connection class.
 *
 * This class ensures that only one database connection is ever made per request,
 * preventing resource overhead. It also handles connection errors gracefully
 * by terminating the script with a clear message, which prevents fatal errors
 * in other parts of the application.
 */

// Include the main configuration file which defines the database constants.
require_once __DIR__ . '/../config.php';

class Database {
    // Hold the single class instance.
    private static $instance = null;
    
    // The PDO connection object.
    private $conn;

    // Database connection details from the config file.
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    private $charset = DB_CHARSET;

    /**
     * The constructor is private to prevent direct creation of the object.
     * This is key to the Singleton pattern.
     */
    private function __construct() {
        // Set PDO options for the connection.
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Most important: throw exceptions on errors.
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Return associative arrays.
            PDO::ATTR_EMULATE_PREPARES   => false,                  // Use real prepared statements for better security.
        ];

        // Create the Data Source Name (DSN) string.
        $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset={$this->charset}";

        try {
            // Attempt to create the PDO connection object.
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            // If the connection fails, stop the script and show a detailed error.
            // In a production environment, you would log this error instead of displaying it.
            die("Database Connection Error: " . $e->getMessage());
        }
    }

    /**
     * The static method that controls access to the single instance.
     *
     * @return PDO The PDO connection object.
     */
    public static function getInstance() {
        if (self::$instance == null) {
            // If no instance exists, create one.
            self::$instance = new Database();
        }
        // Return the single instance's connection object.
        return self::$instance->conn;
    }

    /**
     * Prevent the instance from being cloned.
     */
    private function __clone() {}

    /**
     * Prevent the instance from being unserialized.
     */
    public function __wakeup() {}
}

// --- Global Usage ---
// To get the database connection, call the static getInstance() method.
// This single line replaces the instantiation and getConnection() call from your original file.
$db = Database::getInstance();

?>