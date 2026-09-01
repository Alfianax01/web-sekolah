<?php
session_start();
$nav_role = $_SESSION['role'] ?? 'admin';
require_once 'koneksi.php';

// ================= AMBIL DATA USER DARI SESSION =================
$user_id = $_SESSION['user_id'] ?? null;
$foto_user = '';
$user_display_name = $_SESSION['username'] ?? ucfirst($nav_role);
$user_nip = '';

if ($user_id) {
    try {
        $stmtUser = $pdo->prepare("SELECT foto, nama_lengkap, username, nip, role FROM users WHERE id = :id LIMIT 1");
        $stmtUser->execute([':id' => $user_id]);
        $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);
        if ($userData) {
            if (!empty($userData['foto'])) {
                $foto_user = $userData['foto'];
            }
            if (!empty($userData['nama_lengkap'])) {
                $user_display_name = $userData['nama_lengkap'];
            } elseif (!empty($userData['username'])) {
                $user_display_name = $userData['username'];
            }
            $user_nip = $userData['nip'] ?? '';
        }
    } catch (Exception $e) {
        $foto_user = '';
    }
}

// ================= DATA GLOBAL UNTUK PIE CHART =================
try { $total_siswa_global = (int)$pdo->query("SELECT COUNT(DISTINCT nis) FROM siswa")->fetchColumn(); } catch (Exception $e) { $total_siswa_global = 0; }
try { $total_guru_global = (int)$pdo->query("SELECT COUNT(*) FROM guru")->fetchColumn(); } catch (Exception $e) { $total_guru_global = 0; }
try { $total_staf_global = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('staf', 'tu')")->fetchColumn(); } catch (Exception $e) { $total_staf_global = 0; }
try { $total_lainnya_global = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role NOT IN ('siswa', 'guru', 'staf', 'tu')")->fetchColumn(); } catch (Exception $e) { $total_lainnya_global = 0; }

$total_global = $total_siswa_global + $total_guru_global + $total_staf_global + $total_lainnya_global;
if ($total_global == 0) $total_global = 1;

$persen_siswa = round(($total_siswa_global / $total_global) * 100, 1);
$persen_guru  = round(($total_guru_global / $total_global) * 100, 1);
$persen_staf  = round(($total_staf_global / $total_global) * 100, 1);
$persen_lain  = round(($total_lainnya_global / $total_global) * 100, 1);

// ================= DATA ROLE: GURU =================
$is_guru = ($nav_role === 'guru');
$guru_nip = $user_nip;
$guru_nama = $user_display_name;
$total_siswa_guru = 0;
$total_kelas_guru = 0;
$total_mapel_guru = 0;
$total_tugas_guru = 0;
$total_bahan_guru = 0;
$total_pengumpulan_guru = 0;
$siswa_per_kelas_guru = [];
$mapel_list_guru = [];
$list_siswa_guru = [];
$max_siswa_kelas = 1;

if ($is_guru) {
    if (empty($guru_nip)) {
        try {
            $stmtCariGuru = $pdo->prepare("SELECT nip, nama_guru FROM guru WHERE LOWER(nama_guru) = LOWER(:nama) LIMIT 1");
            $stmtCariGuru->execute([':nama' => $user_display_name]);
            $dGuru = $stmtCariGuru->fetch(PDO::FETCH_ASSOC);
            if ($dGuru) {
                $guru_nip = $dGuru['nip'];
                $guru_nama = $dGuru['nama_guru'];
            }
        } catch (Exception $e) {}
    } else {
        try {
            $stmtNamaGuru = $pdo->prepare("SELECT nama_guru FROM guru WHERE nip = :nip LIMIT 1");
            $stmtNamaGuru->execute([':nip' => $guru_nip]);
            $dNama = $stmtNamaGuru->fetch(PDO::FETCH_ASSOC);
            if ($dNama && !empty($dNama['nama_guru'])) {
                $guru_nama = $dNama['nama_guru'];
            }
        } catch (Exception $e) {}
    }

    try {
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT nis) FROM siswa WHERE nip = :nip");
        $stmt->execute([':nip' => $guru_nip]);
        $total_siswa_guru = (int)$stmt->fetchColumn();
    } catch (Exception $e) { $total_siswa_guru = 0; }

    try {
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT kelas) FROM siswa WHERE nip = :nip AND kelas IS NOT NULL AND kelas != ''");
        $stmt->execute([':nip' => $guru_nip]);
        $total_kelas_guru = (int)$stmt->fetchColumn();
    } catch (Exception $e) { $total_kelas_guru = 0; }

    try {
        $stmt = $pdo->prepare("SELECT kelas, COUNT(DISTINCT nis) as total FROM siswa WHERE nip = :nip AND kelas IS NOT NULL AND kelas != '' GROUP BY kelas ORDER BY kelas ASC");
        $stmt->execute([':nip' => $guru_nip]);
        $siswa_per_kelas_guru = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($siswa_per_kelas_guru as $sk) {
            if ((int)$sk['total'] > $max_siswa_kelas) {
                $max_siswa_kelas = (int)$sk['total'];
            }
        }
    } catch (Exception $e) { $siswa_per_kelas_guru = []; }

    try {
        $stmt = $pdo->prepare("SELECT m.id_mapel, m.nama_mapel FROM guru g JOIN mapel m ON g.id_mapel = m.id_mapel WHERE g.nip = :nip");
        $stmt->execute([':nip' => $guru_nip]);
        $mapel_list_guru = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_mapel_guru = count($mapel_list_guru);
    } catch (Exception $e) { 
        $mapel_list_guru = []; 
        $total_mapel_guru = 0; 
    }

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tugas WHERE guru_id = :nip OR guru_id = :uid");
        $stmt->execute([':nip' => $guru_nip, ':uid' => $user_id]);
        $total_tugas_guru = (int)$stmt->fetchColumn();
    } catch (Exception $e) { $total_tugas_guru = 0; }

    $mapel_ids = array_column($mapel_list_guru, 'id_mapel');
    if (!empty($mapel_ids)) {
        try {
            $inClause = implode(',', array_fill(0, count($mapel_ids), '?'));
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM bahan_ajar WHERE id_mapel IN ($inClause)");
            $stmt->execute($mapel_ids);
            $total_bahan_guru = (int)$stmt->fetchColumn();
        } catch (Exception $e) { $total_bahan_guru = 0; }
    }

    try {
        $stmt = $pdo->prepare("SELECT DISTINCT nis, nama_siswa, kelas FROM siswa WHERE nip = :nip ORDER BY nis ASC LIMIT 8");
        $stmt->execute([':nip' => $guru_nip]);
        $list_siswa_guru = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $list_siswa_guru = []; }

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pengumpulan_tugas p JOIN tugas t ON p.id_tugas = t.id_tugas WHERE t.guru_id = :nip OR t.guru_id = :uid");
        $stmt->execute([':nip' => $guru_nip, ':uid' => $user_id]);
        $total_pengumpulan_guru = (int)$stmt->fetchColumn();
    } catch (Exception $e) { $total_pengumpulan_guru = 0; }
}

// ================= DATA ROLE: ADMIN =================
$total_siswa_admin = $total_siswa_global;
$total_guru_admin = $total_guru_global;
$total_mapel_admin = 0;
$total_jurusan_admin = 0;
$total_bahan_admin = 0;
$siswa_per_jurusan_admin = [];
$max_siswa_jurusan = 1;
$recent_guru_admin = [];
$recent_siswa_admin = [];

