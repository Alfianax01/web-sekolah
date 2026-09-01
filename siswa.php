<?php
session_start();
require_once 'koneksi.php';
require_role(['admin', 'guru']);
$nav_role = $_SESSION['role'];

$msg_success = "";
$msg_error   = "";

// ==========================================
// 1. PROSES INSERT & UPDATE NILAI / DATA SISWA
// ==========================================
if (isset($_POST['simpan'])) {
    verify_csrf();
    $mode = $_POST['mode'] ?? 'insert';

    if ($mode === 'update_siswa') {
        // Update data siswa (nama, kelas, jurusan) berdasarkan NIS
        $nis        = sanitize_input($_POST['nis'] ?? '');
        $nama       = sanitize_input($_POST['nama_siswa'] ?? '');
        $kelas      = sanitize_input($_POST['kelas'] ?? '');
        $id_jurusan = sanitize_input($_POST['id_jurusan'] ?? '');

        if (empty($nis) || empty($nama)) {
            $msg_error = "NIS dan Nama Siswa wajib diisi.";
        } else {
            try {
                $sql = "UPDATE siswa SET 
                            nama_siswa = :nama, 
                            kelas = :kelas, 
                            id_jurusan = :id_jurusan 
                        WHERE nis = :nis";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':nama'       => $nama,
                    ':kelas'      => $kelas,
                    ':id_jurusan' => $id_jurusan,
                    ':nis'        => $nis
                ]);
                header("Location: siswa.php?status=edit_siswa_sukses");
                exit();
            } catch (PDOException $e) {
                $msg_error = "Gagal memperbarui data siswa: " . $e->getMessage();
            }
        }
    } else {
        // Insert atau update nilai per mapel (mode insert / update)
        $nis        = sanitize_input($_POST['nis'] ?? '');
        $nama       = sanitize_input($_POST['nama_siswa'] ?? '');
        $ttl        = sanitize_input($_POST['ttl'] ?? '');
        $kelas      = sanitize_input($_POST['kelas'] ?? '');
        $nip        = sanitize_input($_POST['nip'] ?? '');
        $id_mapel   = sanitize_input($_POST['id_mapel'] ?? '');
        $id_jurusan = sanitize_input($_POST['id_jurusan'] ?? '');
        $nilai      = isset($_POST['nilai']) && $_POST['nilai'] !== '' ? max(0, min(100, (int)$_POST['nilai'])) : null;

        if (empty($nis) || empty($nama) || empty($id_mapel)) {
            $msg_error = "NIS, Nama Siswa, dan Mata Pelajaran wajib diisi.";
        } else {
            if ($mode === 'insert') {
                try {
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
                    header("Location: siswa.php?status=tambah_sukses");
                    exit();
                } catch (PDOException $e) {
                    $msg_error = "Gagal menambahkan data siswa: " . $e->getMessage();
                }
            } elseif ($mode === 'update') {
                try {
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
                    header("Location: siswa.php?status=edit_sukses");
                    exit();
                } catch (PDOException $e) {
                    $msg_error = "Gagal memperbarui data siswa: " . $e->getMessage();
                }
            }
        }
    }
}

// ==========================================
// 2. PROSES DELETE (HANYA ADMIN)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf();
    if ($nav_role !== 'admin') {
        http_response_code(403);
        exit('Hanya admin yang memiliki izin menghapus data.');
    }

    if ($_POST['action'] === 'delete_nilai') {
        $nis      = $_POST['nis'] ?? '';
        $id_mapel = $_POST['id_mapel'] ?? '';
        try {
            $sql = "DELETE FROM siswa WHERE nis = :nis AND id_mapel = :id_mapel";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':nis' => $nis, ':id_mapel' => $id_mapel]);
            header("Location: siswa.php?status=hapus_sukses");
            exit();
        } catch (PDOException $e) {
            $msg_error = "Gagal menghapus data: " . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'delete_siswa') {
        $nis = $_POST['nis'] ?? '';
        try {
            $sql = "DELETE FROM siswa WHERE nis = :nis";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':nis' => $nis]);
            header("Location: siswa.php?status=hapus_siswa_sukses");
            exit();
        } catch (PDOException $e) {
            $msg_error = "Gagal menghapus data siswa: " . $e->getMessage();
        }
    }
}

