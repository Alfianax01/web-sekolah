<?php
session_start();
require_once 'koneksi.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = "";
$success = "";

if (isset($_POST['register'])) {
    verify_csrf();
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role     = $_POST['role'] ?? 'siswa';
    $nis      = trim($_POST['nis'] ?? '');
    $nip      = trim($_POST['nip'] ?? '');

    if ($role !== 'siswa') {
        $error = "Pendaftaran publik hanya tersedia untuk akun siswa.";
    } elseif (strlen($password) < 8) {
        $error = "Password minimal 8 karakter.";
    } elseif ($password !== $confirm_password) {
        $error = "Konfirmasi password tidak cocok!";
    } elseif ($role === 'siswa' && empty($nis)) {
        $error = "NIS wajib diisi untuk role siswa!";
    } elseif ($role === 'guru' && empty($nip)) {
        $error = "NIP wajib diisi untuk role guru!";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username LIMIT 1");
        $stmt->execute([':username' => $username]);

        if ($stmt->fetch()) {
            $error = "Username sudah digunakan!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("INSERT INTO users (username, password, role, nis) VALUES (:username, :password, :role, :nis)");
            $result = $stmt->execute([
    ':username' => $username,
    ':password' => $hashed_password,
    ':role'     => $role,
    ':nis'      => $role === 'siswa' ? $nis : null
]);

            if ($result) {
                $success = "Akun berhasil dibuat! Silakan <a href='login.php'>login</a>.";
            } else {
                $error = "Gagal mendaftar, terjadi kesalahan sistem.";
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
    <title>Sign Up - Academic System</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { 
            background: linear-gradient(135deg, #f4f6f9 0%, #e2e8f0 100%); 
            display: flex; align-items: center; justify-content: center; min-height: 100vh; color: #1e293b; 
            padding: 30px 0;
        }
        .auth-card { 
            background: #ffffff; width: 100%; max-width: 400px; padding: 40px; 
            border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); 
        }
        .auth-header { text-align: center; margin-bottom: 30px; }
        .auth-header h1 { font-size: 24px; font-weight: 700; color: #0f172a; margin-bottom: 8px; }
        .auth-header p { font-size: 14px; color: #64748b; }
        
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 8px; }
        .form-control { 
            width: 100%; padding: 12px 16px; font-size: 14px; border-radius: 8px; 
            border: 1px solid #e2e8f0; transition: 0.2s; outline: none; 
            font-family: inherit;
        }
        .form-control:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
        
        .btn-submit { 
            width: 100%; background: #2563eb; color: #fff; border: none; padding: 14px; 
            border-radius: 8px; font-weight: 600; font-size: 15px; cursor: pointer; transition: 0.2s; 
        }
        .btn-submit:hover { background: #1d4ed8; }
        
        .auth-footer { text-align: center; margin-top: 24px; font-size: 14px; color: #64748b; }
        .auth-footer a { color: #2563eb; text-decoration: none; font-weight: 600; }
        .auth-footer a:hover { text-decoration: underline; }
        
        .alert { padding: 12px; border-radius: 8px; font-size: 13px; font-weight: 500; margin-bottom: 20px; text-align: center; }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .alert-success a { color: #15803d; font-weight: 600; }
    </style>
</head>
<body>

    <div class="auth-card">
        <div class="auth-header">
            <h1>Buat Akun Baru</h1>
            <p>Sistem Informasi Akademik Sekolah</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form action="register.php" method="POST">
            <?php echo csrf_field(); ?>
            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" required placeholder="Buat username baru" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Daftar Sebagai</label>
                <select name="role" id="role-select" class="form-control" onchange="toggleRoleField()" required>
                    <option value="siswa" <?php echo (($_POST['role'] ?? '') === 'siswa') ? 'selected' : ''; ?>>Siswa</option>
                </select>
            </div>

            <div class="form-group" id="nis-field">
                <label class="form-label">NIS</label>
                <input type="text" name="nis" class="form-control" placeholder="Masukkan NIS kamu" value="<?php echo htmlspecialchars($_POST['nis'] ?? ''); ?>">
            </div>

            <div class="form-group" id="nip-field" style="display:none;">
                <label class="form-label">NIP</label>
                <input type="text" name="nip" class="form-control" placeholder="Masukkan NIP kamu" value="<?php echo htmlspecialchars($_POST['nip'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required placeholder="Buat password">
            </div>
            <div class="form-group">
                <label class="form-label">Konfirmasi Password</label>
                <input type="password" name="confirm_password" class="form-control" required placeholder="Ulangi password">
            </div>
            <button type="submit" name="register" class="btn-submit">Daftar Sekarang</button>
        </form>

        <div class="auth-footer">
            Sudah punya akun? <a href="login.php">Login di sini</a>
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