if ($nav_role === 'admin') {
    try { $total_mapel_admin = (int)$pdo->query("SELECT COUNT(*) FROM mapel")->fetchColumn(); } catch (Exception $e) { $total_mapel_admin = 0; }
    try { $total_jurusan_admin = (int)$pdo->query("SELECT COUNT(*) FROM jurusan")->fetchColumn(); } catch (Exception $e) { $total_jurusan_admin = 0; }
    try { $total_bahan_admin = (int)$pdo->query("SELECT COUNT(*) FROM bahan_ajar")->fetchColumn(); } catch (Exception $e) { $total_bahan_admin = 0; }

    try {
        $stmtJur = $pdo->query("SELECT j.nama_jurusan, COUNT(DISTINCT s.nis) as total 
                                FROM jurusan j 
                                LEFT JOIN siswa s ON j.id_jurusan = s.id_jurusan 
                                GROUP BY j.id_jurusan, j.nama_jurusan 
                                ORDER BY total DESC");
        $siswa_per_jurusan_admin = $stmtJur->fetchAll(PDO::FETCH_ASSOC);
        foreach ($siswa_per_jurusan_admin as $sj) {
            if ((int)$sj['total'] > $max_siswa_jurusan) {
                $max_siswa_jurusan = (int)$sj['total'];
            }
        }
    } catch (Exception $e) { $siswa_per_jurusan_admin = []; }

    try {
        $recent_guru_admin = $pdo->query("SELECT nip, nama_guru, nama_mapel FROM guru ORDER BY nip ASC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $recent_guru_admin = []; }

    try {
        $recent_siswa_admin = $pdo->query("SELECT DISTINCT s.nis, s.nama_siswa, s.kelas, j.nama_jurusan 
                                          FROM siswa s 
                                          LEFT JOIN jurusan j ON s.id_jurusan = j.id_jurusan 
                                          ORDER BY s.nis ASC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $recent_siswa_admin = []; }
}

// ================= DATA ROLE: SISWA =================
$siswa_data = null;
$total_tugas_siswa = 0;
$total_pelajaran_siswa = 0;
$total_bahan_siswa = 0;
$siswa_nilai_list = [];
$total_nilai_siswa = 0;
$avg_nilai_siswa = 0;
$total_tuntas_siswa = 0;
$total_remedial_siswa = 0;
$tugas_siswa_list = [];
$total_tugas_selesai_siswa = 0;
$bahan_siswa_list = [];
$wali_kelas_siswa = '-';

if ($nav_role === 'siswa') {
    $session_nis = $_SESSION['nis'] ?? null;
    if ($session_nis) {
        try {
            $stmt = $pdo->prepare("SELECT s.nis, s.nama_siswa, s.kelas, s.id_jurusan, j.nama_jurusan, s.nip FROM siswa s LEFT JOIN jurusan j ON s.id_jurusan = j.id_jurusan WHERE s.nis = :nis LIMIT 1");
            $stmt->execute([':nis' => $session_nis]);
            $siswa_data = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}
    }

    if (!$siswa_data) {
        try {
            $stmt = $pdo->prepare("SELECT s.nis, s.nama_siswa, s.kelas, s.id_jurusan, j.nama_jurusan, s.nip FROM siswa s LEFT JOIN jurusan j ON s.id_jurusan = j.id_jurusan WHERE LOWER(s.nama_siswa) = LOWER(:nama) LIMIT 1");
            $stmt->execute([':nama' => $user_display_name]);
            $siswa_data = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}
    }

    try { $total_tugas_siswa = (int)$pdo->query("SELECT COUNT(*) FROM tugas")->fetchColumn(); } catch (Exception $e) { $total_tugas_siswa = 0; }

    if ($siswa_data && !empty($siswa_data['id_jurusan'])) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM mapel WHERE id_jurusan = :j");
            $stmt->execute([':j' => $siswa_data['id_jurusan']]);
            $total_pelajaran_siswa = (int)$stmt->fetchColumn();
        } catch (Exception $e) { $total_pelajaran_siswa = 0; }

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM bahan_ajar WHERE id_jurusan = :j OR id_jurusan IS NULL");
            $stmt->execute([':j' => $siswa_data['id_jurusan']]);
            $total_bahan_siswa = (int)$stmt->fetchColumn();
        } catch (Exception $e) { $total_bahan_siswa = 0; }
    }

    if ($siswa_data && !empty($siswa_data['nis'])) {
        $nis_curr = $siswa_data['nis'];

        if (!empty($siswa_data['nip'])) {
            try {
                $stmtWali = $pdo->prepare("SELECT nama_guru FROM guru WHERE nip = :nip LIMIT 1");
                $stmtWali->execute([':nip' => $siswa_data['nip']]);
                $wali_kelas_siswa = $stmtWali->fetchColumn() ?: '-';
            } catch (Exception $e) {}
        }

        try {
            $stmtNilai = $pdo->prepare("SELECT s.id_mapel, m.nama_mapel, s.nilai, g.nama_guru 
                                        FROM siswa s 
                                        JOIN mapel m ON s.id_mapel = m.id_mapel 
                                        LEFT JOIN guru g ON s.nip = g.nip 
                                        WHERE s.nis = :nis 
                                        ORDER BY s.nilai DESC");
            $stmtNilai->execute([':nis' => $nis_curr]);
            $siswa_nilai_list = $stmtNilai->fetchAll(PDO::FETCH_ASSOC);

            foreach ($siswa_nilai_list as $sn) {
                $val = (int)$sn['nilai'];
                $total_nilai_siswa += $val;
                if ($val >= 75) {
                    $total_tuntas_siswa++;
                } else {
                    $total_remedial_siswa++;
                }
            }
            $count_mapel_siswa = count($siswa_nilai_list);
            if ($count_mapel_siswa > 0) {
                $avg_nilai_siswa = round($total_nilai_siswa / $count_mapel_siswa, 1);
            }
        } catch (Exception $e) {
            $siswa_nilai_list = [];
        }

        try {
            $stmtTugas = $pdo->prepare("SELECT t.id_tugas, t.judul, t.deadline, m.nama_mapel, 
                                        (SELECT status FROM pengumpulan_tugas p WHERE p.id_tugas = t.id_tugas AND p.nis = :nis LIMIT 1) as status_kumpul 
                                        FROM tugas t 
                                        LEFT JOIN mapel m ON t.id_mapel = m.id_mapel 
                                        ORDER BY t.deadline ASC LIMIT 6");
            $stmtTugas->execute([':nis' => $nis_curr]);
            $tugas_siswa_list = $stmtTugas->fetchAll(PDO::FETCH_ASSOC);
            foreach ($tugas_siswa_list as $ts) {
                if (!empty($ts['status_kumpul'])) {
                    $total_tugas_selesai_siswa++;
                }
            }
        } catch (Exception $e) {
            $tugas_siswa_list = [];
        }

        try {
            $stmtBahan = $pdo->prepare("SELECT b.id_bahan, b.judul_materi, b.file_path, b.tanggal_upload, m.nama_mapel 
                                        FROM bahan_ajar b 
                                        LEFT JOIN mapel m ON b.id_mapel = m.id_mapel 
                                        WHERE b.id_jurusan = :jurusan OR b.id_jurusan IS NULL 
                                        ORDER BY b.tanggal_upload DESC LIMIT 5");
            $stmtBahan->execute([':jurusan' => $siswa_data['id_jurusan']]);
            $bahan_siswa_list = $stmtBahan->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $bahan_siswa_list = [];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Akademik & Portal Utama</title>
    <!-- Google Fonts: Plus Jakarta Sans -->
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
            --accent-purple: #8b5cf6;
            --accent-rose: #ef4444;
            
            --bg-main: #f8fafc;
            --bg-card: #ffffff;
            --bg-subtle: #f1f5f9;
            
            --text-main: #334155;
            --text-title: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --border-light: #f1f5f9;
            
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.02);
            --shadow-card: 0 2px 10px rgba(0,0,0,0.03);
            --shadow-hover: 0 8px 20px rgba(30, 41, 59, 0.08);
            --radius-lg: 14px;
            --radius-md: 10px;
            --radius-sm: 6px;
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

        .header-accent-line {
            width: 6px;
            background-color: var(--accent);
            flex-shrink: 0;
        }

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

        .header-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

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
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-info {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }
        .user-role {
            color: var(--primary);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .user-status {
            color: var(--accent-green);
            font-size: 10px;
            font-weight: 600;
        }

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
        .navbar-menu {
            background-color: var(--primary);
            width: 100%;
            box-shadow: inset 0 -3px 0 rgba(0,0,0,0.1);
        }
        .navbar-inner {
            max-width: 1440px;
            margin: 0 auto;
            padding: 0 40px;
        }
        .navbar-menu ul {
            list-style: none;
            display: flex;
            align-items: center;
            gap: 2px;
        }
        .navbar-menu a.nav-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: rgba(255, 255, 255, 0.75);
            text-decoration: none;
            font-weight: 600;
            font-size: 12.5px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            padding: 13px 16px;
            border-bottom: 3px solid transparent;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .navbar-menu a.nav-link:hover {
            color: white;
            background-color: rgba(255, 255, 255, 0.05);
        }
        .navbar-menu a.nav-link.active {
            color: white;
            border-bottom-color: var(--accent);
            background-color: rgba(255, 255, 255, 0.05);
        }
        .navbar-menu a.nav-link.nav-logout {
            color: #fca5a5;
            margin-left: auto;
        }

        /* ===== MAIN CONTAINER ===== */
        .main-container {
            max-width: 1440px;
            margin: 26px auto 50px;
            padding: 0 40px;
            display: flex;
            flex-direction: column;
            gap: 22px;
        }

        /* ===== HERO BANNER ===== */
        .hero-banner {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border: 1px solid #334155;
            border-radius: var(--radius-lg);
            padding: 24px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: #ffffff;
            box-shadow: 0 4px 15px rgba(15, 23, 42, 0.15);
            gap: 20px;
            flex-wrap: wrap;
        }
        .hero-left h2 {
            font-size: 21px;
            font-weight: 800;
            margin-bottom: 4px;
            letter-spacing: -0.2px;
        }
        .hero-left p {
            font-size: 13px;
            color: #94a3b8;
        }
        .hero-right {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .hero-lang-box {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 5px 12px;
            border-radius: 30px;
            transition: all 0.2s;
        }
        .hero-lang-box:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.35);
        }
        .hero-lang-select {
            background: transparent;
            color: #ffffff;
            border: none;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            outline: none;
        }
        .hero-lang-select option {
            background: #1e293b;
            color: #ffffff;
        }
        .hero-badge {
            background: rgba(37, 99, 235, 0.25);
            border: 1px solid rgba(59, 130, 246, 0.4);
            color: #93c5fd;
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        /* ===== CONTENT BOX ===== */
        .content-box {
            background: var(--bg-card);
            padding: 24px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-card);
            border: 1px solid var(--border);
        }

        .section-title {
            font-size: 15px;
            font-weight: 800;
            color: var(--text-title);
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid var(--bg-subtle);
            padding-bottom: 10px;
        }

        /* ===== STATS GRID ADMIN & KPI GURU ===== */
        .stats-grid-admin {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }
        .card-admin {
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 20px;
            text-decoration: none;
            color: inherit;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .card-admin::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }
        .card-admin.siswa::before { background: var(--accent); }
        .card-admin.guru::before { background: var(--accent-green); }
        .card-admin.mapel::before { background: var(--accent-amber); }
        .card-admin.jurusan::before { background: var(--accent-purple); }

        .card-admin:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
            border-color: var(--accent);
        }
        .card-admin h3 {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .card-admin .value {
            font-size: 28px;
            font-weight: 800;
            color: var(--text-title);
            line-height: 1;
            margin-bottom: 6px;
        }
        .card-admin .sub-text {
            font-size: 11.5px;
            color: var(--accent);
            font-weight: 600;
        }

        /* KPI GURU */
        .kpi-grid-guru {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }
        .kpi-card-guru {
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 18px 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            transition: all 0.2s ease;
        }
        .kpi-card-guru::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }
        .kpi-card-guru.c-siswa::before { background: var(--accent); }
        .kpi-card-guru.c-mapel::before { background: var(--accent-purple); }
        .kpi-card-guru.c-kelas::before { background: var(--accent-green); }
        .kpi-card-guru.c-tugas::before { background: var(--accent-amber); }

        .kpi-card-guru.is-link:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
            border-color: var(--accent);
        }
        .kpi-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 6px;
        }
        .kpi-title {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .kpi-arrow {
            font-size: 14px;
            color: var(--text-muted);
        }
        .kpi-value {
            font-size: 28px;
            font-weight: 800;
            color: var(--text-title);
            line-height: 1.1;
            margin-bottom: 6px;
        }
        .kpi-sub {
            font-size: 11.5px;
            color: var(--text-muted);
            font-weight: 600;
        }

        /* ===== PIE CHART WRAPPER ===== */
        .pie-distribution-wrap {
            display: grid;
            grid-template-columns: 240px 1fr;
            gap: 24px;
            align-items: center;
        }
        .pie-legend-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .pie-legend-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 12px;
            background: var(--bg-main);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 12.5px;
        }
        .pie-legend-left {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .pie-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
        }
        .pie-legend-name { font-weight: 600; color: var(--text-main); }
        .pie-legend-value { font-weight: 800; color: var(--text-title); }
        .pie-legend-pct { color: var(--text-muted); font-size: 11.5px; font-weight: 600; }
        .pie-legend-total {
            margin-top: 4px;
            padding: 8px 12px;
            background: var(--primary);
            color: #ffffff;
            border-radius: var(--radius-sm);
            display: flex;
            justify-content: space-between;
            font-weight: 800;
            font-size: 13px;
        }

        /* ===== WIDGET GRIDS ===== */
        .widget-grid-top {
            display: grid;
            grid-template-columns: 1.2fr 1fr 1fr;
            gap: 18px;
        }
        .widget-grid-bottom {
            display: grid;
            grid-template-columns: 1.2fr 1fr 1fr;
            gap: 18px;
        }

        /* CAPSULE BAR CHART */
        .capsule-chart-container {
            display: flex;
            align-items: flex-end;
            justify-content: space-around;
            height: 160px;
            padding: 10px 0 0;
            gap: 8px;
        }
        .capsule-col {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            height: 100%;
            justify-content: flex-end;
            cursor: pointer;
        }
        .capsule-count {
            font-size: 11.5px;
            font-weight: 800;
            color: var(--text-title);
            margin-bottom: 4px;
        }
        .capsule-track {
            width: 14px;
            height: 110px;
            background: var(--bg-subtle);
            border-radius: 20px;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: flex-end;
        }
        .capsule-fill {
            width: 100%;
            background: linear-gradient(180deg, #60a5fa 0%, #2563eb 100%);
            border-radius: 20px;
            transition: height 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }
        .capsule-dot {
            width: 6px;
            height: 6px;
            background: #ffffff;
            border-radius: 50%;
            position: absolute;
            top: 4px;
            left: 50%;
            transform: translateX(-50%);
        }
        .capsule-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            margin-top: 6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 50px;
            text-align: center;
        }
        .capsule-footer {
            margin-top: 14px;
            padding-top: 10px;
            border-top: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12px;
            color: var(--text-muted);
        }

        /* SPOTLIGHT CARD */
        .spotlight-card {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            border-radius: var(--radius-lg);
            padding: 22px;
            color: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            border: 1px solid #3b82f6;
            box-shadow: 0 4px 15px rgba(30, 58, 138, 0.2);
        }
        .spotlight-tag {
            font-size: 11px;
            font-weight: 800;
            color: #93c5fd;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 6px;
        }
        .spotlight-headline {
            font-size: 17px;
            font-weight: 800;
            line-height: 1.3;
            margin-bottom: 8px;
        }
        .spotlight-desc {
            font-size: 12px;
            color: #dbeafe;
            margin-bottom: 14px;
            line-height: 1.5;
        }
        .spotlight-meta-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 14px;
        }
        .spotlight-meta-item {
            font-size: 12px;
            color: #eff6ff;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .spotlight-btn {
            background: #ffffff;
            color: #1e40af;
            padding: 9px 16px;
            border-radius: var(--radius-sm);
            font-size: 12px;
            font-weight: 800;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .spotlight-btn:hover {
            background: #f8fafc;
            transform: translateY(-1px);
        }

        /* MAPEL LIST */
        .mapel-list-wrap {
            display: flex;
            flex-direction: column;
            gap: 8px;
            max-height: 180px;
            overflow-y: auto;
        }
        .mapel-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 12px;
            background: var(--bg-main);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
        }
        .mapel-item-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .mapel-icon {
            width: 28px;
            height: 28px;
            background: #eff6ff;
            color: var(--accent);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
        }
        .mapel-title-text {
            font-size: 12.5px;
            font-weight: 700;
            color: var(--text-title);
        }
        .mapel-badge {
            font-size: 11px;
            font-weight: 700;
            color: #166534;
            background: #f0fdf4;
            padding: 2px 8px;
            border-radius: 20px;
            border: 1px solid #bbf7d0;
        }

        /* STUDENT LIST */
        .student-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            max-height: 180px;
            overflow-y: auto;
        }
        .student-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 12px;
            background: var(--bg-main);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
        }
        .student-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .student-avatar {
            width: 28px;
            height: 28px;
            background: var(--accent);
            color: #ffffff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 800;
        }
        .student-name {
            font-size: 12.5px;
            font-weight: 700;
            color: var(--text-title);
        }
        .student-nis {
            font-size: 10.5px;
            color: var(--text-muted);
        }
        .student-class-tag {
            font-size: 11px;
            font-weight: 700;
            color: #1e40af;
            background: #eff6ff;
            padding: 2px 8px;
            border-radius: 20px;
        }

        /* GAUGE PROGRESS */
        .gauge-inner {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .gauge-svg-box {
            position: relative;
            width: 170px;
            height: 95px;
        }
        .gauge-text-center {
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            text-align: center;
        }
        .gauge-val-big {
            font-size: 22px;
            font-weight: 800;
            color: var(--text-title);
            line-height: 1;
        }
        .gauge-val-label {
            font-size: 10px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
        }
        .gauge-legend {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
            font-size: 11.5px;
            color: var(--text-muted);
        }
        .gauge-legend-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }

        /* ===== TIME CARD / LIVE CLOCK WIDGET ===== */
        .time-card {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #ffffff;
            border: 1px solid #334155;
            padding: 22px;
            border-radius: var(--radius-lg);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 4px 15px rgba(15, 23, 42, 0.2);
        }
        .time-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12px;
            color: #94a3b8;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .time-live-dot {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 10.5px;
            font-weight: 700;
            background: rgba(16, 185, 129, 0.2);
            color: #34d399;
            padding: 2px 8px;
            border-radius: 20px;
        }
        .clock-display {
            font-size: 34px;
            font-weight: 800;
            letter-spacing: 1.5px;
            color: #ffffff;
            text-align: center;
            margin: 10px 0;
            font-variant-numeric: tabular-nums;
            text-shadow: 0 0 12px rgba(59, 130, 246, 0.4);
        }
        .clock-date {
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .time-actions {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .time-action-btn {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #ffffff;
            padding: 6px 12px;
            border-radius: var(--radius-sm);
            font-size: 11.5px;
            font-weight: 700;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .time-action-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        /* ===== TENTANG SEKOLAH (GRID 1:1) ===== */
        .school-profile-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; align-items: center; width: 100%; }
        .school-img-container { width: 100%; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.06); }
        .school-img-container img { width: 100%; height: auto; aspect-ratio: 16/9; object-fit: cover; display: block; }
        .school-desc p { font-size: 13.5px; color: var(--text-main); line-height: 1.7; margin-bottom: 14px; }
        .school-desc p:last-child { margin-bottom: 0; }
        .school-desc strong { color: var(--primary); font-weight: 700; }

        /* ===== PORTAL GRID BAWAH (TATA TERTIB, VISI & PENGUMUMAN) ===== */
        .portal-grid { display: grid; grid-template-columns: 1.4fr 1fr; gap: 25px; width: 100%; align-items: start; }
        
        .rules-list { list-style-type: none; width: 100%; }
        .rules-list li { 
            padding: 11px 12px; border-bottom: 1px solid var(--border); font-size: 13px; 
            display: flex; align-items: flex-start; gap: 12px; border-radius: var(--radius-sm);
        }
        .rules-list li:hover { background-color: var(--bg-subtle); }
        .rules-list li:last-child { border-bottom: none; }
        .badge-num { 
            background-color: #eff6ff; color: var(--accent); font-weight: 800; font-size: 11.5px; 
            width: 24px; height: 24px; border-radius: 6px; display: inline-flex; align-items: center; 
            justify-content: center; flex-shrink: 0; 
        }

        .vision-box { 
            background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; 
            padding: 18px; border-radius: var(--radius-md); font-size: 13.5px; line-height: 1.5; text-align: center; 
            box-shadow: 0 4px 15px rgba(30, 41, 59, 0.15); margin-bottom: 20px;
        }
        .vision-box strong { display: block; font-size: 14.5px; font-style: italic; letter-spacing: 0.3px; margin-top: 4px; }

        .announcement-card { 
            background-color: var(--bg-main); border: 1px solid var(--border); border-left: 4px solid var(--accent); 
            padding: 12px 14px; border-radius: var(--radius-sm); margin-bottom: 12px; 
        }
        .announcement-card:last-child { margin-bottom: 0; }
        .announcement-card .date { font-size: 11px; color: var(--text-muted); font-weight: 700; margin-bottom: 3px; text-transform: uppercase; }
        .announcement-card h4 { font-size: 13px; color: var(--text-title); margin-bottom: 4px; line-height: 1.3; }
        .announcement-card p { font-size: 12px; color: var(--text-main); line-height: 1.4; }

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

        @media (max-width: 1200px) {
            .stats-grid-admin { grid-template-columns: repeat(2, 1fr); }
            .kpi-grid-guru { grid-template-columns: repeat(2, 1fr); }
            .widget-grid-top { grid-template-columns: 1fr 1fr; }
            .widget-grid-bottom { grid-template-columns: 1fr 1fr; }
            .portal-grid { grid-template-columns: 1fr; }
            .school-profile-grid { grid-template-columns: 1fr; }
            .pie-distribution-wrap { grid-template-columns: 1fr; justify-items: center; text-align: center; }
            .pie-legend-list { width: 100%; max-width: 380px; }
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
                gap: 8px;
            }
            .user-badge { padding: 4px 10px 4px 4px; }
            .header-date { font-size: 11px; padding: 4px 10px; }
            .main-container { padding: 0 14px; margin: 16px auto 36px; gap: 16px; }

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

            .hero-banner {
                padding: 18px 16px;
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
            .hero-left h2 { font-size: 18px; }
            .hero-badge { font-size: 11.5px; padding: 6px 14px; }

            .stats-grid-admin { grid-template-columns: 1fr; }
            .kpi-grid-guru { grid-template-columns: 1fr; }
            .widget-grid-top { grid-template-columns: 1fr; }
            .widget-grid-bottom { grid-template-columns: 1fr; }
            .content-box { padding: 16px 14px; }
            .clock-display { font-size: 28px; }
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
                    <p data-translate="sub_header">SISTEM INFORMASI AKADEMIK</p>
                    <h1 data-translate="main_title">PORTAL UTAMA SEKOLAH</h1>
                </div>
            </div>
            
            <div class="header-right">
                <!-- User Badge -->
                <a href="profile.php" style="text-decoration: none;">
                    <div class="user-badge">
                        <div class="user-avatar">
                            <?php if (!empty($foto_user) && file_exists('uploads/' . $foto_user)): ?>
                                <img src="uploads/<?php echo htmlspecialchars($foto_user); ?>" alt="Foto">
                            <?php else: ?>
                                <?php echo strtoupper(substr($user_display_name, 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        <div class="user-info">
                            <span class="user-role"><?php echo htmlspecialchars($user_display_name); ?></span>
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
                <li><a href="index.php" class="nav-link active" data-translate="nav_home">Beranda</a></li>
                <?php if ($nav_role !== 'siswa'): ?>
                    <li><a href="siswa.php" class="nav-link" data-translate="nav_siswa">Data Siswa</a></li>
                    <li><a href="guru.php" class="nav-link" data-translate="nav_guru">Data Guru</a></li>
                    <li><a href="mapel.php" class="nav-link" data-translate="nav_mapel">Data Mapel</a></li>
                    <li><a href="jurusan.php" class="nav-link" data-translate="nav_jurusan">Data Jurusan</a></li>
                    <li><a href="bahan_ajar.php" class="nav-link" data-translate="nav_bahan_ajar">Bahan Ajar</a></li>
                <?php endif; ?>
                <li><a href="tugas.php" class="nav-link" data-translate="nav_tugas">Tugas</a></li>
                <?php if ($nav_role === 'siswa'): ?>
                    <li><a href="pelajaran.php" class="nav-link" data-translate="nav_pelajaran">Pelajaran</a></li>
                <?php endif; ?>
                <li><a href="profile.php" class="nav-link" data-translate="nav_profile">Profil Saya</a></li>
                <li><a href="logout.php" class="nav-link nav-logout" data-translate="nav_logout">Logout</a></li>
            </ul>
        </div>
    </nav>

    <!-- ===== MAIN CONTENT CONTAINER ===== -->
    <main class="main-container">
        
        <!-- ================= HERO WELCOME BANNER (SEMUA ROLE) ================= -->
        <div class="hero-banner">
            <div class="hero-left">
                <?php if ($nav_role === 'admin'): ?>
                    <h2>🛡️ Portal Administrator Sistem Akademik</h2>
                    <p>Selamat datang, <strong><?php echo htmlspecialchars($user_display_name); ?></strong>. Kelola seluruh master data siswa, guru, kurikulum, serta bahan pembelajaran.</p>
                <?php elseif ($is_guru): ?>
                    <h2>👨‍🏫 Dashboard Mengajar: <?php echo htmlspecialchars($guru_nama); ?></h2>
                    <p>NIP: <strong><?php echo htmlspecialchars($guru_nip ?: '-'); ?></strong> &bull; Rincian kelas, siswa bimbingan, serta penugasan yang Anda ampu.</p>
                <?php else: ?>
                    <h2>🎓 Portal Belajar Siswa: <?php echo htmlspecialchars($user_display_name); ?></h2>
                    <p>
                        NIS: <strong><?php echo htmlspecialchars($siswa_data['nis'] ?? '-'); ?></strong> &bull; 
                        Kelas: <strong><?php echo htmlspecialchars($siswa_data['kelas'] ?? '-'); ?></strong> &bull; 
                        Jurusan: <strong><?php echo htmlspecialchars($siswa_data['nama_jurusan'] ?? 'Reguler'); ?></strong>
                    </p>
                <?php endif; ?>
            </div>
            <div class="hero-right">
                <!-- Action Button Ganti Bahasa -->
                <div class="hero-lang-box">
                    <label for="langSelect" style="font-size: 11.5px; font-weight: 700; color: #94a3b8; display: flex; align-items: center; gap: 4px; cursor: pointer;">
                        🌐 <span data-translate="lang_label">Bahasa:</span>
                    </label>
                    <select id="langSelect" onchange="changeLanguage(this.value)" class="hero-lang-select">
                        <option value="id" selected>🇮🇩 Indonesia</option>
                        <option value="en">🇬🇧 English</option>
                        <option value="jp">🇯🇵 日本語</option>
                        <option value="kr">🇰🇷 한국어</option>
                    </select>
                </div>

                <div class="hero-badge">
                    📅 T.A. 2025/2026 Semester Ganjil
                </div>
                <div class="hero-badge" style="background: rgba(16, 185, 129, 0.25); border-color: rgba(52, 211, 153, 0.4); color: #a7f3d0;">
                    🟢 <span class="live-clock-time">00:00:00</span> WIB
                </div>
            </div>
        </div>

        <!-- ================= KHUSUS ROLE ADMIN: RINGKASAN DATA AKADEMIK ================= -->
        <?php if ($nav_role === 'admin'): ?>
        <section class="content-box">
            <h2 class="section-title"><span data-translate="sec_summary">Ringkasan Data Akademik</span></h2>
            <div class="stats-grid-admin">
                <a href="siswa.php" class="card-admin siswa">
                    <h3 data-translate="card_siswa_all">Total Siswa</h3>
                    <div class="value"><?php echo $total_siswa_admin; ?></div>
                    <span class="sub-text">Kelola Data Siswa →</span>
                </a>
                <a href="guru.php" class="card-admin guru">
                    <h3 data-translate="card_guru_all">Total Guru</h3>
                    <div class="value"><?php echo $total_guru_admin; ?></div>
                    <span class="sub-text">Kelola Data Guru →</span>
                </a>
                <a href="mapel.php" class="card-admin mapel">
                    <h3 data-translate="card_mapel_all">Mata Pelajaran</h3>
                    <div class="value"><?php echo $total_mapel_admin; ?></div>
                    <span class="sub-text">Kelola Kurikulum →</span>
                </a>
                <a href="jurusan.php" class="card-admin jurusan">
                    <h3 data-translate="card_jurusan_all">Kompetensi / Jurusan</h3>
                    <div class="value"><?php echo $total_jurusan_admin; ?></div>
                    <span class="sub-text">Kelola Jurusan →</span>
                </a>
            </div>
        </section>
        <?php endif; ?>

        <!-- ================= KHUSUS ROLE GURU: DASHBOARD MENGAJAR MODEL BARU ================= -->
        <?php if ($is_guru): ?>

        <!-- 4 KPI Cards Guru (Hanya data yang diajar) -->
        <div class="kpi-grid-guru">
            <!-- Siswa yang Diajar -->
            <a href="siswa.php" class="kpi-card-guru c-siswa is-link">
                <div class="kpi-header">
                    <span class="kpi-title" data-translate="card_siswa_guru">Siswa yang Diajar</span>
                    <div class="kpi-arrow">↗</div>
                </div>
                <div class="kpi-value"><?php echo $total_siswa_guru; ?></div>
                <div class="kpi-sub">
                    <span>🏫 Terbagi dlm <?php echo $total_kelas_guru; ?> Rombel Kelas</span>
                </div>
            </a>

            <!-- Mapel yang Diampu -->
            <a href="mapel.php" class="kpi-card-guru c-mapel is-link">
                <div class="kpi-header">
                    <span class="kpi-title" data-translate="card_mapel_guru">Mapel yang Diampu</span>
                    <div class="kpi-arrow">↗</div>
                </div>
                <div class="kpi-value"><?php echo $total_mapel_guru; ?></div>
                <div class="kpi-sub">
                    <span>📚 Mata Pelajaran Aktif</span>
                </div>
            </a>

            <!-- Kelas yang Diajar -->
            <div class="kpi-card-guru c-kelas">
                <div class="kpi-header">
                    <span class="kpi-title" data-translate="card_kelas_guru">Kelas yang Diajar</span>
                </div>
                <div class="kpi-value"><?php echo $total_kelas_guru; ?></div>
                <div class="kpi-sub">
                    <span>👥 Rombongan Belajar</span>
                </div>
            </div>

            <!-- Tugas & Bahan Ajar Guru -->
            <a href="tugas.php" class="kpi-card-guru c-tugas is-link">
                <div class="kpi-header">
                    <span class="kpi-title" data-translate="card_tugas_guru">Tugas & Bahan Ajar</span>
                    <div class="kpi-arrow">↗</div>
                </div>
                <div class="kpi-value"><?php echo $total_tugas_guru; ?></div>
                <div class="kpi-sub">
                    <span>📁 <?php echo $total_bahan_guru; ?> Modul Materi Terupload</span>
                </div>
            </a>
        </div>

        <!-- Widget Grid Top: Grafik Kapsul + Spotlight + Mapel Diajar -->
        <div class="widget-grid-top">
            
            <!-- Bar Capsule Chart: Murid per Kelas yang Diajar -->
            <div class="content-box" style="margin-bottom: 0;">
                <div class="section-title">
                    <span>📊 <span data-translate="chart_title_guru">Siswa per Kelas yang Diajar</span></span>
                    <span style="font-size: 11.5px; font-weight: 700; background: var(--bg-subtle); padding: 3px 9px; border-radius: 20px; color: var(--text-muted);">
                        <?php echo count($siswa_per_kelas_guru); ?> Kelas
                    </span>
                </div>

                <div class="capsule-chart-container">
                    <?php if (!empty($siswa_per_kelas_guru)): ?>
                        <?php foreach ($siswa_per_kelas_guru as $sk): ?>
                            <?php 
                                $count = (int)$sk['total'];
                                $pct = max(25, min(95, round(($count / max($max_siswa_kelas, 1)) * 95)));
                            ?>
                            <div class="capsule-col" title="<?php echo htmlspecialchars($sk['kelas']); ?>: <?php echo $count; ?> Siswa">
                                <span class="capsule-count"><?php echo $count; ?></span>
                                <div class="capsule-track">
                                    <div class="capsule-fill" style="height: <?php echo $pct; ?>%;">
                                        <div class="capsule-dot"></div>
                                    </div>
                                </div>
                                <span class="capsule-label"><?php echo htmlspecialchars($sk['kelas']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="font-size: 12px; color: var(--text-muted); font-style: italic; margin: auto;">Belum ada siswa di kelas yang diajar.</div>
                    <?php endif; ?>
                </div>

                <div class="capsule-footer">
                    <span>👥 Total: <strong><?php echo $total_siswa_guru; ?> Siswa</strong></span>
                    <a href="siswa.php" style="color: var(--accent); font-weight: 700; text-decoration: none;">Kelola Siswa →</a>
                </div>
            </div>

            <!-- Spotlight Pembelajaran -->
            <div class="spotlight-card">
                <div>
                    <div class="spotlight-tag" data-translate="spotlight_tag">Agenda Pembelajaran Terkini</div>
                    <div class="spotlight-headline" data-translate="spotlight_headline">Penugasan & Modul Belajar</div>
                    <div class="spotlight-desc" data-translate="spotlight_desc">Pantau progres pengumpulan tugas harian dan modul bahan ajar siswa di seluruh kelas.</div>
                    
                    <div class="spotlight-meta-list">
                        <div class="spotlight-meta-item">
                            <span>📌 <strong>Tugas Dibuat:</strong> <?php echo $total_tugas_guru; ?> Tugas</span>
                        </div>
                        <div class="spotlight-meta-item">
                            <span>📥 <strong>Pengumpulan Siswa:</strong> <?php echo $total_pengumpulan_guru; ?> Berkas</span>
                        </div>
                        <div class="spotlight-meta-item">
                            <span>🕒 <strong>Status Sesi:</strong> Aktif Berjalan</span>
                        </div>
                    </div>
                </div>

                <a href="tugas.php" class="spotlight-btn" data-translate="btn_tugas_portal">
                    🚀 Buka Portal Tugas & Nilai
                </a>
            </div>

            <!-- Mata Pelajaran yang Diajar -->
            <div class="content-box" style="margin-bottom: 0;">
                <div class="section-title">
                    <span>📚 <span data-translate="sec_mapel_guru">Mata Pelajaran yang Diajar</span></span>
                    <a href="mapel.php" style="font-size: 11.5px; font-weight: 700; color: var(--accent); text-decoration: none;">Rincian</a>
                </div>

                <div class="mapel-list-wrap">
                    <?php if (!empty($mapel_list_guru)): ?>
                        <?php foreach ($mapel_list_guru as $mp): ?>
                            <div class="mapel-item">
                                <div class="mapel-item-left">
                                    <div class="mapel-icon">📖</div>
                                    <div class="mapel-title-text"><?php echo htmlspecialchars($mp['nama_mapel']); ?></div>
                                </div>
                                <span class="mapel-badge"><?php echo htmlspecialchars($mp['id_mapel'] ?? 'Aktif'); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="font-size: 12px; color: var(--text-muted); font-style: italic;">Belum ada mata pelajaran.</div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- Widget Grid Bottom: Siswa Diajar + Gauge + Live Clock -->
        <div class="widget-grid-bottom">
            
            <!-- Daftar Siswa yang Diajar -->
            <div class="content-box" style="margin-bottom: 0;">
                <div class="section-title">
                    <span>👥 <span data-translate="sec_student_guru">Daftar Siswa yang Diajar</span></span>
                    <a href="siswa.php" style="font-size: 11.5px; font-weight: 700; color: var(--accent); text-decoration: none;">+ Semua Siswa</a>
                </div>

                <div class="student-list">
                    <?php if (!empty($list_siswa_guru)): ?>
                        <?php foreach ($list_siswa_guru as $st): ?>
                            <div class="student-item">
                                <div class="student-left">
                                    <div class="student-avatar">
                                        <?php echo strtoupper(substr($st['nama_siswa'], 0, 2)); ?>
                                    </div>
                                    <div>
                                        <div class="student-name"><?php echo htmlspecialchars($st['nama_siswa']); ?></div>
                                        <div class="student-nis">NIS: <?php echo htmlspecialchars($st['nis']); ?></div>
                                    </div>
                                </div>
                                <span class="student-class-tag"><?php echo htmlspecialchars($st['kelas'] ?? 'Umum'); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="font-size: 12px; color: var(--text-muted); font-style: italic;">Belum ada siswa di kelas yang diajar.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Semicircle Progress Gauge -->
            <div class="content-box" style="margin-bottom: 0;">
                <div class="section-title">
                    <span>🎯 <span data-translate="gauge_title">Kapasitas & Distribusi Kelas</span></span>
                    <span style="font-size: 11.5px; font-weight: 700; background: var(--bg-subtle); padding: 3px 8px; border-radius: 20px;">Semester Ganjil</span>
                </div>

                <div class="gauge-inner">
                    <?php 
                        $ratio_pct = $total_kelas_guru > 0 ? round(($total_siswa_guru / ($total_kelas_guru * 10)) * 100) : 80;
                        $ratio_pct = max(15, min(100, $ratio_pct));
                        $arcLen = 251.2;
                        $filled_pct = ($arcLen * $ratio_pct) / 100;
                    ?>
                    <div class="gauge-svg-box">
                        <svg viewBox="0 0 200 115" style="width: 100%; height: 100%;">
                            <path d="M 20 100 A 80 80 0 0 1 180 100" fill="none" stroke="#e2e8f0" stroke-width="18" stroke-linecap="round"/>
                            <path d="M 20 100 A 80 80 0 0 1 180 100" fill="none" stroke="url(#blueGaugeGrad)" stroke-width="18" stroke-linecap="round"
                                  stroke-dasharray="<?php echo $filled_pct; ?> 999"/>
                            <defs>
                                <linearGradient id="blueGaugeGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                                    <stop offset="0%" stop-color="#3b82f6" />
                                    <stop offset="100%" stop-color="#1e40af" />
                                </linearGradient>
                            </defs>
                        </svg>
                        <div class="gauge-text-center">
                            <div class="gauge-val-big"><?php echo $ratio_pct; ?>%</div>
                            <div class="gauge-val-label">Kapasitas Kelas</div>
                        </div>
                    </div>

                    <div class="gauge-legend">
                        <div><span class="gauge-legend-dot" style="background: #3b82f6;"></span> Siswa (<?php echo $total_siswa_guru; ?>)</div>
                        <div><span class="gauge-legend-dot" style="background: #10b981;"></span> Kelas (<?php echo $total_kelas_guru; ?>)</div>
                        <div><span class="gauge-legend-dot" style="background: #f59e0b;"></span> Mapel (<?php echo $total_mapel_guru; ?>)</div>
                    </div>
                </div>
            </div>

            <!-- Time Tracker Guru -->
            <div class="time-card">
                <div class="time-header">
                    <span data-translate="clock_title">Waktu & Sesi Mengajar</span>
                    <span class="time-live-dot">🟢 Live WIB</span>
                </div>

                <div>
                    <div class="clock-display live-clock-time" id="liveDigitalClock">00:00:00</div>
                    <div class="clock-date live-clock-date" id="liveDigitalDate"><?php echo date('l, d F Y'); ?></div>
                </div>

                <div class="time-actions">
                    <span class="time-action-btn">🟢 Server Online</span>
                    <a href="bahan_ajar.php" class="time-action-btn" style="background: rgba(37, 99, 235, 0.4); border-color: rgba(37, 99, 235, 0.6);">
                        📚 Bahan Ajar
                    </a>
                </div>
            </div>

        </div>

        <?php endif; ?>

        <!-- ================= KHUSUS ROLE SISWA: MENU BELAJAR ================= -->
        <?php if ($nav_role === 'siswa'): ?>
        <section class="content-box">
            <h2 class="section-title">🚀 Menu Belajar Siswa</h2>
            <div class="stats-grid-admin">
                <a href="tugas.php" class="card-admin siswa">
                    <div>
                        <h3>Tugas & Pratikum</h3>
                        <div class="value"><?php echo $total_tugas_siswa; ?></div>
                    </div>
                    <span class="sub-text">Kumpulkan Tugas PDF →</span>
                </a>
                <a href="pelajaran.php" class="card-admin mapel">
                    <div>
                        <h3>Mata Pelajaran</h3>
                        <div class="value"><?php echo $total_pelajaran_siswa; ?></div>
                    </div>
                    <span class="sub-text">Buka Modul Belajar →</span>
                </a>
                <a href="pelajaran.php" class="card-admin jurusan">
                    <div>
                        <h3>Modul Bahan Ajar</h3>
                        <div class="value"><?php echo $total_bahan_siswa; ?></div>
                    </div>
                    <span class="sub-text">Baca & Unduh PDF →</span>
                </a>
                <a href="profile.php" class="card-admin guru">
                    <div>
                        <h3>Profil Siswa</h3>
                        <div class="value">NIS: <?php echo htmlspecialchars($siswa_data['nis'] ?? '-'); ?></div>
                    </div>
                    <span class="sub-text">Edit Profil & Sandi →</span>
                </a>
            </div>
        </section>
        <?php endif; ?>

        <!-- ===== PIE CHART DISTRIBUSI ANGGOTA SEKOLAH (KHUSUS GURU) ===== -->
        <?php if ($is_guru): ?>
        <div class="content-box">
            <div class="section-title">
                <span>📊 <span data-translate="pie_title">Distribusi Anggota Sekolah</span></span>
                <span style="font-size: 12px; font-weight: 600; color: var(--text-muted);">Total: <?php echo $total_global; ?> Jiwa</span>
            </div>

            <div class="pie-distribution-wrap">
                <!-- SVG PIE CHART -->
                <div style="position: relative; width: 200px; height: 200px; margin: 0 auto;">
                    <svg viewBox="0 0 220 220" style="width: 100%; height: 100%; transform: rotate(-90deg);">
                        <?php
                        $colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444'];
                        $categories = [
                            ['label' => 'Murid (Siswa)', 'value' => $total_siswa_global, 'pct' => $persen_siswa, 'data-translate' => 'card_murid'],
                            ['label' => 'Guru', 'value' => $total_guru_global, 'pct' => $persen_guru, 'data-translate' => 'card_guru'],
                            ['label' => 'Staf & TU', 'value' => $total_staf_global, 'pct' => $persen_staf, 'data-translate' => 'card_staf'],
                            ['label' => 'Lainnya', 'value' => $total_lainnya_global, 'pct' => $persen_lain, 'data-translate' => 'card_lainnya'],
                        ];
                        $start = 0;
                        foreach ($categories as $i => $cat) {
                            $angle = ($cat['pct'] / 100) * 360;
                            $end = $start + $angle;
                            if ($angle > 0) {
                                $x1 = 110 + 90 * cos(deg2rad($start));
                                $y1 = 110 + 90 * sin(deg2rad($start));
                                $x2 = 110 + 90 * cos(deg2rad($end));
                                $y2 = 110 + 90 * sin(deg2rad($end));
                                $largeArc = ($angle > 180) ? 1 : 0;
                                echo "<path d=\"M 110,110 L $x1,$y1 A 90,90 0 $largeArc,1 $x2,$y2 Z\" fill=\"{$colors[$i]}\" stroke=\"#fff\" stroke-width=\"3\" />";
                            }
                            $start = $end;
                        }
                        ?>
                    </svg>
                </div>

                <!-- Legenda & Detail -->
                <div class="pie-legend-list">
                    <?php foreach ($categories as $i => $cat): ?>
                        <?php if ($cat['value'] > 0): ?>
                            <div class="pie-legend-row">
                                <div class="pie-legend-left">
                                    <span class="pie-dot" style="background: <?php echo $colors[$i]; ?>;"></span>
                                    <span class="pie-legend-name"><?php echo $cat['label']; ?></span>
                                </div>
                                <div>
                                    <span class="pie-legend-value"><?php echo $cat['value']; ?></span>
                                    <span class="pie-legend-pct">(<?php echo $cat['pct']; ?>%)</span>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <div class="pie-legend-total">
                        <span>Total Anggota Terdata</span>
                        <span><?php echo $total_global; ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ================= INFORMASI SEKOLAH (UNTUK SEMUA ROLE) ================= -->
        
        <!-- Tentang Lingkungan Sekolah -->
        <section class="content-box">
            <h2 class="section-title" data-translate="sec_about">Tentang Lingkungan Sekolah</h2>
            <div class="school-profile-grid">
                <div class="school-img-container">
                    <img src="image/Sekolah.jpg" alt="Gedung Sekolah">
                </div>
                <div class="school-desc">
                    <p data-translate="school_desc_1"><strong>Sistem Informasi Akademik Sekolah</strong> berdedikasi tinggi dalam mencetak generasi muda yang unggul, berdaya saing global, serta memiliki penguasaan teknologi yang matang di era digital modern saat ini.</p>
                    <p data-translate="school_desc_2">Didukung oleh tenaga pendidik profesional, fasilitas laboratorium dan ruang kelas yang memadai, serta lingkungan belajar yang kondusif, sekolah kami terus berkomitmen memberikan layanan pendidikan terbaik serta kemudahan akses administrasi melalui sistem terpadu ini.</p>
                </div>
            </div>
        </section>

        <!-- Tata Tertib, Visi, & Pengumuman -->
        <div class="portal-grid">
            
            <!-- Tata Tertib & Peraturan Sekolah -->
            <section class="content-box" style="margin-bottom: 0;">
                <div class="section-title">
                    <span data-translate="sec_rules">Tata Tertib & Peraturan Sekolah</span>
                    <span style="font-size: 11.5px; font-weight: 600; color: var(--text-muted); background: var(--bg-main); padding: 4px 10px; border-radius: 20px;">T.A. 2025/2026</span>
                </div>
                <ul class="rules-list">
                    <li>
                        <span class="badge-num">1</span>
                        <div><strong data-translate="rule_1_title">Kehadiran & Jam Masuk:</strong> <span data-translate="rule_1_desc">Siswa dan Tenaga Pengajar wajib hadir sebelum pukul 07.00 WIB. Gerbang sekolah ditutup tepat pada pukul 07.00 WIB.</span></div>
                    </li>
                    <li>
                        <span class="badge-num">2</span>
                        <div><strong data-translate="rule_2_title">Seragam Sekolah:</strong> <span data-translate="rule_2_desc">Seluruh siswa wajib mengenakan seragam rapi dan lengkap dengan atribut sesuai dengan ketentuan jadwal hari yang berlaku.</span></div>
                    </li>
                    <li>
                        <span class="badge-num">3</span>
                        <div><strong data-translate="rule_3_title">Kedisiplinan & Etika:</strong> <span data-translate="rule_3_desc">Seluruh warga sekolah wajib saling menghormati, menjaga kebersihan lingkungan sekolah, serta melarang keras segala bentuk perundungan (bullying).</span></div>
                    </li>
                    <li>
                        <span class="badge-num">4</span>
                        <div><strong data-translate="rule_4_title">Penggunaan Alat Elektronik:</strong> <span data-translate="rule_4_desc">HP/Gawai hanya digunakan untuk kepentingan pembelajaran atas izin guru mata pelajaran yang bersangkutan.</span></div>
                    </li>
                    <li>
                        <span class="badge-num">5</span>
                        <div><strong data-translate="rule_5_title">Administrasi & Data:</strong> <span data-translate="rule_5_desc">Perubahan data diri siswa dan guru wajib dilaporkan melalui tata usaha / sistem pendataan paling lambat akhir semester.</span></div>
                    </li>
                </ul>
            </section>

            <!-- Visi & Pengumuman -->
            <div style="display: flex; flex-direction: column; gap: 20px;">
                
                <!-- Visi Sekolah -->
                <div class="vision-box">
                    <span data-translate="vision_title" style="font-weight: 800; text-transform: uppercase; font-size: 11.5px; letter-spacing: 0.5px; opacity: 0.85;">Visi Sekolah</span>
                    <strong data-translate="vision_desc">"Terwujudnya Insan Berakhlak Mulia, Unggul dalam IPTEK, Berwawasan Lingkungan, serta Mampu Berkompetisi di Tingkat Global."</strong>
                </div>

                <!-- Pengumuman Terkini -->
                <section class="content-box" style="margin-bottom: 0;">
                    <div class="section-title">
                        <span data-translate="sec_announcement">Pengumuman Terkini</span>
                        <span style="font-size: 11.5px; font-weight: 700; color: var(--accent);">Penting</span>
                    </div>

                    <div class="announcement-card">
                        <div class="date">📅 28 Agustus 2026</div>
                        <h4 data-translate="announcement_1_title">Jadwal Penilaian Tengah Semester (PTS)</h4>
                        <p data-translate="announcement_1_desc">Diberitahukan kepada seluruh siswa bahwa PTS akan diselenggarakan pekan depan. Pastikan data mata pelajaran Anda telah lengkap.</p>
                    </div>

                    <div class="announcement-card">
                        <div class="date">📅 25 Agustus 2026</div>
                        <h4 data-translate="announcement_2_title">Pembaruan Data Pokok Guru & Siswa</h4>
                        <p data-translate="announcement_2_desc">Pengisian nilai dan penugasan telah dibuka. Mohon periksa kembali kesesuaian data Anda.</p>
                    </div>
                </section>

            </div>

        </div>

    </main>

    <!-- ===== JAVASCRIPT: LIVE CLOCK, SIDEBAR DRAWER, MULTILANGUAGE ===== -->
    <script>
        // Realtime Live Clock
        function updateLiveClock() {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            const timeStr = `${hours}:${minutes}:${seconds}`;

            document.querySelectorAll('.live-clock-time, #liveDigitalClock, .header-live-clock').forEach(el => {
                el.innerText = timeStr;
            });

            const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
            const dateStr = `${days[now.getDay()]}, ${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()}`;

            document.querySelectorAll('.live-clock-date, #liveDigitalDate').forEach(el => {
                el.innerText = dateStr;
            });
        }

        // Mobile Sidebar Drawer
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
        };

        // Kamus Bahasa (Multilanguage)
        const translations = {
            id: {
                main_title: "PORTAL UTAMA SEKOLAH",
                sub_header: "SISTEM INFORMASI AKADEMIK",
                lang_label: "Bahasa:",
                nav_home: "Beranda",
                nav_siswa: "Data Siswa",
                nav_guru: "Data Guru",
                nav_mapel: "Data Mapel",
                nav_jurusan: "Data Jurusan",
                nav_bahan_ajar: "Bahan Ajar",
                nav_tugas: "Tugas",
                nav_pelajaran: "Pelajaran",
                nav_profile: "Profil Saya",
                nav_logout: "Logout",
                sec_summary: "Ringkasan Data Akademik",
                pie_title: "Distribusi Anggota Sekolah",
                card_siswa_all: "Total Siswa",
                card_guru_all: "Total Guru",
                card_mapel_all: "Mata Pelajaran",
                card_jurusan_all: "Kompetensi / Jurusan",
                card_siswa_guru: "Siswa yang Diajar",
                card_mapel_guru: "Mapel yang Diampu",
                card_kelas_guru: "Kelas yang Diajar",
                card_tugas_guru: "Tugas & Bahan Ajar",
                chart_title_guru: "Siswa per Kelas yang Diajar",
                spotlight_tag: "Agenda Pembelajaran Terkini",
                spotlight_headline: "Penugasan & Modul Belajar",
                spotlight_desc: "Pantau progres pengumpulan tugas harian dan modul bahan ajar siswa di seluruh kelas.",
                btn_tugas_portal: "🚀 Buka Portal Tugas & Nilai",
                sec_mapel_guru: "Mata Pelajaran yang Diajar",
                sec_student_guru: "Daftar Siswa yang Diajar",
                gauge_title: "Kapasitas & Distribusi Kelas",
                clock_title: "Waktu & Sesi Mengajar",
                sec_about: "Tentang Lingkungan Sekolah",
                school_desc_1: "Sistem Informasi Akademik Sekolah berdedikasi tinggi dalam mencetak generasi muda yang unggul, berdaya saing global, serta memiliki penguasaan teknologi yang matang di era digital modern saat ini.",
                school_desc_2: "Didukung oleh tenaga pendidik profesional, fasilitas laboratorium dan ruang kelas yang memadai, serta lingkungan belajar yang kondusif, sekolah kami terus berkomitmen memberikan layanan pendidikan terbaik serta kemudahan akses administrasi melalui sistem terpadu ini.",
                sec_rules: "Tata Tertib & Peraturan Sekolah",
                rule_1_title: "Kehadiran & Jam Masuk:",
                rule_1_desc: "Siswa dan Tenaga Pengajar wajib hadir sebelum pukul 07.00 WIB. Gerbang sekolah ditutup tepat pada pukul 07.00 WIB.",
                rule_2_title: "Seragam Sekolah:",
                rule_2_desc: "Seluruh siswa wajib mengenakan seragam rapi dan lengkap dengan atribut sesuai dengan ketentuan jadwal hari yang berlaku.",
                rule_3_title: "Kedisiplinan & Etika:",
                rule_3_desc: "Seluruh warga sekolah wajib saling menghormati, menjaga kebersihan lingkungan sekolah, serta melarang keras segala bentuk perundungan (bullying).",
                rule_4_title: "Penggunaan Alat Elektronik:",
                rule_4_desc: "HP/Gawai hanya digunakan untuk kepentingan pembelajaran atas izin guru mata pelajaran yang bersangkutan.",
                rule_5_title: "Administrasi & Data:",
                rule_5_desc: "Perubahan data diri siswa dan guru wajib dilaporkan melalui tata usaha / sistem pendataan paling lambat akhir semester.",
                vision_title: "Visi Sekolah",
                vision_desc: "\"Terwujudnya Insan Berakhlak Mulia, Unggul dalam IPTEK, Berwawasan Lingkungan, serta Mampu Berkompetisi di Tingkat Global.\"",
                sec_announcement: "Pengumuman Terkini",
                announcement_1_title: "Jadwal Penilaian Tengah Semester (PTS)",
                announcement_1_desc: "Diberitahukan kepada seluruh siswa bahwa PTS akan diselenggarakan pekan depan. Pastikan data mata pelajaran Anda telah lengkap.",
                announcement_2_title: "Pembaruan Data Pokok Guru & Siswa",
                announcement_2_desc: "Pengisian nilai dan penugasan telah dibuka. Mohon periksa kembali kesesuaian data Anda.",
                kpi_tugas_siswa: "Tugas & Praktikum",
                kpi_mapel_siswa: "Mata Pelajaran",
                kpi_bahan_siswa: "Modul Bahan Ajar",
                kpi_nilai_siswa: "Rata-rata Nilai",
                chart_title_siswa: "Capaian Nilai per Mata Pelajaran",
                spotlight_tag_siswa: "Identitas & Agenda Siswa",
                btn_pelajaran_portal: "🚀 Buka Modul & Materi Belajar",
                sec_bahan_siswa: "Modul Bahan Ajar Terbaru",
                sec_nilai_siswa: "Nilai Akademik Semester",
                gauge_title_siswa: "Target Kelulusan & KKM",
                clock_title_siswa: "Waktu & Sesi Belajar",
                chart_title_admin: "Distribusi Siswa per Jurusan",
                spotlight_tag_admin: "Administrator Sistem",
                btn_admin_portal: "🚀 Buka Manajemen Master Data",
                sec_guru_admin: "Tenaga Pendidik Terbaru",
                sec_siswa_admin: "Siswa Terdaftar",
                gauge_title_admin: "Rasio & Kapasitas Sekolah",
                clock_title_admin: "Waktu Sistem & Server"
            },
            en: {
                main_title: "SCHOOL MAIN PORTAL",
                sub_header: "ACADEMIC INFORMATION SYSTEM",
                lang_label: "Language:",
                nav_home: "Home",
                nav_siswa: "Students Data",
                nav_guru: "Teachers Data",
                nav_mapel: "Subjects Data",
                nav_jurusan: "Departments",
                nav_bahan_ajar: "Learning Materials",
                nav_tugas: "Assignments",
                nav_pelajaran: "Lessons",
                nav_profile: "My Profile",
                nav_logout: "Logout",
                sec_summary: "Academic Data Summary",
                pie_title: "School Members Distribution",
                card_siswa_all: "Total Students",
                card_guru_all: "Total Teachers",
                card_mapel_all: "Subjects",
                card_jurusan_all: "Majors / Departments",
                card_siswa_guru: "Taught Students",
                card_mapel_guru: "Taught Subjects",
                card_kelas_guru: "Taught Classes",
                card_tugas_guru: "Tasks & Materials",
                chart_title_guru: "Students per Taught Class",
                spotlight_tag: "Latest Learning Agenda",
                spotlight_headline: "Assignments & Learning Modules",
                spotlight_desc: "Monitor daily submission progress and learning materials across all classes.",
                btn_tugas_portal: "🚀 Open Task Portal",
                sec_mapel_guru: "Taught Subjects",
                sec_student_guru: "List of Taught Students",
                gauge_title: "Class Capacity & Ratio",
                clock_title: "Teaching Session Time",
                sec_about: "About School Environment",
                school_desc_1: "The School Academic Information System is committed to developing outstanding, globally competitive generations with mastery in modern digital technology.",
                school_desc_2: "Supported by professional educators, well-equipped labs, and a conducive environment, our school provides premium education services.",
                sec_rules: "School Rules & Regulations",
                rule_1_title: "Attendance & Entry Time:",
                rule_1_desc: "Students and teachers must arrive before 07.00 AM (WIB). School gates close strictly at 07.00 AM.",
                rule_2_title: "School Uniform:",
                rule_2_desc: "All students must wear clean and complete uniforms according to the daily schedule rules.",
                rule_3_title: "Discipline & Ethics:",
                rule_3_desc: "All school members must respect each other and maintain cleanliness; bullying is strictly prohibited.",
                rule_4_title: "Electronic Devices:",
                rule_4_desc: "Phones may only be used for educational purposes with permission from the respective subject teacher.",
                rule_5_title: "Administration & Data:",
                rule_5_desc: "Changes in personal records must be reported through administration before the semester ends.",
                vision_title: "School Vision",
                vision_desc: "\"To shape individuals with noble character, excellence in science & technology, and global competence.\"",
                sec_announcement: "Latest Announcements",
                announcement_1_title: "Mid-Semester Assessment Schedule",
                announcement_1_desc: "Students are notified that mid-term exams begin next week. Ensure all course data is up to date.",
                announcement_2_title: "Master Data Updates for Teachers & Students",
                announcement_2_desc: "Grading and task submissions are now open. Please review your profile data.",
                kpi_tugas_siswa: "Tasks & Practicum",
                kpi_mapel_siswa: "Subjects",
                kpi_bahan_siswa: "Learning Modules",
                kpi_nilai_siswa: "Average Grade",
                chart_title_siswa: "Subject Grade Achievement",
                spotlight_tag_siswa: "Student Profile & Agenda",
                btn_pelajaran_portal: "🚀 Open Modules & Lessons",
                sec_bahan_siswa: "Latest Learning Modules",
                sec_nilai_siswa: "Semester Academic Grades",
                gauge_title_siswa: "Graduation Target & Passing Grade",
                clock_title_siswa: "Study Session Time",
                chart_title_admin: "Students by Department",
                spotlight_tag_admin: "System Administrator",
                btn_admin_portal: "🚀 Open Master Data Management",
                sec_guru_admin: "Teachers Directory",
                sec_siswa_admin: "Enrolled Students",
                gauge_title_admin: "School Ratio & Capacity",
                clock_title_admin: "Server & System Time"
            },
            jp: {
                main_title: "学校メインポータル",
                sub_header: "学術情報管理システム",
                lang_label: "言語:",
                nav_home: "ホーム",
                nav_siswa: "生徒データ",
                nav_guru: "教師データ",
                nav_mapel: "科目データ",
                nav_jurusan: "学科データ",
                nav_bahan_ajar: "教材",
                nav_tugas: "課題",
                nav_pelajaran: "授業",
                nav_profile: "マイプロフィール",
                nav_logout: "ログアウト",
                sec_summary: "学術データ概要",
                pie_title: "学校構成員の分布",
                card_siswa_all: "全生徒数",
                card_guru_all: "全教師数",
                card_mapel_all: "科目数",
                card_jurusan_all: "学科・専攻",
                card_siswa_guru: "担当生徒数",
                card_mapel_guru: "担当科目数",
                card_kelas_guru: "担当クラス数",
                card_tugas_guru: "課題・教材数",
                chart_title_guru: "担当クラス別生徒数",
                spotlight_tag: "最新の学習予定",
                spotlight_headline: "課題進行状況と学習モジュール",
                spotlight_desc: "全クラスの日々の課題提出状況と教材の進捗を管理します。",
                btn_tugas_portal: "🚀 課題ポータルを開く",
                sec_mapel_guru: "担当科目一覧",
                sec_student_guru: "担当生徒名簿",
                gauge_title: "クラス収容率と配分",
                clock_title: "授業セッション時間",
                sec_about: "学校環境について",
                school_desc_1: "学校学術情報システムは、現代のデジタル時代において優秀で国際競争力のある人材の育成に全力で取り組んでいます。",
                school_desc_2: "プロフェッショナルな教員陣、充実した実験室や教室施設を備え、最高の教育サービスを提供し続けます。",
                sec_rules: "校則および規定",
                rule_1_title: "登校時間と出席:",
                rule_1_desc: "生徒および教職員は午前7時00分(WIB)までに登校してください。校門は7時00分ちょうどに閉まります。",
                rule_2_title: "制服の着用:",
                rule_2_desc: "指定された規定に従い、正しく整った制服を着用してください。",
                rule_3_title: "規律とマナー:",
                rule_3_desc: "互いに尊重し合い、清潔を保ち、いじめ行為は一切禁止します。",
                rule_4_title: "電子機器の使用:",
                rule_4_desc: "スマートフォン等は担当教員の許可がある学習目的のみ使用できます。",
                rule_5_title: "事務手続き・データ:",
                rule_5_desc: "登録情報の変更は学期末までに事務室へ届け出てください。",
                vision_title: "学校の理念 (ビジョン)",
                vision_desc: "「高潔な品格、優れた科学技術力、そして国際的な競争力を兼ね備えた人材の育成。」",
                sec_announcement: "最新のお知らせ",
                announcement_1_title: "中間試験(PTS)日程のお知らせ",
                announcement_1_desc: "来週より中間試験を実施いたします。履修科目の登録確認を行ってください。",
                announcement_2_title: "教員・生徒基本データ更新",
                announcement_2_desc: "生徒および教師データの入力・編集受付を開始しました。",
                kpi_tugas_siswa: "課題・実習",
                kpi_mapel_siswa: "履修科目",
                kpi_bahan_siswa: "学習教材モジュール",
                kpi_nilai_siswa: "平均成績",
                chart_title_siswa: "科目別成績達成度",
                spotlight_tag_siswa: "生徒情報と学習予定",
                btn_pelajaran_portal: "🚀 教材・授業を開く",
                sec_bahan_siswa: "最新の学習教材",
                sec_nilai_siswa: "学期成績一覧",
                gauge_title_siswa: "卒業目標と合格基準",
                clock_title_siswa: "学習セッション時間",
                chart_title_admin: "学科別生徒分布",
                spotlight_tag_admin: "システム管理者",
                btn_admin_portal: "🚀 マスターデータ管理を開く",
                sec_guru_admin: "教師名簿",
                sec_siswa_admin: "登録生徒",
                gauge_title_admin: "学校収容率と比率",
                clock_title_admin: "サーバー・システム時間"
            },
            kr: {
                main_title: "학교 메인 포털",
                sub_header: "학술 정보 시스템",
                lang_label: "언어:",
                nav_home: "홈",
                nav_siswa: "학생 데이터",
                nav_guru: "교사 데이터",
                nav_mapel: "과목 데이터",
                nav_jurusan: "학과 데이터",
                nav_bahan_ajar: "교육 자료",
                nav_tugas: "과제",
                nav_pelajaran: "수업",
                nav_profile: "내 프로필",
                nav_logout: "로그아웃",
                sec_summary: "학업 데이터 요약",
                pie_title: "학교 구성원 분포",    
                card_siswa_all: "총 학생 수",
                card_guru_all: "총 교사 수",
                card_mapel_all: "과목 수",
                card_jurusan_all: "전공/계열",
                card_siswa_guru: "담당 학생 수",
                card_mapel_guru: "담당 과목 수",
                card_kelas_guru: "담당 학급 수",
                card_tugas_guru: "과제 및 교육 자료",
                chart_title_guru: "담당 학급별 학생 수",
                spotlight_tag: "주요 학습 일정",
                spotlight_headline: "과제 진행 및 학습 모듈",
                spotlight_desc: "전체 학급의 일일 과제 제출 현황과 교육 자료를 모니터링합니다.",
                btn_tugas_portal: "🚀 과제 및 성적 포털 열기",
                sec_mapel_guru: "담당 과목",
                sec_student_guru: "담당 학생 명단",
                gauge_title: "학급 수용 및 분포",
                clock_title: "수업 세션 시간",
                sec_about: "학교 환경 소개",
                school_desc_1: "학교 학술 정보 시스템은 현대 디지털 시대에 발맞추어 뛰어난 역량과 글로벌 경쟁력을 갖추고 기술을 능숙하게 다루는 인재 양성에 헌신하고 있습니다.",
                school_desc_2: "전문 교직원, 충분한 실험실 및 교실 시설, 그리고 최적의 학습 환경을 바탕으로 통합 시스템을 통해 최고의 교육 서비스와 편리한 행정 접근성을 제공하기 위해 지속적으로 노력하고 있습니다.",
                sec_rules: "학교 규칙 및 규정",
                rule_1_title: "출석 및 입실 시간:",
                rule_1_desc: "학생 및 교직원은 오전 7시 00분(WIB) 이전까지 입실해야 합니다. 학교 정문은 오전 7시 00분에 정각에 폐쇄됩니다.",
                rule_2_title: "학교 교복:",
                rule_2_desc: "모든 학생은 해당 요일의 시간표 규정에 맞는 단정한 교복과 부착물을 착용해야 합니다.",
                rule_3_title: "규율 및 윤리:",
                rule_3_desc: "모든 학교 구성원은 서로 존중하고 학교 환경을 청결하게 유지해야 하며, 모든 형태의 학교 폭력(따돌림)을 엄격히 금지합니다.",
                rule_4_title: "전자기기 사용:",
                rule_4_desc: "휴대전화 및 전자기기는 해당 과목 교사의 허가 하에 학습 목적으로만 사용할 수 있습니다.",
                rule_5_title: "행정 및 데이터:",
                rule_5_desc: "학생 및 교사의 개인 정보 변경 사항은 늦어도 학기 말까지 행정실/데이터 시스템을 통해 처리해야 합니다.",
                vision_title: "학교 비전",
                vision_desc: "\"탁월한 인성과 디지털 역량, 그리고 성취감을 갖춘 인재 양성.\"",
                sec_announcement: "최신 공지사항",
                announcement_1_title: "중간고사(PTS) 실시 안내",
                announcement_1_desc: "다음 주 중간고사 실시 전에 모든 학생은 수강 과목 데이터를 업데이트해 주시기 바랍니다.",
                announcement_2_title: "교사 및 학생 기본 데이터 업데이트",
                announcement_2_desc: "11학년 학생 및 교사 데이터의 입력 및 편집이 학생 데이터 및 교사 데이터 메뉴를 통해 시작되었습니다.",
                kpi_tugas_siswa: "과제 및 실습",
                kpi_mapel_siswa: "이수 과목",
                kpi_bahan_siswa: "학습 교재 모듈",
                kpi_nilai_siswa: "평균 성적",
                chart_title_siswa: "과목별 성적 달성도",
                spotlight_tag_siswa: "학생 프로필 및 일정",
                btn_pelajaran_portal: "🚀 학습 모듈 및 수업 열기",
                sec_bahan_siswa: "최신 학습 교재",
                sec_nilai_siswa: "학기 성적 현황",
                gauge_title_siswa: "수료 목표 및 통과 기준",
                clock_title_siswa: "학습 세션 시간",
                chart_title_admin: "학과별 학생 분포",
                spotlight_tag_admin: "시스템 관리자",
                btn_admin_portal: "🚀 마스터 데이터 관리 열기",
                sec_guru_admin: "교원 현황",
                sec_siswa_admin: "등록 학생",
                gauge_title_admin: "학교 정원 및 수용 비율",
                clock_title_admin: "서버 및 시스템 시간"
            }
        };

        function changeLanguage(lang) {
            const elements = document.querySelectorAll('[data-translate]');
            elements.forEach(el => {
                const key = el.getAttribute('data-translate');
                if (translations[lang] && translations[lang][key]) {
                    el.innerText = translations[lang][key];
                }
            });
            localStorage.setItem('selected_lang', lang);
        }

        // Init on DOM ready
        window.addEventListener('DOMContentLoaded', () => {
            updateLiveClock();
            setInterval(updateLiveClock, 1000);

            const savedLang = localStorage.getItem('selected_lang') || 'id';
            const langSelect = document.getElementById('langSelect');
            if (langSelect) {
                langSelect.value = savedLang;
            }
            changeLanguage(savedLang);
        });
    </script>
</body>
</html>