if (isset($_GET['status'])) {
    $status = $_GET['status'];
    $messages = [
        'tambah_sukses'      => "Data nilai berhasil ditambahkan!",
        'edit_sukses'        => "Data nilai berhasil diperbarui!",
        'hapus_sukses'       => "Data nilai berhasil dihapus!",
        'edit_siswa_sukses'  => "Data siswa berhasil diperbarui!",
        'hapus_siswa_sukses' => "Data siswa berhasil dihapus!",
    ];
    if (isset($messages[$status])) {
        $msg_success = $messages[$status];
    }
}

// ==========================================
// 3. DATA REFERENSI
// ==========================================
$stmt_jurusan = $pdo->query("SELECT * FROM jurusan ORDER BY nama_jurusan ASC");
$jurusan_list = $stmt_jurusan->fetchAll(PDO::FETCH_ASSOC);

$stmt_mapel = $pdo->query("SELECT * FROM mapel ORDER BY nama_mapel ASC");
$mapel_list = $stmt_mapel->fetchAll(PDO::FETCH_ASSOC);

$stmt_guru = $pdo->query("SELECT nip, nama_guru FROM guru ORDER BY nama_guru ASC");
$guru_list = $stmt_guru->fetchAll(PDO::FETCH_ASSOC);

$stmt_kelas_all = $pdo->query("SELECT DISTINCT kelas FROM siswa WHERE kelas IS NOT NULL AND kelas != '' ORDER BY kelas ASC");
$kelas_list = $stmt_kelas_all->fetchAll(PDO::FETCH_COLUMN);

// ==========================================
// 4. QUERY READ + FILTER + SEARCH + PAGINASI (5 SISWA/HALAMAN)
// ==========================================
$filter_jurusan = $_GET['filter_jurusan'] ?? '';
$filter_kelas   = $_GET['filter_kelas'] ?? '';
$search         = trim($_GET['search'] ?? '');

$where = "WHERE 1=1";
$params = [];

if ($filter_jurusan !== '') {
    $where .= " AND s.id_jurusan = :jurusan";
    $params[':jurusan'] = $filter_jurusan;
}
if ($filter_kelas !== '') {
    $where .= " AND s.kelas = :kelas";
    $params[':kelas'] = $filter_kelas;
}
if ($search !== '') {
    $where .= " AND (s.nis LIKE :q OR s.nama_siswa LIKE :q OR s.kelas LIKE :q)";
    $params[':q'] = "%$search%";
}

$sql_count = "SELECT COUNT(DISTINCT s.nis) AS total FROM siswa s $where";
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_data = (int)$stmt_count->fetchColumn();

$per_page = 5;
$total_pages = max(1, (int)ceil($total_data / $per_page));

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
if ($page > $total_pages) $page = $total_pages;

$offset = ($page - 1) * $per_page;

$sql_siswa = "SELECT s.nis, s.nama_siswa, s.kelas, s.id_jurusan, j.nama_jurusan
              FROM siswa s
              LEFT JOIN jurusan j ON s.id_jurusan = j.id_jurusan
              $where
              GROUP BY s.nis, s.nama_siswa, s.kelas, s.id_jurusan, j.nama_jurusan
              ORDER BY s.kelas ASC, s.nama_siswa ASC, s.nis ASC
              LIMIT :offset, :per_page";

