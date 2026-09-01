<?php
session_start();
require_once 'koneksi.php';
require_login();

$nav_role = $_SESSION['role'] ?? 'siswa';

// Khusus role siswa, jika admin atau guru alihkan ke index.php
if ($nav_role !== 'siswa') {
    header("Location: index.php");
    exit();
}

$nis_login = $_SESSION['nis'] ?? null;
$nama_jurusan_siswa = "-";
$id_jurusan_siswa   = null;

if ($nis_login) {
    $stmt = $pdo->prepare("SELECT s.id_jurusan, j.nama_jurusan 
                           FROM siswa s 
                           JOIN jurusan j ON s.id_jurusan = j.id_jurusan 
                           WHERE s.nis = :nis LIMIT 1");
    $stmt->execute([':nis' => $nis_login]);
    $data_siswa = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($data_siswa) {
        $id_jurusan_siswa   = $data_siswa['id_jurusan'];
        $nama_jurusan_siswa = $data_siswa['nama_jurusan'];
    }
}

// 1. DAFTAR MAPEL SESUAI JURUSAN SISWA
$daftar_mapel = [];
if ($id_jurusan_siswa) {
    $stmt = $pdo->prepare("SELECT id_mapel, nama_mapel FROM mapel WHERE id_jurusan = :id_jurusan ORDER BY nama_mapel ASC");
    $stmt->execute([':id_jurusan' => $id_jurusan_siswa]);
    $daftar_mapel = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 2. BAHAN AJAR PER MAPEL
$bahan_per_mapel = [];
if ($id_jurusan_siswa) {
    $stmt = $pdo->prepare("SELECT id_mapel, judul_materi, nama_file, file_path, tanggal_upload
                           FROM bahan_ajar
                           WHERE id_jurusan = :id_jurusan
                           ORDER BY tanggal_upload DESC");
    $stmt->execute([':id_jurusan' => $id_jurusan_siswa]);
    $semua_bahan = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($semua_bahan as $b) {
        $bahan_per_mapel[$b['id_mapel']][] = $b;
    }
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
    <title>Materi & Modul Pelajaran - Portal Siswa</title>
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

        .jurusan-banner {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            color: #ffffff;
            padding: 24px 28px;
            border-radius: var(--radius-lg);
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.08);
        }
        .jurusan-banner h3 { font-size: 18px; font-weight: 800; margin-bottom: 4px; }
        .jurusan-banner p { font-size: 13px; opacity: 0.85; }
        .jurusan-tag {
            background: rgba(37, 99, 235, 0.35);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12.5px;
            font-weight: 700;
        }

        .mapel-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 20px;
        }

        .mapel-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 22px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-card);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.2s ease;
        }
        .mapel-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(0,0,0,0.06);
        }

        .mapel-card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
        }
        .mapel-icon {
            width: 42px;
            height: 42px;
            background: #eff6ff;
            color: var(--accent);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
            border: 1px solid #dbeafe;
        }
        .mapel-card-title {
            font-size: 15px;
            font-weight: 800;
            color: var(--text-title);
            line-height: 1.3;
        }
        .mapel-card-code {
            font-size: 11.5px;
            color: var(--text-muted);
            font-weight: 600;
        }

        .materi-list {
            margin-top: 12px;
            border-top: 1px dashed var(--border);
            padding-top: 12px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .materi-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--bg-subtle);
            padding: 8px 12px;
            border-radius: var(--radius-sm);
            font-size: 12.5px;
            border: 1px solid var(--border);
            gap: 8px;
        }
        .btn-open-pdf {
            background: #eff6ff;
            color: var(--accent);
            border: 1px solid #bfdbfe;
            padding: 4px 10px;
            border-radius: var(--radius-sm);
            font-size: 11.5px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            text-decoration: none;
            white-space: nowrap;
        }
        .btn-open-pdf:hover {
            background: #dbeafe;
        }

        /* ===== MODAL POPUP PDF PREVIEW ===== */
        .modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); display: none; align-items: center; justify-content: center; z-index: 1000; padding: 20px; animation: fadeIn 0.2s ease; }
        .modal-overlay.active { display: flex; }
        .modal-card-lg { background: #ffffff; width: 100%; max-width: 900px; border-radius: var(--radius-lg); box-shadow: var(--shadow-modal); border: 1px solid var(--border); overflow: hidden; animation: slideUp 0.25s ease; }
        .modal-header { padding: 16px 24px; background: #ffffff; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .modal-header h3 { font-size: 16px; font-weight: 800; color: var(--text-title); display: flex; align-items: center; gap: 8px; }
        .modal-close-btn { background: none; border: none; font-size: 20px; color: var(--text-muted); cursor: pointer; padding: 4px; line-height: 1; border-radius: 4px; }
        .modal-close-btn:hover { color: var(--text-title); background: var(--bg-subtle); }
        .modal-body { padding: 0; }

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
                gap: 8px;
            }
            .page-header h2 { font-size: 17px; }
            .page-header p { font-size: 12px; }

            .jurusan-banner {
                padding: 18px 16px;
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            .jurusan-banner h3 { font-size: 16px; }

            .mapel-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .modal-overlay { padding: 12px; }
            .modal-card-lg {
                max-width: 100% !important;
                max-height: 90vh;
            }
            .modal-card-lg iframe {
                height: 70vh !important;
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
                <li><a href="tugas.php" class="nav-link">Tugas</a></li>
                <li><a href="pelajaran.php" class="nav-link active">Pelajaran</a></li>
                <li><a href="profile.php" class="nav-link">Profil Saya</a></li>
                <li><a href="logout.php" class="nav-link nav-logout">Logout</a></li>
            </ul>
        </div>
    </nav>

    <!-- ===== MAIN CONTAINER ===== -->
    <main class="main-container">

        <div class="page-header">
            <div>
                <h2>📖 Mata Pelajaran & Modul Belajar</h2>
                <p>Akses silabus, bahan ajar, dan modul pembelajaran PDF sesuai dengan program peminatan Anda.</p>
            </div>
        </div>

        <div class="jurusan-banner">
            <div>
                <p>Program Keahlian / Peminatan Anda:</p>
                <h3><?php echo htmlspecialchars($nama_jurusan_siswa); ?></h3>
            </div>
            <div class="jurusan-tag">
                Kode Jurusan: <?php echo htmlspecialchars($id_jurusan_siswa ?: '-'); ?>
            </div>
        </div>

        <!-- GRID MATA PELAJARAN -->
        <div class="mapel-grid">
            <?php if (!empty($daftar_mapel)): ?>
                <?php foreach ($daftar_mapel as $m): ?>
                    <?php 
                        $m_id   = $m['id_mapel'];
                        $m_name = $m['nama_mapel'];
                        $materi = $bahan_per_mapel[$m_id] ?? [];
                    ?>
                    <div class="mapel-card">
                        <div>
                            <div class="mapel-card-header">
                                <div class="mapel-icon">📚</div>
                                <div>
                                    <div class="mapel-card-title"><?php echo htmlspecialchars($m_name); ?></div>
                                    <div class="mapel-card-code">Kode: <?php echo htmlspecialchars($m_id); ?></div>
                                </div>
                            </div>

                            <div class="materi-list">
                                <div style="font-size: 11.5px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
                                    Modul & Materi Pembelajaran:
                                </div>
                                <?php if (!empty($materi)): ?>
                                    <?php foreach ($materi as $mat): ?>
                                        <div class="materi-item">
                                            <span style="font-weight: 600; color: var(--text-title); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($mat['judul_materi']); ?>">
                                                <?php echo htmlspecialchars($mat['judul_materi']); ?>
                                            </span>
                                            <button type="button" class="btn-open-pdf" 
                                                onclick="openPdfModal('<?php echo htmlspecialchars(addslashes($mat['file_path'])); ?>', '<?php echo htmlspecialchars(addslashes($mat['judul_materi'])); ?>')">
                                                📄 Buka PDF
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div style="font-size: 12px; color: var(--text-muted); font-style: italic; padding: 4px 0;">
                                        Belum ada modul PDF untuk mata pelajaran ini.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column: 1 / -1; background: #fff; padding: 40px; border-radius: var(--radius-lg); text-align: center; color: var(--text-muted); border: 1px solid var(--border);">
                    Tidak ada mata pelajaran yang terdaftar pada jurusan Anda saat ini.
                </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- ===== MODAL POPUP PDF PREVIEW ===== -->
    <div id="modal-pdf" class="modal-overlay">
        <div class="modal-card-lg">
            <div class="modal-header">
                <h3 id="pdf-title">📄 Preview Modul Pembelajaran</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('modal-pdf')">✕</button>
            </div>
            <div class="modal-body">
                <iframe id="pdf-iframe" src="" style="width: 100%; height: 75vh; border: none;"></iframe>
            </div>
        </div>
    </div>

    <script>
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
