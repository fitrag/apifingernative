<?php
// Konfigurasi Database
define('DB_HOST', 'localhost');
define('DB_PORT', '83306');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'adms_db');

// Singleton Database Connection untuk menghindari multiple connections
class Database {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        if ($this->conn->connect_error) {
            die("Koneksi gagal: " . $this->conn->connect_error);
        }
        $this->conn->set_charset("utf8");
        $this->conn->query("SET SESSION sql_mode = ''");
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        if (!$this->conn->ping()) {
            $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
            $this->conn->set_charset("utf8");
        }
        return $this->conn;
    }
}

// Backward compatible function
function getConnection() {
    return Database::getInstance()->getConnection();
}
?>