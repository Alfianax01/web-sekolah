<?php
session_start();
require_once 'koneksi.php';
require_role(['admin']);
$nav_role = $_SESSION['role'];

$id_mapel_edit   = "";
$nama_mapel_edit = "";
$id_jurusan_edit = "";
$is_edit         = false;

// 1. AMBIL DATA JURUSAN UNTUK DROPDOWN & FILTER
$stmt_jurusan = $pdo->query("SELECT * FROM jurusan ORDER BY nama_jurusan ASC");
$daftar_jurusan = $stmt_jurusan->fetchAll(PDO::FETCH_ASSOC);

// 2. PROSES INSERT & UPDATE MAPEL
if (isset($_POST['simpan'])) {
    verify_csrf();
    $id_mapel   = $_POST['id_mapel'];
    $nama_mapel = $_POST['nama_mapel'];
    $id_jurusan = $_POST['id_jurusan'];
    $mode       = $_POST['mode'];

    if ($mode == 'insert') {
        $sql = "INSERT INTO mapel (id_mapel, nama_mapel, id_jurusan) VALUES (:id_mapel, :nama_mapel, :id_jurusan)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id_mapel' => $id_mapel, ':nama_mapel' => $nama_mapel, ':id_jurusan' => $id_jurusan]);
    } else if ($mode == 'update') {
        $sql = "UPDATE mapel SET nama_mapel = :nama_mapel, id_jurusan = :id_jurusan WHERE id_mapel = :id_mapel";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':nama_mapel' => $nama_mapel, ':id_jurusan' => $id_jurusan, ':id_mapel' => $id_mapel]);
    }
    header("Location: mapel.php");
    exit();
}

// 3. PROSES DELETE MAPEL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    verify_csrf();
    $id_mapel = $_POST['id_mapel'] ?? '';
    $sql = "DELETE FROM mapel WHERE id_mapel = :id_mapel";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id_mapel' => $id_mapel]);
    
    header("Location: mapel.php");
    exit();
}

// 4. PROSES AMBIL DATA EDIT
if (isset($_GET['action']) && $_GET['action'] == 'edit') {
    $id_mapel = $_GET['id_mapel'];
    $sql = "SELECT * FROM mapel WHERE id_mapel = :id_mapel LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id_mapel' => $id_mapel]);
    $data_edit = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($data_edit) {
        $id_mapel_edit   = $data_edit['id_mapel'];
        $nama_mapel_edit = $data_edit['nama_mapel'];
        $id_jurusan_edit = $data_edit['id_jurusan'];
        $is_edit         = true;
    }
}

// 5. FILTER & READ DATA MAPEL
$filter_jurusan = $_GET['filter_jurusan'] ?? '';

if (!empty($filter_jurusan)) {
    $stmt = $pdo->prepare("SELECT m.*, j.nama_jurusan FROM mapel m LEFT JOIN jurusan j ON m.id_jurusan = j.id_jurusan WHERE m.id_jurusan = :jurusan ORDER BY m.id_mapel ASC");
    $stmt->execute([':jurusan' => $filter_jurusan]);
} else {
    $stmt = $pdo->query("SELECT m.*, j.nama_jurusan FROM mapel m LEFT JOIN jurusan j ON m.id_jurusan = j.id_jurusan ORDER BY m.id_mapel ASC");
}
$all_mapel = $stmt->fetchAll(PDO::FETCH_ASSOC);

$per_page    = 5;
$total_data  = count($all_mapel);
$total_pages = max(1, (int) ceil($total_data / $per_page));

$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) $page = 1;
if ($page > $total_pages) $page = $total_pages;

