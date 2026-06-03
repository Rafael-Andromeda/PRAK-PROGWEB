<?php
require_once 'config.php';

if (!isLoggedIn() || currentUser()['role'] !== 'pengelola') {
    header('Location: login.html');
    exit;
}

$user = currentUser();
$db   = getDB();

$msg = '';
$msgType = 'success';
$KATEGORI_LIST = ['Lingkungan','Kesehatan','Pendidikan','Bencana','Fasilitas Umum'];

function rp($n) {
    return 'Rp ' . number_format((float)$n, 0, ',', '.');
}

function setFlash(&$msg, &$msgType, $text, $type = 'success') {
    $msg = $text;
    $msgType = $type;
}

function uploadImageField($fieldName, $folder, $prefix, $userId, &$errorMsg) {
    if (empty($_FILES[$fieldName]['name'])) {
        return '';
    }

    $file = $_FILES[$fieldName];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMsg = 'Upload gambar gagal. Silakan pilih file ulang.';
        return false;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    $maxSize = 5 * 1024 * 1024;

    if (!in_array($ext, $allowed, true)) {
        $errorMsg = 'Format gambar kampanye harus JPG, JPEG, PNG, atau WEBP.';
        return false;
    }

    if ($file['size'] > $maxSize) {
        $errorMsg = 'Ukuran gambar kampanye maksimal 5 MB.';
        return false;
    }

    if (!is_dir($folder)) {
        mkdir($folder, 0755, true);
    }

    $safeName = $prefix . '_' . time() . '_' . $userId . '_' . random_int(1000, 9999) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $folder . $safeName)) {
        $errorMsg = 'Gagal menyimpan gambar ke server.';
        return false;
    }

    return $safeName;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'tambah') {
        $judul     = trim($_POST['judul'] ?? '');
        $kategori  = trim($_POST['kategori'] ?? '');
        $lokasi    = trim($_POST['lokasi'] ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $target    = (float)($_POST['target_dana'] ?? 0);
        $deadline  = trim($_POST['deadline'] ?? '');
        $metode    = trim($_POST['metode_donasi'] ?? 'Transfer Bank, E-Wallet, QRIS');

        if ($judul === '' || $kategori === '' || $target <= 0 || $deadline === '') {
            setFlash($msg, $msgType, 'Judul, kategori, target dana, dan deadline wajib diisi.', 'error');
        } elseif (!in_array($kategori, $KATEGORI_LIST, true)) {
            setFlash($msg, $msgType, 'Kategori tidak valid.', 'error');
        } elseif ($target < 100000) {
            setFlash($msg, $msgType, 'Target dana minimal Rp 100.000.', 'error');
        } else {
            $uploadError = '';
            $gambarFile = uploadImageField('gambar', 'uploads/kampanye/', 'kampanye', $user['id'], $uploadError);
            if ($gambarFile === false) {
                setFlash($msg, $msgType, $uploadError, 'error');
            } else {
                $stmt = $db->prepare(
                    "INSERT INTO kampanye (pengelola_id, judul, kategori, lokasi, deskripsi, gambar, target_dana, deadline, metode_donasi)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->bind_param('isssssdss', $user['id'], $judul, $kategori, $lokasi, $deskripsi, $gambarFile, $target, $deadline, $metode);
                if ($stmt->execute()) {
                    setFlash($msg, $msgType, 'Kampanye berhasil ditambahkan.', 'success');
                } else {
                    setFlash($msg, $msgType, 'Gagal menambahkan kampanye: ' . $stmt->error, 'error');
                }
                $stmt->close();
            }
        }
    }

    elseif ($aksi === 'edit') {
        $kid       = (int)($_POST['kampanye_id'] ?? 0);
        $judul     = trim($_POST['judul'] ?? '');
        $kategori  = trim($_POST['kategori'] ?? '');
        $lokasi    = trim($_POST['lokasi'] ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $target    = (float)($_POST['target_dana'] ?? 0);
        $deadline  = trim($_POST['deadline'] ?? '');
        $metode    = trim($_POST['metode_donasi'] ?? 'Transfer Bank, E-Wallet, QRIS');

        if ($kid <= 0 || $judul === '' || $kategori === '' || $target <= 0 || $deadline === '') {
            setFlash($msg, $msgType, 'Data edit kampanye belum lengkap.', 'error');
        } elseif (!in_array($kategori, $KATEGORI_LIST, true)) {
            setFlash($msg, $msgType, 'Kategori tidak valid.', 'error');
        } elseif ($target < 100000) {
            setFlash($msg, $msgType, 'Target dana minimal Rp 100.000.', 'error');
        } else {
            $checkStmt = $db->prepare('SELECT id, gambar FROM kampanye WHERE id = ? AND pengelola_id = ? LIMIT 1');
            $checkStmt->bind_param('ii', $kid, $user['id']);
            $checkStmt->execute();
            $existing = $checkStmt->get_result()->fetch_assoc();
            $checkStmt->close();

            if (!$existing) {
                setFlash($msg, $msgType, 'Kampanye tidak ditemukan atau bukan milik akun ini.', 'error');
            } else {
                $gambarFile = $existing['gambar'];
                if (!empty($_FILES['gambar']['name'])) {
                    $uploadError = '';
                    $newFile = uploadImageField('gambar', 'uploads/kampanye/', 'kampanye', $user['id'], $uploadError);
                    if ($newFile === false) {
                        setFlash($msg, $msgType, $uploadError, 'error');
                    } else {
                        if (!empty($existing['gambar']) && file_exists('uploads/kampanye/' . $existing['gambar'])) {
                            unlink('uploads/kampanye/' . $existing['gambar']);
                        }
                        $gambarFile = $newFile;
                    }
                }

                if ($msgType !== 'error') {
                    $stmt = $db->prepare(
                        "UPDATE kampanye
                         SET judul = ?, kategori = ?, lokasi = ?, deskripsi = ?, gambar = ?, target_dana = ?, deadline = ?, metode_donasi = ?
                         WHERE id = ? AND pengelola_id = ?"
                    );
                    $stmt->bind_param('sssssdssii', $judul, $kategori, $lokasi, $deskripsi, $gambarFile, $target, $deadline, $metode, $kid, $user['id']);
                    if ($stmt->execute()) {
                        setFlash($msg, $msgType, 'Kampanye berhasil diperbarui.', 'success');
                    } else {
                        setFlash($msg, $msgType, 'Gagal memperbarui kampanye: ' . $stmt->error, 'error');
                    }
                    $stmt->close();
                }
            }
        }
    }

    elseif ($aksi === 'hapus') {
        $kid = (int)($_POST['kampanye_id'] ?? 0);
        $check = $db->prepare('SELECT dana_terkumpul, gambar FROM kampanye WHERE id = ? AND pengelola_id = ? LIMIT 1');
        $check->bind_param('ii', $kid, $user['id']);
        $check->execute();
        $row = $check->get_result()->fetch_assoc();
        $check->close();

        if (!$row) {
            setFlash($msg, $msgType, 'Kampanye tidak ditemukan atau bukan milik akun ini.', 'error');
        } elseif ((float)$row['dana_terkumpul'] >= 10000) {
            setFlash($msg, $msgType, 'Kampanye tidak dapat dihapus karena dana terkumpul sudah ≥ Rp 10.000.', 'error');
        } else {
            if (!empty($row['gambar']) && file_exists('uploads/kampanye/' . $row['gambar'])) {
                unlink('uploads/kampanye/' . $row['gambar']);
            }
            $del = $db->prepare('DELETE FROM kampanye WHERE id = ? AND pengelola_id = ?');
            $del->bind_param('ii', $kid, $user['id']);
            if ($del->execute()) {
                setFlash($msg, $msgType, 'Kampanye berhasil dihapus.', 'success');
            } else {
                setFlash($msg, $msgType, 'Gagal menghapus kampanye: ' . $del->error, 'error');
            }
            $del->close();
        }
    }

    elseif ($aksi === 'verifikasi') {
        $donId  = (int)($_POST['donasi_id'] ?? 0);
        $status = $_POST['status'] ?? '';

        if (!in_array($status, ['verified', 'rejected'], true)) {
            setFlash($msg, $msgType, 'Status verifikasi tidak valid.', 'error');
        } else {
            $check = $db->prepare(
                "SELECT d.id, d.nominal, d.kampanye_id, d.status
                 FROM donasi d
                 JOIN kampanye k ON k.id = d.kampanye_id
                 WHERE d.id = ? AND k.pengelola_id = ?
                 LIMIT 1"
            );
            $check->bind_param('ii', $donId, $user['id']);
            $check->execute();
            $don = $check->get_result()->fetch_assoc();
            $check->close();

            if (!$don) {
                setFlash($msg, $msgType, 'Donasi tidak ditemukan atau bukan milik kampanye Anda.', 'error');
            } elseif ($don['status'] !== 'pending') {
                setFlash($msg, $msgType, 'Donasi ini sudah pernah diverifikasi/ditolak.', 'error');
            } else {
                $db->begin_transaction();
                try {
                    $upd = $db->prepare('UPDATE donasi SET status = ?, verified_at = NOW() WHERE id = ? AND status = "pending"');
                    $upd->bind_param('si', $status, $donId);
                    $upd->execute();
                    $upd->close();

                    if ($status === 'verified') {
                        $addDana = $db->prepare(
                            'UPDATE kampanye SET dana_terkumpul = dana_terkumpul + ? WHERE id = ? AND pengelola_id = ?'
                        );
                        $addDana->bind_param('dii', $don['nominal'], $don['kampanye_id'], $user['id']);
                        $addDana->execute();
                        $addDana->close();
                        setFlash($msg, $msgType, 'Donasi berhasil diverifikasi dan dana terkumpul bertambah.', 'success');
                    } else {
                        setFlash($msg, $msgType, 'Donasi berhasil ditolak. Dana tidak ditambahkan.', 'success');
                    }
                    $db->commit();
                } catch (Throwable $e) {
                    $db->rollback();
                    setFlash($msg, $msgType, 'Gagal memproses verifikasi: ' . $e->getMessage(), 'error');
                }
            }
        }
    }
}

// Ambil kampanye hanya milik pengelola login + total pending per kampanye
$stmtK = $db->prepare(
    "SELECT k.id, k.pengelola_id, k.judul, k.kategori, k.lokasi, k.deskripsi, k.gambar,
            k.target_dana, k.dana_terkumpul, k.deadline, k.metode_donasi, k.created_at,
            COALESCE(SUM(CASE WHEN d.status = 'pending' THEN d.nominal ELSE 0 END), 0) AS dana_pending,
            COUNT(d.id) AS jumlah_donasi
     FROM kampanye k
     LEFT JOIN donasi d ON d.kampanye_id = k.id
     WHERE k.pengelola_id = ?
     GROUP BY k.id
     ORDER BY k.created_at DESC"
);
$stmtK->bind_param('i', $user['id']);
$stmtK->execute();
$kampanyes = $stmtK->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtK->close();

// Ambil donasi hanya dari kampanye milik pengelola login
$stmtD = $db->prepare(
    "SELECT d.id, d.nominal, d.metode, d.status, d.pesan, d.bukti_file, d.created_at, d.verified_at,
            dt.nama AS donatur_nama, dt.email AS donatur_email,
            k.judul AS kampanye_judul, k.id AS kampanye_id, k.dana_terkumpul
     FROM donasi d
     JOIN donatur dt ON dt.id = d.donatur_id
     JOIN kampanye k ON k.id = d.kampanye_id
     WHERE k.pengelola_id = ?
     ORDER BY d.created_at DESC"
);
$stmtD->bind_param('i', $user['id']);
$stmtD->execute();
$donasis = $stmtD->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtD->close();

$totalKampanye  = count($kampanyes);
$pendingDonasi  = count(array_filter($donasis, fn($d) => $d['status'] === 'pending'));
$verifiedDonasi = count(array_filter($donasis, fn($d) => $d['status'] === 'verified'));
$totalDana      = array_sum(array_column($kampanyes, 'dana_terkumpul'));
$danaPending    = array_sum(array_column($kampanyes, 'dana_pending'));
$danaVerified   = array_sum(array_map(fn($d) => (float)$d['nominal'], array_filter($donasis, fn($d) => $d['status'] === 'verified')));
$danaRejected   = array_sum(array_map(fn($d) => (float)$d['nominal'], array_filter($donasis, fn($d) => $d['status'] === 'rejected')));

$db->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Pengelola - Kindnesia</title>
  <link rel="stylesheet" href="assets/css/dashboard.css">
  <style>
    .alert { padding:12px 18px; border-radius:10px; margin-bottom:18px; font-weight:600; }
    .alert-success { background:#F0FDF4; border:1.5px solid #86EFAC; color:#166534; }
    .alert-error { background:#FEF2F2; border:1.5px solid #FCA5A5; color:#DC2626; }
    .badge-pending { background:#FEF9C3; color:#854D0E; padding:3px 10px; border-radius:20px; font-size:.8rem; font-weight:700; }
    .badge-verified { background:#DCFCE7; color:#166534; padding:3px 10px; border-radius:20px; font-size:.8rem; font-weight:700; }
    .badge-rejected { background:#FEE2E2; color:#991B1B; padding:3px 10px; border-radius:20px; font-size:.8rem; font-weight:700; }
    .modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; justify-content:center; align-items:flex-start; padding:40px 16px; overflow-y:auto; }
    .modal.open { display:flex; }
    .modal-box { background:#fff; border-radius:16px; padding:32px; width:100%; max-width:560px; position:relative; }
    .modal-box h3 { margin:0 0 20px; }
    .modal-close { position:absolute; top:16px; right:20px; background:none; border:none; font-size:1.4rem; cursor:pointer; color:#888; }
    .form-row { margin-bottom:16px; }
    .form-row label { display:block; font-size:.85rem; font-weight:600; color:#555; margin-bottom:5px; }
    .form-row input, .form-row select, .form-row textarea { width:100%; padding:10px 12px; border:1.5px solid #ddd; border-radius:8px; font-size:.95rem; box-sizing:border-box; }
    .form-row input:focus, .form-row select:focus, .form-row textarea:focus { border-color:#4CAF50; outline:none; }
    .form-row textarea { min-height:80px; resize:vertical; }
    .btn-form { padding:11px 24px; border:none; border-radius:9px; cursor:pointer; font-weight:700; font-size:.95rem; }
    .btn-green { background:#4CAF50; color:#fff; }
    .btn-green:hover { background:#388E3C; }
    .bukti-link { color:#2563eb; font-size:.8rem; text-decoration:none; }
    .bukti-link:hover { text-decoration:underline; }
    .donasi-summary { display:flex; gap:16px; flex-wrap:wrap; margin-bottom:16px; }
    .ds-box { flex:1; min-width:150px; background:#f8f8f8; border-radius:10px; padding:12px 16px; border:1px solid #eee; }
    .ds-box .ds-label { font-size:.78rem; color:#888; }
    .ds-box .ds-val { font-weight:700; font-size:1rem; margin-top:2px; }
    .text-muted { color:#888; font-size:.82rem; }
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

  <section class="summary-grid">
    <div class="summary-card blue">
      <div class="summary-icon">📋</div>
      <div class="summary-info"><div class="summary-num"><?= $totalKampanye ?></div><div class="summary-label">Kampanye Saya</div></div>
    </div>
    <div class="summary-card green">
      <div class="summary-icon">✅</div>
      <div class="summary-info"><div class="summary-num"><?= $verifiedDonasi ?></div><div class="summary-label">Donasi Terverifikasi</div></div>
    </div>
    <div class="summary-card yellow">
      <div class="summary-icon">⏳</div>
      <div class="summary-info"><div class="summary-num"><?= $pendingDonasi ?></div><div class="summary-label">Menunggu Verifikasi</div></div>
    </div>
    <div class="summary-card teal">
      <div class="summary-icon">💰</div>
      <div class="summary-info"><div class="summary-num"><?= rp($totalDana) ?></div><div class="summary-label">Dana Terkumpul</div></div>
    </div>
  </section>

  <div class="tabs">
    <button class="tab-btn active" onclick="showTab('kampanye', this)">📋 Kampanye Saya</button>
    <button class="tab-btn" onclick="showTab('donasi', this)">📥 Donasi Masuk</button>
  </div>

  <section id="tab-kampanye" class="tab-content active">
    <div class="section-header">
      <h2>Kampanye Saya</h2>
      <button class="btn-primary" onclick="openModal('modalTambah')">+ Tambah Kampanye</button>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Judul</th><th>Kategori</th><th>Target</th><th>Terkumpul</th><th>Pending</th><th>Progress</th><th>Deadline</th><th>Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($kampanyes as $k):
          $pct = $k['target_dana'] > 0 ? min(100, round($k['dana_terkumpul'] / $k['target_dana'] * 100)) : 0;
          $sisa = max(0, (int)((strtotime($k['deadline']) - strtotime(date('Y-m-d'))) / 86400));
          $json = htmlspecialchars(json_encode($k, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
        ?>
          <tr>
            <td><strong><?= htmlspecialchars($k['judul']) ?></strong><br><span class="text-muted"><?= (int)$k['jumlah_donasi'] ?> donasi</span></td>
            <td><span class="badge"><?= htmlspecialchars($k['kategori']) ?></span></td>
            <td><?= rp($k['target_dana']) ?></td>
            <td><?= rp($k['dana_terkumpul']) ?></td>
            <td><?= rp($k['dana_pending']) ?></td>
            <td><div class="mini-bar"><div style="width:<?= $pct ?>%"></div></div><small><?= $pct ?>%</small></td>
            <td><?= date('d/m/Y', strtotime($k['deadline'])) ?> <small>(<?= $sisa ?> hr)</small></td>
            <td>
              <button class="btn-sm btn-edit" onclick="openEdit(<?= (int)$k['id'] ?>, <?= $json ?>)">Edit</button>
              <form method="POST" style="display:inline" onsubmit="return confirm('Yakin hapus kampanye ini? Kampanye hanya bisa dihapus jika dana terkumpul belum mencapai Rp 10.000.')">
                <input type="hidden" name="aksi" value="hapus">
                <input type="hidden" name="kampanye_id" value="<?= (int)$k['id'] ?>">
                <button type="submit" class="btn-sm btn-danger">Hapus</button>
              </form>
              <a href="donatur_kampanye.php?id=<?= (int)$k['id'] ?>" class="btn-sm" style="background:#3b82f6;color:#fff;padding:5px 10px;border-radius:6px;text-decoration:none;font-size:.8rem;">Donatur</a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($kampanyes)): ?>
          <tr><td colspan="8" style="text-align:center;color:#aaa;padding:24px;">Belum ada kampanye. Tambahkan yang pertama!</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section id="tab-donasi" class="tab-content">
    <h2>Donasi Masuk</h2>
    <div class="donasi-summary">
      <div class="ds-box"><div class="ds-label">✅ Dana Terverifikasi</div><div class="ds-val" style="color:#166534"><?= rp($danaVerified) ?></div></div>
      <div class="ds-box"><div class="ds-label">⏳ Dana Pending</div><div class="ds-val" style="color:#92400e"><?= rp($danaPending) ?></div></div>
      <div class="ds-box"><div class="ds-label">❌ Dana Ditolak</div><div class="ds-val" style="color:#991B1B"><?= rp($danaRejected) ?></div></div>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Donatur</th><th>Kampanye</th><th>Nominal</th><th>Metode</th><th>Bukti</th><th>Status</th><th>Tanggal</th><th>Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($donasis as $d): ?>
          <tr>
            <td><strong><?= htmlspecialchars($d['donatur_nama']) ?></strong><br><small style="color:#888"><?= htmlspecialchars($d['donatur_email']) ?></small></td>
            <td style="font-size:.85rem"><?= htmlspecialchars($d['kampanye_judul']) ?><br><span class="text-muted">Terkumpul: <?= rp($d['dana_terkumpul']) ?></span></td>
            <td><?= rp($d['nominal']) ?></td>
            <td><?= htmlspecialchars($d['metode']) ?></td>
            <td>
              <?php if ($d['bukti_file']): ?>
                <a href="uploads/bukti/<?= rawurlencode($d['bukti_file']) ?>" class="bukti-link" target="_blank">Lihat Bukti</a>
              <?php else: ?>
                <span style="color:#ccc">—</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($d['status'] === 'pending'): ?><span class="badge-pending">⏳ Pending</span>
              <?php elseif ($d['status'] === 'verified'): ?><span class="badge-verified">✅ Verified</span>
              <?php else: ?><span class="badge-rejected">❌ Ditolak</span><?php endif; ?>
            </td>
            <td style="font-size:.8rem"><?= date('d/m/Y H:i', strtotime($d['created_at'])) ?></td>
            <td>
              <?php if ($d['status'] === 'pending'): ?>
                <form method="POST" style="display:flex;gap:4px;flex-wrap:wrap">
                  <input type="hidden" name="aksi" value="verifikasi">
                  <input type="hidden" name="donasi_id" value="<?= (int)$d['id'] ?>">
                  <button type="submit" name="status" value="verified" class="btn-sm btn-edit" onclick="return confirm('Verifikasi donasi ini? Dana kampanye akan bertambah.')">✅ Terima</button>
                  <button type="submit" name="status" value="rejected" class="btn-sm btn-danger" onclick="return confirm('Tolak donasi ini? Dana kampanye tidak akan bertambah.')">❌ Tolak</button>
                </form>
              <?php else: ?>
                <span style="color:#aaa;font-size:.8rem">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($donasis)): ?>
          <tr><td colspan="8" style="text-align:center;color:#aaa;padding:24px;">Belum ada donasi masuk untuk kampanye Anda.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>

<div class="modal" id="modalTambah">
  <div class="modal-box">
    <button class="modal-close" onclick="closeModal('modalTambah')">✕</button>
    <h3>+ Tambah Kampanye</h3>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="aksi" value="tambah">
      <div class="form-row"><label>Judul Kampanye *</label><input type="text" name="judul" required></div>
      <div class="form-row"><label>Kategori *</label><select name="kategori" required><?php foreach ($KATEGORI_LIST as $kat): ?><option><?= htmlspecialchars($kat) ?></option><?php endforeach; ?></select></div>
      <div class="form-row"><label>Lokasi</label><input type="text" name="lokasi" placeholder="Contoh: Jawa Barat, Indonesia"></div>
      <div class="form-row"><label>Deskripsi</label><textarea name="deskripsi"></textarea></div>
      <div class="form-row"><label>Target Dana (Rp) *</label><input type="number" name="target_dana" min="100000" required></div>
      <div class="form-row"><label>Deadline *</label><input type="date" name="deadline" min="<?= date('Y-m-d') ?>" required></div>
      <div class="form-row"><label>Metode Donasi</label><input type="text" name="metode_donasi" value="Transfer Bank, E-Wallet, QRIS"></div>
      <div class="form-row"><label>Gambar Kampanye (JPG/PNG/WEBP, maks 5MB)</label><input type="file" name="gambar" accept=".jpg,.jpeg,.png,.webp"></div>
      <button type="submit" class="btn-form btn-green" style="width:100%;margin-top:8px">Simpan Kampanye</button>
    </form>
  </div>
</div>

<div class="modal" id="modalEdit">
  <div class="modal-box">
    <button class="modal-close" onclick="closeModal('modalEdit')">✕</button>
    <h3>✏ Edit Kampanye</h3>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="aksi" value="edit">
      <input type="hidden" name="kampanye_id" id="editId">
      <div class="form-row"><label>Judul Kampanye *</label><input type="text" name="judul" id="editJudul" required></div>
      <div class="form-row"><label>Kategori *</label><select name="kategori" id="editKategori" required><?php foreach ($KATEGORI_LIST as $kat): ?><option><?= htmlspecialchars($kat) ?></option><?php endforeach; ?></select></div>
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

<script src="assets/js/dashboard.js"></script>
</body>
</html>