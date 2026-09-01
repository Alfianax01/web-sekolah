<?php
session_start();
require_once 'koneksi.php';
require_role(['admin', 'guru']);
$nav_role = $_SESSION['role'];

$upload_dir = __DIR__ . '/uploads/bahan_ajar/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$pesan      = "";
$pesan_tipe = "";

// ==========================================
// 1. PROSES UPLOAD BAHAN AJAR
// ==========================================
if (isset($_POST['upload_materi'])) {
    verify_csrf();
    $id_jurusan   = sanitize_input($_POST['id_jurusan'] ?? '');
    $id_mapel     = sanitize_input($_POST['id_mapel'] ?? '');
    $judul_materi = sanitize_input($_POST['judul_materi'] ?? '');

    if (empty($id_jurusan) || empty($id_mapel) || empty($judul_materi)) {
        $pesan = "Jurusan, Mata Pelajaran, dan Judul Materi wajib diisi.";
        $pesan_tipe = "error";
    } elseif (isset($_FILES['file_materi']) && $_FILES['file_materi']['error'] === UPLOAD_ERR_OK) {
        $file  = $_FILES['file_materi'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);

        if ($mime !== 'application/pdf') {
            $pesan = "Format berkas harus PDF.";
            $pesan_tipe = "error";
        } elseif ($file['size'] > 15 * 1024 * 1024) {
            $pesan = "Ukuran file maksimal 15 MB.";
            $pesan_tipe = "error";
        } else {
            $nama_asli   = sanitize_input($file['name']);
            $nama_unik   = 'materi_' . bin2hex(random_bytes(14)) . '.pdf';
            $tujuan      = $upload_dir . $nama_unik;
            $path_simpan = 'uploads/bahan_ajar/' . $nama_unik;

            if (move_uploaded_file($file['tmp_name'], $tujuan)) {
                $sql = "INSERT INTO bahan_ajar (id_jurusan, id_mapel, judul_materi, nama_file, file_path, tanggal_upload)
                        VALUES (:id_jurusan, :id_mapel, :judul_materi, :nama_file, :file_path, NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':id_jurusan'   => $id_jurusan,
                    ':id_mapel'     => $id_mapel,
                    ':judul_materi' => $judul_materi,
                    ':nama_file'    => $nama_asli,
                    ':file_path'    => $path_simpan
                ]);
                header("Location: bahan_ajar.php?status=sukses");
                exit();
            } else {
                $pesan = "Gagal mengunggah file ke penyimpanan server.";
                $pesan_tipe = "error";
            }
        }
    } else {
        $pesan = "Silakan pilih berkas PDF terlebih dahulu.";
        $pesan_tipe = "error";
    }
}

if (isset($_GET['status']) && $_GET['status'] == 'sukses') {
    $pesan = "Bahan ajar modul PDF berhasil diunggah!";
    $pesan_tipe = "success";
}

// ==========================================
// 2. PROSES HAPUS BAHAN AJAR (HANYA ADMIN)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    verify_csrf();
    if ($nav_role !== 'admin') {
        http_response_code(403);
        exit('Hanya admin yang memiliki izin menghapus bahan ajar.');
    }
    $id_bahan = $_POST['id_bahan'] ?? '';

    $stmt = $pdo->prepare("SELECT file_path FROM bahan_ajar WHERE id_bahan = :id");
    $stmt->execute([':id' => $id_bahan]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $file_fisik  = realpath(__DIR__ . '/' . $row['file_path']);
        $upload_root = realpath($upload_dir);
        if ($file_fisik && $upload_root && str_starts_with($file_fisik, $upload_root . DIRECTORY_SEPARATOR) && is_file($file_fisik)) {
            @unlink($file_fisik);
        }
        $stmt = $pdo->prepare("DELETE FROM bahan_ajar WHERE id_bahan = :id");
        $stmt->execute([':id' => $id_bahan]);
    }

    header("Location: bahan_ajar.php?status=hapus");
    exit();
}

if (isset($_GET['status']) && $_GET['status'] == 'hapus') {
    $pesan = "Bahan ajar berhasil dihapus!";
    $pesan_tipe = "success";
}

// ==========================================
// 3. DATA DROPDOWN & FILTER
// ==========================================
$stmt_jurusan = $pdo->query("SELECT * FROM jurusan ORDER BY nama_jurusan ASC");
$jurusan_list = $stmt_jurusan->fetchAll(PDO::FETCH_ASSOC);

