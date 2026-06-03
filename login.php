<?php
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

// CEK STATUS LOGIN (GET ?check=1)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['check'])) {
    echo json_encode(
        isLoggedIn()
            ? ['logged_in' => true,  'user' => currentUser()]
            : ['logged_in' => false]
    );
    exit;
}

// HANYA TERIMA POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan.']);
    exit;
}

// BACA INPUT (JSON body atau form biasa)
$input    = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$login    = trim($input['username'] ?? '');  // bisa email atau username
$password = trim($input['password'] ?? '');
$role     = trim($input['role']     ?? '');

// VALIDASI
if ($login === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Email/username dan password wajib diisi.']);
    exit;
}

if (!in_array($role, ['donatur', 'pengelola'])) {
    echo json_encode(['success' => false, 'message' => 'Role tidak valid.']);
    exit;
}

// QUERY KE TABEL YANG SESUAI ROLE
// Skema DB: tabel 'donatur' dan 'pengelola' (tidak ada tabel 'users')
$db = getDB();

if ($role === 'donatur') {
    $stmt = $db->prepare(
        "SELECT id, nama, email, password
         FROM donatur
         WHERE email = ? OR username = ?
         LIMIT 1"
    );
    $stmt->bind_param("ss", $login, $login);
} else {
    // pengelola
    $stmt = $db->prepare(
        "SELECT id, nama_pengelola AS nama, email, password
         FROM pengelola
         WHERE email = ? OR username = ?
         LIMIT 1"
    );
    $stmt->bind_param("ss", $login, $login);
}

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'DB prepare error: ' . $db->error]);
    $db->close();
    exit;
}

$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$db->close();

// USER TIDAK DITEMUKAN
if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Akun tidak ditemukan. Periksa email/username dan role Anda.']);
    exit;
}

// CEK PASSWORD (plain text & password_hash)
$valid = password_verify($password, $row['password'])
      || ($password === $row['password']);

if (!$valid) {
    echo json_encode(['success' => false, 'message' => 'Password salah.']);
    exit;
}

// SIMPAN SESSION
$_SESSION['user_id']    = $row['id'];
$_SESSION['user_nama']  = $row['nama'];
$_SESSION['user_email'] = $row['email'];
$_SESSION['user_role']  = $role; // hardcode dari input karena kolom role tidak ada di tabel

// Donatur ke index, pengelola ke dashboard
$redirect = ($role === 'pengelola') ? 'dashboard.php' : 'index.php';

echo json_encode([
    'success'  => true,
    'message'  => 'Selamat datang, ' . $row['nama'] . '!',
    'redirect' => $redirect,
    'user'     => [
        'id'    => $row['id'],
        'nama'  => $row['nama'],
        'email' => $row['email'],
        'role'  => $role,
    ],
]);
?>