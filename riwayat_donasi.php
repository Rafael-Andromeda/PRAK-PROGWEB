<?php
// riwayat_donasi.php — Riwayat Donasi Donatur (BONUS)
require_once 'config.php';

if (!isLoggedIn() || currentUser()['role'] !== 'donatur') {
    header('Location: login.html');
    exit;
}
$user = currentUser();
$db   = getDB();

$stmt = $db->prepare(
    "SELECT d.id, d.nominal, d.metode, d.status, d.pesan, d.bukti_file, d.created_at, d.verified_at,
            k.judul AS kampanye_judul, k.id AS kampanye_id,
            p.nama_pengelola
     FROM donasi d
     JOIN kampanye k ON k.id = d.kampanye_id
     JOIN pengelola p ON p.id = k.pengelola_id
     WHERE d.donatur_id = ?
     ORDER BY d.created_at DESC"
);
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$donasis = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// BONUS: ringkasan
$totalVerified = array_sum(array_map(fn($d) => $d['nominal'], array_filter($donasis, fn($d) => $d['status'] === 'verified')));
$totalPending  = array_sum(array_map(fn($d) => $d['nominal'], array_filter($donasis, fn($d) => $d['status'] === 'pending')));
$totalRejected = array_sum(array_map(fn($d) => $d['nominal'], array_filter($donasis, fn($d) => $d['status'] === 'rejected')));
$ctVerified    = count(array_filter($donasis, fn($d) => $d['status'] === 'verified'));
$ctPending     = count(array_filter($donasis, fn($d) => $d['status'] === 'pending'));
$ctRejected    = count(array_filter($donasis, fn($d) => $d['status'] === 'rejected'));

$db->close();

