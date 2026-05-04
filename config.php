<?php
// config.php — Konfigurasi Database Kindnesia
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // Sesuaikan dengan user DB Anda
define('DB_PASS', '');           // Sesuaikan dengan password DB Anda
define('DB_NAME', 'kindnesia');

function getDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die(json_encode([
            'success' => false,
            'message' => 'Koneksi database gagal: ' . $conn->connect_error
        ]));
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

// Mulai session hanya jika belum berjalan
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.html');
        exit;
    }
}

function currentUser() {
    return [
        'id'    => $_SESSION['user_id']    ?? null,
        'nama'  => $_SESSION['user_nama']  ?? null,
        'email' => $_SESSION['user_email'] ?? null,
        'role'  => $_SESSION['user_role']  ?? null,
    ];
}
?>
