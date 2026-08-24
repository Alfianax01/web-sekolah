<?php
session_start();
require_once 'koneksi.php';
require_role(['admin']);
$nav_role = $_SESSION['role'];

// Inisialisasi variabel untuk form edit
$nis_edit         = "";
$nama_edit        = "";
$ttl_edit         = "";
$kelas_edit       = "";
$nip_edit         = "";
$id_mapel_edit    = "";
$id_jurusan_edit  = "";
$nilai_edit       = "";
$is_edit          = false;

// ==========================================
// 1. PROSES INSERT & UPDATE
// ==========================================
if (isset($_POST['simpan'])) {
    verify_csrf();
    $nis        = $_POST['nis'];
    $nama       = $_POST['nama_siswa'];
    $ttl        = $_POST['ttl'];
    $kelas      = $_POST['kelas'];
    $nip        = $_POST['nip'];
    $id_mapel   = $_POST['id_mapel'];
    $id_jurusan = $_POST['id_jurusan'];
    $nilai      = $_POST['nilai'];
    $mode       = $_POST['mode'];

    if ($mode == 'insert') {
        $sql = "INSERT INTO siswa (nis, ttl, nama_siswa, kelas, nip, id_mapel, id_jurusan, nilai) 
                VALUES (:nis, :ttl, :nama, :kelas, :nip, :id_mapel, :id_jurusan, :nilai)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':nis'        => $nis,
            ':ttl'        => $ttl,
            ':nama'       => $nama,
            ':kelas'      => $kelas,
            ':nip'        => $nip,
            ':id_mapel'   => $id_mapel,
            ':id_jurusan' => $id_jurusan,
            ':nilai'      => $nilai
        ]);
    } else if ($mode == 'update') {
        $sql = "UPDATE siswa SET 
                    ttl = :ttl, 
                    nama_siswa = :nama, 
                    kelas = :kelas, 
                    nip = :nip, 
                    id_jurusan = :id_jurusan, 
                    nilai = :nilai 
                WHERE nis = :nis AND id_mapel = :id_mapel";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':ttl'        => $ttl,
            ':nama'       => $nama,
            ':kelas'      => $kelas,
            ':nip'        => $nip,
            ':id_jurusan' => $id_jurusan,
            ':nilai'      => $nilai,
            ':nis'        => $nis,
            ':id_mapel'   => $id_mapel
        ]);
    }
    header("Location: siswa.php");
    exit();
}

// ==========================================
// 2. PROSES DELETE (Hapus 1 Record Spesifik)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    verify_csrf();
    $nis        = $_POST['nis'] ?? '';
    $id_mapel = $_POST['id_mapel'] ?? '';

    $sql = "DELETE FROM siswa WHERE nis = :nis AND id_mapel = :id_mapel";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':nis' => $nis, ':id_mapel' => $id_mapel]);
    
    header("Location: siswa.php");
    exit();
}

// ==========================================
// 3. PROSES AMBIL DATA UNTUK EDIT
// ==========================================
if (isset($_GET['action']) && $_GET['action'] == 'edit') {
    $nis        = $_GET['nis'];
    $id_mapel = $_GET['id_mapel'];

    $sql = "SELECT * FROM siswa WHERE nis = :nis AND id_mapel = :id_mapel LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':nis' => $nis, ':id_mapel' => $id_mapel]);
    $data_edit = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($data_edit) {
        $nis_edit        = $data_edit['nis'];
        $nama_edit       = $data_edit['nama_siswa'];
        $ttl_edit        = $data_edit['ttl'];
        $kelas_edit      = $data_edit['kelas'];
        $nip_edit        = $data_edit['nip'];
        $id_mapel_edit   = $data_edit['id_mapel'];
        $id_jurusan_edit = $data_edit['id_jurusan'];
        $nilai_edit      = $data_edit['nilai'];
        $is_edit         = true;
    }
}

// ==========================================
// 4. FILTER JURUSAN
// ==========================================
$filter_jurusan = isset($_GET['filter_jurusan']) ? trim($_GET['filter_jurusan']) : "";

// Ambil daftar jurusan unik buat opsi dropdown
$stmt_jurusan = $pdo->query("SELECT DISTINCT id_jurusan FROM siswa WHERE id_jurusan IS NOT NULL AND id_jurusan != '' ORDER BY id_jurusan ASC");
$daftar_jurusan = $stmt_jurusan->fetchAll(PDO::FETCH_COLUMN);

