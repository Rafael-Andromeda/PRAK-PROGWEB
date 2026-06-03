<?php
// Halaman Utama Kindnesia (Dinamis)
require_once 'config.php';

// Cek status login
$user = isLoggedIn() ? currentUser() : null;

// Parameter search & pagination
$keyword  = trim($_GET['q']      ?? '');
$kategori = trim($_GET['kategori'] ?? '');
$lokasi   = trim($_GET['lokasi'] ?? '');
$tanggal  = trim($_GET['tanggal'] ?? ''); // opsional: filter tanggal/deadline kampanye
$page     = max(1, intval($_GET['page'] ?? 1));
$perPage  = 6;
$offset   = ($page - 1) * $perPage;

// Query kampanye dari DB
$db = getDB();

$where  = ["k.deadline >= CURDATE()", "k.dana_terkumpul < k.target_dana"];
$params = [];
$types  = "";

if ($keyword !== '') {
    $where[]  = "(k.judul LIKE ? OR k.kategori LIKE ? OR k.lokasi LIKE ? OR DATE_FORMAT(k.deadline, '%Y-%m-%d') LIKE ? OR DATE_FORMAT(k.deadline, '%d-%m-%Y') LIKE ?)";
    $like     = "%$keyword%";
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    $types   .= "sssss";
}
if ($kategori !== '') {
    $where[]  = "k.kategori = ?";
    $params[] = $kategori;
    $types   .= "s";
}
if ($lokasi !== '') {
    $where[]  = "k.lokasi LIKE ?";
    $params[] = "%$lokasi%";
    $types   .= "s";
}
if ($tanggal !== '') {
    $where[]  = "k.deadline = ?";
    $params[] = $tanggal;
    $types   .= "s";
}

$whereSQL = implode(' AND ', $where);

// Hitung total data (untuk pagination)
$countSQL  = "SELECT COUNT(*) FROM kampanye k WHERE $whereSQL";
$stmtCount = $db->prepare($countSQL);
if ($params) $stmtCount->bind_param($types, ...$params);
$stmtCount->execute();
$totalRows = $stmtCount->get_result()->fetch_row()[0];
$stmtCount->close();
$totalPages = max(1, ceil($totalRows / $perPage));

// Ambil data kampanye, urut: deadline terdekat, dana terkumpul terkecil
$sql  = "SELECT k.id, k.judul, k.kategori, k.lokasi, k.gambar,
                k.target_dana, k.dana_terkumpul, k.deadline,
                p.nama_pengelola
         FROM kampanye k
         JOIN pengelola p ON p.id = k.pengelola_id
         WHERE $whereSQL
         ORDER BY k.deadline ASC, k.dana_terkumpul ASC
         LIMIT ? OFFSET ?";
$paramsPage   = array_merge($params, [$perPage, $offset]);
$typesPage    = $types . "ii";
$stmt         = $db->prepare($sql);
$stmt->bind_param($typesPage, ...$paramsPage);
$stmt->execute();
$kampanyes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$db->close();