$stmt_mapel = $pdo->query("SELECT * FROM mapel ORDER BY nama_mapel ASC");
$mapel_list = $stmt_mapel->fetchAll(PDO::FETCH_ASSOC);

$filter_jurusan = $_GET['filter_jurusan'] ?? '';
$search         = trim($_GET['search'] ?? '');

$sql_bahan = "SELECT b.*, j.nama_jurusan, m.nama_mapel 
              FROM bahan_ajar b
              LEFT JOIN jurusan j ON b.id_jurusan = j.id_jurusan
              LEFT JOIN mapel m ON b.id_mapel = m.id_mapel
              WHERE 1=1";
$params = [];

if (!empty($filter_jurusan)) {
    $sql_bahan .= " AND b.id_jurusan = :jurusan";
    $params[':jurusan'] = $filter_jurusan;
}
if (!empty($search)) {
    $sql_bahan .= " AND (b.judul_materi LIKE :q OR b.nama_file LIKE :q OR m.nama_mapel LIKE :q)";
    $params[':q'] = "%$search%";
}

$sql_bahan .= " ORDER BY b.tanggal_upload DESC, b.id_bahan DESC";
$stmt = $pdo->prepare($sql_bahan);
$stmt->execute($params);
$all_bahan = $stmt->fetchAll(PDO::FETCH_ASSOC);

$per_page    = 8;
$total_data  = count($all_bahan);
$total_pages = max(1, (int)ceil($total_data / $per_page));

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
if ($page > $total_pages) $page = $total_pages;

$daftar_bahan = array_slice($all_bahan, ($page - 1) * $per_page, $per_page);

