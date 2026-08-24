<?php
session_start();
require_once 'koneksi.php';
require_role(['admin']);
$nav_role = $_SESSION['role'];

$nip_edit       = "";
$nama_edit      = "";
$is_edit        = false;

// 1. PROSES INSERT & UPDATE GURU (Hanya NIP dan Nama Guru)
if (isset($_POST['simpan'])) {
    verify_csrf();
    $nip        = $_POST['nip'];
    $nama_guru  = $_POST['nama_guru'];
    $mode       = $_POST['mode'];

    if ($mode == 'insert') {
        $sql = "INSERT INTO guru (nip, nama_guru) VALUES (:nip, :nama_guru)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':nip' => $nip, 
            ':nama_guru' => $nama_guru
        ]);
    } else if ($mode == 'update') {
        $sql = "UPDATE guru SET nama_guru = :nama_guru WHERE nip = :nip";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':nama_guru' => $nama_guru, 
            ':nip' => $nip
        ]);
    }
    header("Location: guru.php");
    exit();
}

// 2. PROSES DELETE GURU
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    verify_csrf();
    $nip = $_POST['nip'] ?? '';
    $sql = "DELETE FROM guru WHERE nip = :nip";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':nip' => $nip]);
    
    header("Location: guru.php");
    exit();
}

// 3. PROSES AMBIL DATA EDIT
if (isset($_GET['action']) && $_GET['action'] == 'edit') {
    $nip = $_GET['nip'];
    $sql = "SELECT * FROM guru WHERE nip = :nip LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':nip' => $nip]);
    $data_edit = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($data_edit) {
        $nip_edit  = $data_edit['nip'];
        $nama_edit = $data_edit['nama_guru'];
        $is_edit   = true;
    }
}

// 4. READ & PAGINASI GURU
$stmt = $pdo->query("SELECT * FROM guru ORDER BY nip ASC");
$all_guru = $stmt->fetchAll(PDO::FETCH_ASSOC);

$per_page    = 5;
$total_data  = count($all_guru);
$total_pages = max(1, (int) ceil($total_data / $per_page));

$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) $page = 1;
if ($page > $total_pages) $page = $total_pages;

