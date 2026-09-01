<?php
require_once 'koneksi.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = "";
$success = "";

if (isset($_POST['register'])) {
    verify_csrf();
    $username         = trim($_POST['username'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role             = $_POST['role'] ?? 'siswa';
    $nis              = trim($_POST['nis'] ?? '');
    $nip              = trim($_POST['nip'] ?? '');
    $local_request    = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);

    if (empty($username) || empty($password)) {
        $error = "Username dan Password wajib diisi.";
    } elseif (!in_array($role, ['siswa', 'guru', 'admin'], true)) {
        $error = "Role yang dipilih tidak valid.";
    } elseif ($role === 'admin' && !$local_request) {
        $error = "Pendaftaran admin hanya dapat dilakukan dari server lokal.";
    } elseif (strlen($password) < 8) {
        $error = "Password minimal 8 karakter.";
    } elseif ($password !== $confirm_password) {
        $error = "Konfirmasi password tidak cocok.";
    } elseif ($role === 'siswa' && empty($nis)) {
        $error = "Nomor Induk Siswa (NIS) wajib diisi untuk siswa.";
    } elseif ($role === 'guru' && empty($nip)) {
        $error = "Nomor Induk Pegawai (NIP) wajib diisi untuk guru.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username LIMIT 1");
        $stmt->execute([':username' => $username]);

        if ($stmt->fetch()) {
            $error = "Username sudah digunakan, silakan gunakan username lain.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("INSERT INTO users (username, password, role, nis, nip, nama_lengkap) VALUES (:username, :password, :role, :nis, :nip, :nama_lengkap)");
            $result = $stmt->execute([
                ':username'     => $username,
                ':password'     => $hashed_password,
                ':role'         => $role,
                ':nis'          => ($role === 'siswa' ? $nis : null),
                ':nip'          => ($role === 'guru' ? $nip : null),
                ':nama_lengkap' => $username
            ]);

            if ($result) {
                $success = "Pendaftaran berhasil! Akun Anda telah aktif. Silakan <a href='login.php'>Masuk ke akun</a>.";
            } else {
                $error = "Gagal mendaftar, terjadi kesalahan pada server.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun - Portal Akademik</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1e293b;
            --accent: #2563eb;
            --accent-hover: #1d4ed8;
            --bg-canvas: #f8fafc;
            --border: #e2e8f0;
            --text-title: #0f172a;
            --text-body: #334155;
            --text-muted: #64748b;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            color: var(--text-body);
            padding: 30px 20px;
        }

        .auth-card {
            background: #ffffff;
            width: 100%;
            max-width: 440px;
            padding: 38px 34px;
            border-radius: 18px;
            box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.08), 0 4px 10px -2px rgba(15, 23, 42, 0.04);
            border: 1px solid var(--border);
        }

        .brand-header {
            text-align: center;
            margin-bottom: 24px;
        }

        .brand-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            color: white;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 10px;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.3);
        }

        .brand-header h1 {
            font-size: 22px;
            font-weight: 800;
            color: var(--text-title);
            letter-spacing: -0.3px;
        }

        .brand-header p {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 18px;
        }

        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 18px;
        }
        .alert-success a {
            color: #15803d;
            font-weight: 700;
            text-decoration: underline;
        }

        .form-group {
            margin-bottom: 16px;
        }

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
            border-radius: 10px;
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

        .btn-submit {
            width: 100%;
            height: 46px;
            background: var(--accent);
            color: #ffffff;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.25);
            margin-top: 8px;
        }

        .btn-submit:hover {
            background: var(--accent-hover);
            transform: translateY(-1px);
        }

        .auth-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
            color: var(--text-muted);
        }

        .auth-footer a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 700;
        }

        .auth-footer a:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            body { padding: 16px 12px; }
            .auth-card { padding: 26px 20px; border-radius: 14px; }
            .brand-header h1 { font-size: 20px; }
        }
    </style>
</head>
<body>

    <div class="auth-card">
        <div class="brand-header">
            <div class="brand-icon">🎓</div>
            <h1>Buat Akun Baru</h1>
            <p>Pendaftaran Sistem Informasi Akademik</p>
        </div>

        <?php if ($error): ?>
            <div class="alert-error">⚠️ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert-success">✅ <?php echo $success; ?></div>
        <?php endif; ?>

        <form action="register.php" method="POST" autocomplete="off">
            <?php echo csrf_field(); ?>

            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" required placeholder="Buat username baru" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Daftar Sebagai</label>
                <select name="role" id="role-select" class="form-control" onchange="toggleRoleField()" required>
                    <option value="siswa" <?php echo (($_POST['role'] ?? '') === 'siswa') ? 'selected' : ''; ?>>Siswa</option>
                    <option value="guru" <?php echo (($_POST['role'] ?? '') === 'guru') ? 'selected' : ''; ?>>Guru</option>
                    <option value="admin" <?php echo (($_POST['role'] ?? '') === 'admin') ? 'selected' : ''; ?>>Admin</option>
                </select>
            </div>

            <div class="form-group" id="nis-field">
                <label class="form-label">Nomor Induk Siswa (NIS)</label>
                <input type="text" name="nis" class="form-control" placeholder="Contoh: 242511001" value="<?php echo htmlspecialchars($_POST['nis'] ?? ''); ?>">
            </div>

            <div class="form-group" id="nip-field" style="display: none;">
                <label class="form-label">Nomor Induk Pegawai (NIP)</label>
                <input type="text" name="nip" class="form-control" placeholder="Contoh: G01" value="<?php echo htmlspecialchars($_POST['nip'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Password (Minimal 8 Karakter)</label>
                <input type="password" name="password" class="form-control" required placeholder="Buat password aman">
            </div>

            <div class="form-group">
                <label class="form-label">Konfirmasi Password</label>
                <input type="password" name="confirm_password" class="form-control" required placeholder="Ulangi password">
            </div>

            <button type="submit" name="register" class="btn-submit">Daftar Sekarang</button>
        </form>

        <div class="auth-footer">
            Sudah memiliki akun? <a href="login.php">Masuk di sini</a>
        </div>
    </div>

    <script>
        function toggleRoleField() {
            const role = document.getElementById('role-select').value;
            document.getElementById('nis-field').style.display = (role === 'siswa') ? 'block' : 'none';
            document.getElementById('nip-field').style.display = (role === 'guru') ? 'block' : 'none';
        }
        toggleRoleField();
    </script>
</body>
</html>
