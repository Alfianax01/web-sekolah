<?php
session_start();
require_once __DIR__ . '/security.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$nav_role   = $_SESSION['role'] ?? 'siswa';
$session_nis = $_SESSION['nis'] ?? null;
$session_nama = $_SESSION['nama_lengkap'] ?? ($_SESSION['username'] ?? '');

// 1. KONEKSI DATABASE (sekolah2)
$host = 'localhost';
$db   = 'crud';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

$buat_status   = "";
$kumpul_status = "";
$nilai_status  = "";

// 2. PROSES BUAT TUGAS (HANYA ADMIN)
if (isset($_POST['buat_tugas'])) {
    verify_csrf();
    if ($nav_role !== 'admin') {
        $buat_status = "<div class='status-msg status-err'>Kamu tidak punya akses untuk membuat tugas.</div>";
    } else {
        $judul    = trim($_POST['judul']);
        $id_mapel = $_POST['id_mapel'] ?: null;
        $deadline = $_POST['deadline'] ?: null;
        $desc     = trim($_POST['deskripsi']);

        if (empty($judul)) {
            $buat_status = "<div class='status-msg status-err'>Judul tugas wajib diisi.</div>";
        } elseif (empty($id_mapel)) {
            $buat_status = "<div class='status-msg status-err'>Mata pelajaran wajib dipilih.</div>";
        } else {
            $stmt = $pdo->prepare("INSERT INTO tugas (judul, id_mapel, deadline, deskripsi) VALUES (:judul, :id_mapel, :deadline, :deskripsi)");
            $result = $stmt->execute([
                ':judul'     => $judul,
                ':id_mapel'  => $id_mapel,
                ':deadline'  => $deadline,
                ':deskripsi' => $desc
            ]);

            $buat_status = $result
                ? "<div class='status-msg status-ok'>Tugas berhasil dibuat.</div>"
                : "<div class='status-msg status-err'>Gagal membuat tugas.</div>";
        }
    }
}

// 3. PROSES KUMPULKAN TUGAS (PDF UPLOAD)
if (isset($_POST['kumpul_tugas'])) {
    verify_csrf();
    $id_tugas   = $_POST['id_tugas'];
    $nis        = ($nav_role === 'siswa') ? $session_nis : trim($_POST['nis']);
    $nama_siswa = ($nav_role === 'siswa') ? $session_nama : trim($_POST['nama_siswa']);
    $file       = $_FILES['file_pdf'];

    if (empty($id_tugas) || empty($nama_siswa) || empty($file['name'])) {
        $kumpul_status = "<div class='status-msg status-err'>Semua field dan file PDF wajib diisi.</div>";
    } else {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);

        if ($mime !== 'application/pdf') {
            $kumpul_status = "<div class='status-msg status-err'>File harus berformat PDF.</div>";
        } elseif ($file['size'] > 4 * 1024 * 1024) {
            $kumpul_status = "<div class='status-msg status-err'>Ukuran file maksimal 4 MB.</div>";
        } else {
            $stmt_t = $pdo->prepare("SELECT deadline FROM tugas WHERE id_tugas = :id");
            $stmt_t->execute([':id' => $id_tugas]);
            $tugas_data = $stmt_t->fetch();

            $submitted_at = date('Y-m-d H:i:s');
            $status = 'Tepat Waktu';

            if (!empty($tugas_data['deadline'])) {
                $deadline_time = strtotime($tugas_data['deadline'] . ' 23:59:59');
                if (time() > $deadline_time) {
                    $status = 'Terlambat';
                }
            }

            $target_dir = "uploads/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }

            $unique_name = time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $file['name']);
            $target_file = $target_dir . $unique_name;

            if (move_uploaded_file($file['tmp_name'], $target_file)) {
                $stmt = $pdo->prepare("INSERT INTO pengumpulan_tugas (id_tugas, nis, nama_siswa, nama_file, path_file, ukuran_file, status, submitted_at) VALUES (:id_tugas, :nis, :nama_siswa, :nama_file, :path_file, :ukuran_file, :status, :submitted_at)");
                $stmt->execute([
                    ':id_tugas'    => $id_tugas,
                    ':nis'         => $nis,
                    ':nama_siswa'  => $nama_siswa,
                    ':nama_file'   => $file['name'],
                    ':path_file'   => $target_file,
                    ':ukuran_file' => $file['size'],
                    ':status'      => $status,
                    ':submitted_at'=> $submitted_at
                ]);
                $kumpul_status = "<div class='status-msg status-ok'>Tugas berhasil dikumpulkan.</div>";
            } else {
                $kumpul_status = "<div class='status-msg status-err'>Gagal mengunggah file ke server.</div>";
            }
        }
    }
}