$daftar_guru = array_slice($all_guru, ($page - 1) * $per_page, $per_page);
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
    <title>Kelola Data Guru - Pemantauan Sekolah</title>
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

        .header-left h1 { color: var(--primary); font-size: 18px; font-weight: 800; text-transform: uppercase; }
        .header-left p { color: var(--text-muted); font-size: 11px; font-weight: 600; text-transform: uppercase; }

        .header-right { display: flex; align-items: center; gap: 12px; }

        .user-badge {
            display: flex; align-items: center; gap: 8px; background: var(--bg-main);
            padding: 5px 12px 5px 5px; border-radius: 30px; border: 1px solid var(--border);
        }

        .user-avatar {
            background: var(--accent); color: white; width: 32px; height: 32px;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-size: 14px; font-weight: bold; overflow: hidden; flex-shrink: 0; border: 2px solid #fff;
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

        /* TOMBOL TOGGLE */
        .btn-action-toggle {
            border: none; cursor: pointer; background: var(--accent); color: white;
            padding: 10px 18px; border-radius: 8px; font-size: 13px; font-weight: 700;
            transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; text-transform: uppercase;
        }
        .btn-action-toggle:hover { background: #1d4ed8; }

        /* FORM */
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; }
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

        /* TABLE */
        .table-responsive { overflow-x: auto; width: 100%; }
        table { width: 100%; border-collapse: collapse; min-width: 600px; }
        th { background: var(--bg-main); color: var(--primary); font-size: 12px; font-weight: 800; text-align: left; padding: 12px 14px; border-bottom: 2px solid var(--border); text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 12px 14px; font-size: 13px; border-bottom: 1px solid var(--border); color: var(--text-main); }
        tbody tr:hover { background-color: var(--bg-main); }
        td strong { color: var(--primary); font-weight: 700; }

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
                <h1>Kelola Data Guru</h1>
            </div>
            <div class="header-right">
                <div class="header-date">
                    <?php 
                    $hari = array("Minggu","Senin","Selasa","Rabu","Kamis","Jumat","Sabtu");
                    $bulan = array("","Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember");
                    echo $hari[date("w")] . ", " . date("j") . " " . $bulan[date("n")] . " " . date("Y");
                    ?>
                </div>
                <div class="user-badge">
                    <div class="user-avatar">
                        <?php if (!empty($foto_user) && file_exists('uploads/' . $foto_user)): ?>
                            <img src="uploads/<?php echo htmlspecialchars($foto_user); ?>" alt="Foto">
                        <?php else: ?>
                            <?php echo strtoupper(substr($nav_role, 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="user-info">
                        <span class="user-role"><?php echo htmlspecialchars($nav_role); ?></span>
                        <span class="user-status">● Online</span>
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
                <li><a href="siswa.php" class="nav-link ">Data Siswa</a></li>
                <li><a href="guru.php" class="nav-link active">Data Guru</a></li>
                <li><a href="mapel.php" class="nav-link">Data Mapel</a></li>
                <li><a href="jurusan.php" class="nav-link">Data Jurusan</a></li>
                 <li><a href="bahan_ajar.php" class="nav-link">Bahan Ajar</a></li>
                <?php endif; ?>
                <li><a href="tugas.php" class="nav-link">Tugas</a></li>
                <li><a href="profile.php" class="nav-link">Profil Saya</a></li>
                <li><a href="logout.php" class="nav-link nav-logout">Logout</a></li>

            </ul>
        </div>
    </nav>

    <!-- KONTEN UTAMA -->
    <main class="main-container">
        
        <!-- 1. Tombol Toggle Tambah Data -->
        <div>
            <button type="button" id="btn-toggle-form" class="btn-action-toggle" onclick="toggleForm()">
                <span id="btn-icon">+</span> <span id="btn-text">Tambah Data Guru Baru</span>
            </button>
        </div>

        <!-- 2. Kotak Tabel Data -->
        <div class="content-box">
            <h2 class="section-title">Daftar Pengajar / Guru</h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>NIP</th>
                            <th>Nama Guru</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($daftar_guru) > 0): ?>
                            <?php $no = (($page - 1) * $per_page) + 1; ?>
                            <?php foreach ($daftar_guru as $g): ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($g['nip']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($g['nama_guru']); ?></td>
                                    <td>
                                        <a href="guru.php?action=edit&nip=<?php echo urlencode($g['nip']); ?>" class="btn-warning">Edit</a>
                                        <form action="guru.php" method="POST" style="display:inline;" onsubmit="return confirm('Yakin ingin menghapus guru ini?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="nip" value="<?php echo e($g['nip']); ?>">
                                            <button type="submit" class="btn-danger">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: var(--text-muted);">
                                    Belum ada data guru.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <span class="page-info">Halaman <?php echo $page; ?> dari <?php echo $total_pages; ?> (<?php echo $total_data; ?> guru)</span>
                <div class="page-links">
                    <a href="guru.php?page=<?php echo max(1, $page - 1); ?>" class="page-link <?php echo $page <= 1 ? 'disabled' : ''; ?>">&laquo; Sebelumnya</a>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="guru.php?page=<?php echo $i; ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <a href="guru.php?page=<?php echo min($total_pages, $page + 1); ?>" class="page-link <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">Berikutnya &raquo;</a>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- 3. Kotak Form (Tambah / Edit) -->
        <div id="form-container" class="content-box" style="display: <?php echo $is_edit ? 'block' : 'none'; ?>;">
            <h2 class="section-title">Form <?php echo $is_edit ? 'Edit Data' : 'Tambah Data'; ?> Guru</h2>
            <form action="guru.php" method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="mode" value="<?php echo $is_edit ? 'update' : 'insert'; ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label>NIP:</label>
                        <input type="text" name="nip" value="<?php echo htmlspecialchars($nip_edit); ?>" <?php echo $is_edit ? 'readonly' : 'required'; ?> placeholder="Contoh: G001">
                    </div>

                    <div class="form-group">
                        <label>Nama Guru:</label>
                        <input type="text" name="nama_guru" value="<?php echo htmlspecialchars($nama_edit); ?>" required placeholder="Contoh: Budi Santoso">
                    </div>
                </div>

                <div style="margin-top: 20px;">
                    <button type="submit" name="simpan" class="btn btn-primary"><?php echo $is_edit ? 'Update Data' : 'Simpan Data'; ?></button>
                    <?php if ($is_edit): ?>
                        <a href="guru.php" class="btn btn-secondary">Batal</a>
                    <?php else: ?>
                        <button type="button" class="btn btn-secondary" onclick="toggleForm()">Tutup Form</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

    </main>

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
                btnText.innerText = "Tambah Data Guru Baru";
                btnIcon.innerText = "+";
                btnToggle.style.background = "var(--accent)";
            }
        }
    </script>
</body>
</html>