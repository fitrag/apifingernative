<?php
// Konfigurasi Database
define('DB_HOST', 'localhost');
define('DB_PORT', '83306');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'adms_db');

// Koneksi Database
function getConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    
    if ($conn->connect_error) {
        die("Koneksi gagal: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8");
    return $conn;
}
?>