function rp($n) { return 'Rp ' . number_format($n, 0, ',', '.'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Riwayat Donasi — Kindnesia</title>
  <link rel="stylesheet" href="index.css">
  <style>
    .riwayat-container { max-width:900px; margin:32px auto; padding:0 16px; }
    .summary-box { display:flex; gap:16px; flex-wrap:wrap; margin-bottom:28px; }
    .s-card { flex:1; min-width:180px; border-radius:14px; padding:18px 20px; }
    .s-card .s-label { font-size:.8rem; font-weight:600; margin-bottom:4px; }
    .s-card .s-val { font-size:1.2rem; font-weight:700; }
    .s-card .s-count { font-size:.8rem; margin-top:2px; opacity:.8; }
    .s-green  { background:#DCFCE7; color:#166534; border:1.5px solid #86EFAC; }
    .s-yellow { background:#FEF9C3; color:#854D0E; border:1.5px solid #FDE68A; }
    .s-red    { background:#FEE2E2; color:#991B1B; border:1.5px solid #FCA5A5; }
    .donasi-card { background:#fff; border:1.5px solid #e5e7eb; border-radius:14px; padding:20px 22px; margin-bottom:16px; }
    .donasi-card .dc-top { display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:8px; }
    .donasi-card .dc-judul { font-weight:700; font-size:1rem; color:#1a1a1a; }
    .donasi-card .dc-org { font-size:.8rem; color:#888; margin-top:2px; }
    .dc-nominal { font-size:1.2rem; font-weight:800; color:#2e7d32; }
    .dc-meta { display:flex; gap:20px; margin-top:12px; flex-wrap:wrap; }
    .dc-meta span { font-size:.82rem; color:#555; }
    .dc-pesan { margin-top:10px; font-size:.85rem; color:#666; background:#f9f9f9; padding:10px; border-radius:8px; border-left:3px solid #ddd; }
    .badge-pending  { background:#FEF9C3; color:#854D0E; padding:4px 12px; border-radius:20px; font-size:.82rem; font-weight:700; }
    .badge-verified { background:#DCFCE7; color:#166534; padding:4px 12px; border-radius:20px; font-size:.82rem; font-weight:700; }
    .badge-rejected { background:#FEE2E2; color:#991B1B; padding:4px 12px; border-radius:20px; font-size:.82rem; font-weight:700; }
    .empty-state { text-align:center; padding:60px 20px; color:#aaa; }
    .user-info { color:#fff; font-size:.9rem; margin-right:8px; }
    .logout-btn { background:#e74c3c; color:#fff !important; padding:6px 14px; border-radius:8px; }
    .bukti-link { color:#2563eb; font-size:.82rem; }
  </style>
</head>
<body>
<header>
  <div class="container nav">
    <h1 class="logo">Kindnesia</h1>
    <nav>
      <a href="index.php">Beranda</a>
      <span class="user-info">👤 <?= htmlspecialchars($user['nama']) ?></span>
      <a href="logout.php" class="logout-btn">Logout</a>
    </nav>
  </div>
</header>

<div class="riwayat-container">
  <h2 style="margin-bottom:20px;">📜 Riwayat Donasi Saya</h2>

  <!-- RINGKASAN BONUS -->
  <div class="summary-box">
    <div class="s-card s-green">
      <div class="s-label">✅ Terverifikasi</div>
      <div class="s-val"><?= rp($totalVerified) ?></div>
      <div class="s-count"><?= $ctVerified ?> donasi</div>
    </div>
    <div class="s-card s-yellow">
      <div class="s-label">⏳ Pending</div>
      <div class="s-val"><?= rp($totalPending) ?></div>
      <div class="s-count"><?= $ctPending ?> donasi</div>
    </div>
    <div class="s-card s-red">
      <div class="s-label">❌ Ditolak</div>
      <div class="s-val"><?= rp($totalRejected) ?></div>
      <div class="s-count"><?= $ctRejected ?> donasi</div>
    </div>
  </div>

  <?php if (empty($donasis)): ?>
    <div class="empty-state">
      <p style="font-size:2.5rem">💚</p>
      <p>Anda belum pernah berdonasi.</p>
      <a href="index.php" style="color:#4CAF50;font-weight:700;">Temukan Kampanye →</a>
    </div>
  <?php else: ?>
    <?php foreach ($donasis as $d): ?>
    <div class="donasi-card">
      <div class="dc-top">
        <div>
          <div class="dc-judul">
            <a href="details.php?id=<?= $d['kampanye_id'] ?>" style="color:#1a1a1a;text-decoration:none">
              <?= htmlspecialchars($d['kampanye_judul']) ?>
            </a>
          </div>
          <div class="dc-org">oleh <?= htmlspecialchars($d['nama_pengelola']) ?></div>
        </div>
        <div style="text-align:right">
          <div class="dc-nominal"><?= rp($d['nominal']) ?></div>
          <div style="margin-top:5px">
            <?php if ($d['status'] === 'pending'): ?>
              <span class="badge-pending">⏳ Menunggu Verifikasi</span>
            <?php elseif ($d['status'] === 'verified'): ?>
              <span class="badge-verified">✅ Terverifikasi</span>
            <?php else: ?>
              <span class="badge-rejected">❌ Ditolak</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="dc-meta">
        <span>💳 <?= htmlspecialchars($d['metode']) ?></span>
        <span>📅 <?= date('d F Y, H:i', strtotime($d['created_at'])) ?></span>
        <?php if ($d['bukti_file']): ?>
          <a href="uploads/bukti/<?= htmlspecialchars($d['bukti_file']) ?>" class="bukti-link" target="_blank">📎 Lihat Bukti Transfer</a>
        <?php endif; ?>
        <?php if ($d['verified_at'] && $d['status'] === 'verified'): ?>
          <span style="color:#166534">✅ Diverifikasi: <?= date('d/m/Y', strtotime($d['verified_at'])) ?></span>
        <?php endif; ?>
      </div>
      <?php if ($d['pesan']): ?>
        <div class="dc-pesan">💬 "<?= htmlspecialchars($d['pesan']) ?>"</div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<footer>
  <div class="footer-content container">
    <div><h3>Kindnesia</h3><p>Platform donasi untuk lingkungan yang lebih baik.</p></div>
  </div>
  <div class="copyright"><p>© 2026 Kindnesia | Crowdfunding Sosial</p></div>
</footer>
</body>
</html>