// Helper
function rupiahFormat($num) {
    if ($num >= 1000000) return 'Rp ' . number_format($num / 1000000, 1) . ' Jt';
    if ($num >= 1000)    return 'Rp ' . number_format($num / 1000, 0) . ' Rb';
    return 'Rp ' . number_format($num, 0, ',', '.');
}
function sisaHari($deadline) {
    $diff = (strtotime($deadline) - strtotime(date('Y-m-d'))) / 86400;
    return max(0, intval($diff));
}
function progressPct($terkumpul, $target) {
    if ($target <= 0) return 0;
    return min(100, round($terkumpul / $target * 100));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kindnesia - Crowdfunding Sosial</title>
    <link rel="stylesheet" href="assets/css/index.css">
    <style>
        .pagination { display:flex; justify-content:center; gap:8px; margin:32px 0; flex-wrap:wrap; }
        .pagination a, .pagination span {
            padding:8px 14px; border-radius:8px; text-decoration:none;
            border:1.5px solid #ccc; color:#333; font-size:.9rem;
        }
        .pagination a:hover { background:#e8f5e9; border-color:#4CAF50; }
        .pagination .current { background:#4CAF50; color:#fff; border-color:#4CAF50; }
        .no-result { text-align:center; padding:60px 20px; color:#888; }
        .user-info { color:#fff; font-size:.9rem; margin-right:8px; }
        .logout-btn { background:#e74c3c; color:#fff !important; padding:6px 14px; border-radius:8px; }
        .logout-btn:hover { background:#c0392b; }
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

<section class="hero">
    <div class="hero-content">
        <h2>Mulai Donasi Sekarang</h2>
        <p>Dari aksi kecil, tumbuh perubahan besar</p>
    </div>
</section>

<!-- SEARCH FORM -->
<section class="filter container">
    <form method="GET" action="index.php" style="display:contents">
        <input type="text" name="q" placeholder="Cari judul/kategori/lokasi/tanggal..."
               value="<?= htmlspecialchars($keyword) ?>">
        <select name="kategori">
            <option value="">Semua Kategori</option>
            <?php foreach(['Lingkungan','Kesehatan','Pendidikan','Bencana','Fasilitas Umum'] as $kat): ?>
                <option value="<?= $kat ?>" <?= $kategori === $kat ? 'selected' : '' ?>><?= $kat ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="lokasi" placeholder="Lokasi"
               value="<?= htmlspecialchars($lokasi) ?>">
        <input type="date" name="tanggal" value="<?= htmlspecialchars($tanggal) ?>" title="Filter berdasarkan deadline kampanye">
        <button type="submit">🔍 Cari</button>
        <?php if ($keyword || $kategori || $lokasi || $tanggal): ?>
            <a href="index.php" style="padding:8px 14px;background:#eee;border-radius:8px;text-decoration:none;color:#555">✕ Reset</a>
        <?php endif; ?>
    </form>
</section>

<!-- HASIL SEARCH INFO -->
<?php if ($keyword || $kategori || $lokasi || $tanggal): ?>
<div class="container" style="margin-bottom:8px;color:#555;font-size:.9rem;">
    Menampilkan <strong><?= $totalRows ?></strong> kampanye
    <?= $keyword ? "untuk kata kunci \"<em>" . htmlspecialchars($keyword) . "</em>\"" : '' ?>
    <?= $kategori ? "| Kategori: <em>" . htmlspecialchars($kategori) . "</em>" : '' ?>
    <?= $lokasi ? "| Lokasi: <em>" . htmlspecialchars($lokasi) . "</em>" : '' ?>
    <?= $tanggal ? "| Deadline: <em>" . htmlspecialchars($tanggal) . "</em>" : '' ?>
</div>
<?php endif; ?>

<!-- DAFTAR KAMPANYE -->
<section class="campaigns container">

<?php if (empty($kampanyes)): ?>
    <div class="no-result">
        <p style="font-size:2rem;">🔍</p>
        <p>Tidak ada kampanye yang ditemukan.</p>
        <a href="index.php">Lihat semua kampanye</a>
    </div>
<?php else: ?>
    <?php foreach ($kampanyes as $k):
        $pct   = progressPct($k['dana_terkumpul'], $k['target_dana']);
        $sisa  = sisaHari($k['deadline']);
        $imgSrc = $k['gambar'] ? 'uploads/kampanye/' . htmlspecialchars($k['gambar']) : 'assets/img/placeholder.svg';
    ?>
    <div class="card glass">
        <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($k['judul']) ?>"
             onerror="this.src='assets/img/placeholder.svg'">
        <div class="card-body">
            <span class="badge"><?= htmlspecialchars($k['kategori']) ?></span>
            <h3><a href="details.php?id=<?= $k['id'] ?>"><?= htmlspecialchars($k['judul']) ?></a></h3>
            <p class="org"><?= htmlspecialchars($k['nama_pengelola']) ?></p>
            <?php if ($k['lokasi']): ?>
                <p class="org" style="font-size:.8rem;color:#999">📍 <?= htmlspecialchars($k['lokasi']) ?></p>
            <?php endif; ?>
            <div class="fund">
                <span><?= rupiahFormat($k['dana_terkumpul']) ?></span>
                <span class="target">dari <?= rupiahFormat($k['target_dana']) ?></span>
            </div>
            <div class="progress"><div style="width:<?= $pct ?>%"></div></div>
            <p class="deadline"><?= $sisa > 0 ? "$sisa hari lagi" : 'Segera berakhir' ?></p>
            <a href="details.php?id=<?= $k['id'] ?>">
                <button class="donate-btn">Lihat Detail</button>
            </a>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

</section>

<!-- PAGINATION -->
<?php if ($totalPages > 1): ?>
<div class="pagination">
    <?php
    $qBase = http_build_query(array_filter(['q'=>$keyword,'kategori'=>$kategori,'lokasi'=>$lokasi,'tanggal'=>$tanggal]));
    $qBase = $qBase ? "&$qBase" : "";
    ?>
    <?php if ($page > 1): ?>
        <a href="?page=<?= $page-1 ?><?= $qBase ?>">« Prev</a>
    <?php endif; ?>

    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <?php if ($i == $page): ?>
            <span class="current"><?= $i ?></span>
        <?php else: ?>
            <a href="?page=<?= $i ?><?= $qBase ?>"><?= $i ?></a>
        <?php endif; ?>
    <?php endfor; ?>

    <?php if ($page < $totalPages): ?>
        <a href="?page=<?= $page+1 ?><?= $qBase ?>">Next »</a>
    <?php endif; ?>
</div>
<?php endif; ?>

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

<script src="assets/js/index.js"></script>
</body>
</html>