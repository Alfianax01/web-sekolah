<?php
session_start();
require_once 'koneksi.php';
require_role(['admin', 'guru']);
$nav_role = $_SESSION['role'];

$upload_dir = __DIR__ . '/uploads/bahan_ajar/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$pesan = "";
$pesan_tipe = ""; // success / error

// ==========================================
// 1. PROSES UPLOAD BAHAN AJAR
// ==========================================
if (isset($_POST['upload_materi'])) {
    verify_csrf();
    $id_jurusan   = trim($_POST['id_jurusan'] ?? '');
    $id_mapel     = trim($_POST['id_mapel'] ?? '');
    $judul_materi = trim($_POST['judul_materi']);

    if (isset($_FILES['file_materi']) && $_FILES['file_materi']['error'] === UPLOAD_ERR_OK) {
        $file       = $_FILES['file_materi'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);

        if ($mime !== 'application/pdf') {
            $pesan = "File harus berformat PDF.";
            $pesan_tipe = "error";
        } elseif ($file['size'] > 10 * 1024 * 1024) { // max 10MB
            $pesan = "Ukuran file maksimal 10MB.";
            $pesan_tipe = "error";
        } else {
            $nama_asli   = $file['name'];
            $nama_unik   = uniqid('materi_') . '.' . $ekstensi;
            $tujuan      = $upload_dir . $nama_unik;
            $path_simpan = 'uploads/bahan_ajar/' . $nama_unik;

            if (move_uploaded_file($file['tmp_name'], $tujuan)) {
                $sql = "INSERT INTO bahan_ajar (id_jurusan, id_mapel, judul_materi, nama_file, file_path)
                        VALUES (:id_jurusan, :id_mapel, :judul_materi, :nama_file, :file_path)";
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
                $pesan = "Gagal mengupload file ke server.";
                $pesan_tipe = "error";
            }
        }
    } else {
        $pesan = "Silakan pilih file PDF terlebih dahulu.";
        $pesan_tipe = "error";
    }
}

if (isset($_GET['status']) && $_GET['status'] == 'sukses') {
    $pesan = "Bahan ajar berhasil diupload.";
    $pesan_tipe = "success";
}

// ==========================================
// 2. PROSES HAPUS BAHAN AJAR
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    verify_csrf();
    $id_bahan = $_POST['id_bahan'] ?? '';

    $stmt = $pdo->prepare("SELECT file_path FROM bahan_ajar WHERE id_bahan = :id");
    $stmt->execute([':id' => $id_bahan]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $file_fisik = realpath(__DIR__ . '/' . $row['file_path']);
        $upload_root = realpath($upload_dir);
        if ($file_fisik && $upload_root && str_starts_with($file_fisik, $upload_root . DIRECTORY_SEPARATOR) && is_file($file_fisik)) {
            unlink($file_fisik);
        }
        $stmt = $pdo->prepare("DELETE FROM bahan_ajar WHERE id_bahan = :id");
        $stmt->execute([':id' => $id_bahan]);
    }

    header("Location: bahan_ajar.php?status=hapus");
    exit();
}
if (isset($_GET['status']) && $_GET['status'] == 'hapus') {
    $pesan = "Bahan ajar berhasil dihapus.";
    $pesan_tipe = "success";
}

// ==========================================
// 3. AMBIL DATA JURUSAN & MAPEL (untuk dropdown form)
// ==========================================
$daftar_jurusan_all = $pdo->query("SELECT id_jurusan, nama_jurusan FROM jurusan ORDER BY nama_jurusan ASC")->fetchAll(PDO::FETCH_ASSOC);
$daftar_mapel_all   = $pdo->query("SELECT id_mapel, nama_mapel, id_jurusan FROM mapel ORDER BY nama_mapel ASC")->fetchAll(PDO::FETCH_ASSOC);

// ==========================================
// 4. FILTER TABEL
// ==========================================
$filter_jurusan = isset($_GET['filter_jurusan']) ? trim($_GET['filter_jurusan']) : "";

