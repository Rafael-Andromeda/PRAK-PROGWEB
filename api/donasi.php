<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method tidak diizinkan. Gunakan POST."]);
    exit;
}

function jsonResponse($success, $message, $extra = []) {
    echo json_encode(array_merge(["success" => $success, "message" => $message], $extra));
    exit;
}

$nama = trim($_POST['nama'] ?? '');
$email = trim($_POST['email'] ?? '');
$nominal = trim($_POST['nominal'] ?? '');
$metode = trim($_POST['metode'] ?? '');
$pesan = trim($_POST['pesan'] ?? '');

$errors = [];
if ($nama === '') {
    $errors[] = 'Nama Lengkap wajib diisi.';
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Email tidak valid.';
}
if ($nominal === '') {
    $errors[] = 'Nominal donasi wajib dipilih.';
}
if ($metode === '') {
    $errors[] = 'Metode pembayaran wajib dipilih.';
}

if (!isset($_FILES['bukti']) || $_FILES['bukti']['error'] !== UPLOAD_ERR_OK) {
    $errors[] = 'Bukti transfer wajib diunggah.';
}

if ($errors) {
    http_response_code(400);
    jsonResponse(false, 'Validasi gagal.', ['errors' => $errors]);
}

$allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
$file = $_FILES['bukti'];
if (!in_array($file['type'], $allowedTypes, true)) {
    http_response_code(400);
    jsonResponse(false, 'Format file tidak didukung. Gunakan PDF, JPG, atau PNG.');
}
if ($file['size'] > 5 * 1024 * 1024) {
    http_response_code(400);
    jsonResponse(false, 'Ukuran file terlalu besar. Maksimal 5 MB.');
}

$uploadDir = __DIR__ . '/../uploads';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
    http_response_code(500);
    jsonResponse(false, 'Tidak dapat membuat folder upload.');
}

$filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file['name']));
$destination = $uploadDir . '/' . $filename;
if (!move_uploaded_file($file['tmp_name'], $destination)) {
    http_response_code(500);
    jsonResponse(false, 'Gagal menyimpan file bukti transfer.');
}

$dbHost = '127.0.0.1';
$dbName = 'kindnesia';
$dbUser = 'root';
$dbPass = '';
$dbCharset = 'utf8mb4';

$dsn = "mysql:host={$dbHost};dbname={$dbName};charset={$dbCharset}";
try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $pdo->exec('CREATE TABLE IF NOT EXISTS donasi (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        nominal INT NOT NULL,
        metode VARCHAR(100) NOT NULL,
        pesan TEXT,
        bukti_path VARCHAR(255) NOT NULL,
        created_at DATETIME NOT NULL
    )');

    $stmt = $pdo->prepare('INSERT INTO donasi (nama, email, nominal, metode, pesan, bukti_path, created_at) 
                       VALUES (:nama, :email, :nominal, :metode, :pesan, :bukti_path, :created_at)');
    $createdAt = date('Y-m-d H:i:s');
    $stmt->execute([
    ':nama'       => $nama,
    ':email'      => $email,
    ':nominal'    => (int)$nominal,
    ':metode'     => $metode,
    ':pesan'      => $pesan,
    ':bukti_path' => 'uploads/' . $filename,
    ':created_at' => $createdAt,
    ]);
    $donasiId = $pdo->lastInsertId();

} catch (PDOException $e) {
    http_response_code(500);
    jsonResponse(false, 'Gagal menyimpan data donasi ke database.', ['error' => $e->getMessage()]);
}

$newDonasi = [
    'id' => $donasiId,
    'nama' => $nama,
    'email' => $email,
    'nominal' => $nominal,
    'metode' => $metode,
    'pesan' => $pesan,
    'bukti_path' => 'uploads/' . $filename,
    'created_at' => $createdAt,
];

jsonResponse(true, 'Donasi berhasil disimpan ke database MySQL.', ['donasi' => $newDonasi]);