// ==========================================
// 5. READ DATA SISWA (dengan filter jurusan jika ada)
// ==========================================
if ($filter_jurusan !== "") {
    $stmt = $pdo->prepare("SELECT * FROM siswa WHERE id_jurusan = :id_jurusan ORDER BY nis ASC, id_mapel ASC");
    $stmt->execute([':id_jurusan' => $filter_jurusan]);
} else {
    $stmt = $pdo->query("SELECT * FROM siswa ORDER BY nis ASC, id_mapel ASC");
}
$daftar_siswa = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==========================================
// 6. PAGINATION PER SISWA (unik berdasarkan NIS)
// ==========================================
$nis_unik = [];
foreach ($daftar_siswa as $row) {
    if (!in_array($row['nis'], $nis_unik)) {
        $nis_unik[] = $row['nis'];
    }
}

$per_page         = 5; 
$total_siswa_unik = count($nis_unik);
$total_pages      = max(1, (int) ceil($total_siswa_unik / $per_page));

$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) $page = 1;
if ($page > $total_pages) $page = $total_pages;

$nis_halaman_ini = array_slice($nis_unik, ($page - 1) * $per_page, $per_page);

$daftar_siswa_halaman = array_values(array_filter($daftar_siswa, function ($row) use ($nis_halaman_ini) {
    return in_array($row['nis'], $nis_halaman_ini);
}));

$query_filter = $filter_jurusan !== "" ? "&filter_jurusan=" . urlencode($filter_jurusan) : "";

