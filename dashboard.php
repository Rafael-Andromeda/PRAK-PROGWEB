<?php
// dashboard.php — Dashboard Pengelola Kampanye
require_once 'config.php';

// Hanya pengelola yang boleh akses
if (!isLoggedIn() || currentUser()['role'] !== 'pengelola') {
    header('Location: login.html');
    exit;
}
$user = currentUser();
$db   = getDB();

// ── HANDLE AKSI (POST) ────────────────────────────────────────────
$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    // TAMBAH KAMPANYE
    if ($aksi === 'tambah') {
        $judul    = trim($_POST['judul'] ?? '');
        $kategori = trim($_POST['kategori'] ?? '');
        $lokasi   = trim($_POST['lokasi'] ?? '');
        $deskripsi= trim($_POST['deskripsi'] ?? '');
        $target   = floatval($_POST['target_dana'] ?? 0);
        $deadline = $_POST['deadline'] ?? '';
        $metode   = trim($_POST['metode_donasi'] ?? 'Transfer Bank, E-Wallet, QRIS');
        $gambarFile = '';

        // Upload gambar
        if (!empty($_FILES['gambar']['name'])) {
            $f   = $_FILES['gambar'];
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp']) && $f['size'] <= 5*1024*1024) {
                $dir = 'uploads/kampanye/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $gambarFile = 'kampanye_' . time() . '_' . $user['id'] . '.' . $ext;
                if (!move_uploaded_file($f['tmp_name'], $dir . $gambarFile)) $gambarFile = '';
            }
        }

        $stmt = $db->prepare(
            "INSERT INTO kampanye (pengelola_id, judul, kategori, lokasi, deskripsi, gambar, target_dana, deadline, metode_donasi)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("isssssdss", $user['id'], $judul, $kategori, $lokasi, $deskripsi, $gambarFile, $target, $deadline, $metode);
        $stmt->execute() ? ($msg = 'Kampanye berhasil ditambahkan.') : ($msg = 'Gagal: ' . $db->error);
        $msgType = $stmt->affected_rows > 0 ? 'success' : 'error';
        $stmt->close();
    }

    // EDIT KAMPANYE
    elseif ($aksi === 'edit') {
        $kid      = intval($_POST['kampanye_id'] ?? 0);
        $judul    = trim($_POST['judul'] ?? '');
        $kategori = trim($_POST['kategori'] ?? '');
        $lokasi   = trim($_POST['lokasi'] ?? '');
        $deskripsi= trim($_POST['deskripsi'] ?? '');
        $target   = floatval($_POST['target_dana'] ?? 0);
        $deadline = $_POST['deadline'] ?? '';
        $metode   = trim($_POST['metode_donasi'] ?? 'Transfer Bank, E-Wallet, QRIS');

        // Pastikan kampanye milik pengelola ini
        $checkStmt = $db->prepare("SELECT id, gambar FROM kampanye WHERE id=? AND pengelola_id=?");
        $checkStmt->bind_param("ii", $kid, $user['id']);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if ($existing) {
            $gambarFile = $existing['gambar'];
            // Update gambar jika ada upload baru
            if (!empty($_FILES['gambar']['name'])) {
                $f   = $_FILES['gambar'];
                $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','webp']) && $f['size'] <= 5*1024*1024) {
                    $dir = 'uploads/kampanye/';
                    if (!is_dir($dir)) mkdir($dir, 0755, true);
                    $newFile = 'kampanye_' . time() . '_' . $user['id'] . '.' . $ext;
                    if (move_uploaded_file($f['tmp_name'], $dir . $newFile)) {
                        // Hapus gambar lama
                        if ($existing['gambar'] && file_exists($dir . $existing['gambar'])) {
                            unlink($dir . $existing['gambar']);
                        }
                        $gambarFile = $newFile;
                    }
                }
            }

            $stmt = $db->prepare(
                "UPDATE kampanye SET judul=?, kategori=?, lokasi=?, deskripsi=?, gambar=?, target_dana=?, deadline=?, metode_donasi=?
                 WHERE id=? AND pengelola_id=?"
            );
            $stmt->bind_param("sssssdsiii", $judul, $kategori, $lokasi, $deskripsi, $gambarFile, $target, $deadline, $metode, $kid, $user['id']);
            $stmt->execute();
            $msg = 'Kampanye berhasil diperbarui.';
            $stmt->close();
        }
    }

    // HAPUS KAMPANYE
    elseif ($aksi === 'hapus') {
        $kid = intval($_POST['kampanye_id'] ?? 0);
        // Cek: tidak bisa dihapus jika dana >= 10000
        $check = $db->prepare("SELECT dana_terkumpul, gambar FROM kampanye WHERE id=? AND pengelola_id=?");
        $check->bind_param("ii", $kid, $user['id']);
        $check->execute();
        $row = $check->get_result()->fetch_assoc();
        $check->close();

        if (!$row) {
            $msg = 'Kampanye tidak ditemukan.'; $msgType = 'error';
        } elseif ($row['dana_terkumpul'] >= 10000) {
            $msg = 'Kampanye tidak dapat dihapus karena sudah ada dana terkumpul (≥ Rp 10.000).';
            $msgType = 'error';
        } else {
            // Hapus gambar dari server
            if ($row['gambar'] && file_exists('uploads/kampanye/' . $row['gambar'])) {
                unlink('uploads/kampanye/' . $row['gambar']);
            }
            $del = $db->prepare("DELETE FROM kampanye WHERE id=? AND pengelola_id=?");
            $del->bind_param("ii", $kid, $user['id']);
            $del->execute();
            $msg = 'Kampanye berhasil dihapus.';
            $del->close();
        }
    }

    // VERIFIKASI DONASI
    elseif ($aksi === 'verifikasi') {
        $donId   = intval($_POST['donasi_id'] ?? 0);
        $status  = $_POST['status'] ?? ''; // 'verified' atau 'rejected'
        if (!in_array($status, ['verified','rejected'])) {
            $msg = 'Status tidak valid.'; $msgType = 'error';
        } else {
            // Pastikan donasi ini milik kampanye si pengelola
            $check = $db->prepare(
                "SELECT d.id, d.nominal, d.kampanye_id, d.status
                 FROM donasi d
                 JOIN kampanye k ON k.id = d.kampanye_id
                 WHERE d.id=? AND k.pengelola_id=?
                 LIMIT 1"
            );
            $check->bind_param("ii", $donId, $user['id']);
            $check->execute();
            $don = $check->get_result()->fetch_assoc();
            $check->close();

            if ($don && $don['status'] === 'pending') {
                $upd = $db->prepare("UPDATE donasi SET status=?, verified_at=NOW() WHERE id=?");
                $upd->bind_param("si", $status, $donId);
                $upd->execute();
                $upd->close();

                // Jika diverifikasi, tambahkan dana terkumpul
                if ($status === 'verified') {
                    $addDana = $db->prepare("UPDATE kampanye SET dana_terkumpul = dana_terkumpul + ? WHERE id=?");
                    $addDana->bind_param("di", $don['nominal'], $don['kampanye_id']);
                    $addDana->execute();
                    $addDana->close();
                    $msg = 'Donasi diverifikasi dan dana berhasil ditambahkan.';
                } else {
                    $msg = 'Donasi ditolak.';
                }
            } else {
                $msg = 'Donasi tidak ditemukan atau sudah diverifikasi.'; $msgType = 'error';
            }
        }
    }
}

