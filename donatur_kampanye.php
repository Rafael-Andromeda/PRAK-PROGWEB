<?php
require_once 'config.php';

if (!isLoggedIn() || currentUser()['role'] !== 'pengelola') {
    header('Location: login.html');
    exit;
}
$user = currentUser();
$kampanyeId = intval($_GET['id'] ?? 0);
if ($kampanyeId <= 0) { header('Location: dashboard.php'); exit; }

$db = getDB();

// Pastikan kampanye milik pengelola ini
$stmt = $db->prepare("SELECT judul FROM kampanye WHERE id=? AND pengelola_id=?");
$stmt->bind_param("ii", $kampanyeId, $user['id']);
$stmt->execute();
$kampanye = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$kampanye) { $db->close(); header('Location: dashboard.php'); exit; }

// Ambil donatur
$stmt = $db->prepare(
    "SELECT d.nominal, d.metode, d.status, d.created_at, d.pesan, d.bukti_file,
            dt.nama, dt.email, dt.no_telepon
     FROM donasi d
     JOIN donatur dt ON dt.id = d.donatur_id
     WHERE d.kampanye_id = ?
     ORDER BY d.created_at DESC"
);
$stmt->bind_param("i", $kampanyeId);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$db->close();

function rp($n) { return 'Rp ' . number_format($n, 0, ',', '.'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Donatur Kampanye - Kindnesia</title>
  <link rel="stylesheet" href="assets/css/dashboard.css">
  <style>
    .badge-pending  { background:#FEF9C3; color:#854D0E; padding:3px 10px; border-radius:20px; font-size:.8rem; font-weight:700; }
    .badge-verified { background:#DCFCE7; color:#166534; padding:3px 10px; border-radius:20px; font-size:.8rem; font-weight:700; }
    .badge-rejected { background:#FEE2E2; color:#991B1B; padding:3px 10px; border-radius:20px; font-size:.8rem; font-weight:700; }
  </style>
</head>
<body>
<header>
  <div class="container nav">
    <h1 class="logo">Kindnesia</h1>
    <nav>
      <a href="dashboard.php">← Dashboard</a>
      <a href="logout.php" class="logout-btn">Logout</a>
    </nav>
  </div>
</header>
<main class="container">
  <h2 style="margin-bottom:6px">Daftar Donatur</h2>
  <p style="color:#888;margin-bottom:24px">Kampanye: <strong><?= htmlspecialchars($kampanye['judul']) ?></strong></p>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Nama</th><th>Email</th><th>No. Telp</th><th>Nominal</th><th>Metode</th><th>Status</th><th>Pesan</th><th>Tanggal</th><th>Bukti</th></tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><strong><?= htmlspecialchars($r['nama']) ?></strong></td>
          <td><?= htmlspecialchars($r['email']) ?></td>
          <td><?= htmlspecialchars($r['no_telepon'] ?? '—') ?></td>
          <td><?= rp($r['nominal']) ?></td>
          <td><?= htmlspecialchars($r['metode']) ?></td>
          <td>
            <?php if ($r['status'] === 'pending'): ?><span class="badge-pending">⏳ Pending</span>
            <?php elseif ($r['status'] === 'verified'): ?><span class="badge-verified">✅ Verified</span>
            <?php else: ?><span class="badge-rejected">❌ Ditolak</span>
            <?php endif; ?>
          </td>
          <td style="font-size:.82rem;color:#666"><?= $r['pesan'] ? htmlspecialchars(substr($r['pesan'],0,60)) . '…' : '—' ?></td>
          <td style="font-size:.8rem"><?= date('d/m/Y', strtotime($r['created_at'])) ?></td>
          <td>
            <?php if ($r['bukti_file']): ?>
              <a href="uploads/bukti/<?= htmlspecialchars($r['bukti_file']) ?>" target="_blank" style="color:#2563eb;font-size:.82rem">Lihat</a>
            <?php else: ?>—<?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($rows)): ?>
        <tr><td colspan="9" style="text-align:center;color:#aaa;padding:24px">Belum ada donatur untuk kampanye ini.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>
</body>
</html>