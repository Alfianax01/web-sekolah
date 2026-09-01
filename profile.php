<?php
session_start();
require_once 'koneksi.php';
require_login();

$nav_role    = $_SESSION['role'] ?? 'siswa';
$user_id     = $_SESSION['user_id'];
$success_msg = "";
$error_msg   = "";

// 1. AMBIL DATA USER DARI TABEL USERS & SISWA
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if ($nav_role === 'siswa') {
            $stmtSiswa = $pdo->prepare("SELECT * FROM siswa WHERE nama_siswa = :nama LIMIT 1");
            $stmtSiswa->execute([':nama' => $user['username']]);
            $siswaData = $stmtSiswa->fetch(PDO::FETCH_ASSOC);

            if ($siswaData) {
                $user['nama_lengkap'] = $siswaData['nama_siswa'];
                $user['kelas']        = $siswaData['kelas'] ?? '-';
                $user['ttl']          = $siswaData['ttl'] ?? '-';
            } else {
                $user['nama_lengkap'] = $user['nama_lengkap'] ?? $user['username'];
                $user['kelas']        = '-';
                $user['ttl']          = '-';
            }
        } else {
            $user['nama_lengkap'] = $user['nama_lengkap'] ?? $user['username'];
            $user['ttl']          = $user['ttl'] ?? '-';
            $user['kelas']        = '';
        }
    } else {
        $error_msg = "Data user tidak ditemukan.";
        $user = ['username' => '', 'nama_lengkap' => '', 'email' => '', 'foto' => '', 'kelas' => '', 'ttl' => '-'];
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    $error_msg = "Data profil tidak dapat dimuat.";
    $user = ['username' => '', 'nama_lengkap' => '', 'email' => '', 'foto' => '', 'kelas' => '', 'ttl' => '-'];
}

// 2. HITUNG STATISTIK AKTIVITAS
try {
    $total_tugas       = (int)$pdo->query("SELECT COUNT(*) FROM tugas")->fetchColumn();
    $total_pengumpulan = (int)$pdo->query("SELECT COUNT(*) FROM pengumpulan_tugas")->fetchColumn();
} catch (PDOException $e) {
    $total_tugas       = 0;
    $total_pengumpulan = 0;
}

// 3. PROSES SIMPAN EDIT PROFIL & FOTO
if (isset($_POST['update_profile'])) {
    verify_csrf();

    $email         = trim($_POST['email'] ?? '');
    $nama_lengkap  = trim($_POST['nama_lengkap'] ?? ($user['nama_lengkap'] ?? ''));
    $ttl           = trim($_POST['ttl'] ?? '');
    $password_baru = $_POST['password_baru'] ?? '';

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = "Format email tidak valid.";
    }

    if ($password_baru !== '' && strlen($password_baru) < 8) {
        $error_msg = "Password baru minimal 8 karakter.";
    }

    $nama_file_foto = $user['foto'] ?? '';
    if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
        $file_tmp     = $_FILES['foto_profil']['tmp_name'];
        $allowed_mime = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
        ];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file_tmp);

        if ($_FILES['foto_profil']['size'] > 2 * 1024 * 1024) {
            $error_msg = "Ukuran foto maksimal 2 MB.";
        } elseif (!isset($allowed_mime[$mime]) || @getimagesize($file_tmp) === false) {
            $error_msg = "File foto tidak valid. Gunakan format JPG, PNG, atau GIF.";
        } else {
            $nama_file_foto = 'profile_' . $user_id . '_' . bin2hex(random_bytes(12)) . '.' . $allowed_mime[$mime];
            $upload_dir = __DIR__ . '/uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            if (!move_uploaded_file($file_tmp, $upload_dir . $nama_file_foto)) {
                $error_msg = "Foto profil gagal disimpan ke server.";
                $nama_file_foto = $user['foto'] ?? '';
            }
        }
    }

    if (empty($error_msg)) {
        try {
            if (!empty($password_baru)) {
                $hashed = password_hash($password_baru, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET email = :email, nama_lengkap = :nama_lengkap, ttl = :ttl, password = :pass, foto = :foto WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':email'        => $email,
                    ':nama_lengkap' => $nama_lengkap,
                    ':ttl'          => $ttl,
                    ':pass'         => $hashed,
                    ':foto'         => $nama_file_foto,
                    ':id'           => $user_id
                ]);
            } else {
                $sql = "UPDATE users SET email = :email, nama_lengkap = :nama_lengkap, ttl = :ttl, foto = :foto WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':email'        => $email,
                    ':nama_lengkap' => $nama_lengkap,
                    ':ttl'          => $ttl,
                    ':foto'         => $nama_file_foto,
                    ':id'           => $user_id
                ]);
            }

            $user['email']        = $email;
            $user['nama_lengkap'] = $nama_lengkap;
            $user['ttl']          = $ttl;
            $user['foto']         = $nama_file_foto;

            $success_msg = "Profil Anda berhasil diperbarui!";
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error_msg = "Profil gagal diperbarui karena kesalahan database.";
        }
    }
}

