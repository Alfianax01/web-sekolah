<?php
session_start();
require_once 'koneksi.php';
require_login();

$nav_role     = $_SESSION['role'] ?? 'siswa';
$session_nis  = $_SESSION['nis'] ?? null;
$session_nama = $_SESSION['nama_lengkap'] ?? ($_SESSION['username'] ?? '');

$msg_success = "";
$msg_error   = "";

// ==========================================
// 1. PROSES BUAT TUGAS (ADMIN & GURU)
// ==========================================
if (isset($_POST['buat_tugas'])) {
    verify_csrf();
    if ($nav_role !== 'admin' && $nav_role !== 'guru') {
        $msg_error = "Anda tidak memiliki izin untuk membuat tugas.";
    } else {
        $judul    = sanitize_input($_POST['judul'] ?? '');
        $id_mapel = sanitize_input($_POST['id_mapel'] ?? '');
        $deadline = sanitize_input($_POST['deadline'] ?? '');
        $desc     = sanitize_input($_POST['deskripsi'] ?? '');

        if (empty($judul) || empty($id_mapel)) {
            $msg_error = "Judul tugas dan Mata Pelajaran wajib diisi.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO tugas (judul, id_mapel, deadline, deskripsi) VALUES (:judul, :id_mapel, :deadline, :deskripsi)");
                $stmt->execute([
                    ':judul'     => $judul,
                    ':id_mapel'  => $id_mapel,
                    ':deadline'  => (!empty($deadline) ? $deadline : null),
                    ':deskripsi' => $desc
                ]);
                header("Location: tugas.php?status=tugas_dibuat");
                exit();
            } catch (PDOException $e) {
                $msg_error = "Gagal membuat tugas: " . $e->getMessage();
            }
        }
    }
}

// ==========================================
// 2. PROSES KUMPULKAN TUGAS (PDF UPLOAD) - HANYA SISWA
// ==========================================
if (isset($_POST['kumpul_tugas'])) {
    verify_csrf();
    if ($nav_role !== 'siswa') {
        $msg_error = "Hanya siswa yang dapat mengumpulkan tugas.";
    } else {
        $id_tugas   = sanitize_input($_POST['id_tugas'] ?? '');
        $nis        = $session_nis;
        $nama_siswa = $session_nama;
        $file       = $_FILES['file_pdf'] ?? null;

        if (empty($id_tugas) || empty($file['name'])) {
            $msg_error = "Pilih tugas dan lampirkan file PDF.";
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($file['tmp_name']);

            if ($mime !== 'application/pdf') {
                $msg_error = "Berkas harus berformat PDF.";
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $msg_error = "Ukuran berkas maksimal 5 MB.";
            } else {
                $stmt_t = $pdo->prepare("SELECT deadline FROM tugas WHERE id_tugas = :id");
                $stmt_t->execute([':id' => $id_tugas]);
                $tugas_data = $stmt_t->fetch(PDO::FETCH_ASSOC);

                $submitted_at = date('Y-m-d H:i:s');
                $status = 'Tepat Waktu';

                if (!empty($tugas_data['deadline'])) {
                    $deadline_time = strtotime($tugas_data['deadline'] . ' 23:59:59');
                    if (time() > $deadline_time) {
                        $status = 'Terlambat';
                    }
                }

                $target_dir = __DIR__ . "/uploads/";
                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0755, true);
                }

                $unique_name = 'tugas_' . time() . '_' . bin2hex(random_bytes(8)) . '.pdf';
                $target_file = $target_dir . $unique_name;
                $db_path     = 'uploads/' . $unique_name;

                if (move_uploaded_file($file['tmp_name'], $target_file)) {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO pengumpulan_tugas (id_tugas, nis, nama_siswa, nama_file, path_file, ukuran_file, status, submitted_at) 
                                                VALUES (:id_tugas, :nis, :nama_siswa, :nama_file, :path_file, :ukuran_file, :status, :submitted_at)");
                        $stmt->execute([
                            ':id_tugas'     => $id_tugas,
                            ':nis'          => $nis,
                            ':nama_siswa'   => $nama_siswa,
                            ':nama_file'    => sanitize_input($file['name']),
                            ':path_file'    => $db_path,
                            ':ukuran_file'  => $file['size'],
                            ':status'       => $status,
                            ':submitted_at' => $submitted_at
                        ]);
                        header("Location: tugas.php?status=tugas_dikumpul");
                        exit();
                    } catch (PDOException $e) {
                        $msg_error = "Gagal menyimpan data tugas: " . $e->getMessage();
                    }
                } else {
                    $msg_error = "Gagal mengunggah berkas ke server.";
                }
            }
        }
    }
}