// ── AMBIL DATA KAMPANYE ───────────────────────────────────────────
$stmtK = $db->prepare(
    "SELECT id, pengelola_id, judul, kategori, target_dana, dana_terkumpul, deadline
     FROM kampanye ORDER BY created_at DESC"
);
$stmtK->execute();
$kampanyes = $stmtK->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtK->close();

// ── AMBIL SEMUA DONASI ────────────────────────────────────────────
$stmtD = $db->prepare(
    "SELECT d.id, d.nominal, d.metode, d.status, d.pesan, d.bukti_file, d.created_at, d.verified_at,
            dt.nama AS donatur_nama, dt.email AS donatur_email,
            k.judul AS kampanye_judul, k.id AS kampanye_id
     FROM donasi d
     JOIN donatur dt ON dt.id = d.donatur_id
     JOIN kampanye k ON k.id = d.kampanye_id
     ORDER BY d.created_at DESC"
);
$stmtD->execute();
$donasis = $stmtD->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtD->close();

// ── SUMMARY ───────────────────────────────────────────────────────
$totalKampanye   = count($kampanyes);
$pendingDonasi   = count(array_filter($donasis, fn($d) => $d['status'] === 'pending'));
$verifiedDonasi  = count(array_filter($donasis, fn($d) => $d['status'] === 'verified'));
$totalDana       = array_sum(array_column($kampanyes, 'dana_terkumpul'));
$danaPending     = array_sum(array_map(fn($d) => $d['nominal'], array_filter($donasis, fn($d) => $d['status'] === 'pending')));