$foto_user = $user['foto'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - Portal Akademik</title>
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
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: var(--bg-main);
            color: var(--text-main);
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* ===== HEADER UTAMA ===== */
        .header-main {
            display: flex;
            align-items: stretch;
            background: #ffffff;
            border-bottom: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
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
            padding: 14px 40px;
            max-width: 1440px;
            margin: 0 auto;
            gap: 20px;
        }

        .header-left h1 {
            color: var(--primary);
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            line-height: 1.2;
        }
        .header-left p {
            color: var(--text-muted);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
            text-transform: uppercase;
        }

        .header-right { display: flex; align-items: center; gap: 12px; }

        .user-badge {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--bg-main);
            padding: 5px 14px 5px 5px;
            border-radius: 30px;
            border: 1px solid var(--border);
            text-decoration: none;
            transition: all 0.2s;
        }
        .user-badge:hover {
            border-color: var(--accent);
            background: #ffffff;
        }
        .user-avatar {
            background: var(--accent);
            color: white;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: bold;
            overflow: hidden;
            flex-shrink: 0;
        }
        .user-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .user-info { display: flex; flex-direction: column; line-height: 1.2; }
        .user-role { color: var(--primary); font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .user-status { color: var(--accent-green); font-size: 10px; font-weight: 600; }
        .header-date {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 600;
            background: var(--bg-subtle);
            padding: 6px 12px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            white-space: nowrap;
        }

        /* ===== NAVBAR ===== */
        .navbar-menu { background-color: var(--primary); width: 100%; box-shadow: inset 0 -3px 0 rgba(0,0,0,0.1); }
        .navbar-inner { max-width: 1440px; margin: 0 auto; padding: 0 40px; }
        .navbar-menu ul { list-style: none; display: flex; align-items: center; gap: 2px; overflow-x: auto; }
        .navbar-menu a.nav-link {
            display: inline-flex; align-items: center; gap: 6px; color: rgba(255, 255, 255, 0.75);
            text-decoration: none; font-weight: 600; font-size: 12.5px; text-transform: uppercase;
            padding: 13px 16px; transition: all 0.2s ease; border-bottom: 3px solid transparent; letter-spacing: 0.3px;
            white-space: nowrap;
        }
        .navbar-menu a.nav-link:hover { color: white; background-color: rgba(255, 255, 255, 0.05); }
        .navbar-menu a.nav-link.active { color: white; border-bottom-color: var(--accent); background-color: rgba(255, 255, 255, 0.05); }
        .navbar-menu a.nav-link.nav-logout { color: #fca5a5; margin-left: auto; }
        .navbar-menu a.nav-link.nav-logout:hover { color: #ef4444; background-color: rgba(239, 68, 68, 0.1); }

        /* ===== CONTAINER ===== */
        .main-container { max-width: 1440px; margin: 26px auto 50px; padding: 0 40px; }

        .profile-layout {
            display: grid;
            grid-template-columns: 360px 1fr;
            gap: 24px;
            align-items: flex-start;
        }

        .content-box {
            background: var(--bg-card);
            padding: 26px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-card);
            border: 1px solid var(--border);
        }

        /* PROFILE CARD SIDEBAR */
        .profile-card-center {
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .profile-photo-large {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent) 0%, #1e40af 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 38px;
            font-weight: 800;
            margin-bottom: 16px;
            overflow: hidden;
            border: 4px solid #ffffff;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.25);
        }
        .profile-photo-large img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .profile-fullname {
            font-size: 18px;
            font-weight: 800;
            color: var(--text-title);
            margin-bottom: 4px;
        }
        .profile-username {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 12px;
        }
        .badge-role {
            display: inline-block;
            padding: 4px 12px;
            background: #eff6ff;
            color: var(--accent);
            border: 1px solid #bfdbfe;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 20px;
        }
        .info-list {
            width: 100%;
            text-align: left;
            border-top: 1px solid var(--border);
            padding-top: 16px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 13px;
            border-bottom: 1px dashed var(--border);
        }
        .info-row:last-child { border-bottom: none; }
        .info-row .label { color: var(--text-muted); font-weight: 600; }
        .info-row .val { color: var(--text-title); font-weight: 700; }

        /* FORM EDIT */
        .section-title {
            font-size: 16px;
            color: var(--text-title);
            font-weight: 800;
            border-bottom: 2px solid var(--bg-subtle);
            padding-bottom: 12px;
            margin-bottom: 20px;
        }

        .alert-box {
            padding: 12px 16px;
            border-radius: var(--radius-md);
            font-size: 13.5px;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .alert-box.success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .alert-box.error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .form-group { margin-bottom: 16px; }
        .form-group.full { grid-column: span 2; }
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: var(--text-title);
            margin-bottom: 6px;
        }
        .form-control {
            width: 100%;
            height: 42px;
            padding: 0 14px;
            font-size: 13.5px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            background: #ffffff;
            color: var(--text-title);
            outline: none;
            transition: all 0.2s ease;
        }
        .form-control:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }
        .form-control:disabled {
            background: var(--bg-subtle);
            color: var(--text-muted);
            cursor: not-allowed;
        }

        .btn-save {
            background: var(--accent);
            color: #ffffff;
            border: none;
            padding: 12px 24px;
            border-radius: var(--radius-sm);
            font-size: 13.5px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 3px 10px rgba(37, 99, 235, 0.2);
        }
        .btn-save:hover { background: var(--accent-hover); }

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
            .profile-layout { grid-template-columns: 1fr; }
            .form-grid { grid-template-columns: 1fr; }
            .form-group.full { grid-column: span 1; }
            .main-container { padding: 0 20px; }
            .header-content { padding: 12px 20px; }
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
                gap: 8px;
            }
            .page-header h2 { font-size: 17px; }
            .page-header p { font-size: 12px; }

            .card-profile, .card-form { padding: 20px 16px; }
            .stats-pill-grid { grid-template-columns: 1fr 1fr; }
            .btn-save { width: 100%; justify-content: center; }
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
                                <?php echo strtoupper(substr($user['username'] ?? 'U', 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        <div class="user-info">
                            <span class="user-role"><?php echo htmlspecialchars($user['nama_lengkap'] ?? $user['username']); ?></span>
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
                <?php if ($nav_role !== 'siswa'): ?>
                    <li><a href="siswa.php" class="nav-link">Data Siswa</a></li>
                    <li><a href="guru.php" class="nav-link">Data Guru</a></li>
                    <li><a href="mapel.php" class="nav-link">Data Mapel</a></li>
                    <li><a href="jurusan.php" class="nav-link">Data Jurusan</a></li>
                    <li><a href="bahan_ajar.php" class="nav-link">Bahan Ajar</a></li>
                <?php endif; ?>
                <li><a href="tugas.php" class="nav-link">Tugas</a></li>
                <?php if ($nav_role === 'siswa'): ?>
                    <li><a href="pelajaran.php" class="nav-link">Pelajaran</a></li>
                <?php endif; ?>
                <li><a href="profile.php" class="nav-link active">Profil Saya</a></li>
                <li><a href="logout.php" class="nav-link nav-logout">Logout</a></li>
            </ul>
        </div>
    </nav>

    <!-- ===== MAIN CONTAINER ===== -->
    <main class="main-container">

        <div class="profile-layout">
            
            <!-- LEFT: PROFILE CARD -->
            <div class="content-box profile-card-center">
                <div class="profile-photo-large">
                    <?php if (!empty($user['foto']) && file_exists('uploads/' . $user['foto'])): ?>
                        <img src="uploads/<?php echo htmlspecialchars($user['foto']); ?>" alt="Foto Profil">
                    <?php else: ?>
                        <?php echo strtoupper(substr($user['username'] ?? 'U', 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <div class="profile-fullname"><?php echo htmlspecialchars($user['nama_lengkap'] ?? $user['username']); ?></div>
                <div class="profile-username">@<?php echo htmlspecialchars($user['username']); ?></div>
                <span class="badge-role"><?php echo htmlspecialchars(strtoupper($nav_role)); ?></span>

                <div class="info-list">
                    <div class="info-row">
                        <span class="label">Email</span>
                        <span class="val"><?php echo htmlspecialchars($user['email'] ?: 'Belum diisi'); ?></span>
                    </div>
                    <?php if ($nav_role === 'siswa'): ?>
                    <div class="info-row">
                        <span class="label">NIS</span>
                        <span class="val"><?php echo htmlspecialchars($user['nis'] ?: '-'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Kelas</span>
                        <span class="val"><?php echo htmlspecialchars($user['kelas'] ?: '-'); ?></span>
                    </div>
                    <?php elseif ($nav_role === 'guru'): ?>
                    <div class="info-row">
                        <span class="label">NIP</span>
                        <span class="val"><?php echo htmlspecialchars($user['nip'] ?: '-'); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <span class="label">Tempat, Tgl Lahir</span>
                        <span class="val"><?php echo htmlspecialchars($user['ttl'] ?: '-'); ?></span>
                    </div>
                </div>
            </div>

            <!-- RIGHT: FORM EDIT PROFIL -->
            <div class="content-box">
                <h2 class="section-title">✏️ Pengaturan Profil</h2>

                <?php if ($success_msg): ?>
                    <div class="alert-box success">✅ <?php echo htmlspecialchars($success_msg); ?></div>
                <?php endif; ?>

                <?php if ($error_msg): ?>
                    <div class="alert-box error">⚠️ <?php echo htmlspecialchars($error_msg); ?></div>
                <?php endif; ?>

                <form action="profile.php" method="POST" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Role Akun</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars(ucfirst($nav_role)); ?>" disabled>
                        </div>

                        <div class="form-group full">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" name="nama_lengkap" class="form-control" value="<?php echo htmlspecialchars($user['nama_lengkap'] ?? ''); ?>" required placeholder="Masukkan nama lengkap">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Alamat Email</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" placeholder="contoh@sekolah.sch.id">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Tempat, Tanggal Lahir</label>
                            <input type="text" name="ttl" class="form-control" value="<?php echo htmlspecialchars($user['ttl'] ?? ''); ?>" placeholder="Contoh: Jakarta, 15 Jan 2007">
                        </div>

                        <div class="form-group full">
                            <label class="form-label">Foto Profil Baru (Maks 2MB, JPG/PNG)</label>
                            <input type="file" name="foto_profil" class="form-control" accept="image/jpeg,image/png,image/gif" style="padding-top: 8px;">
                        </div>

                        <div class="form-group full">
                            <label class="form-label">Ganti Password (Kosongkan jika tidak diubah)</label>
                            <input type="password" name="password_baru" class="form-control" placeholder="Minimal 8 karakter">
                        </div>
                    </div>

                    <div style="margin-top: 10px;">
                        <button type="submit" name="update_profile" class="btn-save">💾 Simpan Perubahan Profil</button>
                    </div>
                </form>
            </div>

        </div>

    </main>

    <script>
        function toggleSidebar() {
            document.getElementById('navbarMenu').classList.toggle('active');
            document.getElementById('navBackdrop').classList.toggle('active');
        }

        function closeSidebar() {
            document.getElementById('navbarMenu').classList.remove('active');
            document.getElementById('navBackdrop').classList.remove('active');
        }

        window.onkeydown = function(e) {
            if (e.key === 'Escape') {
                closeSidebar();
            }
        }
    </script>
</body>
</html>
