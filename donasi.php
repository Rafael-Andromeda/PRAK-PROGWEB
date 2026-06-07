<?php
require_once 'config.php';

// Wajib login sebagai donatur
if (!isLoggedIn()) {
    $id = intval($_GET['id'] ?? 0);
    header('Location: login.html?redirect=' . urlencode('donasi.php' . ($id ? "?id=$id" : '')));
    exit;
}
$user = currentUser();
if ($user['role'] !== 'donatur') {
    header('Location: index.php');
    exit;
}

$kampanyeId = intval($_GET['id'] ?? 0);
if ($kampanyeId <= 0) { header('Location: index.php'); exit; }

$db   = getDB();
$stmt = $db->prepare(
    "SELECT k.id, k.judul, k.target_dana, k.dana_terkumpul, k.metode_donasi, k.deadline
     FROM kampanye k
     WHERE k.id = ? AND k.deadline >= CURDATE()
     LIMIT 1"
);
$stmt->bind_param("i", $kampanyeId);
$stmt->execute();
$kampanye = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$kampanye) {
    $db->close();
    header('Location: index.php');
    exit;
}

// Ambil data donatur dari DB
$stmt = $db->prepare("SELECT nama, email FROM donatur WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$donatur = $stmt->get_result()->fetch_assoc();
$stmt->close();

// PROSES SUBMIT DONASI
$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nominal = intval($_POST['nominal'] ?? 0);
    $metode  = trim($_POST['metode'] ?? '');
    $pesan   = trim($_POST['pesan'] ?? '');

    // Validasi nominal
    if ($nominal < 10000) {
        $error = 'Nominal donasi minimal Rp 10.000.';
    } elseif ($metode === '') {
        $error = 'Pilih metode pembayaran.';
    } elseif (empty($_FILES['bukti']['name'])) {
        $error = 'Bukti transfer wajib diupload.';
    } else {
        // Upload file bukti
        $file      = $_FILES['bukti'];
        $ext       = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed   = ['jpg','jpeg','png','pdf'];
        $maxSize   = 5 * 1024 * 1024; // 5 MB

        if (!in_array($ext, $allowed)) {
            $error = 'Format file harus JPG, PNG, atau PDF.';
        } elseif ($file['size'] > $maxSize) {
            $error = 'Ukuran file maksimal 5 MB.';
        } else {
            $uploadDir = 'uploads/bukti/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $filename  = 'bukti_' . time() . '_' . $user['id'] . '_' . random_int(1000, 9999) . '.' . $ext;
            $destPath  = $uploadDir . $filename;

            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                // Insert ke DB - status PENDING
                $stmt = $db->prepare(
                    "INSERT INTO donasi (kampanye_id, donatur_id, nominal, metode, bukti_file, pesan, status)
                     VALUES (?, ?, ?, ?, ?, ?, 'pending')"
                );
                $stmt->bind_param("iidsss", $kampanyeId, $user['id'], $nominal, $metode, $filename, $pesan);
                if ($stmt->execute()) {
                    $success = true;
                } else {
                    $error = 'Gagal menyimpan donasi: ' . $db->error;
                }
                $stmt->close();
            } else {
                $error = 'Gagal mengupload file. Coba lagi.';
            }
        }
    }
}
$db->close();

function rp($num) {
    return 'Rp ' . number_format($num, 0, ',', '.');
}
$pct = $kampanye['target_dana'] > 0 ? min(100, round($kampanye['dana_terkumpul'] / $kampanye['target_dana'] * 100)) : 0;

