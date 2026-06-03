<?php
require_once 'config.php';

$user = isLoggedIn() ? currentUser() : null;

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: index.php'); exit; }

$db   = getDB();
$stmt = $db->prepare(
    "SELECT k.*, p.nama_pengelola, p.email AS pengelola_email, p.no_telepon AS pengelola_telp, p.alamat AS pengelola_alamat
     FROM kampanye k
     JOIN pengelola p ON p.id = k.pengelola_id
     WHERE k.id = ?
     LIMIT 1"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$k = $stmt->get_result()->fetch_assoc();
$stmt->close();
$db->close();

if (!$k) { header('Location: index.php'); exit; }

$pct      = $k['target_dana'] > 0 ? min(100, round($k['dana_terkumpul'] / $k['target_dana'] * 100)) : 0;
$sisaHari = max(0, intval((strtotime($k['deadline']) - strtotime(date('Y-m-d'))) / 86400));
$deadlineStr = date('d F Y', strtotime($k['deadline']));
$imgSrc   = $k['gambar'] ? 'uploads/kampanye/' . htmlspecialchars($k['gambar']) : 'assets/img/placeholder.svg';

function rp($num) {
    return 'Rp ' . number_format($num, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($k['judul']) ?> - Kindnesia</title>
    <link rel="stylesheet" href="assets/css/details.css">
    <style>
        .user-info { color:#fff; font-size:.9rem; margin-right:8px; }
        .logout-btn { background:#e74c3c; color:#fff !important; padding:6px 14px; border-radius:8px; }
    </style>
</head>
<body>
<header>
    <div class="container nav">
        <h1 class="logo">Kindnesia</h1>
        <nav>
            <a href="index.php">Beranda</a>
            <?php if ($user): ?>
                <span class="user-info">👤 <?= htmlspecialchars($user['nama']) ?></span>
                <?php if ($user['role'] === 'pengelola'): ?>
                    <a href="dashboard.php">Dashboard</a>
                <?php else: ?>
                    <a href="riwayat_donasi.php">Donasi Saya</a>
                <?php endif; ?>
                <a href="logout.php" class="logout-btn">Logout</a>
            <?php else: ?>
                <a href="login.html" class="login-btn">Login</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main>
    <div class="image">
        <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($k['judul']) ?>"
             onerror="this.src='assets/img/placeholder.svg'">
    </div>

    <div class="card">
        <div class="title">
            <h1><?= htmlspecialchars($k['judul']) ?></h1>
            <span class="subtitle"><?= htmlspecialchars($k['deskripsi'] ?? '') ?></span>
        </div>

        <div class="profile">
            <div class="campaign-info">
                <div class="info-item">
                    <span>🏷️</span>
                    <p>Kategori: <strong><?= htmlspecialchars($k['kategori']) ?></strong></p>
                </div>
                <?php if ($k['lokasi']): ?>
                <div class="info-item">
                    <span>📍</span>
                    <p>Lokasi: <?= htmlspecialchars($k['lokasi']) ?></p>
                </div>
                <?php endif; ?>
                <div class="info-item">
                    <span>💳</span>
                    <p>Metode Donasi: <?= htmlspecialchars($k['metode_donasi'] ?? 'Transfer Bank, E-Wallet, QRIS') ?></p>
                </div>
            </div>
            <div class="profile-picture">🏢</div>
            <div class="nama-profile">
                <div class="nama"><?= htmlspecialchars($k['nama_pengelola']) ?></div>
                <div class="sejak">✔ Terverifikasi · Penyelenggara Kampanye</div>
            </div>
        </div>
        <hr>

        <!-- PROGRESS DANA -->
        <div class="terkumpul-nominal">
            <div class="terkumpul">TERKUMPUL</div>
            <div class="nominal">
                <div class="nom-sekarang"><?= rp($k['dana_terkumpul']) ?></div>
                <div class="nom-target">dari target <span><?= rp($k['target_dana']) ?></span></div>
            </div>

            <div class="bar-track">
                <div class="bar-fill" style="width:<?= $pct ?>%"></div>
            </div>

            <div class="stats">
                <div class="stat">
                    <div class="stat-num"><?= $pct ?><span>%</span></div>
                    <div class="stat-label">Tercapai</div>
                </div>
                <div class="stat">
                    <div class="stat-num"><?= $sisaHari ?></div>
                    <div class="stat-label">Hari Lagi</div>
                </div>
                <div class="stat">
                    <div class="stat-num"><?= rp($k['target_dana'] - $k['dana_terkumpul']) ?></div>
                    <div class="stat-label">Sisa Target</div>
                </div>
            </div>
        </div>

        <div class="sisa-hari">
            <span>⏳</span>
            <div class="sisa-tanggal-segera">
                <div class="sisa-tanggal">
                    <div class="sisa"><?= $sisaHari > 0 ? "$sisaHari hari lagi tersisa" : 'Kampanye telah berakhir' ?></div>
                    <div class="tanggal">Berakhir pada <?= $deadlineStr ?></div>
                </div>
                <?php if ($sisaHari > 0): ?>
                    <?php if ($user && $user['role'] === 'donatur'): ?>
                        <a href="donasi.php?id=<?= $k['id'] ?>" class="donate-btn-main" style="display:inline-block;padding:12px 28px;background:#4CAF50;color:#fff;border-radius:10px;text-decoration:none;font-weight:700;margin-top:12px;">Donasi Sekarang</a>
                    <?php elseif ($user && $user['role'] === 'pengelola'): ?>
                        <p style="color:#e67e22;margin-top:12px;font-size:.9rem;">⚠ Pengelola tidak dapat berdonasi.</p>
                    <?php else: ?>
                        <a href="login.html?redirect=<?= urlencode('donasi.php?id=' . $k['id']) ?>" class="donate-btn-main" style="display:inline-block;padding:12px 28px;background:#4CAF50;color:#fff;border-radius:10px;text-decoration:none;font-weight:700;margin-top:12px;">Donasi Sekarang</a>
                        <p style="color:#888;font-size:.8rem;margin-top:4px;">*Anda harus login terlebih dahulu</p>
                    <?php endif; ?>
                <?php else: ?>
                    <p style="color:#e74c3c;font-weight:700;margin-top:8px;">Kampanye ini telah berakhir</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- DESKRIPSI -->
        <div style="margin-top:24px;">
            <h3 style="margin-bottom:12px;">📋 Tentang Kampanye</h3>
            <p style="line-height:1.8;color:#555;"><?= nl2br(htmlspecialchars($k['deskripsi'] ?? 'Tidak ada deskripsi.')) ?></p>
        </div>

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
</body>
</html>