// ==========================================
// 3. PROSES SIMPAN NILAI (ADMIN & GURU)
// ==========================================
if (isset($_POST['update_nilai'])) {
    verify_csrf();
    if ($nav_role === 'admin' || $nav_role === 'guru') {
        $id_pengumpulan = sanitize_input($_POST['id_pengumpulan'] ?? '');
        $nilai = isset($_POST['nilai']) && $_POST['nilai'] !== '' ? max(0, min(100, (int)$_POST['nilai'])) : null;

        try {
            $stmt = $pdo->prepare("UPDATE pengumpulan_tugas SET nilai = :nilai WHERE id_pengumpulan = :id");
            $stmt->execute([':nilai' => $nilai, ':id' => $id_pengumpulan]);
            header("Location: tugas.php?status=nilai_disimpan");
            exit();
        } catch (PDOException $e) {
            $msg_error = "Gagal menyimpan nilai: " . $e->getMessage();
        }
    }
}

// ==========================================
// 4. PROSES HAPUS PENGUMPULAN TUGAS (HANYA ADMIN)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_submission') {
    verify_csrf();
    if ($nav_role === 'admin') {
        $del_id = $_POST['id'] ?? '';
        $stmt = $pdo->prepare("SELECT path_file FROM pengumpulan_tugas WHERE id_pengumpulan = :id");
        $stmt->execute([':id' => $del_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            $file_path = realpath(__DIR__ . '/' . $data['path_file']);
            $upload_root = realpath(__DIR__ . '/uploads');
            if ($file_path && $upload_root && str_starts_with($file_path, $upload_root . DIRECTORY_SEPARATOR) && is_file($file_path)) {
                @unlink($file_path);
            }
            $stmt_del = $pdo->prepare("DELETE FROM pengumpulan_tugas WHERE id_pengumpulan = :id");
            $stmt_del->execute([':id' => $del_id]);
        }
        header("Location: tugas.php?status=pengumpulan_dihapus");
        exit();
    }
}

// Status messages
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'tugas_dibuat') $msg_success = "Tugas baru berhasil diterbitkan!";
    if ($_GET['status'] === 'tugas_dikumpul') $msg_success = "Tugas berhasil dikumpulkan!";
    if ($_GET['status'] === 'nilai_disimpan') $msg_success = "Nilai tugas berhasil disimpan!";
    if ($_GET['status'] === 'pengumpulan_dihapus') $msg_success = "Data pengumpulan tugas berhasil dihapus!";
}

// ==========================================
// 5. DATA QUERY
// ==========================================
$mapel_list = $pdo->query("SELECT * FROM mapel ORDER BY nama_mapel ASC")->fetchAll(PDO::FETCH_ASSOC);

