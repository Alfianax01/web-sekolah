<?php
session_start();
$nav_role = $_SESSION['role'] ?? 'siswa';
require_once 'koneksi.php';

// Jika admin atau guru mencoba akses langsung URL pelajaran.php, tendang/alihkan ke index.php
if ($nav_role !== 'siswa') {
    header("Location: index.php");
    exit;
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

// ==========================================
// AMBIL DAFTAR MAPEL SESUAI JURUSAN SISWA
// ==========================================
$daftar_mapel = [];
if ($id_jurusan_siswa) {
    $stmt = $pdo->prepare("SELECT id_mapel, nama_mapel FROM mapel WHERE id_jurusan = :id_jurusan ORDER BY nama_mapel ASC");
    $stmt->execute([':id_jurusan' => $id_jurusan_siswa]);
    $daftar_mapel = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ==========================================
// AMBIL SEMUA BAHAN AJAR UNTUK JURUSAN INI, DIKELOMPOKKAN PER MAPEL
// ==========================================
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
    <title>Pelajaran - Pemantauan Sekolah</title>
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
            background: var(--accent); 
            color: white; 
            width: 32px;
            height: 32px;
            border-radius: 50%; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 14px; 
            font-weight: bold;
            overflow: hidden;
            flex-shrink: 0;
            border: 2px solid #fff;
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
        .section-sub { font-size: 12px; font-weight: 600; color: var(--text-muted); text-transform: none; }

        .table-responsive { overflow-x: auto; width: 100%; }
        table { width: 100%; border-collapse: collapse; min-width: 700px; }
        th { background: var(--bg-main); color: var(--primary); font-size: 12px; font-weight: 800; text-align: left; padding: 12px 14px; border-bottom: 2px solid var(--border); text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 12px 14px; font-size: 13px; border-bottom: 1px solid var(--border); color: var(--text-main); vertical-align: top; }
        tbody tr:hover { background-color: var(--bg-main); }
        td strong { color: var(--primary); font-weight: 700; }
        .col-no { width: 50px; text-align: center; color: var(--text-muted); font-weight: 700; }

        .badge-jurusan { background: #ede9fe; color: #5b21b6; padding: 3px 10px; border-radius: 4px; font-weight: 700; font-size: 11px; }
        .badge-mapel { background: #e0f2fe; color: #0369a1; padding: 3px 10px; border-radius: 4px; font-weight: 600; font-size: 11px; }

        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .status-ada { background: #dcfce7; color: #166534; }
        .status-kosong { background: #fee2e2; color: #991b1b; }

        .file-list { display: flex; flex-direction: column; gap: 6px; }
        .file-item { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .file-link { color: var(--accent); text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; font-size: 12px; }
        .file-link:hover { text-decoration: underline; }
        .file-empty { color: var(--text-muted); font-size: 12px; font-style: italic; }

        @media (max-width: 992px) { .main-container, .header-content, .navbar-inner { padding-left: 20px; padding-right: 20px; } }
        @media (max-width: 768px) { .header-content { flex-direction: column; gap: 12px; align-items: flex-start; padding: 12px 20px; } .navbar-menu a.nav-link.nav-logout { margin-left: 0; } }
    </style>
</head>
<body>

    <header class="header-main">
        <div class="header-accent-line"></div>
        <div class="header-content">
            <div class="header-left">
                <p>Pemantauan Sekolah</p>
                <h1>Pelajaran</h1>
            </div>
            <div class="header-right">
                <div class="header-date">
                    <?php
                    $hari = array("Minggu","Senin","Selasa","Rabu","Kamis","Jumat","Sabtu");
                    $bulan = array("","Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember");
                    echo $hari[date("w")] . ", " . date("j") . " " . $bulan[date("n")] . " " . date("Y");
                    ?>
                </div>
                <div class="user-badge" style="display: flex; align-items: center; gap: 10px; background: #fff; padding: 5px 15px; border-radius: 50px; border: 1px solid var(--border);">
    <div class="user-avatar">
        <?php if (!empty($foto_user) && file_exists('uploads/' . $foto_user)): ?>
            <img src="uploads/<?php echo htmlspecialchars($foto_user); ?>" alt="Foto">
        <?php else: ?>
            <?php echo strtoupper(substr($nav_role, 0, 1)); ?>
        <?php endif; ?>
    </div>
    <div class="user-info">
        <span class="user-role" style="font-size: 10px; font-weight: 800; color: var(--primary);"><?php echo htmlspecialchars($nav_role); ?></span>
        <span class="user-status" style="color: #10b981; font-size: 9px; font-weight: 600;">● Online</span>
    </div>
</div>
    </header>

    <nav class="navbar-menu">
        <div class="navbar-inner">
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

                <!-- Menu Pelajaran HANYA MUNCUL DI AKUN SISWA -->
                <?php if ($nav_role === 'siswa'): ?>
                <li><a href="pelajaran.php" class="nav-link active">Pelajaran</a></li>
                <?php endif; ?>

                <li><a href="profile.php" class="nav-link">Profil Saya</a></li>
                <li><a href="logout.php" class="nav-link nav-logout">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="main-container">

        <div class="content-box">
            <div class="section-title">
                Bahan Ajar Jurusan Saya
                <span class="section-sub">
                    Jurusan: <span class="badge-jurusan"><?php echo htmlspecialchars($nama_jurusan_siswa); ?></span>
                </span>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th class="col-no">No</th>
                            <th>Jurusan</th>
                            <th>Mapel</th>
                            <th>Bahan Ajar</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$id_jurusan_siswa): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: var(--text-muted);">
                                    Data jurusan tidak ditemukan. Hubungi admin untuk memastikan akun kamu terhubung ke data jurusan.
                                </td>
                            </tr>
                        <?php elseif (count($daftar_mapel) === 0): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: var(--text-muted);">
                                    Belum ada mapel yang terdaftar untuk jurusan ini.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $no = 1; ?>
                            <?php foreach ($daftar_mapel as $mp): ?>
                                <?php
                                    $files = $bahan_per_mapel[$mp['id_mapel']] ?? [];
                                    $jumlah = count($files);
                                ?>
                                <tr>
                                    <td class="col-no"><?php echo $no++; ?></td>
                                    <td><span class="badge-jurusan"><?php echo htmlspecialchars($nama_jurusan_siswa); ?></span></td>
                                    <td><span class="badge-mapel"><?php echo htmlspecialchars($mp['nama_mapel']); ?></span></td>
                                    <td>
                                        <?php if ($jumlah > 0): ?>
                                            <div class="file-list">
                                                <?php foreach ($files as $f): ?>
                                                    <div class="file-item">
                                                        <a href="<?php echo htmlspecialchars($f['file_path']); ?>" target="_blank" class="file-link">
                                                            📄 <?php echo htmlspecialchars($f['judul_materi']); ?>
                                                        </a>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="file-empty">Belum ada materi diupload</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($jumlah > 0): ?>
                                            <span class="status-badge status-ada"><?php echo $jumlah; ?> Materi Tersedia</span>
                                        <?php else: ?>
                                            <span class="status-badge status-kosong">Belum Ada</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</body>
</html>