$metodeOptions = array_values(array_filter(array_map('trim', explode(',', $kampanye['metode_donasi'] ?: 'Transfer Bank, E-Wallet, QRIS'))));
if (empty($metodeOptions)) {
    $metodeOptions = ['Transfer Bank', 'E-Wallet', 'QRIS'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donasi - <?= htmlspecialchars($kampanye['judul']) ?> | Kindnesia</title>
    <link rel="stylesheet" href="assets/css/donasi.css">
    <style>
        .alert-error   { background:#FEF2F2; border:1.5px solid #FCA5A5; color:#DC2626; border-radius:10px; padding:14px 18px; margin-bottom:18px; font-weight:500; }
        .alert-success { background:#F0FDF4; border:1.5px solid #86EFAC; color:#166534; border-radius:10px; padding:18px; margin-bottom:18px; font-weight:600; text-align:center; }
        .campaign-summary { background:#f8fffe; border:1.5px solid #b2dfdb; border-radius:12px; padding:16px 20px; margin-bottom:24px; }
        .campaign-summary h3 { margin:0 0 10px; color:#2e7d32; }
        .summary-row { display:flex; gap:16px; flex-wrap:wrap; margin-top:8px; }
        .summary-item { flex:1; min-width:120px; }
        .summary-item .label { font-size:.78rem; color:#888; }
        .summary-item .value { font-weight:700; color:#2e7d32; font-size:1rem; }
        .mini-bar { background:#ddd; border-radius:6px; height:6px; margin-top:4px; }
        .mini-bar-fill { background:#4CAF50; border-radius:6px; height:6px; }
        .donatur-info { background:#f0f4ff; border-radius:10px; padding:14px 18px; margin-bottom:20px; border:1px solid #c5cae9; }
        .donatur-info p { margin:4px 0; color:#444; }
        .nominal-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; margin-top:8px; }
        .nominal-btn { padding:10px; border:1.5px solid #ccc; border-radius:8px; cursor:pointer; text-align:center; font-size:.9rem; background:#fff; transition:.2s; }
        .nominal-btn:hover, .nominal-btn.active { border-color:#4CAF50; background:#E8F5E9; color:#2e7d32; font-weight:700; }
        .nominal-custom { width:100%; margin-top:8px; padding:10px; border:1.5px solid #ccc; border-radius:8px; font-size:.95rem; }
        .nominal-custom:focus { border-color:#4CAF50; outline:none; }
    </style>
</head>
<body>
<header>
    <div class="container nav">
        <h1 class="logo">Kindnesia</h1>
        <nav>
            <span class="welcome-text">👋 <?= htmlspecialchars($donatur['nama']) ?></span>
            <a href="details.php?id=<?= $kampanyeId ?>">← Kembali</a>
            <a href="index.php">Beranda</a>
            <a href="logout.php" class="logout-btn">Logout</a>
        </nav>
    </div>
</header>

<main>
    <div class="card glass">
        <h2>Form Donasi</h2>

        <?php if ($success): ?>
            <div class="alert-success">
                ✅ Donasi Anda berhasil dikirim! Menunggu verifikasi dari pengelola kampanye.<br>
                Status saat ini: <strong>PENDING</strong><br><br>
                <a href="riwayat_donasi.php" style="color:#166534;text-decoration:underline;">Lihat Riwayat Donasi Saya</a>
                &nbsp;·&nbsp;
                <a href="index.php" style="color:#166534;text-decoration:underline;">Kembali ke Beranda</a>
            </div>
        <?php else: ?>

        <?php if ($error): ?>
            <div class="alert-error">⚠ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- RINGKASAN KAMPANYE -->
        <div class="campaign-summary">
            <h3>📋 <?= htmlspecialchars($kampanye['judul']) ?></h3>
            <div class="summary-row">
                <div class="summary-item">
                    <div class="label">Target Dana</div>
                    <div class="value"><?= rp($kampanye['target_dana']) ?></div>
                </div>
                <div class="summary-item">
                    <div class="label">Terkumpul</div>
                    <div class="value"><?= rp($kampanye['dana_terkumpul']) ?></div>
                </div>
                <div class="summary-item">
                    <div class="label">Progress</div>
                    <div class="value"><?= $pct ?>%</div>
                    <div class="mini-bar"><div class="mini-bar-fill" style="width:<?= $pct ?>%"></div></div>
                </div>
            </div>
        </div>

        <!-- DATA DONATUR (dari DB) -->
        <div class="donatur-info">
            <strong>👤 Data Anda</strong>
            <p>Nama: <?= htmlspecialchars($donatur['nama'] ?? $user['nama']) ?></p>
            <p>Email: <?= htmlspecialchars($donatur['email'] ?? $user['email']) ?></p>
        </div>

        <!-- FORM DONASI -->
        <form id="donasiForm" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="kampanye_id" value="<?= $kampanyeId ?>">

            <!-- NOMINAL -->
            <div class="form-group">
                <label>Pilih Nominal Donasi</label>
                <div class="nominal-grid">
                    <?php foreach ([10000, 25000, 50000, 100000, 250000, 500000] as $n): ?>
                        <div class="nominal-btn" onclick="setNominal(<?= $n ?>, this)">
                            Rp <?= number_format($n, 0, ',', '.') ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <input type="number" id="nominalInput" name="nominal" class="nominal-custom"
                       placeholder="Atau masukkan nominal lain (min. Rp 10.000)"
                       min="10000" value="<?= $_POST['nominal'] ?? '' ?>" required
                       oninput="clearNominalBtn()">
            </div>

            <!-- METODE -->
            <div class="form-group">
                <label for="metode">Metode Pembayaran</label>
                <select id="metode" name="metode" required>
                    <option value="">Pilih Metode</option>
                    <?php foreach ($metodeOptions as $opt): ?>
                        <option value="<?= htmlspecialchars($opt) ?>" <?= ($_POST['metode'] ?? '') === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="file-hint">Metode tersedia dari kampanye ini: <?= htmlspecialchars($kampanye['metode_donasi'] ?: 'Transfer Bank, E-Wallet, QRIS') ?></p>
            </div>

            <!-- BUKTI TRANSFER -->
            <div class="form-group">
                <label for="bukti">Bukti Transfer <span style="color:#e74c3c">*</span></label>
                <div class="file-upload-wrapper">
                    <input type="file" id="bukti" name="bukti" accept=".jpg,.jpeg,.png,.pdf" required
                          >
                    <label for="bukti" class="file-upload-label">
                        <span id="fileNameDisplay">Pilih file (JPG / PNG / PDF)</span>
                    </label>
                </div>
                <p class="file-hint">Format: JPG, PNG, PDF. Maks. 5 MB.</p>
            </div>

            <!-- PESAN -->
            <div class="form-group">
                <label for="pesan">Pesan Dukungan <span class="optional">(Opsional)</span></label>
                <textarea id="pesan" name="pesan" placeholder="Tulis pesan atau doa untuk kampanye ini..."><?= htmlspecialchars($_POST['pesan'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn-submit">💚 Donasi Sekarang</button>
        </form>
        <?php endif; ?>
    </div>
</main>

<footer>
    <div class="footer-content container">
        <div>
            <h3>Kindnesia</h3>
            <p>Platform donasi untuk lingkungan yang lebih baik.</p>
        </div>
    </div>
    <div class="copyright">
        <p>© 2026 Kindnesia | Crowdfunding Sosial</p>
    </div>
</footer>

<script src="assets/js/donasi.js"></script>
</body>
</html>