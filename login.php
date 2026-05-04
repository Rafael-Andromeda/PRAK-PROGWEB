<?php
// login.php — Handler Login Kindnesia
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

// ── CEK STATUS LOGIN (GET ?check=1) ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['check'])) {
    echo json_encode(
        isLoggedIn()
            ? ['logged_in' => true,  'user' => currentUser()]
            : ['logged_in' => false]
    );
    exit;
}

// ── HANYA TERIMA POST ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan.']);
    exit;
}

// ── BACA INPUT (JSON body atau form biasa) ────────────────────────
$input    = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$email    = trim($input['username'] ?? '');   // field dari login.js bernama 'username'
$password = trim($input['password'] ?? '');
$role     = trim($input['role']     ?? '');

// ── VALIDASI ──────────────────────────────────────────────────────
if ($email === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Email dan password wajib diisi.']);
    exit;
}

if (!in_array($role, ['donatur', 'pengelola'])) {
    echo json_encode(['success' => false, 'message' => 'Role tidak valid.']);
    exit;
}

// ── QUERY KE TABEL users ──────────────────────────────────────────
$db   = getDB();
$stmt = $db->prepare(
    "SELECT id, nama, email, password, role
     FROM users
     WHERE email = ? AND role = ?
     LIMIT 1"
);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare error: ' . $db->error]);
    $db->close();
    exit;
}

$stmt->bind_param("ss", $email, $role);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$db->close();

// ── CEK USER ──────────────────────────────────────────────────────
if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Akun tidak ditemukan. Periksa email dan role Anda.']);
    exit;
}

// ── CEK PASSWORD (dukung plain text dan password_hash) ────────────
$valid = password_verify($password, $row['password'])
      || ($password === $row['password']);

if (!$valid) {
    echo json_encode(['success' => false, 'message' => 'Password salah.']);
    exit;
}

// ── SIMPAN SESSION ────────────────────────────────────────────────
$_SESSION['user_id']    = $row['id'];
$_SESSION['user_nama']  = $row['nama'];
$_SESSION['user_email'] = $row['email'];
$_SESSION['user_role']  = $row['role'];

// Redirect: pengelola ke dashboard, donatur ke index
$redirect = ($row['role'] === 'pengelola') ? 'dashboard.html' : 'index.html';

echo json_encode([
    'success'  => true,
    'message'  => 'Selamat datang, ' . $row['nama'] . '!',
    'redirect' => $redirect,
    'user'     => [
        'id'    => $row['id'],
        'nama'  => $row['nama'],
        'email' => $row['email'],
        'role'  => $row['role'],
    ],
]);
?>
