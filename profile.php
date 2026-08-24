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
            // Ambil data dari tabel siswa khusus untuk role siswa
            $stmtSiswa = $pdo->prepare("SELECT * FROM siswa WHERE nama_siswa = :nama LIMIT 1");
            $stmtSiswa->execute([':nama' => $user['username']]);
            $siswaData = $stmtSiswa->fetch(PDO::FETCH_ASSOC);

            if ($siswaData) {
                $user['nama_lengkap'] = $siswaData['nama_siswa'];
                $user['kelas'] = $siswaData['kelas'] ?? '-';
                $user['ttl'] = $siswaData['ttl'] ?? '-';
            } else {
                $user['nama_lengkap'] = $user['nama_lengkap'] ?? $user['username'];
                $user['kelas'] = '-';
                $user['ttl'] = '-';
            }
        } else {
            // Untuk Admin/Guru, ambil langsung dari tabel users
            $user['nama_lengkap'] = $user['nama_lengkap'] ?? $user['username'];
            $user['ttl'] = $user['ttl'] ?? '-';
            $user['kelas'] = ''; // Admin tidak pakai kelas
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

// 2. HITUNG STATISTIK
try {
    $total_tugas       = $pdo->query("SELECT COUNT(*) FROM tugas")->fetchColumn();
    $total_pengumpulan = $pdo->query("SELECT COUNT(*) FROM pengumpulan_tugas")->fetchColumn();
} catch (PDOException $e) {
    $total_tugas       = 0;
    $total_pengumpulan = 0;
}

// 3. PROSES SIMPAN EDIT PROFIL & FOTO
if (isset($_POST['update_profile'])) {
    verify_csrf();

    $email         = trim($_POST['email'] ?? '');
    $nama_lengkap  = trim($_POST['nama_lengkap'] ?? $user['nama_lengkap']);
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
        $file_tmp  = $_FILES['foto_profil']['tmp_name'];
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
            $error_msg = "File foto tidak valid. Gunakan JPG, PNG, atau GIF.";
        } else {
            $nama_file_foto = 'profile_' . $user_id . '_' . bin2hex(random_bytes(12)) . '.' . $allowed_mime[$mime];
            $upload_dir = __DIR__ . '/uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            if (!move_uploaded_file($file_tmp, $upload_dir . $nama_file_foto)) {
                $error_msg = "Foto gagal disimpan.";
                $nama_file_foto = $user['foto'] ?? '';
            }
        }
    }

    if (empty($error_msg)) {
        try {
            // Update database (admin bisa update nama_lengkap & ttl sendiri)
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

            $success_msg = "Profil berhasil diperbarui!";
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error_msg = "Profil gagal diperbarui.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - Pemantauan Sekolah</title>
    <style>
        :root {
            --primary: #1e293b; --primary-light: #334155; --accent: #2563eb;
            --bg-main: #f8fafc; --bg-card: #ffffff; --text-main: #334155;
            --text-muted: #64748b; --border: #e2e8f0; --success: #10b981; --danger: #ef4444;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: var(--bg-main); color: var(--text-main); line-height: 1.6; }

        .header-main { display: flex; align-items: stretch; background: #ffffff; border-bottom: 1px solid var(--border); box-shadow: 0 2px 10px rgba(0,0,0,0.02); position: sticky; top: 0; z-index: 100; width: 100%; }
        .header-accent-line { width: 6px; background-color: var(--accent); flex-shrink: 0; }
        .header-content { flex: 1; display: flex; justify-content: space-between; align-items: center; padding: 12px 40px; max-width: 1400px; margin: 0 auto; }
        .header-left h1 { color: var(--primary); font-size: 18px; font-weight: 800; text-transform: uppercase; }
        .header-left p { color: var(--text-muted); font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .header-right { display: flex; align-items: center; gap: 12px; }
        .user-badge { display: flex; align-items: center; gap: 8px; background: var(--bg-main); padding: 5px 12px 5px 5px; border-radius: 30px; border: 1px solid var(--border); }
        .user-avatar { background: var(--accent); color: white; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: bold; overflow: hidden; flex-shrink: 0; border: 2px solid #fff; }
        .user-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .user-info { display: flex; flex-direction: column; }
        .user-role { color: var(--primary); font-size: 11px; font-weight: 700; text-transform: uppercase; line-height: 1; margin-bottom: 2px; }
        .user-status { color: var(--success); font-size: 9px; font-weight: 600; }
        .header-date { color: var(--text-main); font-size: 12px; font-weight: 600; background: var(--bg-main); padding: 6px 12px; border-radius: 6px; border: 1px solid var(--border); }

        .navbar-menu { background-color: var(--primary); width: 100%; }
        .navbar-inner { max-width: 1400px; margin: 0 auto; padding: 0 40px; }
        .navbar-menu ul { list-style: none; display: flex; align-items: center; gap: 2px; overflow-x: auto; }
        .navbar-menu a.nav-link { display: inline-block; color: rgba(255, 255, 255, 0.75); text-decoration: none; font-weight: 600; font-size: 12px; text-transform: uppercase; padding: 12px 16px; border-bottom: 3px solid transparent; }
        .navbar-menu a.nav-link:hover { color: white; background-color: rgba(255, 255, 255, 0.05); }
        .navbar-menu a.nav-link.active { color: white; border-bottom-color: var(--accent); }
        .navbar-menu a.nav-link.nav-logout { color: #fca5a5; margin-left: auto; }

        .main-container { max-width: 1400px; margin: 25px auto 40px; padding: 0 40px; width: 100%; }
        .content-box { background: var(--bg-card); padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); border: 1px solid var(--border); margin-bottom: 25px; }
        .section-title { font-size: 16px; color: var(--primary); font-weight: 800; border-bottom: 2px solid var(--bg-main); padding-bottom: 10px; margin-bottom: 20px; }

        .alert { padding: 12px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; margin-bottom: 20px; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        .profile-wrapper { display: grid; grid-template-columns: 320px 1fr; gap: 30px; align-items: start; }
        .profile-card-summary { background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; padding: 25px 20px; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 15px; }
        
        .profile-avatar-large {
            width: 90px; height: 90px; border-radius: 50%; background: var(--accent); color: white;
            font-size: 36px; font-weight: 800; display: flex; align-items: center; justify-content: center;
            box-shadow: 0 4px 12px rgba(37,99,235,0.3); overflow: hidden;
        }
        .profile-avatar-large img { width: 100%; height: 100%; object-fit: cover; }

        .profile-name { font-size: 16px; font-weight: 800; color: var(--primary); }
        .profile-username { font-size: 12px; color: var(--text-muted); font-weight: 600; margin-top: 2px; }
        .profile-role-badge { background: var(--primary); color: white; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; margin-top: 4px; }

        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; width: 100%; margin-top: 10px; border-top: 1px solid var(--border); padding-top: 15px; }
        .stat-card { background: #ffffff; border: 1px solid var(--border); padding: 12px; border-radius: 8px; text-align: center; }
        .stat-num { font-size: 18px; font-weight: 800; color: var(--accent); }
        .stat-label { font-size: 11px; color: var(--text-muted); font-weight: 600; margin-top: 2px; text-transform: uppercase; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group.full-width { grid-column: span 2; }
        .form-group label { font-size: 12px; font-weight: 700; color: var(--primary); margin-bottom: 6px; text-transform: uppercase; }
        .form-group input { height: 42px; border: 1px solid var(--border); border-radius: 6px; padding: 0 14px; font-size: 13px; background: var(--bg-main); color: var(--text-main); }
        .form-group input:focus { outline: none; border-color: var(--accent); background: white; }
        .form-group input[readonly] { background: #e2e8f0; color: var(--text-muted); cursor: not-allowed; }
        .form-hint { font-size: 11px; color: var(--text-muted); margin-top: 4px; }

        .btn { border: none; cursor: pointer; text-decoration: none; display: inline-block; padding: 10px 20px; border-radius: 6px; font-size: 12px; font-weight: 700; text-transform: uppercase; background: var(--accent); color: white; }
        .btn:hover { background: #1d4ed8; }

        @media (max-width: 992px) { .profile-wrapper { grid-template-columns: 1fr; } .form-grid { grid-template-columns: 1fr; } .form-group.full-width { grid-column: span 1; } }
    </style>
</head>
<body>

    <!-- HEADER UTAMA -->
    <header class="header-main">
        <div class="header-accent-line"></div>
        <div class="header-content">
            <div class="header-left">
                <p>Pemantauan Sekolah</p>
                <h1>Profil Saya</h1>
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
                        <?php if (!empty($user['foto']) && file_exists('uploads/' . $user['foto'])): ?>
                            <img src="uploads/<?php echo htmlspecialchars($user['foto']); ?>" alt="Foto">
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
                <li><a href="index.php" class="nav-link">Home</a></li>
                <?php if ($nav_role !== 'siswa'): ?>
                <li><a href="siswa.php" class="nav-link">Data Siswa</a></li>
                <li><a href="guru.php" class="nav-link">Data Pengajar</a></li>
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

    <!-- KONTEN UTAMA -->
    <div class="main-container">
        <div class="content-box">
            <div class="section-title">Pengaturan Akun & Ringkasan</div>

            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div>
            <?php endif; ?>

            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_msg); ?></div>
            <?php endif; ?>

            <div class="profile-wrapper">
                <!-- RINGKASAN PROFIL & FOTO -->
                <div class="profile-card-summary">
                    <div class="profile-avatar-large">
                        <?php if (!empty($user['foto']) && file_exists('uploads/' . $user['foto'])): ?>
                            <img src="uploads/<?php echo htmlspecialchars($user['foto']); ?>" alt="Foto Profil">
                        <?php else: ?>
                            <?php 
                                $nama_tampil = $user['nama_lengkap'] ?? $user['username'] ?? 'U';
                                echo strtoupper(substr($nama_tampil, 0, 1)); 
                            ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="profile-name"><?php echo htmlspecialchars($user['nama_lengkap']); ?></div>
                        <div class="profile-username">@<?php echo htmlspecialchars($user['username'] ?? 'user'); ?></div>
                        <div class="profile-role-badge"><?php echo htmlspecialchars($nav_role); ?></div>
                    </div>

                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-num"><?php echo $total_tugas; ?></div>
                            <div class="stat-label">Total Tugas</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-num"><?php echo $total_pengumpulan; ?></div>
                            <div class="stat-label">Pengumpulan</div>
                        </div>
                    </div>
                </div>

                <!-- FORM EDIT PROFIL -->
                <div>
                    <form action="profile.php" method="POST" enctype="multipart/form-data">
                        <?php echo csrf_field(); ?>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Username (Read-Only)</label>
                                <input type="text" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" readonly>
                            </div>

                            <div class="form-group">
                                <label>Nama Lengkap <?php echo ($nav_role === 'siswa') ? '(Read-Only dari Database Siswa)' : ''; ?></label>
                                <input type="text" name="nama_lengkap" value="<?php echo htmlspecialchars($user['nama_lengkap']); ?>" <?php echo ($nav_role === 'siswa') ? 'readonly' : ''; ?>>
                            </div>

                            <div class="form-group">
                                <label>TTL (Tempat, Tanggal Lahir)</label>
                                <input type="text" name="ttl" value="<?php echo htmlspecialchars($user['ttl'] ?? '-'); ?>" placeholder="Contoh: Jakarta, 17 Agustus 2005" <?php echo ($nav_role === 'siswa') ? 'readonly' : ''; ?>>
                            </div>

                            <?php if ($nav_role === 'siswa'): ?>
                            <div class="form-group">
                                <label>Kelas</label>
                                <input type="text" value="<?php echo htmlspecialchars($user['kelas'] ?? '-'); ?>" readonly>
                            </div>
                            <?php endif; ?>

                            <div class="form-group full-width">
                                <label>Alamat Email</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" placeholder="nama@email.com">
                            </div>

                            <div class="form-group full-width">
                                <label>Ganti Foto Profil</label>
                                <input type="file" name="foto_profil" accept="image/*">
                                <span class="form-hint">Format file: JPG, JPEG, PNG. Biarkan kosong jika tidak ingin mengubah foto.</span>
                            </div>

                            <div class="form-group full-width">
                                <label>Password Baru (Opsional)</label>
                                <input type="password" name="password_baru" placeholder="Ketik password baru jika ingin mengganti">
                                <span class="form-hint">Biarkan kosong jika tidak ingin merubah password.</span>
                            </div>
                        </div>

                        <div style="margin-top: 25px;">
                            <button type="submit" name="update_profile" class="btn">Simpan Perubahan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

</body>
</html>