// 4. PROSES SIMPAN NILAI (HANYA ADMIN & GURU)
if (isset($_POST['update_nilai'])) {
    verify_csrf();
    if ($nav_role === 'admin' || $nav_role === 'guru') {
        $id_pengumpulan = $_POST['id_pengumpulan'];
        $nilai = trim($_POST['nilai']);

        $stmt = $pdo->prepare("UPDATE pengumpulan_tugas SET nilai = :nilai WHERE id_pengumpulan = :id");
        $stmt->execute([':nilai' => $nilai, ':id' => $id_pengumpulan]);
        $nilai_status = "<div class='status-msg status-ok'>Nilai berhasil disimpan.</div>";
    }
}

// 5. PROSES HAPUS PENGUMPULAN (HANYA ADMIN & GURU)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && !empty($_POST['id'])) {
    verify_csrf();
    if ($nav_role === 'admin' || $nav_role === 'guru') {
        $del_id = $_POST['id'];
        $stmt = $pdo->prepare("SELECT path_file FROM pengumpulan_tugas WHERE id_pengumpulan = :id");
        $stmt->execute([':id' => $del_id]);
        $data = $stmt->fetch();

        if ($data) {
            $file_path = realpath(__DIR__ . '/' . $data['path_file']);
            $upload_root = realpath(__DIR__ . '/uploads');
            if ($file_path && $upload_root && str_starts_with($file_path, $upload_root . DIRECTORY_SEPARATOR) && is_file($file_path)) {
                unlink($file_path);
            }
            $stmt_del = $pdo->prepare("DELETE FROM pengumpulan_tugas WHERE id_pengumpulan = :id");
            $stmt_del->execute([':id' => $del_id]);
        }
    }
    header("Location: tugas.php");
    exit();
}

// 6. AMBIL DATA DARI DATABASE
$mapel_list = $pdo->query("SELECT * FROM mapel ORDER BY nama_mapel ASC")->fetchAll();