$sql_list = "SELECT b.id_bahan, b.judul_materi, b.nama_file, b.file_path, b.tanggal_upload,
                    j.nama_jurusan, m.nama_mapel, b.id_jurusan, b.id_mapel
             FROM bahan_ajar b
             JOIN jurusan j ON b.id_jurusan = j.id_jurusan
             JOIN mapel m ON b.id_mapel = m.id_mapel";
if ($filter_jurusan !== "") {
    $sql_list .= " WHERE b.id_jurusan = :id_jurusan";
}
$sql_list .= " ORDER BY j.nama_jurusan ASC, m.nama_mapel ASC, b.tanggal_upload DESC";

$stmt_list = $pdo->prepare($sql_list);
if ($filter_jurusan !== "") {
    $stmt_list->execute([':id_jurusan' => $filter_jurusan]);
} else {
    $stmt_list->execute();
}
$daftar_bahan_ajar = $stmt_list->fetchAll(PDO::FETCH_ASSOC);

$query_filter = $filter_jurusan !== "" ? "&filter_jurusan=" . urlencode($filter_jurusan) : "";

$user_id = $_SESSION['user_id'];
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
    <title>Bahan Ajar - Pemantauan Sekolah</title>
    <style>
        :root {
            --primary: #1e293b; --primary-light: #334155; --accent: #2563eb;
            --bg-main: #f8fafc; --bg-card: #ffffff; --text-main: #334155; --text-muted: #64748b;
            --border: #e2e8f0; --warning: #f59e0b; --danger: #ef4444; --success: #10b981;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: var(--bg-main); color: var(--text-main); line-height: 1.6; overflow-x: hidden; }

        .header-main { display: flex; align-items: stretch; background: #fff; border-bottom: 1px solid var(--border); box-shadow: 0 2px 10px rgba(0,0,0,0.02); position: sticky; top: 0; z-index: 100; width: 100%; }
        .header-accent-line { width: 6px; background-color: var(--accent); flex-shrink: 0; }
        .header-content { flex: 1; display: flex; justify-content: space-between; align-items: center; padding: 12px 40px; max-width: 1400px; margin: 0 auto; }
        .header-left h1 { color: var(--primary); font-size: 18px; font-weight: 800; letter-spacing: 0.5px; text-transform: uppercase; }
        .header-left p { color: var(--text-muted); font-size: 11px; font-weight: 600; letter-spacing: 0.5px; margin-bottom: 2px; text-transform: uppercase; }
        
        .header-right { display: flex; align-items: center; gap: 12px; }
        
        .user-badge { display: flex; align-items: center; gap: 8px; background: var(--bg-main); padding: 5px 12px 5px 5px; border-radius: 30px; border: 1px solid var(--border); }
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

        .navbar-menu { background-color: var(--primary); width: 100%; box-shadow: inset 0 -3px 0 rgba(0,0,0,0.1); }
        .navbar-inner { max-width: 1400px; margin: 0 auto; padding: 0 40px; }
        .navbar-menu ul { list-style: none; display: flex; align-items: center; gap: 2px; overflow-x: auto; }
        .navbar-menu a.nav-link { display: inline-block; color: rgba(255,255,255,0.75); text-decoration: none; font-weight: 600; font-size: 12px; text-transform: uppercase; padding: 12px 16px; transition: all 0.2s ease; border-bottom: 3px solid transparent; letter-spacing: 0.5px; white-space: nowrap; }
        .navbar-menu a.nav-link:hover { color: white; background-color: rgba(255,255,255,0.05); }
        .navbar-menu a.nav-link.active { color: white; border-bottom-color: var(--accent); }
        .navbar-menu a.nav-link.nav-logout { color: #fca5a5; margin-left: auto; }
        .navbar-menu a.nav-link.nav-logout:hover { color: #ef4444; background-color: rgba(239,68,68,0.1); }

        .main-container { max-width: 1400px; margin: 25px auto 40px; padding: 0 40px; width: 100%; }
        .content-box { background: var(--bg-card); padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); margin-bottom: 25px; border: 1px solid var(--border); width: 100%; }
        .section-title { font-size: 16px; color: var(--primary); font-weight: 800; border-bottom: 2px solid var(--bg-main); padding-bottom: 10px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; }

        .alert { padding: 12px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; margin-bottom: 20px; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        .toolbar-row { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; }
        .filter-group { display: flex; align-items: center; gap: 8px; }
        .filter-group label { font-size: 12px; font-weight: 700; color: var(--primary); text-transform: uppercase; }
        .filter-group select { height: 38px; border: 1px solid var(--border); border-radius: 6px; padding: 0 12px; font-size: 13px; background: var(--bg-main); color: var(--text-main); cursor: pointer; }
        .filter-reset { font-size: 11px; color: var(--text-muted); text-decoration: none; font-weight: 600; }
        .filter-reset:hover { color: var(--danger); }

        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-size: 12px; font-weight: 700; color: var(--primary); margin-bottom: 6px; text-transform: uppercase; }
        .form-group input, .form-group select { height: 42px; border: 1px solid var(--border); border-radius: 6px; padding: 0 14px; font-size: 13px; background: var(--bg-main); color: var(--text-main); transition: all 0.2s; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); background: white; }
        .form-group input[type="file"] { padding: 8px 14px; height: auto; }

        .btn { border: none; cursor: pointer; text-decoration: none; display: inline-block; padding: 8px 16px; border-radius: 6px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; transition: all 0.2s; }
        .btn-primary { background: var(--accent); color: white; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-danger { background: var(--danger); color: white; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 11px; font-weight: 700; }
        .btn-danger:hover { background: #dc2626; }

        .table-responsive { overflow-x: auto; width: 100%; }
        table { width: 100%; border-collapse: collapse; min-width: 700px; }
        th { background: var(--bg-main); color: var(--primary); font-size: 12px; font-weight: 800; text-align: left; padding: 12px 14px; border-bottom: 2px solid var(--border); text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 12px 14px; font-size: 13px; border-bottom: 1px solid var(--border); color: var(--text-main); vertical-align: middle; }
        tbody tr:hover { background-color: var(--bg-main); }
        td strong { color: var(--primary); font-weight: 700; }
        .col-no { width: 50px; text-align: center; color: var(--text-muted); font-weight: 700; }
        .badge-jurusan { background: #ede9fe; color: #5b21b6; padding: 3px 10px; border-radius: 4px; font-weight: 700; font-size: 11px; }
        .badge-mapel { background: #e0f2fe; color: #0369a1; padding: 3px 10px; border-radius: 4px; font-weight: 600; font-size: 11px; }
        .file-link { color: var(--accent); text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
        .file-link:hover { text-decoration: underline; }

        @media (max-width: 992px) { .main-container, .header-content, .navbar-inner { padding-left: 20px; padding-right: 20px; } }
        @media (max-width: 768px) {
            .header-content { flex-direction: column; gap: 12px; align-items: flex-start; padding: 12px 20px; }
            .navbar-menu a.nav-link.nav-logout { margin-left: 0; }
            .toolbar-row { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>

    <header class="header-main">
        <div class="header-accent-line"></div>
        <div class="header-content">
            <div class="header-left">
                <p>Pemantauan Sekolah</p>
                <h1>Bahan Ajar</h1>
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

    <nav class="navbar-menu">
        <div class="navbar-inner">
            <ul>
                <li><a href="index.php" class="nav-link">Home</a></li>
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

    <div class="main-container">

        <?php if ($pesan !== ""): ?>
            <div class="alert alert-<?php echo $pesan_tipe; ?>"><?php echo htmlspecialchars($pesan); ?></div>
        <?php endif; ?>

        <!-- FORM UPLOAD -->
        <div class="content-box">
            <div class="section-title">Upload Bahan Ajar Baru</div>
            <form action="bahan_ajar.php" method="POST" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Jurusan:</label>
                        <select name="id_jurusan" id="select_jurusan" required onchange="filterMapelOptions()">
                            <option value="">-- Pilih Jurusan --</option>
                            <?php foreach ($daftar_jurusan_all as $j): ?>
                                <option value="<?php echo htmlspecialchars($j['id_jurusan']); ?>">
                                    <?php echo htmlspecialchars($j['nama_jurusan']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Mapel:</label>
                        <select name="id_mapel" id="select_mapel" required>
                            <option value="">-- Pilih Jurusan Dulu --</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Judul Materi:</label>
                        <input type="text" name="judul_materi" required placeholder="Contoh: Bab 1 - Aljabar Linear">
                    </div>

                    <div class="form-group">
                        <label>File PDF:</label>
                        <input type="file" name="file_materi" accept="application/pdf" required>
                    </div>
                </div>

                <div style="margin-top: 20px;">
                    <button type="submit" name="upload_materi" class="btn btn-primary">Upload Materi</button>
                </div>
            </form>
        </div>

        <!-- TOOLBAR FILTER -->
        <div class="toolbar-row">
            <div class="filter-group">
                <label for="filter_jurusan">Filter Jurusan:</label>
                <select id="filter_jurusan" onchange="filterJurusanTabel(this.value)">
                    <option value="">Semua Jurusan</option>
                    <?php foreach ($daftar_jurusan_all as $j): ?>
                        <option value="<?php echo htmlspecialchars($j['id_jurusan']); ?>" <?php echo ($filter_jurusan === $j['id_jurusan']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($j['nama_jurusan']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($filter_jurusan !== ""): ?>
                    <a href="bahan_ajar.php" class="filter-reset">✕ Reset</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- TABEL BAHAN AJAR -->
        <div class="content-box">
            <div class="section-title">Daftar Bahan Ajar</div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th class="col-no">No</th>
                            <th>Jurusan</th>
                            <th>Mapel</th>
                            <th>Judul Materi</th>
                            <th>File</th>
                            <th>Tanggal Upload</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($daftar_bahan_ajar) > 0): ?>
                            <?php $no = 1; ?>
                            <?php foreach ($daftar_bahan_ajar as $b): ?>
                                <tr>
                                    <td class="col-no"><?php echo $no++; ?></td>
                                    <td><span class="badge-jurusan"><?php echo htmlspecialchars($b['nama_jurusan']); ?></span></td>
                                    <td><span class="badge-mapel"><?php echo htmlspecialchars($b['nama_mapel']); ?></span></td>
                                    <td><strong><?php echo htmlspecialchars($b['judul_materi']); ?></strong></td>
                                    <td>
                                        <a href="<?php echo htmlspecialchars($b['file_path']); ?>" target="_blank" class="file-link">
                                            📄 <?php echo htmlspecialchars($b['nama_file']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo date("d/m/Y H:i", strtotime($b['tanggal_upload'])); ?></td>
                                    <td>
                                        <form action="bahan_ajar.php" method="POST" style="display:inline;" onsubmit="return confirm('Yakin mau hapus materi &quot;<?php echo e($b['judul_materi']); ?>&quot;?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id_bahan" value="<?php echo e($b['id_bahan']); ?>">
                                            <button type="submit" class="btn-danger">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: var(--text-muted);">Belum ada bahan ajar yang diupload.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script>
        const semuaMapel = <?php echo json_encode($daftar_mapel_all); ?>;

        function filterMapelOptions() {
            const jurusanTerpilih = document.getElementById('select_jurusan').value;
            const selectMapel = document.getElementById('select_mapel');
            selectMapel.innerHTML = "";

            if (jurusanTerpilih === "") {
                selectMapel.innerHTML = '<option value="">-- Pilih Jurusan Dulu --</option>';
                return;
            }

            const mapelSesuai = semuaMapel.filter(m => m.id_jurusan === jurusanTerpilih);

            if (mapelSesuai.length === 0) {
                selectMapel.innerHTML = '<option value="">-- Belum ada mapel utk jurusan ini --</option>';
                return;
            }

            selectMapel.innerHTML = '<option value="">-- Pilih Mapel --</option>';
            mapelSesuai.forEach(m => {
                const opt = document.createElement('option');
                opt.value = m.id_mapel;
                opt.textContent = m.nama_mapel;
                selectMapel.appendChild(opt);
            });
        }

        function filterJurusanTabel(value) {
            if (value === "") {
                window.location.href = "bahan_ajar.php";
            } else {
                window.location.href = "bahan_ajar.php?filter_jurusan=" + encodeURIComponent(value);
            }
        }
    </script>
</body>
</html>