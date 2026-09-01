<?php
session_start();
require_once 'koneksi.php';
require_role(['admin', 'guru']);
$nav_role = $_SESSION['role'];

$msg_success = "";
$msg_error   = "";

// 1. DATA JURUSAN UNTUK DROPDOWN
$stmt_jurusan = $pdo->query("SELECT * FROM jurusan ORDER BY nama_jurusan ASC");
$daftar_jurusan = $stmt_jurusan->fetchAll(PDO::FETCH_ASSOC);

// 2. PROSES INSERT & UPDATE MAPEL
if (isset($_POST['simpan'])) {
    verify_csrf();
    $id_mapel   = sanitize_input($_POST['id_mapel'] ?? '');
    $nama_mapel = sanitize_input($_POST['nama_mapel'] ?? '');
    $id_jurusan = sanitize_input($_POST['id_jurusan'] ?? '');
    $mode       = $_POST['mode'] ?? 'insert';

    if (empty($id_mapel) || empty($nama_mapel) || empty($id_jurusan)) {
        $msg_error = "Semua field (Kode Mapel, Nama Mapel, Jurusan) wajib diisi.";
    } else {
        if ($mode === 'insert') {
            try {
                $sql = "INSERT INTO mapel (id_mapel, nama_mapel, id_jurusan) VALUES (:id_mapel, :nama_mapel, :id_jurusan)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':id_mapel' => $id_mapel, ':nama_mapel' => $nama_mapel, ':id_jurusan' => $id_jurusan]);
                header("Location: mapel.php?status=tambah_sukses");
                exit();
            } catch (PDOException $e) {
                $msg_error = "Gagal menambahkan mapel: " . $e->getMessage();
            }
        } elseif ($mode === 'update') {
            try {
                $sql = "UPDATE mapel SET nama_mapel = :nama_mapel, id_jurusan = :id_jurusan WHERE id_mapel = :id_mapel";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':nama_mapel' => $nama_mapel, ':id_jurusan' => $id_jurusan, ':id_mapel' => $id_mapel]);
                header("Location: mapel.php?status=edit_sukses");
                exit();
            } catch (PDOException $e) {
                $msg_error = "Gagal memperbarui mapel: " . $e->getMessage();
            }
        }
    }
}

// 3. PROSES DELETE MAPEL (HANYA ADMIN)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    verify_csrf();
    if ($nav_role !== 'admin') {
        http_response_code(403);
        exit('Hanya admin yang memiliki izin menghapus data.');
    }
    $id_mapel = $_POST['id_mapel'] ?? '';
    try {
        $sql = "DELETE FROM mapel WHERE id_mapel = :id_mapel";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id_mapel' => $id_mapel]);
        header("Location: mapel.php?status=hapus_sukses");
        exit();
    } catch (PDOException $e) {
        $msg_error = "Gagal menghapus data mapel: " . $e->getMessage();
    }
}

if (isset($_GET['status'])) {
    if ($_GET['status'] === 'tambah_sukses') $msg_success = "Mata pelajaran berhasil ditambahkan!";
    if ($_GET['status'] === 'edit_sukses')   $msg_success = "Mata pelajaran berhasil diperbarui!";
    if ($_GET['status'] === 'hapus_sukses')  $msg_success = "Mata pelajaran berhasil dihapus!";
}

// 4. FILTER & READ DATA MAPEL
$filter_jurusan = $_GET['filter_jurusan'] ?? '';
$search         = trim($_GET['search'] ?? '');

$sql_mapel = "SELECT m.*, j.nama_jurusan FROM mapel m LEFT JOIN jurusan j ON m.id_jurusan = j.id_jurusan WHERE 1=1";
$params = [];

if (!empty($filter_jurusan)) {
    $sql_mapel .= " AND m.id_jurusan = :jurusan";
    $params[':jurusan'] = $filter_jurusan;
}
if (!empty($search)) {
    $sql_mapel .= " AND (m.id_mapel LIKE :q OR m.nama_mapel LIKE :q OR j.nama_jurusan LIKE :q)";
    $params[':q'] = "%$search%";
}