$daftar_mapel = array_slice($all_mapel, ($page - 1) * $per_page, $per_page);

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
} catch (PDOException $e) {}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Data Mapel - Pemantauan Sekolah</title>
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
            display: flex; align-items: stretch; background: #ffffff;
            border-bottom: 1px solid var(--border); box-shadow: 0 2px 10px rgba(0,0,0,0.02);
            position: sticky; top: 0; z-index: 100; width: 100%;
        }
        .header-accent-line { width: 6px; background-color: var(--accent); flex-shrink: 0; }
        .header-content {
            flex: 1; display: flex; justify-content: space-between; align-items: center;
            padding: 12px 40px; max-width: 1400px; margin: 0 auto;
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
        .user-avatar img { width: 100%; height: 100%; object-fit: cover; }
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
            text-transform: uppercase; padding: 12px 16px; transition: all 0.2s ease; border-bottom: 3px solid transparent; white-space: nowrap;
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

        /* AKSI & FILTER */
        .action-filter-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        .filter-wrapper { display: flex; align-items: center; gap: 10px; background: white; padding: 6px 14px; border-radius: 8px; border: 1px solid var(--border); }
        .filter-wrapper label { font-size: 12px; font-weight: 700; color: var(--primary); text-transform: uppercase; }
        .filter-wrapper select { height: 32px; border: 1px solid var(--border); border-radius: 6px; padding: 0 10px; font-size: 12px; background: var(--bg-main); color: var(--text-main); font-weight: 600; cursor: pointer; }
        .btn-reset-filter { font-size: 11px; color: var(--danger); text-decoration: none; font-weight: 600; }
        .btn-reset-filter:hover { text-decoration: underline; }

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
        .form-group input, .form-group select {
            height: 42px; border: 1px solid var(--border); border-radius: 6px; padding: 0 14px; font-size: 13px; background: var(--bg-main); color: var(--text-main);
        }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--accent); background: white; }
        .form-group input[readonly] { background: #e2e8f0; color: var(--text-muted); cursor: not-allowed; }

        /* BUTTONS */
        .btn { border: none; cursor: pointer; text-decoration: none; display: inline-block; padding: 8px 16px; border-radius: 6px; font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .btn-primary { background: var(--accent); color: white; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-secondary { background: var(--text-muted); color: white; }
        .btn-secondary:hover { background: #475569; }
        .btn-warning { background: var(--warning); color: white; padding: 6px 12px; border-radius: 6px; font-size: 11px; font-weight: 700; display: inline-block; }
        .btn-danger { background: var(--danger); color: white; padding: 6px 12px; border-radius: 6px; font-size: 11px; font-weight: 700; display: inline-block; }

        /* TABLE */
        .table-responsive { overflow-x: auto; width: 100%; }
        table { width: 100%; border-collapse: collapse; min-width: 600px; }
        th { background: var(--bg-main); color: var(--primary); font-size: 12px; font-weight: 800; text-align: left; padding: 12px 14px; border-bottom: 2px solid var(--border); text-transform: uppercase; }
        td { padding: 12px 14px; font-size: 13px; border-bottom: 1px solid var(--border); color: var(--text-main); }
        tbody tr:hover { background-color: var(--bg-main); }
        td strong { color: var(--primary); font-weight: 700; }

        /* PAGINATION */
        .pagination { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-top: 20px; }
        .page-info { color: var(--text-muted); font-size: 12px; font-weight: 600; }
        .page-links { display: flex; gap: 6px; flex-wrap: wrap; }
        .page-link { text-decoration: none; color: var(--text-main); border: 1px solid var(--border); padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; background: white; }
        .page-link:hover { background: var(--bg-main); color: var(--accent); }
        .page-link.active { background: var(--accent); color: white; border-color: var(--accent); }
        .page-link.disabled { opacity: 0.5; pointer-events: none; }
    </style>
</head>
<body>

    <!-- HEADER UTAMA -->
    <header class="header-main">
        <div class="header-accent-line"></div>
        <div class="header-content">
            <div class="header-left">
                <p>Pemantauan Sekolah</p>
                <h1>Kelola Data Mapel</h1>
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
                <li><a href="siswa.php" class="nav-link">Data Siswa</a></li>
                <li><a href="guru.php" class="nav-link">Data Guru</a></li>
                <li><a href="mapel.php" class="nav-link active">Data Mapel</a></li>
                <li><a href="jurusan.php" class="nav-link">Data Jurusan</a></li>
                <li><a href="bahan_ajar.php" class="nav-link">Bahan Ajar</a></li>
                <?php endif; ?>
                <li><a href="tugas.php" class="nav-link">Tugas</a></li>
                <li><a href="profile.php" class="nav-link">Profil Saya</a></li>
                <li><a href="logout.php" class="nav-link nav-logout">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="main-container">

        <!-- TOMBOL AKSI & FILTER JURUSAN -->
        <div class="action-filter-bar">
            <button type="button" id="btn-toggle-form" class="btn-action-toggle" onclick="toggleForm()">
                <span id="btn-icon">+</span> <span id="btn-text">Tambah Data Mapel Baru</span>
            </button>

            <!-- Filter Jurusan -->
            <div class="filter-wrapper">
                <label>Jurusan:</label>
                <form method="GET" action="mapel.php" style="display: flex; align-items: center; gap: 8px;">
                    <select name="filter_jurusan" onchange="this.form.submit()">
                        <option value="">Semua Jurusan</option>
                        <?php foreach ($daftar_jurusan as $j): ?>
                            <option value="<?php echo $j['id_jurusan']; ?>" <?php echo $filter_jurusan == $j['id_jurusan'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($j['id_jurusan'] . ' - ' . $j['nama_jurusan']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($filter_jurusan)): ?>
                        <a href="mapel.php" class="btn-reset-filter">✕ Reset</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- 1. TABEL DATA MAPEL -->
        <div class="content-box">
            <div class="section-title">Daftar Mata Pelajaran</div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>ID Mapel</th>
                            <th>Nama Mapel</th>
                            <th>Jurusan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($daftar_mapel) > 0): ?>
                            <?php $no = (($page - 1) * $per_page) + 1; ?>
                            <?php foreach ($daftar_mapel as $m): ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($m['id_mapel']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($m['nama_mapel']); ?></td>
                                    <td><?php echo htmlspecialchars($m['id_jurusan'] . ($m['nama_jurusan'] ? ' - ' . $m['nama_jurusan'] : '')); ?></td>
                                    <td>
                                        <a href="mapel.php?action=edit&id_mapel=<?php echo urlencode($m['id_mapel']); ?>" class="btn-warning">Edit</a>
                                        <form action="mapel.php" method="POST" style="display:inline;" onsubmit="return confirm('Yakin ingin menghapus mapel ini?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id_mapel" value="<?php echo e($m['id_mapel']); ?>">
                                            <button type="submit" class="btn-danger">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: var(--text-muted);">
                                    Belum ada data mata pelajaran.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <span class="page-info">Halaman <?php echo $page; ?> dari <?php echo $total_pages; ?> (<?php echo $total_data; ?> mapel)</span>
                <div class="page-links">
                    <a href="mapel.php?page=<?php echo max(1, $page - 1); ?><?php echo !empty($filter_jurusan) ? '&filter_jurusan='.$filter_jurusan : ''; ?>" class="page-link <?php echo $page <= 1 ? 'disabled' : ''; ?>">&laquo; Sebelumnya</a>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="mapel.php?page=<?php echo $i; ?><?php echo !empty($filter_jurusan) ? '&filter_jurusan='.$filter_jurusan : ''; ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <a href="mapel.php?page=<?php echo min($total_pages, $page + 1); ?><?php echo !empty($filter_jurusan) ? '&filter_jurusan='.$filter_jurusan : ''; ?>" class="page-link <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">Berikutnya &raquo;</a>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- 2. FORM INPUT / EDIT -->
        <div id="form-container" class="content-box" style="display: <?php echo $is_edit ? 'block' : 'none'; ?>;">
            <div class="section-title">Form <?php echo $is_edit ? 'Edit Data' : 'Tambah Data'; ?> Mapel</div>
            <form action="mapel.php" method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="mode" value="<?php echo $is_edit ? 'update' : 'insert'; ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label>ID Mapel:</label>
                        <input type="text" name="id_mapel" value="<?php echo htmlspecialchars($id_mapel_edit); ?>" <?php echo $is_edit ? 'readonly' : 'required'; ?> placeholder="Contoh: MTK-001">
                    </div>

                    <div class="form-group">
                        <label>Nama Mapel:</label>
                        <input type="text" name="nama_mapel" value="<?php echo htmlspecialchars($nama_mapel_edit); ?>" required placeholder="Contoh: Matematika">
                    </div>

                    <div class="form-group">
                        <label>Jurusan:</label>
                        <select name="id_jurusan" required>
                            <option value="">-- Pilih Jurusan --</option>
                            <?php foreach ($daftar_jurusan as $j): ?>
                                <option value="<?php echo $j['id_jurusan']; ?>" <?php echo $id_jurusan_edit == $j['id_jurusan'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($j['id_jurusan'] . ' - ' . $j['nama_jurusan']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="margin-top: 20px;">
                    <button type="submit" name="simpan" class="btn btn-primary"><?php echo $is_edit ? 'Update Data' : 'Simpan Data'; ?></button>
                    <?php if ($is_edit): ?>
                        <a href="mapel.php" class="btn btn-secondary">Batal</a>
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
                btnText.innerText = "Tambah Data Mapel Baru";
                btnIcon.innerText = "+";
                btnToggle.style.background = "var(--accent)";
            }
        }
    </script>
</body>
</html>