// ==========================================
// 7. REKAP: gabungkan record mapel 1 siswa jadi 1 baris
// ==========================================
$rekap_siswa_halaman = [];
foreach ($daftar_siswa_halaman as $row) {
    $nis = $row['nis'];
    if (!isset($rekap_siswa_halaman[$nis])) {
        $rekap_siswa_halaman[$nis] = [
            'nis'        => $row['nis'],
            'nama_siswa' => $row['nama_siswa'],
            'ttl'        => $row['ttl'],
            'kelas'      => $row['kelas'],
            'nip'        => $row['nip'],
            'id_jurusan' => $row['id_jurusan'],
            'nilai_list' => [],
        ];
    }
    $rekap_siswa_halaman[$nis]['nilai_list'][] = [
        'id_mapel' => $row['id_mapel'],
        'nilai'    => $row['nilai'],
    ];
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$nav_role = $_SESSION['role'] ?? 'siswa';

$foto_user = '';
try {
    $stmt = $pdo->prepare("SELECT foto FROM users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $user_id]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($userData && !empty($userData['foto'])) {
        $foto_user = $userData['foto'];
    }
} catch (PDOException $e) {
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Data Siswa - Pemantauan Sekolah</title>
    <style>
        :root {
            --primary: #1e293b;
            --primary-light: #334155;
            --accent: #2563eb;
            --bg-main: #f8fafc;
            --bg-card: #ffffff;
            --text-main: #334155;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --warning: #f59e0b;
            --danger: #ef4444;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: var(--bg-main); color: var(--text-main); line-height: 1.6; overflow-x: hidden; }

        /* HEADER UTAMA */
        .header-main {
            display: flex;
            align-items: stretch;
            background: #ffffff;
            border-bottom: 1px solid var(--border);
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
            position: sticky;
            top: 0;
            z-index: 100;
            width: 100%;
        }

        .header-accent-line { width: 6px; background-color: var(--accent); flex-shrink: 0; }

        .header-content {
            flex: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 40px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .header-left h1 { color: var(--primary); font-size: 18px; font-weight: 800; letter-spacing: 0.5px; text-transform: uppercase; line-height: 1.2; }
        .header-left p { color: var(--text-muted); font-size: 11px; font-weight: 600; letter-spacing: 0.5px; margin-bottom: 2px; text-transform: uppercase; }

        .header-right { display: flex; align-items: center; gap: 12px; }

        .user-badge {
            display: flex; align-items: center; gap: 8px;
            background: var(--bg-main); padding: 5px 12px 5px 5px;
            border-radius: 30px; border: 1px solid var(--border);
        }
        .user-avatar {
            background: var(--accent); 
            color: white; 
            width: 32px;
            height: 32px;
            border-radius: 50%; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 14px; 
            font-weight: bold;
            overflow: hidden;
            flex-shrink: 0;
            border: 2px solid #fff;
        }
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .user-info { display: flex; flex-direction: column; }
        .user-role { color: var(--primary); font-size: 11px; font-weight: 700; text-transform: uppercase; line-height: 1; margin-bottom: 2px; }
        .user-status { color: #10b981; font-size: 9px; font-weight: 600; }

        .header-date { color: var(--text-main); font-size: 12px; font-weight: 600; background: var(--bg-main); padding: 6px 12px; border-radius: 6px; border: 1px solid var(--border); }

        /* NAVBAR MENU */
        .navbar-menu { background-color: var(--primary); width: 100%; box-shadow: inset 0 -3px 0 rgba(0,0,0,0.1); }
        .navbar-inner { max-width: 1400px; margin: 0 auto; padding: 0 40px; }
        .navbar-menu ul { list-style: none; display: flex; align-items: center; gap: 2px; overflow-x: auto; }
        .navbar-menu a.nav-link {
            display: inline-block; color: rgba(255, 255, 255, 0.75); text-decoration: none; font-weight: 600; font-size: 12px;
            text-transform: uppercase; padding: 12px 16px; transition: all 0.2s ease; border-bottom: 3px solid transparent; letter-spacing: 0.5px;
            white-space: nowrap;
        }
        .navbar-menu a.nav-link:hover { color: white; background-color: rgba(255, 255, 255, 0.05); }
        .navbar-menu a.nav-link.active { color: white; border-bottom-color: var(--accent); }
        .navbar-menu a.nav-link.nav-logout { color: #fca5a5; margin-left: auto; }
        .navbar-menu a.nav-link.nav-logout:hover { color: #ef4444; background-color: rgba(239, 68, 68, 0.1); }

        /* KONTEN UTAMA */
        .main-container { max-width: 1400px; margin: 25px auto 40px; padding: 0 40px; width: 100%; }
        .content-box { background: var(--bg-card); padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); margin-bottom: 25px; border: 1px solid var(--border); width: 100%; }
        
        .section-title { 
            font-size: 16px; color: var(--primary); font-weight: 800; border-bottom: 2px solid var(--bg-main); 
            padding-bottom: 10px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; 
        }

        /* TOOLBAR ATAS TABEL */
        .toolbar-row {
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 12px; margin-bottom: 20px;
        }
        .filter-group { display: flex; align-items: center; gap: 8px; }
        .filter-group label { font-size: 12px; font-weight: 700; color: var(--primary); text-transform: uppercase; }
        .filter-group select {
            height: 38px; border: 1px solid var(--border); border-radius: 6px; padding: 0 12px;
            font-size: 13px; background: var(--bg-main); color: var(--text-main); cursor: pointer;
        }
        .filter-group select:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); background: white; }
        .filter-reset { font-size: 11px; color: var(--text-muted); text-decoration: none; font-weight: 600; }
        .filter-reset:hover { color: var(--danger); }

        /* TOMBOL TOGGLE */
        .btn-action-toggle {
            border: none; cursor: pointer; background: var(--accent); color: white;
            padding: 10px 18px; border-radius: 8px; font-size: 13px; font-weight: 700;
            box-shadow: 0 2px 6px rgba(37,99,235,0.2); transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px;
            margin-bottom: 20px; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .btn-action-toggle:hover { background: #1d4ed8; transform: translateY(-2px); }

        /* FORM */
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-size: 12px; font-weight: 700; color: var(--primary); margin-bottom: 6px; text-transform: uppercase; }
        .form-group input {
            height: 42px; border: 1px solid var(--border); border-radius: 6px; padding: 0 14px; font-size: 13px; background: var(--bg-main); color: var(--text-main); transition: all 0.2s;
        }
        .form-group input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); background: white; }
        .form-group input[readonly] { background: #e2e8f0; color: var(--text-muted); cursor: not-allowed; }

        /* BUTTONS */
        .btn { border: none; cursor: pointer; text-decoration: none; display: inline-block; padding: 8px 16px; border-radius: 6px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; transition: all 0.2s; }
        .btn-primary { background: var(--accent); color: white; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-secondary { background: var(--text-muted); color: white; }
        .btn-secondary:hover { background: #475569; }
        
        .btn-warning { background: var(--warning); color: white; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 11px; font-weight: 700; margin-right: 4px; }
        .btn-warning:hover { background: #d97706; }
        .btn-danger { background: var(--danger); color: white; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 11px; font-weight: 700; }
        .btn-danger:hover { background: #dc2626; }

        /* TABLE & RECAP */
        .table-responsive { overflow-x: auto; width: 100%; }
        table { width: 100%; border-collapse: collapse; min-width: 600px; }
        th { background: var(--bg-main); color: var(--primary); font-size: 12px; font-weight: 800; text-align: left; padding: 12px 14px; border-bottom: 2px solid var(--border); text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 12px 14px; font-size: 13px; border-bottom: 1px solid var(--border); color: var(--text-main); }
        tbody tr:hover { background-color: var(--bg-main); }
        td strong { color: var(--primary); font-weight: 700; }

        .nilai-recap { display: flex; flex-direction: column; gap: 6px; }
        .nilai-item { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .badge-nilai { background: #e0f2fe; color: #0369a1; padding: 3px 8px; border-radius: 4px; font-weight: 600; font-size: 11px; }

        /* PAGINATION */
        .pagination { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-top: 20px; }
        .page-info { color: var(--text-muted); font-size: 12px; font-weight: 600; }
        .page-links { display: flex; gap: 6px; flex-wrap: wrap; }
        .page-link { text-decoration: none; color: var(--text-main); border: 1px solid var(--border); padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; transition: all 0.2s; background: white; }
        .page-link:hover { background: var(--bg-main); color: var(--accent); }
        .page-link.active { background: var(--accent); color: white; border-color: var(--accent); }
        .page-link.disabled { opacity: 0.5; pointer-events: none; }

        /* RESPONSIVE */
        @media (max-width: 992px) { 
            .main-container, .header-content, .navbar-inner { padding-left: 20px; padding-right: 20px; }
        }
        @media (max-width: 768px) { 
            .header-content { flex-direction: column; gap: 12px; align-items: flex-start; padding: 12px 20px; }
            .navbar-menu a.nav-link.nav-logout { margin-left: 0; }
            .pagination { flex-direction: column; align-items: flex-start; }
            .toolbar-row { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>

    <!-- HEADER UTAMA -->
    <header class="header-main">
        <div class="header-accent-line"></div>
        <div class="header-content">
            <div class="header-left">
                <p>Pemantauan Sekolah</p>
                <h1>Kelola Data Siswa</h1>
            </div>
            <div class="header-right">
                <div class="header-date">
                    <?php 
                    $hari = array("Minggu","Senin","Selasa","Rabu","Kamis","Jumat","Sabtu");
                    $bulan = array("","Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember");
                    echo $hari[date("w")] . ", " . date("j") . " " . $bulan[date("n")] . " " . date("Y");
                    ?>
                </div>
              <div class="user-badge" style="display: flex; align-items: center; gap: 10px; background: #fff; padding: 5px 15px; border-radius: 50px; border: 1px solid var(--border);">
    <div class="user-avatar">
        <?php if (!empty($foto_user) && file_exists('uploads/' . $foto_user)): ?>
            <img src="uploads/<?php echo htmlspecialchars($foto_user); ?>" alt="Foto">
        <?php else: ?>
            <?php echo strtoupper(substr($nav_role, 0, 1)); ?>
        <?php endif; ?>
    </div>
    <div class="user-info">
        <span class="user-role" style="font-size: 10px; font-weight: 800; color: var(--primary);"><?php echo htmlspecialchars($nav_role); ?></span>
        <span class="user-status" style="color: #10b981; font-size: 9px; font-weight: 600;">● Online</span>
    </div>
</div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- NAVBAR MENU -->
    <nav class="navbar-menu">
        <div class="navbar-inner">
            <ul>
                <li><a href="index.php" class="nav-link">Beranda</a></li>
                <?php if ($nav_role !== 'siswa'): ?>
                <li><a href="siswa.php" class="nav-link active">Data Siswa</a></li>
                <li><a href="guru.php" class="nav-link">Data Guru</a></li>
                <li><a href="mapel.php" class="nav-link">Data Mapel</a></li>
                <li><a href="jurusan.php" class="nav-link">Data Jurusan</a></li>
                <?php endif; ?>
                <li><a href="tugas.php" class="nav-link">Tugas</a></li>
                <li><a href="bahan_ajar.php" class="nav-link">Bahan Ajar</a></li>
                <li><a href="profile.php" class="nav-link">Profil Saya</a></li>
                <li><a href="logout.php" class="nav-link nav-logout">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="main-container">

        <!-- TOOLBAR: TOMBOL TAMBAH + FILTER JURUSAN -->
        <div class="toolbar-row">
            <button type="button" id="btn-toggle-form" class="btn-action-toggle" onclick="toggleForm()" style="margin-bottom: 0;">
                <span id="btn-icon">+</span> <span id="btn-text">Tambah Data Siswa Baru</span>
            </button>

            <div class="filter-group">
                <label for="filter_jurusan">Jurusan:</label>
                <select id="filter_jurusan" onchange="filterJurusan(this.value)">
                    <option value="">Semua Jurusan</option>
                    <?php foreach ($daftar_jurusan as $jur): ?>
                        <option value="<?php echo htmlspecialchars($jur); ?>" <?php echo ($filter_jurusan === $jur) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($jur); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($filter_jurusan !== ""): ?>
                    <a href="siswa.php" class="filter-reset">✕ Reset</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- 1. TABEL DATA SISWA -->
        <div class="content-box">
            <div class="section-title">
                Daftar Record Siswa
                <?php if ($filter_jurusan !== ""): ?>
                    <span style="font-size:12px; font-weight:600; color: var(--text-muted); text-transform:none;">
                        Filter: <?php echo htmlspecialchars($filter_jurusan); ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>NIS</th>
                            <th>Nama Siswa</th>
                            <th>TTL</th>
                            <th>Kelas</th>
                            <th>NIP</th>
                            <th>ID Jurusan</th>
                            <th>Rekap Nilai (Mapel)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($rekap_siswa_halaman) > 0): ?>
                            <?php $no = (($page - 1) * $per_page) + 1; ?>
                            <?php foreach ($rekap_siswa_halaman as $siswa): ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($siswa['nis']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($siswa['nama_siswa']); ?></td>
                                    <td><?php echo htmlspecialchars($siswa['ttl'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($siswa['kelas'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($siswa['nip'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($siswa['id_jurusan'] ?? '-'); ?></td>
                                    <td>
                                        <div class="nilai-recap">
                                            <?php foreach ($siswa['nilai_list'] as $nl): ?>
                                                <div class="nilai-item">
                                                    <span class="badge-nilai">
                                                        <?php echo htmlspecialchars($nl['id_mapel']); ?>:
                                                        <?php echo htmlspecialchars($nl['nilai']); ?>
                                                    </span>
                                                    <a href="siswa.php?action=edit&nis=<?php echo urlencode($siswa['nis']); ?>&id_mapel=<?php echo urlencode($nl['id_mapel']); ?><?php echo $query_filter; ?>" class="btn-warning btn-xs">Edit</a>
                                                    <form action="siswa.php" method="POST" style="display:inline;" onsubmit="return confirm('Yakin mau hapus nilai <?php echo e($nl['id_mapel']); ?> milik <?php echo e($siswa['nama_siswa']); ?>?');">
                                                        <?php echo csrf_field(); ?>
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="nis" value="<?php echo e($siswa['nis']); ?>">
                                                        <input type="hidden" name="id_mapel" value="<?php echo e($nl['id_mapel']); ?>">
                                                        <button type="submit" class="btn-danger btn-xs">Hapus</button>
                                                    </form>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; color: var(--text-muted);">
                                    Belum ada data siswa<?php echo $filter_jurusan !== "" ? " untuk jurusan " . htmlspecialchars($filter_jurusan) : ""; ?>.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <span class="page-info">Halaman <?php echo $page; ?> dari <?php echo $total_pages; ?> (<?php echo $total_siswa_unik; ?> siswa)</span>
                <div class="page-links">
                    <a href="siswa.php?page=<?php echo max(1, $page - 1); ?><?php echo $query_filter; ?>" class="page-link <?php echo $page <= 1 ? 'disabled' : ''; ?>">&laquo; Sebelumnya</a>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="siswa.php?page=<?php echo $i; ?><?php echo $query_filter; ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <a href="siswa.php?page=<?php echo min($total_pages, $page + 1); ?><?php echo $query_filter; ?>" class="page-link <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">Berikutnya &raquo;</a>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- 2. FORM INPUT / EDIT -->
        <div id="form-container" class="content-box" style="display: <?php echo $is_edit ? 'block' : 'none'; ?>;">
            <div class="section-title">Form <?php echo $is_edit ? 'Edit Data' : 'Tambah Data'; ?> Siswa</div>
            <form action="siswa.php" method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="mode" value="<?php echo $is_edit ? 'update' : 'insert'; ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label>NIS:</label>
                        <input type="text" name="nis" value="<?php echo htmlspecialchars($nis_edit); ?>" <?php echo $is_edit ? 'readonly' : 'required'; ?> placeholder="Contoh: 242511001">
                    </div>

                    <div class="form-group">
                        <label>Nama Siswa:</label>
                        <input type="text" name="nama_siswa" value="<?php echo htmlspecialchars($nama_edit); ?>" required placeholder="Contoh: Budi Santoso">
                    </div>

                    <div class="form-group">
                        <label>Tempat, Tanggal Lahir (TTL):</label>
                        <input type="text" name="ttl" value="<?php echo htmlspecialchars($ttl_edit); ?>" placeholder="Contoh: Jakarta, 12 Mei 2007">
                    </div>

                    <div class="form-group">
                        <label>Kelas:</label>
                        <input type="text" name="kelas" value="<?php echo htmlspecialchars($kelas_edit); ?>" placeholder="Contoh: XI RPL 1">
                    </div>

                    <div class="form-group">
                        <label>NIP Wali Kelas/Guru:</label>
                        <input type="text" name="nip" value="<?php echo htmlspecialchars($nip_edit); ?>" placeholder="Contoh: 198501012010011001">
                    </div>

                    <div class="form-group">
                        <label>ID Jurusan:</label>
                        <input type="text" name="id_jurusan" value="<?php echo htmlspecialchars($id_jurusan_edit); ?>" placeholder="Contoh: J01">
                    </div>

                    <div class="form-group">
                        <label>ID Mapel:</label>
                        <input type="text" name="id_mapel" value="<?php echo htmlspecialchars($id_mapel_edit); ?>" <?php echo $is_edit ? 'readonly' : 'required'; ?> placeholder="Contoh: M01">
                    </div>

                    <div class="form-group">
                        <label>Nilai:</label>
                        <input type="number" name="nilai" value="<?php echo htmlspecialchars($nilai_edit); ?>" required min="0" max="100" placeholder="0-100">
                    </div>
                </div>

                <div style="margin-top: 20px;">
                    <button type="submit" name="simpan" class="btn btn-primary"><?php echo $is_edit ? 'Update Data' : 'Simpan Data'; ?></button>
                    <?php if ($is_edit): ?>
                        <a href="siswa.php<?php echo $query_filter !== '' ? '?' . ltrim($query_filter, '&') : ''; ?>" class="btn btn-secondary">Batal</a>
                    <?php else: ?>
                        <button type="button" class="btn btn-secondary" onclick="toggleForm()">Tutup Form</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

    </div>

    <script>
        let isEditMode = <?php echo $is_edit ? 'true' : 'false'; ?>;
        if (isEditMode) {
            document.getElementById('btn-text').innerText = "Tutup Form Edit";
            document.getElementById('btn-icon').innerText = "✕";
            document.getElementById('btn-toggle-form').style.background = "var(--danger)";
        }

        function toggleForm() {
            let formBox = document.getElementById('form-container');
            let btnText = document.getElementById('btn-text');
            let btnIcon = document.getElementById('btn-icon');
            let btnToggle = document.getElementById('btn-toggle-form');

            if (formBox.style.display === "none" || formBox.style.display === "") {
                formBox.style.display = "block";
                btnText.innerText = "Tutup Form";
                btnIcon.innerText = "✕";
                btnToggle.style.background = "var(--danger)";
                formBox.scrollIntoView({ behavior: 'smooth' });
            } else {
                formBox.style.display = "none";
                btnText.innerText = "Tambah Data Siswa Baru";
                btnIcon.innerText = "+";
                btnToggle.style.background = "var(--accent)";
            }
        }

        function filterJurusan(value) {
            if (value === "") {
                window.location.href = "siswa.php";
            } else {
                window.location.href = "siswa.php?filter_jurusan=" + encodeURIComponent(value);
            }
        }
    </script>
</body>
</html>