$stmt = $pdo->prepare($sql_siswa);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
$stmt->execute();
$siswa_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$daftar_siswa = [];
foreach ($siswa_list as $row) {
    $stmt_nilai = $pdo->prepare("SELECT m.nama_mapel, s.nilai, s.id_mapel
                                 FROM siswa s
                                 JOIN mapel m ON s.id_mapel = m.id_mapel
                                 WHERE s.nis = :nis
                                 ORDER BY m.nama_mapel ASC
                                 LIMIT 5");
    $stmt_nilai->execute([':nis' => $row['nis']]);
    $nilai_mapel = $stmt_nilai->fetchAll(PDO::FETCH_ASSOC);
    $nilai_padded = array_pad($nilai_mapel, 5, null);
    $row['nilai_list'] = $nilai_padded;
    $daftar_siswa[] = $row;
}

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
    <title>Data Nilai Siswa - Portal Akademik</title>
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

        /* HEADER */
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

        /* NAVBAR */
        .navbar-menu { background-color: var(--primary); width: 100%; box-shadow: inset 0 -3px 0 rgba(0,0,0,0.1); }
        .navbar-inner { max-width: 1440px; margin: 0 auto; padding: 0 40px; }
        .navbar-menu ul { list-style: none; display: flex; align-items: center; gap: 2px; overflow-x: auto; }
        .navbar-menu a.nav-link { display: inline-flex; align-items: center; gap: 6px; color: rgba(255, 255, 255, 0.75); text-decoration: none; font-weight: 600; font-size: 12.5px; text-transform: uppercase; padding: 13px 16px; border-bottom: 3px solid transparent; white-space: nowrap; }
        .navbar-menu a.nav-link:hover { color: white; background-color: rgba(255, 255, 255, 0.05); }
        .navbar-menu a.nav-link.active { color: white; border-bottom-color: var(--accent); background-color: rgba(255, 255, 255, 0.05); }
        .navbar-menu a.nav-link.nav-logout { color: #fca5a5; margin-left: auto; }

        /* MAIN CONTAINER */
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

        /* TABLE */
        .toolbar-box { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
        .filter-group { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .table-responsive { width: 100%; overflow-x: auto; }
        .table-custom { width: 100%; border-collapse: collapse; text-align: left; }
        .table-custom th { background: var(--bg-subtle); padding: 12px 14px; font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border); }
        .table-custom td { padding: 12px 14px; font-size: 13px; color: var(--text-body); border-bottom: 1px solid var(--border); vertical-align: middle; }
        .table-custom tr:hover { background-color: #fcfdfe; }

        .badge-pill { display: inline-block; padding: 3px 9px; border-radius: 20px; font-size: 11.5px; font-weight: 700; }
        .badge-blue { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
        .badge-purple { background: #faf5ff; color: #7e22ce; border: 1px solid #e9d5ff; }
        .badge-score-high { background: #ecfdf5; color: #047857; font-weight: 800; }
        .badge-score-mid { background: #fefce8; color: #a16207; font-weight: 800; }
        .badge-score-low { background: #fef2f2; color: #b91c1c; font-weight: 800; }

        .btn-tbl { padding: 5px 10px; border-radius: var(--radius-sm); font-size: 11.5px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; border: 1px solid transparent; cursor: pointer; }
        .btn-tbl-edit { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
        .btn-tbl-delete { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }

        .pagination { display: flex; align-items: center; justify-content: center; gap: 6px; margin-top: 20px; }
        .page-link { padding: 6px 12px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: #ffffff; color: var(--text-body); font-size: 12.5px; font-weight: 700; text-decoration: none; }
        .page-link:hover { background: var(--bg-subtle); color: var(--accent); }
        .page-link.active { background: var(--accent); color: #ffffff; border-color: var(--accent); }

        /* MODAL */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 20px;
            animation: fadeIn 0.2s ease;
        }
        .modal-overlay.active { display: flex; }

        .modal-card {
            background: #ffffff;
            width: 100%;
            max-width: 620px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-modal);
            border: 1px solid var(--border);
            overflow: hidden;
            animation: slideUp 0.25s ease;
        }
        .modal-card-sm { max-width: 440px; }

        .modal-header {
            padding: 16px 24px;
            background: #ffffff;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .modal-header h3 { font-size: 16px; font-weight: 800; color: var(--text-title); display: flex; align-items: center; gap: 8px; }
        .modal-close-btn {
            background: none; border: none; font-size: 20px; color: var(--text-muted); cursor: pointer; padding: 4px; line-height: 1; border-radius: 4px;
        }
        .modal-close-btn:hover { color: var(--text-title); background: var(--bg-subtle); }

        .modal-body { padding: 22px 24px; }
        .modal-footer {
            padding: 14px 24px;
            background: var(--bg-subtle);
            border-top: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
        }

        .form-grid-modal { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
        .form-group-modal { margin-bottom: 10px; }
        .form-group-modal.full { grid-column: span 2; }
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
            .toolbar-box {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
            }
            .filter-group {
                flex-direction: column;
                align-items: stretch;
                width: 100%;
                gap: 8px;
            }
            .filter-group select.form-control { width: 100% !important; }
            .toolbar-box form,
            .toolbar-box > div {
                width: 100%;
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
            }
            .toolbar-box input.form-control {
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
                <li><a href="siswa.php" class="nav-link active">Data Siswa</a></li>
                <li><a href="guru.php" class="nav-link">Data Guru</a></li>
                <li><a href="mapel.php" class="nav-link">Data Mapel</a></li>
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
                <h2>📋 Nilai Siswa (5 Nilai Per Siswa)</h2>
                <p>Menampilkan maksimal 5 nilai beserta nama mata pelajaran untuk setiap siswa. Halaman menampilkan 5 siswa.</p>
            </div>
            <div>
                <button type="button" class="btn-action btn-primary" onclick="openAddModal()">
                    ➕ Tambah Nilai
                </button>
            </div>
        </div>

        <?php if ($msg_success): ?>
            <div class="alert-box success">✅ <?php echo htmlspecialchars($msg_success); ?></div>
        <?php endif; ?>
        <?php if ($msg_error): ?>
            <div class="alert-box error">⚠️ <?php echo htmlspecialchars($msg_error); ?></div>
        <?php endif; ?>

        <!-- KONTEN TABEL -->
        <div class="content-box">
            
            <!-- Toolbar: Filter, Search, Total -->
            <form action="siswa.php" method="GET" class="toolbar-box">
                <div class="filter-group">
                    <span style="font-size: 14px; font-weight: 700; color: var(--text-title);">
                        Total Siswa: <strong><?php echo $total_data; ?></strong>
                    </span>
                    <select name="filter_jurusan" class="form-control" style="width: auto; height: 36px;" onchange="this.form.submit()">
                        <option value="">Semua Jurusan</option>
                        <?php foreach ($jurusan_list as $j): ?>
                            <option value="<?php echo htmlspecialchars($j['id_jurusan']); ?>" <?php echo ($filter_jurusan === $j['id_jurusan']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($j['nama_jurusan']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="filter_kelas" class="form-control" style="width: auto; height: 36px;" onchange="this.form.submit()">
                        <option value="">Semua Kelas</option>
                        <?php foreach ($kelas_list as $kls): ?>
                            <option value="<?php echo htmlspecialchars($kls); ?>" <?php echo ($filter_kelas === $kls) ? 'selected' : ''; ?>>
                                Kelas <?php echo htmlspecialchars($kls); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: flex; gap: 8px;">
                    <input type="text" name="search" class="form-control" style="width: 220px; height: 36px;" placeholder="Cari NIS, Nama, Kelas..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn-action btn-primary" style="padding: 6px 14px; font-size: 12.5px;">Cari</button>
                    <?php if ($filter_jurusan || $filter_kelas || $search): ?>
                        <a href="siswa.php" class="btn-action btn-secondary" style="padding: 6px 12px; font-size: 12.5px;">Reset</a>
                    <?php endif; ?>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>NIS</th>
                            <th>Nama Siswa</th>
                            <th>Kelas</th>
                            <th>Jurusan</th>
                            <th>Nilai 1</th>
                            <th>Nilai 2</th>
                            <th>Nilai 3</th>
                            <th>Nilai 4</th>
                            <th>Nilai 5</th>
                            <th style="text-align: center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($daftar_siswa)): ?>
                            <?php foreach ($daftar_siswa as $row): ?>
                                <tr>
                                    <td style="font-weight: 700; color: var(--primary);"><?php echo htmlspecialchars($row['nis']); ?></td>
                                    <td style="font-weight: 700; color: var(--text-title);"><?php echo htmlspecialchars($row['nama_siswa']); ?></td>
                                    <td><span class="badge-pill badge-blue"><?php echo htmlspecialchars($row['kelas'] ?: '-'); ?></span></td>
                                    <td><span class="badge-pill badge-purple"><?php echo htmlspecialchars($row['nama_jurusan'] ?: ($row['id_jurusan'] ?: '-')); ?></span></td>
                                    <?php foreach ($row['nilai_list'] as $item): ?>
                                        <td>
                                            <?php if ($item !== null): ?>
                                                <div style="font-size: 11px; color: var(--text-muted); margin-bottom: 2px;">
                                                    <?php echo htmlspecialchars($item['nama_mapel']); ?>
                                                </div>
                                                <?php 
                                                    $nilai = $item['nilai'];
                                                    $cls = ($nilai >= 80) ? 'badge-score-high' : (($nilai >= 70) ? 'badge-score-mid' : 'badge-score-low');
                                                ?>
                                                <span class="badge-pill <?php echo $cls; ?>"><?php echo htmlspecialchars($nilai); ?></span>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted);">-</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td style="text-align: center; white-space: nowrap;">
                                        <button type="button" class="btn-tbl btn-tbl-edit" 
                                                onclick="openEditSiswaModal(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                                            ✏️ Edit
                                        </button>
                                        <?php if ($nav_role === 'admin'): ?>
                                            <button type="button" class="btn-tbl btn-tbl-delete" 
                                                    onclick="openDeleteSiswaModal('<?php echo htmlspecialchars($row['nis']); ?>', '<?php echo htmlspecialchars(addslashes($row['nama_siswa'])); ?>')">
                                                🗑️ Hapus
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" style="text-align: center; padding: 28px; color: var(--text-muted);">
                                    Tidak ada data siswa yang ditemukan.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginasi -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo ($page - 1); ?>&filter_jurusan=<?php echo urlencode($filter_jurusan); ?>&filter_kelas=<?php echo urlencode($filter_kelas); ?>&search=<?php echo urlencode($search); ?>" class="page-link">← Sebelumnya</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&filter_jurusan=<?php echo urlencode($filter_jurusan); ?>&filter_kelas=<?php echo urlencode($filter_kelas); ?>&search=<?php echo urlencode($search); ?>" class="page-link <?php echo ($i === $page) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo ($page + 1); ?>&filter_jurusan=<?php echo urlencode($filter_jurusan); ?>&filter_kelas=<?php echo urlencode($filter_kelas); ?>&search=<?php echo urlencode($search); ?>" class="page-link">Selanjutnya →</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- ===== MODAL POPUP TAMBAH / EDIT NILAI ===== -->
    <div id="modal-siswa" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3 id="modal-title">➕ Tambah Nilai Mata Pelajaran</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('modal-siswa')">✕</button>
            </div>
            <form action="siswa.php" method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="mode" id="form-mode" value="insert">
                <div class="modal-body">
                    <div class="form-grid-modal">
                        <div class="form-group-modal">
                            <label class="form-label">Nomor Induk Siswa (NIS)</label>
                            <input type="text" name="nis" id="form-nis" class="form-control" required placeholder="Contoh: 242511001">
                        </div>
                        <div class="form-group-modal">
                            <label class="form-label">Nama Lengkap Siswa</label>
                            <input type="text" name="nama_siswa" id="form-nama" class="form-control" required placeholder="Nama lengkap">
                        </div>
                        <div class="form-group-modal">
                            <label class="form-label">Tempat, Tanggal Lahir</label>
                            <input type="text" name="ttl" id="form-ttl" class="form-control" placeholder="Jakarta, 15 Jan 2007">
                        </div>
                        <div class="form-group-modal">
                            <label class="form-label">Kelas</label>
                            <input type="text" name="kelas" id="form-kelas" class="form-control" placeholder="Contoh: XI IPA 1">
                        </div>
                        <div class="form-group-modal">
                            <label class="form-label">Mata Pelajaran</label>
                            <select name="id_mapel" id="form-mapel" class="form-control" required>
                                <option value="">-- Pilih Mata Pelajaran --</option>
                                <?php foreach ($mapel_list as $m): ?>
                                    <option value="<?php echo htmlspecialchars($m['id_mapel']); ?>">
                                        <?php echo htmlspecialchars($m['nama_mapel']); ?> (<?php echo htmlspecialchars($m['id_mapel']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="id_mapel_hidden" id="form-mapel-hidden">
                        </div>
                        <div class="form-group-modal">
                            <label class="form-label">Jurusan / Peminatan</label>
                            <select name="id_jurusan" id="form-jurusan" class="form-control">
                                <option value="">-- Pilih Jurusan --</option>
                                <?php foreach ($jurusan_list as $j): ?>
                                    <option value="<?php echo htmlspecialchars($j['id_jurusan']); ?>">
                                        <?php echo htmlspecialchars($j['nama_jurusan']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group-modal">
                            <label class="form-label">Guru Pengampu</label>
                            <select name="nip" id="form-guru" class="form-control">
                                <option value="">-- Pilih Guru --</option>
                                <?php foreach ($guru_list as $g): ?>
                                    <option value="<?php echo htmlspecialchars($g['nip']); ?>">
                                        <?php echo htmlspecialchars($g['nama_guru']); ?> (<?php echo htmlspecialchars($g['nip']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group-modal">
                            <label class="form-label">Nilai Akademik (0 - 100)</label>
                            <input type="number" name="nilai" id="form-nilai" min="0" max="100" class="form-control" placeholder="0-100">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-action btn-secondary" onclick="closeModal('modal-siswa')">Batal</button>
                    <button type="submit" name="simpan" class="btn-action btn-primary">💾 Simpan Nilai</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===== MODAL EDIT DATA SISWA ===== -->
    <div id="modal-edit-siswa" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3>✏️ Edit Data Siswa</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('modal-edit-siswa')">✕</button>
            </div>
            <form action="siswa.php" method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="mode" value="update_siswa">
                <div class="modal-body">
                    <div class="form-grid-modal">
                        <div class="form-group-modal">
                            <label class="form-label">NIS</label>
                            <input type="text" name="nis" id="edit-nis" class="form-control" readonly style="background:#f1f5f9;">
                        </div>
                        <div class="form-group-modal">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" name="nama_siswa" id="edit-nama" class="form-control" required>
                        </div>
                        <div class="form-group-modal">
                            <label class="form-label">Kelas</label>
                            <input type="text" name="kelas" id="edit-kelas" class="form-control">
                        </div>
                        <div class="form-group-modal">
                            <label class="form-label">Jurusan</label>
                            <select name="id_jurusan" id="edit-jurusan" class="form-control">
                                <option value="">-- Pilih Jurusan --</option>
                                <?php foreach ($jurusan_list as $j): ?>
                                    <option value="<?php echo htmlspecialchars($j['id_jurusan']); ?>">
                                        <?php echo htmlspecialchars($j['nama_jurusan']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-action btn-secondary" onclick="closeModal('modal-edit-siswa')">Batal</button>
                    <button type="submit" class="btn-action btn-primary">💾 Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===== MODAL HAPUS SISWA ===== -->
    <div id="modal-delete-siswa" class="modal-overlay">
        <div class="modal-card modal-card-sm">
            <div class="modal-header">
                <h3>🗑️ Hapus Data Siswa</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('modal-delete-siswa')">✕</button>
            </div>
            <form action="siswa.php" method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="delete_siswa">
                <input type="hidden" name="nis" id="del-siswa-nis">
                <div class="modal-body" style="text-align: center;">
                    <div style="font-size: 40px; margin-bottom: 10px;">⚠️</div>
                    <p style="font-size: 14px; color: var(--text-title); font-weight: 700; margin-bottom: 6px;">
                        Anda yakin ingin menghapus seluruh data siswa ini?
                    </p>
                    <p style="font-size: 13px; color: var(--text-muted);" id="del-siswa-name">-</p>
                    <p style="font-size: 12px; color: var(--accent-rose); margin-top: 10px;">
                        Semua nilai mata pelajaran siswa ini akan ikut terhapus.
                    </p>
                </div>
                <div class="modal-footer" style="justify-content: center;">
                    <button type="button" class="btn-action btn-secondary" onclick="closeModal('modal-delete-siswa')">Batal</button>
                    <button type="submit" class="btn-action" style="background: var(--accent-rose); color: #fff;">Ya, Hapus</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal tambah nilai
        function openAddModal() {
            document.getElementById('modal-title').innerHTML = '➕ Tambah Nilai Mata Pelajaran';
            document.getElementById('form-mode').value = 'insert';
            document.getElementById('form-nis').value = '';
            document.getElementById('form-nis').readOnly = false;
            document.getElementById('form-nis').style.background = '#ffffff';
            document.getElementById('form-nama').value = '';
            document.getElementById('form-ttl').value = '';
            document.getElementById('form-kelas').value = '';
            document.getElementById('form-mapel').value = '';
            document.getElementById('form-mapel').disabled = false;
            document.getElementById('form-mapel').style.background = '#ffffff';
            document.getElementById('form-jurusan').value = '';
            document.getElementById('form-guru').value = '';
            document.getElementById('form-nilai').value = '';
            document.getElementById('modal-siswa').classList.add('active');
        }

        // Modal edit data siswa
        function openEditSiswaModal(data) {
            document.getElementById('edit-nis').value = data.nis;
            document.getElementById('edit-nama').value = data.nama_siswa;
            document.getElementById('edit-kelas').value = data.kelas || '';
            document.getElementById('edit-jurusan').value = data.id_jurusan || '';
            document.getElementById('modal-edit-siswa').classList.add('active');
        }

        // Modal hapus siswa
        function openDeleteSiswaModal(nis, nama) {
            document.getElementById('del-siswa-nis').value = nis;
            document.getElementById('del-siswa-name').innerText = nama + ' (NIS: ' + nis + ')';
            document.getElementById('modal-delete-siswa').classList.add('active');
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