$tugas_list = $pdo->query("
    SELECT t.*, m.nama_mapel 
    FROM tugas t 
    LEFT JOIN mapel m ON t.id_mapel = m.id_mapel 
    ORDER BY t.id_tugas DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Submissions query
$submissions_sql = "
    SELECT p.*, t.judul AS tugas_judul, t.id_mapel, m.nama_mapel
    FROM pengumpulan_tugas p 
    LEFT JOIN tugas t ON p.id_tugas = t.id_tugas 
    LEFT JOIN mapel m ON t.id_mapel = m.id_mapel
    WHERE 1=1
";

$params_sub = [];
if ($nav_role === 'siswa') {
    $submissions_sql .= " AND p.nis = :nis";
    $params_sub[':nis'] = $session_nis;
}

$submissions_sql .= " ORDER BY p.id_pengumpulan DESC";
$stmt_sub = $pdo->prepare($submissions_sql);
$stmt_sub->execute($params_sub);
$submissions = $stmt_sub->fetchAll(PDO::FETCH_ASSOC);

// User info
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
    <title>Kelola Tugas & Penilaian - Portal Akademik</title>
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
        .header-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

        .content-box { background: var(--bg-card); padding: 22px; border-radius: var(--radius-lg); box-shadow: var(--shadow-card); border: 1px solid var(--border); margin-bottom: 22px; }
        .section-title { font-size: 14.5px; color: var(--text-title); font-weight: 800; border-bottom: 2px solid var(--bg-subtle); padding-bottom: 12px; margin-bottom: 16px; }

        .alert-box { padding: 12px 16px; border-radius: var(--radius-md); font-size: 13.5px; font-weight: 600; margin-bottom: 18px; display: flex; align-items: center; gap: 8px; }
        .alert-box.success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .alert-box.error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

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
        .badge-ontime { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .badge-late { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .badge-blue { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }

        .btn-tbl { padding: 5px 10px; border-radius: var(--radius-sm); font-size: 11.5px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; border: 1px solid transparent; cursor: pointer; }
        .btn-tbl-view { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
        .btn-tbl-delete { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }

        .form-control { width: 100%; height: 38px; padding: 0 12px; font-size: 13px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: #ffffff; color: var(--text-title); outline: none; transition: all 0.2s; }
        .form-control:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
        textarea.form-control { height: 80px; padding: 10px 12px; resize: vertical; }

        /* ===== MODAL POPUP SYSTEM ===== */
        .modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); display: none; align-items: center; justify-content: center; z-index: 1000; padding: 20px; animation: fadeIn 0.2s ease; }
        .modal-overlay.active { display: flex; }
        .modal-card { background: #ffffff; width: 100%; max-width: 580px; border-radius: var(--radius-lg); box-shadow: var(--shadow-modal); border: 1px solid var(--border); overflow: hidden; animation: slideUp 0.25s ease; }
        .modal-card-sm { max-width: 420px; }
        .modal-card-lg { max-width: 900px; }
        .modal-header { padding: 16px 24px; background: #ffffff; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .modal-header h3 { font-size: 16px; font-weight: 800; color: var(--text-title); display: flex; align-items: center; gap: 8px; }
        .modal-close-btn { background: none; border: none; font-size: 20px; color: var(--text-muted); cursor: pointer; padding: 4px; line-height: 1; border-radius: 4px; }
        .modal-close-btn:hover { color: var(--text-title); background: var(--bg-subtle); }
        .modal-body { padding: 22px 24px; }
        .modal-footer { padding: 14px 24px; background: var(--bg-subtle); border-top: 1px solid var(--border); display: flex; align-items: center; justify-content: flex-end; gap: 10px; }

        .form-grid-modal { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
        .form-group-modal { margin-bottom: 12px; }
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
            .header-actions { width: 100%; }
            .header-actions .btn-action { width: 100%; justify-content: center; }

            .content-box { padding: 16px 14px; }

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
                <?php if ($nav_role !== 'siswa'): ?>
                    <li><a href="siswa.php" class="nav-link">Data Siswa</a></li>
                    <li><a href="guru.php" class="nav-link">Data Guru</a></li>
                    <li><a href="mapel.php" class="nav-link">Data Mapel</a></li>
                    <li><a href="jurusan.php" class="nav-link">Data Jurusan</a></li>
                    <li><a href="bahan_ajar.php" class="nav-link">Bahan Ajar</a></li>
                <?php endif; ?>
                <li><a href="tugas.php" class="nav-link active">Tugas</a></li>
                <?php if ($nav_role === 'siswa'): ?>
                    <li><a href="pelajaran.php" class="nav-link">Pelajaran</a></li>
                <?php endif; ?>
                <li><a href="profile.php" class="nav-link">Profil Saya</a></li>
                <li><a href="logout.php" class="nav-link nav-logout">Logout</a></li>
            </ul>
        </div>
    </nav>

    <!-- ===== MAIN CONTAINER ===== -->
    <main class="main-container">

        <div class="page-header">
            <div>
                <h2>📝 Manajemen Tugas & Pengumpulan Berkas</h2>
                <p>Pemberian tugas akademik, pengumpulan berkas PDF siswa, serta evaluasi penilaian.</p>
            </div>
            <div class="header-actions">
                <?php if ($nav_role === 'admin' || $nav_role === 'guru'): ?>
                    <button type="button" class="btn-action btn-primary" onclick="openCreateTaskModal()">
                        ➕ Terbitkan Tugas Baru
                    </button>
                <?php endif; ?>
                <?php if ($nav_role === 'siswa'): ?>
                    <button type="button" class="btn-action btn-primary" onclick="openSubmitTaskModal()">
                        📤 Kumpulkan Tugas PDF
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($msg_success): ?>
            <div class="alert-box success">✅ <?php echo htmlspecialchars($msg_success); ?></div>
        <?php endif; ?>
        <?php if ($msg_error): ?>
            <div class="alert-box error">⚠️ <?php echo htmlspecialchars($msg_error); ?></div>
        <?php endif; ?>

        <!-- DAFTAR TUGAS AKTIF -->
        <div class="content-box">
            <h3 class="section-title">📋 Daftar Tugas Aktif</h3>

            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Mata Pelajaran</th>
                            <th>Judul Tugas</th>
                            <th>Batas Waktu (Deadline)</th>
                            <th>Petunjuk / Deskripsi</th>
                            <?php if ($nav_role === 'siswa'): ?>
                                <th style="text-align: right;">Aksi</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($tugas_list)): ?>
                            <?php foreach ($tugas_list as $t): ?>
                                <tr>
                                    <td><span class="badge-pill badge-blue"><?php echo htmlspecialchars($t['nama_mapel'] ?: ($t['id_mapel'] ?: '-')); ?></span></td>
                                    <td style="font-weight: 700; color: var(--text-title);"><?php echo htmlspecialchars($t['judul']); ?></td>
                                    <td>
                                        <?php if (!empty($t['deadline'])): ?>
                                            📅 <?php echo date('d M Y', strtotime($t['deadline'])); ?>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted);">Tidak ada batas waktu</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="color: var(--text-muted); font-size: 12.5px;"><?php echo htmlspecialchars($t['deskripsi'] ?: '-'); ?></td>
                                    <?php if ($nav_role === 'siswa'): ?>
                                        <td style="text-align: right; white-space: nowrap;">
                                            <button type="button" class="btn-tbl btn-tbl-view" onclick="openSubmitTaskModal(<?php echo (int)$t['id_tugas']; ?>)">
                                                📤 Kumpul
                                            </button>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?php echo ($nav_role === 'siswa') ? '5' : '4'; ?>" style="text-align: center; padding: 24px; color: var(--text-muted);">
                                    Belum ada tugas yang diterbitkan.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- DAFTAR PENGUMPULAN TUGAS & PENILAIAN -->
        <div class="content-box">
            <h3 class="section-title">
                <span><?php echo ($nav_role === 'siswa') ? '📊 Riwayat Pengumpulan & Nilai Saya' : '📊 Rekap Pengumpulan & Penilaian Tugas Siswa'; ?></span>
            </h3>

            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Siswa</th>
                            <th>Tugas & Mapel</th>
                            <th>Waktu Kirim</th>
                            <th>Status Deadline</th>
                            <th>Berkas PDF</th>
                            <th>Nilai</th>
                            <?php if ($nav_role === 'admin' || $nav_role === 'guru'): ?>
                                <th style="text-align: right;">Aksi</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($submissions)): ?>
                            <?php foreach ($submissions as $sub): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 700; color: var(--text-title);"><?php echo htmlspecialchars($sub['nama_siswa']); ?></div>
                                        <div style="font-size: 11px; color: var(--text-muted);">NIS: <?php echo htmlspecialchars($sub['nis'] ?: '-'); ?></div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 700; color: var(--primary);"><?php echo htmlspecialchars($sub['tugas_judul'] ?: 'Tugas #' . $sub['id_tugas']); ?></div>
                                        <div style="font-size: 11px; color: var(--text-muted);"><?php echo htmlspecialchars($sub['nama_mapel'] ?: '-'); ?></div>
                                    </td>
                                    <td style="font-size: 12px; color: var(--text-muted);"><?php echo htmlspecialchars($sub['submitted_at'] ?? '-'); ?></td>
                                    <td>
                                        <?php if ($sub['status'] === 'Tepat Waktu'): ?>
                                            <span class="badge-pill badge-ontime">✓ Tepat Waktu</span>
                                        <?php else: ?>
                                            <span class="badge-pill badge-late">⚠️ Terlambat</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn-tbl btn-tbl-view" 
                                            onclick="openPdfModal('<?php echo htmlspecialchars(addslashes($sub['path_file'])); ?>', '<?php echo htmlspecialchars(addslashes($sub['tugas_judul'] ?: 'Berkas Tugas')); ?>')">
                                            📄 <?php echo htmlspecialchars($sub['nama_file'] ?: 'Buka PDF'); ?>
                                        </button>
                                    </td>
                                    <td>
                                        <?php if ($sub['nilai'] !== null && $sub['nilai'] !== ''): ?>
                                            <span style="font-size: 15px; font-weight: 800; color: var(--accent);"><?php echo htmlspecialchars($sub['nilai']); ?></span> / 100
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-style: italic;">Belum Dinilai</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($nav_role === 'admin' || $nav_role === 'guru'): ?>
                                        <td style="text-align: right; white-space: nowrap;">
                                            <form action="tugas.php" method="POST" style="display: inline-flex; align-items: center; gap: 6px;">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="id_pengumpulan" value="<?php echo htmlspecialchars($sub['id_pengumpulan']); ?>">
                                                <input type="number" name="nilai" min="0" max="100" class="form-control" style="width: 65px; height: 32px; padding: 0 6px; text-align: center;" placeholder="0-100" value="<?php echo htmlspecialchars($sub['nilai'] ?? ''); ?>" required>
                                                <button type="submit" name="update_nilai" class="btn-tbl btn-tbl-view" style="height: 32px;">💾 Simpan</button>
                                            </form>

                                            <?php if ($nav_role === 'admin'): ?>
                                                <button type="button" class="btn-tbl btn-tbl-delete" style="height: 32px;"
                                                    onclick="openDeleteSubmissionModal('<?php echo htmlspecialchars($sub['id_pengumpulan']); ?>', '<?php echo htmlspecialchars(addslashes($sub['nama_siswa'])); ?>')">
                                                    🗑️
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 24px; color: var(--text-muted);">
                                    Belum ada berkas pengumpulan tugas yang tercatat.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- ===== MODAL TERBITKAN TUGAS BARU (ADMIN & GURU) ===== -->
    <div id="modal-create-task" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3>➕ Buat & Terbitkan Tugas Baru</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('modal-create-task')">✕</button>
            </div>
            <form action="tugas.php" method="POST">
                <?php echo csrf_field(); ?>

                <div class="modal-body">
                    <div class="form-grid-modal">
                        <div class="form-group-modal">
                            <label class="form-label">Mata Pelajaran</label>
                            <select name="id_mapel" class="form-control" required>
                                <option value="">-- Pilih Mata Pelajaran --</option>
                                <?php foreach ($mapel_list as $m): ?>
                                    <option value="<?php echo htmlspecialchars($m['id_mapel']); ?>">
                                        <?php echo htmlspecialchars($m['nama_mapel']); ?> (<?php echo htmlspecialchars($m['id_mapel']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group-modal">
                            <label class="form-label">Batas Waktu (Deadline)</label>
                            <input type="date" name="deadline" class="form-control">
                        </div>

                        <div class="form-group-modal full">
                            <label class="form-label">Judul Tugas</label>
                            <input type="text" name="judul" class="form-control" required placeholder="Contoh: Latihan 3 - Persamaan Kuadrat">
                        </div>

                        <div class="form-group-modal full">
                            <label class="form-label">Deskripsi / Petunjuk Pengerjaan</label>
                            <textarea name="deskripsi" class="form-control" placeholder="Tuliskan petunjuk pengerjaan tugas di sini..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-action btn-secondary" onclick="closeModal('modal-create-task')">Batal</button>
                    <button type="submit" name="buat_tugas" class="btn-action btn-primary">🚀 Terbitkan Tugas</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===== MODAL KUMPULKAN TUGAS (SISWA) ===== -->
    <div id="modal-submit-task" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3>📤 Kumpulkan Berkas Tugas (Format PDF)</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('modal-submit-task')">✕</button>
            </div>
            <form action="tugas.php" method="POST" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>

                <div class="modal-body">
                    <div class="form-group-modal">
                        <label class="form-label">Pilih Tugas</label>
                        <select name="id_tugas" id="submit-tugas-select" class="form-control" required>
                            <option value="">-- Pilih Tugas --</option>
                            <?php foreach ($tugas_list as $t): ?>
                                <option value="<?php echo htmlspecialchars($t['id_tugas']); ?>">
                                    <?php echo htmlspecialchars($t['judul']); ?> (<?php echo htmlspecialchars($t['nama_mapel']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group-modal">
                        <label class="form-label">Identitas Siswa</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($session_nama); ?> (NIS: <?php echo htmlspecialchars($session_nis ?: '-'); ?>)" disabled style="background:#f1f5f9;">
                    </div>

                    <div class="form-group-modal">
                        <label class="form-label">Berkas Tugas (PDF Maks 5MB)</label>
                        <input type="file" name="file_pdf" class="form-control" accept="application/pdf" required style="padding-top: 6px;">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-action btn-secondary" onclick="closeModal('modal-submit-task')">Batal</button>
                    <button type="submit" name="kumpul_tugas" class="btn-action btn-primary">📤 Kirim Berkas Tugas</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===== MODAL HAPUS PENGUMPULAN (ADMIN) ===== -->
    <div id="modal-delete-submission" class="modal-overlay">
        <div class="modal-card modal-card-sm">
            <div class="modal-header">
                <h3>🗑️ Hapus Pengumpulan</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('modal-delete-submission')">✕</button>
            </div>
            <form action="tugas.php" method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="delete_submission">
                <input type="hidden" name="id" id="del-sub-id">

                <div class="modal-body" style="text-align: center;">
                    <div style="font-size: 40px; margin-bottom: 10px;">⚠️</div>
                    <p style="font-size: 14px; color: var(--text-title); font-weight: 700; margin-bottom: 6px;">
                        Hapus pengumpulan tugas siswa ini?
                    </p>
                    <p style="font-size: 13px; color: var(--text-muted);" id="del-sub-name">-</p>
                </div>

                <div class="modal-footer" style="justify-content: center;">
                    <button type="button" class="btn-action btn-secondary" onclick="closeModal('modal-delete-submission')">Batal</button>
                    <button type="submit" class="btn-action" style="background: var(--accent-rose); color: #fff;">Ya, Hapus</button>
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
        function openCreateTaskModal() {
            document.getElementById('modal-create-task').classList.add('active');
        }

        function openSubmitTaskModal(preselectedId) {
            var select = document.getElementById('submit-tugas-select');
            if (preselectedId) {
                select.value = preselectedId;
            }
            document.getElementById('modal-submit-task').classList.add('active');
        }

        function openDeleteSubmissionModal(id, studentName) {
            document.getElementById('del-sub-id').value = id;
            document.getElementById('del-sub-name').innerText = 'Siswa: ' + studentName;
            document.getElementById('modal-delete-submission').classList.add('active');
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