// User Info
$user_id = $_SESSION['user_id'] ?? null;
$foto_user = '';
if ($user_id) {
    try {
        $stmtFoto = $pdo->prepare("SELECT foto FROM users WHERE id = :id LIMIT 1");
        $stmtFoto->execute([':id' => $user_id]);
        $uD = $stmtFoto->fetch(PDO::FETCH_ASSOC);
        if ($uD && !empty($uD['foto'])) {
            $foto_user = $uD['foto'];
        }
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bahan Ajar & Modul - Portal Akademik</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1e293b;
            --primary-light: #334155;
            --accent: #2563eb;
            --accent-hover: #1d4ed8;
            --accent-green: #10b981;
            --accent-amber: #f59e0b;
            --accent-rose: #ef4444;
            --bg-main: #f8fafc;
            --bg-card: #ffffff;
            --bg-subtle: #f1f5f9;
            --text-main: #334155;
            --text-title: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --radius-lg: 14px;
            --radius-md: 10px;
            --radius-sm: 6px;
            --shadow-card: 0 2px 10px rgba(0,0,0,0.03);
            --shadow-modal: 0 20px 40px -10px rgba(15, 23, 42, 0.25);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', 'Segoe UI', sans-serif; }
        body { background-color: var(--bg-main); color: var(--text-main); line-height: 1.6; }

        .header-main { display: flex; align-items: stretch; background: #ffffff; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; }
        .header-accent-line { width: 6px; background-color: var(--accent); flex-shrink: 0; }
        .header-content { flex: 1; display: flex; justify-content: space-between; align-items: center; padding: 14px 40px; max-width: 1440px; margin: 0 auto; gap: 20px; }
        .header-left h1 { color: var(--primary); font-size: 18px; font-weight: 800; text-transform: uppercase; line-height: 1.2; }
        .header-left p { color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .header-right { display: flex; align-items: center; gap: 12px; }
        .user-badge { display: flex; align-items: center; gap: 10px; background: var(--bg-main); padding: 5px 14px 5px 5px; border-radius: 30px; border: 1px solid var(--border); }
        .user-avatar { background: var(--accent); color: white; width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: bold; overflow: hidden; }
        .user-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .user-info { display: flex; flex-direction: column; line-height: 1.2; }
        .user-role { color: var(--primary); font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .user-status { color: var(--accent-green); font-size: 10px; font-weight: 600; }

        .navbar-menu { background-color: var(--primary); width: 100%; box-shadow: inset 0 -3px 0 rgba(0,0,0,0.1); }
        .navbar-inner { max-width: 1440px; margin: 0 auto; padding: 0 40px; }
        .navbar-menu ul { list-style: none; display: flex; align-items: center; gap: 2px; overflow-x: auto; }
        .navbar-menu a.nav-link { display: inline-flex; align-items: center; gap: 6px; color: rgba(255, 255, 255, 0.75); text-decoration: none; font-weight: 600; font-size: 12.5px; text-transform: uppercase; padding: 13px 16px; border-bottom: 3px solid transparent; white-space: nowrap; }
        .navbar-menu a.nav-link:hover { color: white; background-color: rgba(255, 255, 255, 0.05); }
        .navbar-menu a.nav-link.active { color: white; border-bottom-color: var(--accent); background-color: rgba(255, 255, 255, 0.05); }
        .navbar-menu a.nav-link.nav-logout { color: #fca5a5; margin-left: auto; }

        .main-container { max-width: 1440px; margin: 26px auto 50px; padding: 0 40px; }
        .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
        .page-header h2 { font-size: 20px; font-weight: 800; color: var(--text-title); }
        .page-header p { font-size: 13px; color: var(--text-muted); }

        .content-box { background: var(--bg-card); padding: 22px; border-radius: var(--radius-lg); box-shadow: var(--shadow-card); border: 1px solid var(--border); margin-bottom: 22px; }
        .alert-box { padding: 12px 16px; border-radius: var(--radius-md); font-size: 13.5px; font-weight: 600; margin-bottom: 18px; display: flex; align-items: center; gap: 8px; }
        .alert-box.success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .alert-box.error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

        .form-control { width: 100%; height: 40px; padding: 0 12px; font-size: 13px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: #ffffff; color: var(--text-title); outline: none; transition: all 0.2s; }
        .form-control:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }

        .btn-action { display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px; border-radius: var(--radius-sm); font-size: 13px; font-weight: 700; border: none; cursor: pointer; text-decoration: none; transition: all 0.2s; }
        .btn-primary { background: var(--accent); color: #fff; }
        .btn-primary:hover { background: var(--accent-hover); }
        .btn-secondary { background: var(--bg-subtle); color: var(--text-main); border: 1px solid var(--border); }

        .table-responsive { width: 100%; overflow-x: auto; }
        .table-custom { width: 100%; border-collapse: collapse; text-align: left; }
        .table-custom th { background: var(--bg-subtle); padding: 12px 14px; font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border); }
        .table-custom td { padding: 12px 14px; font-size: 13px; color: var(--text-main); border-bottom: 1px solid var(--border); vertical-align: middle; }
        .table-custom tr:hover { background-color: #fcfdfe; }

        .badge-pill { display: inline-block; padding: 3px 9px; border-radius: 20px; font-size: 11.5px; font-weight: 700; }
        .badge-purple { background: #faf5ff; color: #7e22ce; border: 1px solid #e9d5ff; }
        .badge-blue { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }

        .btn-tbl { padding: 5px 10px; border-radius: var(--radius-sm); font-size: 11.5px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; border: 1px solid transparent; cursor: pointer; }
        .btn-tbl-view { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
        .btn-tbl-delete { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }

        .pagination { display: flex; align-items: center; justify-content: center; gap: 6px; margin-top: 20px; }
        .page-link { padding: 6px 12px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: #ffffff; color: var(--text-main); font-size: 12.5px; font-weight: 700; text-decoration: none; }
        .page-link:hover { background: var(--bg-subtle); color: var(--accent); }
        .page-link.active { background: var(--accent); color: #ffffff; border-color: var(--accent); }

        /* ===== MODAL POPUP SYSTEM ===== */
        .modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); display: none; align-items: center; justify-content: center; z-index: 1000; padding: 20px; animation: fadeIn 0.2s ease; }
        .modal-overlay.active { display: flex; }
        .modal-card { background: #ffffff; width: 100%; max-width: 560px; border-radius: var(--radius-lg); box-shadow: var(--shadow-modal); border: 1px solid var(--border); overflow: hidden; animation: slideUp 0.25s ease; }
        .modal-card-sm { max-width: 420px; }
        .modal-card-lg { max-width: 900px; }
        .modal-header { padding: 16px 24px; background: #ffffff; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .modal-header h3 { font-size: 16px; font-weight: 800; color: var(--text-title); display: flex; align-items: center; gap: 8px; }
        .modal-close-btn { background: none; border: none; font-size: 20px; color: var(--text-muted); cursor: pointer; padding: 4px; line-height: 1; border-radius: 4px; }
        .modal-close-btn:hover { color: var(--text-title); background: var(--bg-subtle); }
        .modal-body { padding: 22px 24px; }
        .modal-footer { padding: 14px 24px; background: var(--bg-subtle); border-top: 1px solid var(--border); display: flex; align-items: center; justify-content: flex-end; gap: 10px; }
        .form-group-modal { margin-bottom: 14px; }
        .form-grid-modal { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
        .form-group-modal.full { grid-column: span 2; }
        .form-label { display: block; font-size: 12.5px; font-weight: 700; color: var(--text-title); margin-bottom: 5px; }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { transform: translateY(16px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        /* ===== RESPONSIVE & SIDEBAR DRAWER ===== */
        .btn-hamburger {
            display: none;
            flex-direction: column;
            justify-content: space-between;
            width: 28px;
            height: 20px;
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 0;
            flex-shrink: 0;
        }
        .btn-hamburger span {
            display: block;
            width: 100%;
            height: 3px;
            background-color: var(--primary);
            border-radius: 3px;
            transition: all 0.25s ease;
        }
        .nav-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 1050;
            display: none;
            opacity: 0;
            transition: opacity 0.25s ease;
        }
        .nav-backdrop.active {
            display: block;
            opacity: 1;
        }
        .sidebar-drawer-header {
            display: none;
        }

        @media (max-width: 992px) {
            .header-content { padding: 12px 20px; }
            .main-container { padding: 0 20px; }
            .navbar-inner { padding: 0 20px; }
        }

        @media (max-width: 768px) {
            .btn-hamburger { display: flex; }
            .header-content {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
                padding: 12px 16px;
            }
            .header-right {
                justify-content: space-between;
                flex-wrap: wrap;
                width: 100%;
            }
            .user-badge { padding: 4px 10px 4px 4px; }
            .header-date { font-size: 11px; padding: 4px 10px; }
            .main-container { padding: 0 14px; margin: 16px auto 36px; }

            /* SIDEBAR DRAWER */
            .navbar-menu {
                position: fixed;
                top: 0;
                left: 0;
                bottom: 0;
                width: 280px;
                max-width: 82vw;
                height: 100vh;
                z-index: 1100;
                transform: translateX(-100%);
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                box-shadow: 4px 0 24px rgba(15, 23, 42, 0.25);
                overflow-y: auto;
                display: flex;
                flex-direction: column;
            }
            .navbar-menu.active {
                transform: translateX(0);
            }
            .navbar-inner {
                padding: 0;
                width: 100%;
                flex: 1;
                display: flex;
                flex-direction: column;
            }
            .sidebar-drawer-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 16px 20px;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
                background: rgba(0, 0, 0, 0.15);
            }
            .sidebar-brand {
                color: #ffffff;
                font-weight: 800;
                font-size: 13.5px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .sidebar-close-btn {
                background: transparent;
                border: none;
                color: #94a3b8;
                font-size: 22px;
                cursor: pointer;
                padding: 2px 6px;
                line-height: 1;
                border-radius: 4px;
            }
            .sidebar-close-btn:hover {
                color: #ffffff;
                background: rgba(255, 255, 255, 0.1);
            }
            .navbar-menu ul {
                flex-direction: column;
                align-items: stretch;
                gap: 4px;
                padding: 12px 10px;
                flex: 1;
            }
            .navbar-menu a.nav-link {
                padding: 12px 16px;
                font-size: 13px;
                border-bottom: none;
                border-left: 4px solid transparent;
                border-radius: var(--radius-sm);
            }
            .navbar-menu a.nav-link.active {
                border-left-color: var(--accent);
                background-color: rgba(255, 255, 255, 0.08);
            }
            .navbar-menu a.nav-link.nav-logout {
                margin-top: auto;
                margin-left: 0;
                border-top: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 0;
                padding: 14px 16px;
            }

            .page-header {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
            }
            .page-header h2 { font-size: 17px; }
            .page-header p { font-size: 12px; }
            .page-header .btn-action { width: 100%; justify-content: center; }

            .content-box { padding: 16px 14px; }
            .content-box > div:first-child {
                flex-direction: column;
                align-items: stretch !important;
                gap: 10px;
            }
            .content-box > div:first-child form {
                width: 100%;
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
            }
            .content-box > div:first-child form select.form-control,
            .content-box > div:first-child form input.form-control {
                flex: 1 1 140px;
                width: auto !important;
            }

            .modal-overlay { padding: 12px; }
            .modal-card, .modal-card-sm, .modal-card-lg {
                max-width: 100% !important;
                max-height: 90vh;
                display: flex;
                flex-direction: column;
            }
            .modal-body {
                overflow-y: auto;
                -webkit-overflow-scrolling: touch;
            }
            .modal-footer {
                flex-direction: column-reverse;
                gap: 8px;
            }
            .modal-footer .btn-action {
                width: 100%;
                justify-content: center;
            }
            .form-grid-modal { grid-template-columns: 1fr !important; }
            .form-group-modal.full { grid-column: span 1 !important; }
        }
    </style>
</head>
<body>

    <!-- Backdrop Overlay untuk Mobile Sidebar -->
    <div id="navBackdrop" class="nav-backdrop" onclick="closeSidebar()"></div>

    <!-- ===== HEADER UTAMA ===== -->
    <header class="header-main">
        <div class="header-accent-line"></div>
        <div class="header-content">
            <div style="display: flex; align-items: center; gap: 14px;">
                <button type="button" class="btn-hamburger" onclick="toggleSidebar()" aria-label="Buka Menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <div class="header-left">
                    <p>SISTEM INFORMASI AKADEMIK</p>
                    <h1>PORTAL UTAMA SEKOLAH</h1>
                </div>
            </div>
            
            <div class="header-right">
                <a href="profile.php" style="text-decoration: none;">
                    <div class="user-badge">
                        <div class="user-avatar">
                            <?php if (!empty($foto_user) && file_exists('uploads/' . $foto_user)): ?>
                                <img src="uploads/<?php echo htmlspecialchars($foto_user); ?>" alt="Foto">
                            <?php else: ?>
                                <?php echo strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        <div class="user-info">
                            <span class="user-role"><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></span>
                            <span class="user-status">● <?php echo htmlspecialchars(ucfirst($nav_role)); ?></span>
                        </div>
                    </div>
                </a>

                <div class="header-date">
                    📅 <?php echo date('d M Y'); ?>
                </div>
            </div>
        </div>
    </header>

    <!-- ===== NAVBAR MENU / SIDEBAR (NAVY SLATE) ===== -->
    <nav id="navbarMenu" class="navbar-menu">
        <div class="navbar-inner">
            <div class="sidebar-drawer-header">
                <div class="sidebar-brand">🎓 Portal Akademik</div>
                <button type="button" class="sidebar-close-btn" onclick="closeSidebar()" aria-label="Tutup Menu">✕</button>
            </div>
            <ul>
                <li><a href="index.php" class="nav-link">Beranda</a></li>
                <li><a href="siswa.php" class="nav-link">Data Siswa</a></li>
                <li><a href="guru.php" class="nav-link">Data Guru</a></li>
                <li><a href="mapel.php" class="nav-link">Data Mapel</a></li>
                <li><a href="jurusan.php" class="nav-link">Data Jurusan</a></li>
                <li><a href="bahan_ajar.php" class="nav-link active">Bahan Ajar</a></li>
                <li><a href="tugas.php" class="nav-link">Tugas</a></li>
                <li><a href="profile.php" class="nav-link">Profil Saya</a></li>
                <li><a href="logout.php" class="nav-link nav-logout">Logout</a></li>
            </ul>
        </div>
    </nav>

    <!-- ===== MAIN CONTAINER ===== -->
    <main class="main-container">

        <div class="page-header">
            <div>
                <h2>📁 Bahan Ajar & Modul Pembelajaran</h2>
                <p>Katalog modul referensi, materi pembelajaran berformat PDF, dan distribusi bahan ajar per jurusan.</p>
            </div>
            <div>
                <button type="button" class="btn-action btn-primary" onclick="openUploadModal()">
                    📤 Unggah Bahan Ajar Baru
                </button>
            </div>
        </div>

        <?php if ($pesan): ?>
            <div class="alert-box <?php echo $pesan_tipe; ?>">
                <?php echo ($pesan_tipe === 'success') ? '✅' : '⚠️'; ?> <?php echo htmlspecialchars($pesan); ?>
            </div>
        <?php endif; ?>

        <!-- TABEL BAHAN AJAR -->
        <div class="content-box">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
                <form action="bahan_ajar.php" method="GET" style="display: flex; gap: 10px; align-items: center;">
                    <select name="filter_jurusan" class="form-control" style="width: auto; height: 36px;" onchange="this.form.submit()">
                        <option value="">Semua Jurusan</option>
                        <?php foreach ($jurusan_list as $j): ?>
                            <option value="<?php echo htmlspecialchars($j['id_jurusan']); ?>" <?php echo ($filter_jurusan === $j['id_jurusan']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($j['nama_jurusan']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <form action="bahan_ajar.php" method="GET" style="display: flex; gap: 8px;">
                    <input type="hidden" name="filter_jurusan" value="<?php echo htmlspecialchars($filter_jurusan); ?>">
                    <input type="text" name="search" class="form-control" style="width: 220px; height: 36px;" placeholder="Cari Judul Materi..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn-action btn-primary" style="padding: 6px 14px; font-size: 12.5px;">Cari</button>
                    <?php if ($filter_jurusan || $search): ?>
                        <a href="bahan_ajar.php" class="btn-action btn-secondary" style="padding: 6px 12px; font-size: 12.5px;">Reset</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Judul Materi</th>
                            <th>Mata Pelajaran</th>
                            <th>Jurusan</th>
                            <th>Nama Berkas</th>
                            <th>Waktu Unggah</th>
                            <th style="text-align: right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($daftar_bahan)): ?>
                            <?php foreach ($daftar_bahan as $row): ?>
                                <tr>
                                    <td style="font-weight: 700; color: var(--text-title);">
                                        📄 <?php echo htmlspecialchars($row['judul_materi']); ?>
                                    </td>
                                    <td><span class="badge-pill badge-blue"><?php echo htmlspecialchars($row['nama_mapel'] ?: ($row['id_mapel'] ?: '-')); ?></span></td>
                                    <td><span class="badge-pill badge-purple"><?php echo htmlspecialchars($row['nama_jurusan'] ?: ($row['id_jurusan'] ?: '-')); ?></span></td>
                                    <td style="font-size: 12px; color: var(--text-muted);"><?php echo htmlspecialchars($row['nama_file']); ?></td>
                                    <td style="font-size: 12px; color: var(--text-muted);"><?php echo htmlspecialchars($row['tanggal_upload'] ?? '-'); ?></td>
                                    <td style="text-align: right; white-space: nowrap;">
                                        <button type="button" class="btn-tbl btn-tbl-view" 
                                            onclick="openPdfModal('<?php echo htmlspecialchars(addslashes($row['file_path'])); ?>', '<?php echo htmlspecialchars(addslashes($row['judul_materi'])); ?>')">
                                            👁️ Buka PDF
                                        </button>

                                        <?php if ($nav_role === 'admin'): ?>
                                            <button type="button" class="btn-tbl btn-tbl-delete" 
                                                onclick="openDeleteModal('<?php echo htmlspecialchars($row['id_bahan']); ?>', '<?php echo htmlspecialchars(addslashes($row['judul_materi'])); ?>')">
                                                🗑️ Hapus
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 28px; color: var(--text-muted);">
                                    Belum ada bahan ajar yang diunggah.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINASI -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo ($page - 1); ?>&filter_jurusan=<?php echo urlencode($filter_jurusan); ?>&search=<?php echo urlencode($search); ?>" class="page-link">← Sebelumnya</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&filter_jurusan=<?php echo urlencode($filter_jurusan); ?>&search=<?php echo urlencode($search); ?>" class="page-link <?php echo ($i === $page) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo ($page + 1); ?>&filter_jurusan=<?php echo urlencode($filter_jurusan); ?>&search=<?php echo urlencode($search); ?>" class="page-link">Selanjutnya →</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div>

    </main>

    <!-- ===== MODAL POPUP UPLOAD BAHAN AJAR ===== -->
    <div id="modal-upload" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3>📤 Unggah Bahan Ajar Baru</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('modal-upload')">✕</button>
            </div>
            <form action="bahan_ajar.php" method="POST" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>

                <div class="modal-body">
                    <div class="form-grid-modal">
                        <div class="form-group-modal">
                            <label class="form-label">Jurusan / Kompetensi</label>
                            <select name="id_jurusan" class="form-control" required>
                                <option value="">-- Pilih Jurusan --</option>
                                <?php foreach ($jurusan_list as $j): ?>
                                    <option value="<?php echo htmlspecialchars($j['id_jurusan']); ?>">
                                        <?php echo htmlspecialchars($j['nama_jurusan']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group-modal">
                            <label class="form-label">Mata Pelajaran</label>
                            <select name="id_mapel" class="form-control" required>
                                <option value="">-- Pilih Mapel --</option>
                                <?php foreach ($mapel_list as $m): ?>
                                    <option value="<?php echo htmlspecialchars($m['id_mapel']); ?>">
                                        <?php echo htmlspecialchars($m['nama_mapel']); ?> (<?php echo htmlspecialchars($m['id_mapel']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group-modal full">
                            <label class="form-label">Judul Materi Pembelajaran</label>
                            <input type="text" name="judul_materi" class="form-control" required placeholder="Contoh: Modul 1 - Aljabar Linear">
                        </div>

                        <div class="form-group-modal full">
                            <label class="form-label">Berkas Materi (PDF Maks 15MB)</label>
                            <input type="file" name="file_materi" class="form-control" accept="application/pdf" required style="padding-top: 6px;">
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-action btn-secondary" onclick="closeModal('modal-upload')">Batal</button>
                    <button type="submit" name="upload_materi" class="btn-action btn-primary">🚀 Unggah Bahan Ajar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===== MODAL POPUP KONFIRMASI HAPUS ===== -->
    <div id="modal-delete" class="modal-overlay">
        <div class="modal-card modal-card-sm">
            <div class="modal-header">
                <h3>🗑️ Konfirmasi Hapus Bahan Ajar</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('modal-delete')">✕</button>
            </div>
            <form action="bahan_ajar.php" method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id_bahan" id="del-id">

                <div class="modal-body" style="text-align: center;">
                    <div style="font-size: 40px; margin-bottom: 10px;">⚠️</div>
                    <p style="font-size: 14px; color: var(--text-title); font-weight: 700; margin-bottom: 6px;">
                        Apakah Anda yakin ingin menghapus bahan ajar ini?
                    </p>
                    <p style="font-size: 13px; color: var(--text-muted);" id="del-title">-</p>
                </div>

                <div class="modal-footer" style="justify-content: center;">
                    <button type="button" class="btn-action btn-secondary" onclick="closeModal('modal-delete')">Batal</button>
                    <button type="submit" class="btn-action" style="background: var(--accent-rose); color: #fff;">Ya, Hapus Bahan Ajar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===== MODAL POPUP PDF PREVIEW ===== -->
    <div id="modal-pdf" class="modal-overlay">
        <div class="modal-card modal-card-lg">
            <div class="modal-header">
                <h3 id="pdf-title">📄 Preview PDF</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('modal-pdf')">✕</button>
            </div>
            <div class="modal-body" style="padding: 0;">
                <iframe id="pdf-iframe" src="" style="width: 100%; height: 75vh; border: none;"></iframe>
            </div>
        </div>
    </div>

    <script>
        function openUploadModal() {
            document.getElementById('modal-upload').querySelector('form').reset();
            document.getElementById('modal-upload').classList.add('active');
        }

        function openDeleteModal(id, title) {
            document.getElementById('del-id').value = id;
            document.getElementById('del-title').innerText = title;
            document.getElementById('modal-delete').classList.add('active');
        }

        function openPdfModal(filePath, title) {
            document.getElementById('pdf-title').innerHTML = '📄 ' + title;
            document.getElementById('pdf-iframe').src = filePath;
            document.getElementById('modal-pdf').classList.add('active');
        }

        function toggleSidebar() {
            document.getElementById('navbarMenu').classList.toggle('active');
            document.getElementById('navBackdrop').classList.toggle('active');
        }

        function closeSidebar() {
            document.getElementById('navbarMenu').classList.remove('active');
            document.getElementById('navBackdrop').classList.remove('active');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
            if (id === 'modal-pdf') {
                document.getElementById('pdf-iframe').src = '';
            }
        }

        window.onclick = function(e) {
            if (e.target.classList.contains('modal-overlay')) {
                if (e.target.id === 'modal-pdf') {
                    document.getElementById('pdf-iframe').src = '';
                }
                e.target.classList.remove('active');
            }
        }
        window.onkeydown = function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay').forEach(function(m) {
                    m.classList.remove('active');
                });
                document.getElementById('pdf-iframe').src = '';
            }
        }
    </script>

</body>
</html>