$tugas_list = $pdo->query("
    SELECT t.*, m.nama_mapel 
    FROM tugas t 
    LEFT JOIN mapel m ON t.id_mapel = m.id_mapel 
    ORDER BY t.id_tugas DESC
")->fetchAll();

$submissions_sql = "
    SELECT p.*, t.judul AS tugas_judul, t.id_mapel, m.nama_mapel
    FROM pengumpulan_tugas p 
    LEFT JOIN tugas t ON p.id_tugas = t.id_tugas 
    LEFT JOIN mapel m ON t.id_mapel = m.id_mapel
";

if ($nav_role === 'siswa') {
    $submissions_sql .= " WHERE p.nis = :nis ORDER BY p.id_pengumpulan DESC";
    $stmt_sub = $pdo->prepare($submissions_sql);
    $stmt_sub->execute([':nis' => $session_nis]);
    $submissions = $stmt_sub->fetchAll();
} else {
    $submissions_sql .= " ORDER BY p.id_pengumpulan DESC";
    $submissions = $pdo->query($submissions_sql)->fetchAll();
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
    <title>Kelola Tugas - Pemantauan Sekolah</title>
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
            --success: #10b981;
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
        .user-status { color: var(--success); font-size: 9px; font-weight: 600; }

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
            padding-bottom: 10px; margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between; 
        }
        .section-sub { color: var(--text-muted); font-size: 13px; margin-bottom: 20px; font-weight: 600; }

        /* FORM & INPUT */
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 16px; }
        .form-grid.full { grid-template-columns: 1fr; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label, label { font-size: 12px; font-weight: 700; color: var(--primary); margin-bottom: 6px; text-transform: uppercase; }
        input[type=text], input[type=date], input[type=number], textarea, select {
            height: 42px; border: 1px solid var(--border); border-radius: 6px; padding: 0 14px; font-size: 13px; background: var(--bg-main); color: var(--text-main); transition: all 0.2s; width: 100%;
        }
        input:focus, textarea:focus, select:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); background: white; }
        input[readonly] { background: #e2e8f0; color: var(--text-muted); cursor: not-allowed; }
        textarea { height: auto; resize: vertical; min-height: 80px; padding: 12px 14px; }

        /* DROPZONE FILE */
        .dropzone {
            border: 2px dashed var(--border); border-radius: 8px; padding: 24px; text-align: center;
            color: var(--text-muted); font-size: 13px; cursor: pointer; background: var(--bg-main); transition: all 0.2s;
        }
        .dropzone:hover { border-color: var(--accent); background: white; }
        .dropzone strong { color: var(--primary); }

        /* BUTTONS */
        .btn { border: none; cursor: pointer; text-decoration: none; display: inline-block; padding: 8px 16px; border-radius: 6px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; transition: all 0.2s; }
        .btn-primary { background: var(--accent); color: white; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-blue { background: var(--accent); color: white; padding: 6px 12px; font-size: 11px; }
        .btn-blue:hover { background: #1d4ed8; }
        .btn-green { background: var(--success); color: white; padding: 6px 12px; font-size: 11px; }
        .btn-green:hover { background: #059669; }
        .btn-red { background: var(--danger); color: white; padding: 6px 12px; font-size: 11px; }
        .btn-red:hover { background: #dc2626; }

        /* TABLE */
        .table-responsive { overflow-x: auto; width: 100%; }
        table { width: 100%; border-collapse: collapse; min-width: 700px; }
        th { background: var(--bg-main); color: var(--primary); font-size: 12px; font-weight: 800; text-align: left; padding: 12px 14px; border-bottom: 2px solid var(--border); text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 12px 14px; font-size: 13px; border-bottom: 1px solid var(--border); color: var(--text-main); vertical-align: middle; }
        tbody tr:hover { background-color: var(--bg-main); }
        td strong { color: var(--primary); font-weight: 700; }

        /* BADGES */
        .badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .badge-ontime { background: #ecfdf5; color: #059669; }
        .badge-late { background: #fef2f2; color: #dc2626; }
        .badge-mapel { background: #eff6ff; color: var(--accent); }
        .badge-nilai { background: #fffbeb; color: #d97706; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 12px; border: 1px solid #fde68a; }
        
        .empty { text-align: center; padding: 30px; color: var(--text-muted); font-size: 13px; font-weight: 600; }
        .meta-line { color: var(--text-muted); font-size: 11.5px; font-weight: 600; }
        .fname { display: flex; align-items: center; gap: 6px; margin-top: 3px; }
        
        .nilai-form { display: flex; gap: 6px; align-items: center; }
        .nilai-form input { width: 70px; height: 32px; padding: 0 8px; font-size: 12px; text-align: center; }
        .nilai-form button { height: 32px; padding: 0 10px; font-size: 11px; }

        /* MODAL PREVIEW */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(15, 23, 42, 0.6);
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(2px);
        }

        .modal-content {
            background-color: var(--bg-card);
            margin: auto;
            border: none;
            width: 90%;
            max-width: 850px;
            height: 85vh;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 22px;
            background: var(--primary);
            flex-shrink: 0;
        }

        .modal-header h3 {
            font-size: 15px;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: 0.3px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .close-btn {
            color: rgba(255,255,255,0.6);
            font-size: 24px;
            font-weight: 400;
            line-height: 1;
            cursor: pointer;
            background: transparent;
            border: none;
            padding: 4px 8px;
            border-radius: 6px;
            transition: all 0.15s;
        }

        .close-btn:hover {
            color: #ffffff;
            background: rgba(255,255,255,0.1);
        }

        .modal-body {
            flex: 1;
            width: 100%;
            background: #525659;
            overflow: hidden;
        }

        .modal-body iframe {
            width: 100%;
            height: 100%;
            border: none;
            display: block;
        }

        /* RESPONSIVE */
        @media (max-width: 992px) { 
            .main-container, .header-content, .navbar-inner { padding-left: 20px; padding-right: 20px; }
        }
        @media (max-width: 768px) { 
            .header-content { flex-direction: column; gap: 12px; align-items: flex-start; padding: 12px 20px; }
            .navbar-menu a.nav-link.nav-logout { margin-left: 0; }
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
                <h1>Kelola Tugas</h1>
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
    </header>

    <!-- NAVBAR MENU -->
    <nav class="navbar-menu">
        <div class="navbar-inner">
            <ul>
                <li><a href="index.php" class="nav-link">Home</a></li>
                <?php if ($nav_role !== 'siswa'): ?>
                <li><a href="siswa.php" class="nav-link">Data Siswa</a></li>
                <li><a href="guru.php" class="nav-link">Data Guru</a></li>
                <li><a href="mapel.php" class="nav-link">Data Mapel</a></li>
                <li><a href="jurusan.php" class="nav-link">Data Jurusan</a></li>
                <li><a href="bahan_ajar.php" class="nav-link">Bahan Ajar</a></li>
                <?php endif; ?>
                <li><a href="tugas.php" class="nav-link active">Tugas</a></li>
                 <?php if ($nav_role === 'siswa'): ?>
    <!-- Kode card / menu Pelajaran taruh di sini -->
    <div class="menu-card">
        <li><a href="pelajaran.php" class="nav-link" data-translate="nav_pelajaran">Pelajaran</a></li>
        <!-- dst... -->
    </div>
<?php endif; ?>
                <li><a href="profile.php" class="nav-link">Profil Saya</a></li>
                <li><a href="logout.php" class="nav-link nav-logout">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="main-container">

        <?php if ($nav_role === 'admin'): ?>
        <!-- CARD 1: BUAT TUGAS BARU (ADMIN ONLY) -->
        <div class="content-box">
            <div class="section-title">Buat Tugas Baru</div>
            <div class="section-sub">Tugas yang dibuat akan muncul di daftar dan siswa bisa mengumpulkan file PDF berdasarkan Mata Pelajaran (ID Mapel).</div>
            
            <form action="tugas.php" method="POST">
                <?php echo csrf_field(); ?>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Judul Tugas</label>
                        <input type="text" name="judul" required placeholder="Contoh: Laporan Praktikum Bab 3">
                    </div>
                    <div class="form-group">
                        <label>Mata Pelajaran (ID Mapel)</label>
                        <select name="id_mapel" required>
                            <?php if (empty($mapel_list)): ?>
                                <option value="">— Belum ada data mapel —</option>
                            <?php else: ?>
                                <option value="">— Pilih Mata Pelajaran —</option>
                                <?php foreach ($mapel_list as $m): ?>
                                    <option value="<?php echo $m['id_mapel']; ?>">
                                        [ID: <?php echo $m['id_mapel']; ?>] <?php echo htmlspecialchars($m['nama_mapel']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Batas Waktu Pengumpulan</label>
                        <input type="date" name="deadline">
                    </div>
                </div>

                <div class="form-grid full">
                    <div class="form-group">
                        <label>Deskripsi / Instruksi</label>
                        <textarea name="deskripsi" placeholder="Instruksi pengerjaan tugas untuk siswa..."></textarea>
                    </div>
                </div>

                <div style="margin-top: 10px;">
                    <button type="submit" name="buat_tugas" class="btn btn-primary">+ Buat Tugas</button>
                </div>
                <?php echo $buat_status; ?>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($nav_role === 'siswa'): ?>
        <!-- CARD 2: KUMPULKAN TUGAS (SISWA ONLY) -->
        <div class="content-box">
            <div class="section-title">Kumpulkan Tugas (PDF)</div>
            <div class="section-sub">Pilih tugas beserta Mata Pelajaran terkait, pastikan identitasmu benar, lalu unggah file PDF jawaban.</div>

            <form action="tugas.php" method="POST" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <div class="form-grid" style="grid-template-columns: 2fr 1fr 1fr; margin-bottom: 20px;">
                    <div class="form-group">
                        <label>Pilih Tugas & Mapel</label>
                        <select name="id_tugas" required>
                            <?php if (empty($tugas_list)): ?>
                                <option value="">— Belum ada tugas —</option>
                            <?php else: ?>
                                <option value="">— Pilih Tugas —</option>
                                <?php foreach ($tugas_list as $t): ?>
                                    <option value="<?php echo $t['id_tugas']; ?>">
                                        <?php echo htmlspecialchars($t['judul']); ?> — Mapel: <?php echo htmlspecialchars($t['nama_mapel'] ?: 'Tanpa Mapel'); ?> (ID Mapel: <?php echo $t['id_mapel']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>NIS</label>
                        <input type="text" name="nis" placeholder="Contoh: 12345"
                               value="<?php echo htmlspecialchars($session_nis ?? ''); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Nama Pengumpul</label>
                        <input type="text" name="nama_siswa" required placeholder="Nama siswa"
                               value="<?php echo htmlspecialchars($session_nama); ?>" readonly>
                    </div>
                </div>
                
                <div class="dropzone" onclick="document.getElementById('f-file').click();">
                    <div id="dz-label"><strong>Klik untuk pilih file</strong> (PDF maks. 4 MB)</div>
                    <input type="file" id="f-file" name="file_pdf" accept="application/pdf" required style="display:none;" onchange="updateFileName(this)">
                </div>
                
                <div style="margin-top:20px;">
                    <button type="submit" name="kumpul_tugas" class="btn btn-primary">Kumpulkan Tugas</button>
                </div>
                <?php echo $kumpul_status; ?>
            </form>
        </div>
        <?php endif; ?>

        <!-- CARD 3: RIWAYAT / DAFTAR PENGUMPULAN -->
        <div class="content-box">
            <div class="section-title"><?php echo $nav_role === 'siswa' ? 'Riwayat Pengumpulan Saya' : 'Daftar Pengumpulan Tugas Siswa'; ?></div>
            <div class="section-sub"><?php echo count($submissions); ?> file <?php echo $nav_role === 'siswa' ? 'telah kamu kumpulkan' : 'telah dikumpulkan siswa'; ?>.</div>
            
            <?php echo $nilai_status; ?>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Tugas</th>
                            <th>Mapel (ID Mapel)</th>
                            <th>NIS & Nama Siswa</th>
                            <th>Waktu Kumpul & File</th>
                            <th>Status</th>
                            <th>Nilai</th>
                            <th>Aksi File / Pengelolaan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($submissions)): ?>
                            <tr><td colspan="8" class="empty">Belum ada file yang dikumpulkan.</td></tr>
                        <?php else: ?>
                            <?php foreach ($submissions as $index => $s): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><strong><?php echo htmlspecialchars($s['tugas_judul'] ?? '(tugas dihapus)'); ?></strong></td>
                                    <td>
                                        <span class="badge badge-mapel">
                                            <?php echo htmlspecialchars($s['nama_mapel'] ?? '-'); ?> 
                                            <?php if(!empty($s['id_mapel'])): ?>(ID: <?php echo $s['id_mapel']; ?>)<?php endif; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($s['nama_siswa']); ?></strong>
                                        <?php if (!empty($s['nis'])): ?>
                                            <br><span class="meta-line">NIS: <?php echo htmlspecialchars($s['nis']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo date('d M Y, H:i', strtotime($s['submitted_at'])); ?>
                                        <div class="fname">
                                            <span style="font-size:12px;">📄</span>
                                            <span class="meta-line" style="color:var(--primary);"><?php echo htmlspecialchars($s['nama_file']); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo ($s['status'] === 'Terlambat') ? 'badge-late' : 'badge-ontime'; ?>">
                                            <?php echo htmlspecialchars($s['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($nav_role === 'admin' || $nav_role === 'guru'): ?>
                                            <form action="tugas.php" method="POST" class="nilai-form">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="id_pengumpulan" value="<?php echo $s['id_pengumpulan']; ?>">
                                                <input type="number" name="nilai" min="0" max="100" placeholder="0-100" value="<?php echo htmlspecialchars($s['nilai'] ?? ''); ?>">
                                                <button type="submit" name="update_nilai" class="btn btn-blue">Simpan</button>
                                            </form>
                                        <?php else: ?>
                                            <?php if ($s['nilai'] !== null && $s['nilai'] !== ''): ?>
                                                <span class="badge-nilai"><?php echo htmlspecialchars($s['nilai']); ?></span>
                                            <?php else: ?>
                                                <span class="meta-line">Belum dinilai</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <!-- Tombol Preview PDF Tanpa Download -->
                                        <a href="<?php echo htmlspecialchars($s['path_file']); ?>" download class="btn btn-blue" style="margin-left: 2px;">Unduh</a>
                                        <button type="button" class="btn btn-green"
                                            onclick="openPreview(
                                                'preview_pdf.php?file=<?php echo urlencode(basename($s['path_file'])); ?>',
                                                '<?php echo htmlspecialchars($s['nama_file']); ?>'
                                            )">
                                            Lihat
                                        </button>
                                        <?php if ($nav_role !== 'siswa'): ?>
                                            <form action="tugas.php" method="POST" style="display:inline;" onsubmit="return confirm('Hapus file ini?');">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo e($s['id_pengumpulan']); ?>">
                                                <button type="submit" class="btn btn-red" style="margin-left: 2px;">Hapus</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- MODAL POPUP UNTUK PREVIEW PDF -->
    <div id="pdfModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Preview File PDF</h3>
                <span class="close-btn" onclick="closePreview()">&times;</span>
            </div>
            <div class="modal-body">
                <iframe id="pdfFrame" src="" frameborder="0"></iframe>
            </div>
        </div>
    </div>

    <script>
        function updateFileName(input) {
            const label = document.getElementById('dz-label');
            if (input.files && input.files[0]) {
                const file = input.files[0];
                if (file.type !== 'application/pdf') {
                    alert('File harus berformat PDF!');
                    input.value = '';
                    label.innerHTML = '<strong>Klik untuk pilih file</strong> (PDF maks. 4 MB)';
                    return;
                }
                label.innerHTML = `<strong>${file.name}</strong> (${(file.size/1024).toFixed(0)} KB)`;
            }
        }

        function openPreview(url, fileName) {
            document.getElementById('modalTitle').textContent = 'Preview: ' + fileName;
            document.getElementById('pdfModal').style.display = 'flex';

            // Tambahkan timestamp supaya browser tidak pakai cache lama
            const cacheBuster = (url.includes('?') ? '&' : '?') + '_t=' + Date.now();
            document.getElementById('pdfFrame').src = url + cacheBuster;
        }

        function closePreview() {
            const modal = document.getElementById('pdfModal');
            const iframe = document.getElementById('pdfFrame');
            iframe.src = '';
            modal.style.display = 'none';
        }

        // Tutup modal jika klik di luar kotak modal
        window.onclick = function(event) {
            const modal = document.getElementById('pdfModal');
            if (event.target == modal) {
                closePreview();
            }
        }
    </script>
</body>
</html>