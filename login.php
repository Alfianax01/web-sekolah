<?php
session_start();
$nav_role = $_SESSION['role'] ?? 'siswa';
require_once 'koneksi.php';
// Jika sudah login, langsung tendang ke index
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = "";

if (isset($_POST['login'])) {
    verify_csrf();
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verifikasi user dan password
    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        // Set session
        $_SESSION['user_id']  = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role']     = $user['role'] ?? 'siswa';
        $_SESSION['nis']      = $user['nis'] ?? null;
        $_SESSION['nip']      = $user['nip'] ?? null;
        $_SESSION['nama_lengkap'] = $user['nama_lengkap'] ?? $user['username'];

        header("Location: index.php");
        exit();
    } else {
        $error = "Username atau password salah!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Academic System</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { 
            background: linear-gradient(135deg, #f4f6f9 0%, #e2e8f0 100%); /* Tema terang yang sama seperti Sign Up */
            display: flex; align-items: center; justify-content: center; min-height: 100vh; color: #1e293b; 
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
        }
        .form-control:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }

        .password-wrapper { position: relative; }
        .password-wrapper .form-control { padding-right: 44px; }
        .toggle-password {
            position: absolute;
            right: 6px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #94a3b8;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 6px;
            background: none;
            border: none;
            border-radius: 6px;
        }
        .toggle-password:hover { color: #475569; background: #f1f5f9; }
        .toggle-password svg { width: 20px; height: 20px; pointer-events: none; }
        
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
    </style>
</head>
<body>

    <div class="auth-card">
        <div class="auth-header">
            <h1>Selamat Datang</h1>
            <p>Sistem Informasi Akademik Sekolah</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <?php echo csrf_field(); ?>
            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" required placeholder="Masukkan username Anda">
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <div class="password-wrapper">
                    <input type="password" name="password" id="password" class="form-control" required placeholder="Masukkan password">
                    <button type="button" class="toggle-password" id="togglePasswordBtn" aria-label="Tampilkan password">
                        <svg id="eyeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </button>
                </div>
            </div>
            <button type="submit" name="login" class="btn-submit">Login ke Dashboard</button>
        </form>

        <div class="auth-footer">
            Belum punya akun? <a href="register.php">Daftar sekarang</a>
        </div>
    </div>

    <script>
        var toggleBtn = document.getElementById('togglePasswordBtn');
        var passwordInput = document.getElementById('password');
        var eyeIcon = document.getElementById('eyeIcon');

        var iconOpen = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
        var iconClosed = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';

        toggleBtn.addEventListener('click', function () {
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.innerHTML = iconClosed;
                toggleBtn.setAttribute('aria-label', 'Sembunyikan password');
            } else {
                passwordInput.type = 'password';
                eyeIcon.innerHTML = iconOpen;
                toggleBtn.setAttribute('aria-label', 'Tampilkan password');
            }
        });
    </script>

</body>
</html>