$sql_mapel .= " ORDER BY m.id_jurusan ASC, m.id_mapel ASC";
$stmt = $pdo->prepare($sql_mapel);
$stmt->execute($params);
$all_mapel = $stmt->fetchAll(PDO::FETCH_ASSOC);

$per_page    = 5;
$total_data  = count($all_mapel);
$total_pages = max(1, (int)ceil($total_data / $per_page));

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
if ($page > $total_pages) $page = $total_pages;

$daftar_mapel = array_slice($all_mapel, ($page - 1) * $per_page, $per_page);

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
    <title>Data Mata Pelajaran - Portal Akademik</title>
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
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.02);
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

        .btn-action { display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px; border-radius: var(--radius-sm); font-size: 13px; font-weight: 700; border: none; cursor: pointer; text-decoration: none; transition: all 0.2s; }
        .btn-primary { background: var(--accent); color: #fff; }
        .btn-primary:hover { background: var(--accent-hover); }
        .btn-secondary { background: var(--bg-subtle); color: var(--text-body); border: 1px solid var(--border); }

        .table-responsive { width: 100%; overflow-x: auto; }
        .table-custom { width: 100%; border-collapse: collapse; text-align: left; }
        .table-custom th { background: var(--bg-subtle); padding: 12px 14px; font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border); }
        .table-custom td { padding: 12px 14px; font-size: 13px; color: var(--text-body); border-bottom: 1px solid var(--border); vertical-align: middle; }
        .table-custom tr:hover { background-color: #fcfdfe; }

        .badge-pill { display: inline-block; padding: 3px 9px; border-radius: 20px; font-size: 11.5px; font-weight: 700; }
        .badge-purple { background: #faf5ff; color: #7e22ce; border: 1px solid #e9d5ff; }

        .btn-tbl { padding: 5px 10px; border-radius: var(--radius-sm); font-size: 11.5px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; border: 1px solid transparent; cursor: pointer; }
        .btn-tbl-edit { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
        .btn-tbl-delete { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }

        .pagination { display: flex; align-items: center; justify-content: center; gap: 6px; margin-top: 20px; }
        .page-link { padding: 6px 12px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: #ffffff; color: var(--text-body); font-size: 12.5px; font-weight: 700; text-decoration: none; }
        .page-link:hover { background: var(--bg-subtle); color: var(--accent); }
        .page-link.active { background: var(--accent); color: #ffffff; border-color: var(--accent); }

        /* MODAL POPUP */
        .modal-overlay {
            position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px);
            display: none; align-items: center; justify-content: center; z-index: 1000; padding: 20px; animation: fadeIn 0.2s ease;
        }
        .modal-overlay.active { display: flex; }
        .modal-card {
            background: #ffffff; width: 100%; max-width: 540px; border-radius: var(--radius-lg);
            box-shadow: var(--shadow-modal); border: 1px solid var(--border); overflow: hidden; animation: slideUp 0.25s ease;
        }
        .modal-card-sm { max-width: 440px; }
        .modal-header {
            padding: 16px 24px; background: #ffffff; border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
        }
        .modal-header h3 { font-size: 16px; font-weight: 800; color: var(--text-title); }
        .modal-close-btn { background: none; border: none; font-size: 20px; color: var(--text-muted); cursor: pointer; padding: 4px; line-height: 1; border-radius: 4px; }
        .modal-close-btn:hover { color: var(--text-title); background: var(--bg-subtle); }
        .modal-body { padding: 22px 24px; }
        .modal-footer {
            padding: 14px 24px; background: var(--bg-subtle); border-top: 1px solid var(--border);
            display: flex; align-items: center; justify-content: flex-end; gap: 10px;
        }

        .form-group-modal { margin-bottom: 12px; }
        .form-label { display: block; font-size: 12.5px; font-weight: 700; color: var(--text-title); margin-bottom: 5px; }
        .form-control { width: 100%; height: 38px; padding: 0 12px; font-size: 13px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: #ffffff; outline: none; }
        .form-control:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }

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
            .modal-card, .modal-card-sm {
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
                <li><a href="mapel.php" class="nav-link active">Data Mapel</a></li>
                <li><a href="jurusan.php" class="nav-link">Data Jurusan</a></li>
                <li><a href="bahan_ajar.php" class="nav-link">Bahan Ajar</a></li>
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
                <h2>📚 Kelola Mata Pelajaran</h2>
                <p>Manajemen kurikulum mata pelajaran dan pengelompokan kompetensi keahlian / jurusan.</p>
            </div>
            <div>
                <button type="button" class="btn-action btn-primary" onclick="openAddModal()">
                    ➕ Tambah Mapel Baru
                </button>
            </div>
        </div>

        <?php if ($msg_success): ?>
            <div class="alert-box success">✅ <?php echo htmlspecialchars($msg_success); ?></div>
        <?php endif; ?>

        <?php if ($msg_error): ?>
            <div class="alert-box error">⚠️ <?php echo htmlspecialchars($msg_error); ?></div>
        <?php endif; ?>

        <!-- TABEL DATA MAPEL -->
        <div class="content-box">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
                <form action="mapel.php" method="GET" style="display: flex; gap: 10px; align-items: center;">
                    <select name="filter_jurusan" class="form-control" style="width: auto; height: 36px;" onchange="this.form.submit()">
                        <option value="">Semua Jurusan</option>
                        <?php foreach ($daftar_jurusan as $j): ?>
                            <option value="<?php echo htmlspecialchars($j['id_jurusan']); ?>" <?php echo ($filter_jurusan === $j['id_jurusan']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($j['nama_jurusan']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <form action="mapel.php" method="GET" style="display: flex; gap: 8px;">
                    <input type="hidden" name="filter_jurusan" value="<?php echo htmlspecialchars($filter_jurusan); ?>">
                    <input type="text" name="search" class="form-control" style="width: 220px; height: 36px;" placeholder="Cari Kode atau Nama Mapel..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn-action btn-primary" style="padding: 6px 14px; font-size: 12.5px;">Cari</button>
                    <?php if ($filter_jurusan || $search): ?>
                        <a href="mapel.php" class="btn-action btn-secondary" style="padding: 6px 12px; font-size: 12.5px;">Reset</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Kode Mapel</th>
                            <th>Nama Mata Pelajaran</th>
                            <th>Jurusan / Peminatan</th>
                            <th style="text-align: right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($daftar_mapel)): ?>
                            <?php foreach ($daftar_mapel as $row): ?>
                                <tr>
                                    <td style="font-weight: 700; color: var(--primary);"><?php echo htmlspecialchars($row['id_mapel']); ?></td>
                                    <td style="font-weight: 700; color: var(--text-title);"><?php echo htmlspecialchars($row['nama_mapel']); ?></td>
                                    <td><span class="badge-pill badge-purple"><?php echo htmlspecialchars($row['nama_jurusan'] ?: ($row['id_jurusan'] ?: 'Umum')); ?></span></td>
                                    <td style="text-align: right; white-space: nowrap;">
                                        <button type="button" class="btn-tbl btn-tbl-edit" 
                                            onclick="openEditModal(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                                            ✏️ Edit
                                        </button>

                                        <?php if ($nav_role === 'admin'): ?>
                                            <button type="button" class="btn-tbl btn-tbl-delete" 
                                                onclick="openDeleteModal('<?php echo htmlspecialchars($row['id_mapel']); ?>', '<?php echo htmlspecialchars(addslashes($row['nama_mapel'])); ?>')">
                                                🗑️ Hapus
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 28px; color: var(--text-muted);">
                                    Tidak ada data mata pelajaran yang ditemukan.
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

    <!-- ===== MODAL POPUP TAMBAH / EDIT MAPEL ===== -->
    <div id="modal-mapel" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3 id="modal-title">➕ Tambah Mata Pelajaran Baru</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('modal-mapel')">✕</button>
            </div>
            <form action="mapel.php" method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="mode" id="form-mode" value="insert">

                <div class="modal-body">
                    <div class="form-group-modal">
                        <label class="form-label">Kode Mata Pelajaran (ID)</label>
                        <input type="text" name="id_mapel" id="form-id" class="form-control" required placeholder="Contoh: MTK-001">
                    </div>

                    <div class="form-group-modal">
                        <label class="form-label">Nama Mata Pelajaran</label>
                        <input type="text" name="nama_mapel" id="form-nama" class="form-control" required placeholder="Contoh: Matematika Wajib">
                    </div>

                    <div class="form-group-modal">
                        <label class="form-label">Jurusan / Kelompok Peminatan</label>
                        <select name="id_jurusan" id="form-jurusan" class="form-control" required>
                            <option value="">-- Pilih Jurusan --</option>
                            <?php foreach ($daftar_jurusan as $j): ?>
                                <option value="<?php echo htmlspecialchars($j['id_jurusan']); ?>">
                                    <?php echo htmlspecialchars($j['nama_jurusan']); ?> (<?php echo htmlspecialchars($j['id_jurusan']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-action btn-secondary" onclick="closeModal('modal-mapel')">Batal</button>
                    <button type="submit" name="simpan" class="btn-action btn-primary">💾 Simpan Mapel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===== MODAL POPUP KONFIRMASI HAPUS ===== -->
    <div id="modal-delete" class="modal-overlay">
        <div class="modal-card modal-card-sm">
            <div class="modal-header">
                <h3>🗑️ Konfirmasi Hapus Mapel</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('modal-delete')">✕</button>
            </div>
            <form action="mapel.php" method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id_mapel" id="del-id">

                <div class="modal-body" style="text-align: center;">
                    <div style="font-size: 40px; margin-bottom: 10px;">⚠️</div>
                    <p style="font-size: 14px; color: var(--text-title); font-weight: 700; margin-bottom: 6px;">
                        Apakah Anda yakin ingin menghapus mata pelajaran ini?
                    </p>
                    <p style="font-size: 13px; color: var(--text-muted);" id="del-mapel-name">-</p>
                </div>

                <div class="modal-footer" style="justify-content: center;">
                    <button type="button" class="btn-action btn-secondary" onclick="closeModal('modal-delete')">Batal</button>
                    <button type="submit" class="btn-action" style="background: var(--accent-rose); color: #fff;">Ya, Hapus Mapel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('modal-title').innerHTML = '➕ Tambah Mata Pelajaran Baru';
            document.getElementById('form-mode').value = 'insert';
            document.getElementById('form-id').value = '';
            document.getElementById('form-id').readOnly = false;
            document.getElementById('form-id').style.background = '#ffffff';
            document.getElementById('form-nama').value = '';
            document.getElementById('form-jurusan').value = '';
            document.getElementById('modal-mapel').classList.add('active');
        }

        function openEditModal(data) {
            document.getElementById('modal-title').innerHTML = '✏️ Edit Mata Pelajaran: ' + (data.nama_mapel || '');
            document.getElementById('form-mode').value = 'update';
            document.getElementById('form-id').value = data.id_mapel || '';
            document.getElementById('form-id').readOnly = true;
            document.getElementById('form-id').style.background = '#f1f5f9';
            document.getElementById('form-nama').value = data.nama_mapel || '';
            document.getElementById('form-jurusan').value = data.id_jurusan || '';
            document.getElementById('modal-mapel').classList.add('active');
        }

        function openDeleteModal(idMapel, mapelName) {
            document.getElementById('del-id').value = idMapel;
            document.getElementById('del-mapel-name').innerText = mapelName + ' (Kode: ' + idMapel + ')';
            document.getElementById('modal-delete').classList.add('active');
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
        }

        window.onclick = function(e) {
            if (e.target.classList.contains('modal-overlay')) {
                e.target.classList.remove('active');
            }
        }
        window.onkeydown = function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('active'));
            }
        }
    </script>

</body>
</html>