$db->close();

function rp($n) { return 'Rp ' . number_format($n, 0, ',', '.'); }
$KATEGORI_LIST = ['Lingkungan','Kesehatan','Pendidikan','Bencana','Fasilitas Umum'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Pengelola — Kindnesia</title>
  <link rel="stylesheet" href="dashboard.css">
  <style>
    .alert { padding:12px 18px; border-radius:10px; margin-bottom:18px; font-weight:600; }
    .alert-success { background:#F0FDF4; border:1.5px solid #86EFAC; color:#166534; }
    .alert-error   { background:#FEF2F2; border:1.5px solid #FCA5A5; color:#DC2626; }
    .badge-pending  { background:#FEF9C3; color:#854D0E; padding:3px 10px; border-radius:20px; font-size:.8rem; font-weight:700; }
    .badge-verified { background:#DCFCE7; color:#166534; padding:3px 10px; border-radius:20px; font-size:.8rem; font-weight:700; }
    .badge-rejected { background:#FEE2E2; color:#991B1B; padding:3px 10px; border-radius:20px; font-size:.8rem; font-weight:700; }
    .modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; justify-content:center; align-items:flex-start; padding:40px 16px; overflow-y:auto; }
    .modal.open { display:flex; }
    .modal-box { background:#fff; border-radius:16px; padding:32px; width:100%; max-width:560px; position:relative; }
    .modal-box h3 { margin:0 0 20px; }
    .modal-close { position:absolute; top:16px; right:20px; background:none; border:none; font-size:1.4rem; cursor:pointer; color:#888; }
    .form-row { margin-bottom:16px; }
    .form-row label { display:block; font-size:.85rem; font-weight:600; color:#555; margin-bottom:5px; }
    .form-row input, .form-row select, .form-row textarea {
        width:100%; padding:10px 12px; border:1.5px solid #ddd; border-radius:8px;
        font-size:.95rem; box-sizing:border-box;
    }
    .form-row input:focus, .form-row select:focus, .form-row textarea:focus { border-color:#4CAF50; outline:none; }
    .form-row textarea { min-height:80px; resize:vertical; }
    .btn-form { padding:11px 24px; border:none; border-radius:9px; cursor:pointer; font-weight:700; font-size:.95rem; }
    .btn-green { background:#4CAF50; color:#fff; }
    .btn-green:hover { background:#388E3C; }
    .bukti-link { color:#2563eb; font-size:.8rem; text-decoration:none; }
    .bukti-link:hover { text-decoration:underline; }
    .donasi-summary { display:flex; gap:16px; flex-wrap:wrap; margin-bottom:16px; }
    .ds-box { flex:1; min-width:120px; background:#f8f8f8; border-radius:10px; padding:12px 16px; border:1px solid #eee; }
    .ds-box .ds-label { font-size:.78rem; color:#888; }
    .ds-box .ds-val { font-weight:700; font-size:1rem; margin-top:2px; }
  </style>
</head>
<body>

<header>
  <div class="container nav">
    <h1 class="logo">Kindnesia</h1>
    <nav>
      <span id="welcomeText" class="welcome-text">👋 <?= htmlspecialchars($user['nama']) ?></span>
      <a href="index.php">Beranda</a>
      <a href="logout.php" class="logout-btn">Logout</a>
    </nav>
  </div>
</header>

<main class="container">

  <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType === 'success' ? 'success' : 'error' ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <!-- SUMMARY CARDS -->
  <section class="summary-grid">
    <div class="summary-card blue">
      <div class="summary-icon">📋</div>
      <div class="summary-info">
        <div class="summary-num"><?= $totalKampanye ?></div>
        <div class="summary-label">Total Kampanye</div>
      </div>
    </div>
    <div class="summary-card green">
      <div class="summary-icon">✅</div>
      <div class="summary-info">
        <div class="summary-num"><?= $verifiedDonasi ?></div>
        <div class="summary-label">Donasi Terverifikasi</div>
      </div>
    </div>
    <div class="summary-card yellow">
      <div class="summary-icon">⏳</div>
      <div class="summary-info">
        <div class="summary-num"><?= $pendingDonasi ?></div>
        <div class="summary-label">Menunggu Verifikasi</div>
      </div>
    </div>
    <div class="summary-card teal">
      <div class="summary-icon">💰</div>
      <div class="summary-info">
        <div class="summary-num"><?= rp($totalDana) ?></div>
        <div class="summary-label">Total Dana Terkumpul</div>
      </div>
    </div>
  </section>

  <!-- TABS -->
  <div class="tabs">
    <button class="tab-btn active" onclick="showTab('kampanye', this)">📋 Semua Kampanye</button>
    <button class="tab-btn" onclick="showTab('donasi', this)">📥 Donasi Masuk</button>
  </div>

  <!-- TAB: KAMPANYE -->
  <section id="tab-kampanye" class="tab-content active">
    <div class="section-header">
      <h2>Semua Kampanye</h2>
      <button class="btn-primary" onclick="openModal('modalTambah')">+ Tambah Kampanye</button>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Judul</th><th>Kategori</th><th>Target</th>
            <th>Terkumpul</th><th>Progress</th><th>Deadline</th><th>Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($kampanyes as $k):
            $pct = $k['target_dana'] > 0 ? min(100, round($k['dana_terkumpul'] / $k['target_dana'] * 100)) : 0;
            $sisa = max(0, intval((strtotime($k['deadline']) - time()) / 86400));
        ?>
          <tr>
            <td><strong><?= htmlspecialchars($k['judul']) ?></strong></td>
            <td><span class="badge"><?= htmlspecialchars($k['kategori']) ?></span></td>
            <td><?= rp($k['target_dana']) ?></td>
            <td><?= rp($k['dana_terkumpul']) ?></td>
            <td>
              <div class="mini-bar"><div style="width:<?= $pct ?>%"></div></div>
              <small><?= $pct ?>%</small>
            </td>
            <td><?= date('d/m/Y', strtotime($k['deadline'])) ?> <small>(<?= $sisa ?> hr)</small></td>
            <td>
              <?php if ($k['pengelola_id'] == $user['id']): ?>
              <button class="btn-sm btn-edit"
                onclick="openEdit(<?= $k['id'] ?>, <?= htmlspecialchars(json_encode($k)) ?>)">Edit</button>
              <form method="POST" style="display:inline"
                    onsubmit="return confirm('Yakin hapus kampanye ini? Hanya bisa dihapus jika belum ada dana.')">
                <input type="hidden" name="aksi" value="hapus">
                <input type="hidden" name="kampanye_id" value="<?= $k['id'] ?>">
                <button type="submit" class="btn-sm btn-danger">Hapus</button>
              </form>
              <?php else: ?>
              <span style="color:#888;font-size:.85rem;display:inline-block;margin-right:8px;">Tidak bisa diedit</span>
              <?php endif; ?>
              <a href="donatur_kampanye.php?id=<?= $k['id'] ?>" class="btn-sm" style="background:#3b82f6;color:#fff;padding:5px 10px;border-radius:6px;text-decoration:none;font-size:.8rem;">Donatur</a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($kampanyes)): ?>
          <tr><td colspan="7" style="text-align:center;color:#aaa;padding:24px;">Belum ada kampanye. Tambahkan yang pertama!</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <!-- TAB: DONASI -->
  <section id="tab-donasi" class="tab-content">
    <h2>Donasi Masuk</h2>

    <!-- Summary per-kampanye -->
    <div class="donasi-summary">
      <?php
        $danaVerif   = array_sum(array_map(fn($d) => $d['nominal'], array_filter($donasis, fn($d) => $d['status'] === 'verified')));
        $danaPend    = array_sum(array_map(fn($d) => $d['nominal'], array_filter($donasis, fn($d) => $d['status'] === 'pending')));
        $danaReject  = array_sum(array_map(fn($d) => $d['nominal'], array_filter($donasis, fn($d) => $d['status'] === 'rejected')));
      ?>
      <div class="ds-box"><div class="ds-label">✅ Dana Terverifikasi</div><div class="ds-val" style="color:#166534"><?= rp($danaVerif) ?></div></div>
      <div class="ds-box"><div class="ds-label">⏳ Dana Pending</div><div class="ds-val" style="color:#92400e"><?= rp($danaPend) ?></div></div>
      <div class="ds-box"><div class="ds-label">❌ Dana Ditolak</div><div class="ds-val" style="color:#991B1B"><?= rp($danaReject) ?></div></div>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Donatur</th><th>Kampanye</th><th>Nominal</th>
            <th>Metode</th><th>Bukti</th><th>Status</th><th>Tanggal</th><th>Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($donasis as $d): ?>
          <tr>
            <td>
              <strong><?= htmlspecialchars($d['donatur_nama']) ?></strong><br>
              <small style="color:#888"><?= htmlspecialchars($d['donatur_email']) ?></small>
            </td>
            <td style="font-size:.85rem"><?= htmlspecialchars($d['kampanye_judul']) ?></td>
            <td><?= rp($d['nominal']) ?></td>
            <td><?= htmlspecialchars($d['metode']) ?></td>
            <td>
              <?php if ($d['bukti_file']): ?>
                <a href="uploads/bukti/<?= htmlspecialchars($d['bukti_file']) ?>" class="bukti-link" target="_blank">Lihat Bukti</a>
              <?php else: ?>
                <span style="color:#ccc">—</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($d['status'] === 'pending'): ?>
                <span class="badge-pending">⏳ Pending</span>
              <?php elseif ($d['status'] === 'verified'): ?>
                <span class="badge-verified">✅ Verified</span>
              <?php else: ?>
                <span class="badge-rejected">❌ Ditolak</span>
              <?php endif; ?>
            </td>
            <td style="font-size:.8rem"><?= date('d/m/Y H:i', strtotime($d['created_at'])) ?></td>
            <td>
              <?php if ($d['status'] === 'pending'): ?>
                <form method="POST" style="display:flex;gap:4px">
                  <input type="hidden" name="aksi" value="verifikasi">
                  <input type="hidden" name="donasi_id" value="<?= $d['id'] ?>">
                  <button type="submit" name="status" value="verified" class="btn-sm btn-edit"
                          onclick="return confirm('Verifikasi donasi ini?')">✅ Terima</button>
                  <button type="submit" name="status" value="rejected" class="btn-sm btn-danger"
                          onclick="return confirm('Tolak donasi ini?')">❌ Tolak</button>
                </form>
              <?php else: ?>
                <span style="color:#aaa;font-size:.8rem">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($donasis)): ?>
          <tr><td colspan="8" style="text-align:center;color:#aaa;padding:24px;">Belum ada donasi masuk.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

</main>

<!-- MODAL TAMBAH KAMPANYE -->
<div class="modal" id="modalTambah">
  <div class="modal-box">
    <button class="modal-close" onclick="closeModal('modalTambah')">✕</button>
    <h3>+ Tambah Kampanye</h3>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="aksi" value="tambah">
      <div class="form-row"><label>Judul Kampanye *</label><input type="text" name="judul" required></div>
      <div class="form-row">
        <label>Kategori *</label>
        <select name="kategori" required>
          <?php foreach ($KATEGORI_LIST as $kat): ?><option><?= $kat ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-row"><label>Lokasi</label><input type="text" name="lokasi" placeholder="Contoh: Jawa Barat, Indonesia"></div>
      <div class="form-row"><label>Deskripsi</label><textarea name="deskripsi"></textarea></div>
      <div class="form-row"><label>Target Dana (Rp) *</label><input type="number" name="target_dana" min="100000" required></div>
      <div class="form-row"><label>Deadline *</label><input type="date" name="deadline" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required></div>
      <div class="form-row"><label>Metode Donasi</label><input type="text" name="metode_donasi" value="Transfer Bank, E-Wallet, QRIS"></div>
      <div class="form-row"><label>Gambar Kampanye (JPG/PNG, maks 5MB)</label><input type="file" name="gambar" accept=".jpg,.jpeg,.png,.webp"></div>
      <button type="submit" class="btn-form btn-green" style="width:100%;margin-top:8px">Simpan Kampanye</button>
    </form>
  </div>
</div>

<!-- MODAL EDIT KAMPANYE -->
<div class="modal" id="modalEdit">
  <div class="modal-box">
    <button class="modal-close" onclick="closeModal('modalEdit')">✕</button>
    <h3>✏ Edit Kampanye</h3>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="aksi" value="edit">
      <input type="hidden" name="kampanye_id" id="editId">
      <div class="form-row"><label>Judul Kampanye *</label><input type="text" name="judul" id="editJudul" required></div>
      <div class="form-row">
        <label>Kategori *</label>
        <select name="kategori" id="editKategori" required>
          <?php foreach ($KATEGORI_LIST as $kat): ?><option><?= $kat ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-row"><label>Lokasi</label><input type="text" name="lokasi" id="editLokasi"></div>
      <div class="form-row"><label>Deskripsi</label><textarea name="deskripsi" id="editDeskripsi"></textarea></div>
      <div class="form-row"><label>Target Dana (Rp) *</label><input type="number" name="target_dana" id="editTarget" min="100000" required></div>
      <div class="form-row"><label>Deadline *</label><input type="date" name="deadline" id="editDeadline" required></div>
      <div class="form-row"><label>Metode Donasi</label><input type="text" name="metode_donasi" id="editMetode"></div>
      <div class="form-row"><label>Ganti Gambar (opsional)</label><input type="file" name="gambar" accept=".jpg,.jpeg,.png,.webp"></div>
      <button type="submit" class="btn-form btn-green" style="width:100%;margin-top:8px">Update Kampanye</button>
    </form>
  </div>
</div>

<script>
function showTab(tab, btn) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + tab).classList.add('active');
    btn.classList.add('active');
}
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

function openEdit(id, data) {
    document.getElementById('editId').value       = id;
    document.getElementById('editJudul').value    = data.judul;
    document.getElementById('editKategori').value = data.kategori;
    document.getElementById('editLokasi').value   = data.lokasi || '';
    document.getElementById('editDeskripsi').value= data.deskripsi || '';
    document.getElementById('editTarget').value   = data.target_dana;
    document.getElementById('editDeadline').value = data.deadline;
    document.getElementById('editMetode').value   = data.metode_donasi || '';
    openModal('modalEdit');
}

// Close modal if click outside
document.querySelectorAll('.modal').forEach(m => {
    m.addEventListener('click', function(e) {
        if (e.target === m) m.classList.remove('open');
    });
});
</script>
</body>
</html>
