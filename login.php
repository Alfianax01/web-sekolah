<?php
require_once 'koneksi.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = "";

if (isset($_POST['login'])) {
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = "Username/NIP dan Password wajib diisi.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']      = $user['id'];
            $_SESSION['username']     = $user['username'];
            $_SESSION['role']         = $user['role'] ?? 'siswa';
            $_SESSION['nis']          = $user['nis'] ?? null;
            $_SESSION['nip']          = $user['nip'] ?? null;
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'] ?? $user['username'];

            header("Location: index.php");
            exit();
        } else {
            // Cek login khusus guru dengan NIP dan Nama Guru
            $stmt = $pdo->prepare("SELECT nip, nama_guru FROM guru WHERE nip = :nip AND LOWER(nama_guru) = LOWER(:nama_guru) LIMIT 1");
            $stmt->execute([':nip' => $username, ':nama_guru' => $password]);
            $guru = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($guru) {
                session_regenerate_id(true);
                // Cek apakah ada record di users
                $stmtU = $pdo->prepare("SELECT id FROM users WHERE nip = :nip LIMIT 1");
                $stmtU->execute([':nip' => $guru['nip']]);
                $uRec = $stmtU->fetch(PDO::FETCH_ASSOC);

                $_SESSION['user_id']      = $uRec ? $uRec['id'] : ('guru_' . $guru['nip']);
                $_SESSION['username']     = $guru['nip'];
                $_SESSION['role']         = 'guru';
                $_SESSION['nis']          = null;
                $_SESSION['nip']          = $guru['nip'];
                $_SESSION['nama_lengkap'] = $guru['nama_guru'];

                header("Location: index.php");
                exit();
            }

            $error = "Kombinasi Username/NIP atau Password tidak cocok.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Portal Akademik</title>
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
            padding: 24px;
        }

        .auth-card {
            background: #ffffff;
            width: 100%;
            max-width: 420px;
            padding: 38px 34px;
            border-radius: 18px;
            box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.08), 0 4px 10px -2px rgba(15, 23, 42, 0.04);
            border: 1px solid var(--border);
        }

        .brand-header {
            text-align: center;
            margin-bottom: 28px;
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
            margin-bottom: 12px;
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
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: var(--text-title);
            margin-bottom: 6px;
        }

        .input-wrapper {
            position: relative;
        }

        .form-control {
            width: 100%;
            height: 44px;
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

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-muted);
            font-size: 14px;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
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
            margin-top: 6px;
        }

        .btn-submit:hover {
            background: var(--accent-hover);
            transform: translateY(-1px);
        }

        .auth-footer {
            text-align: center;
            margin-top: 22px;
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
            <h1>Masuk ke Portal</h1>
            <p>Sistem Informasi Akademik & Pembelajaran</p>
        </div>

        <?php if ($error): ?>
            <div class="alert-error">
                <span>⚠️</span>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" autocomplete="off">
            <?php echo csrf_field(); ?>

            <div class="form-group">
                <label class="form-label" for="username">Username / NIP Guru</label>
                <div class="input-wrapper">
                    <input type="text" id="username" name="username" class="form-control" required placeholder="Masukkan username atau NIP" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" autofocus>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password / Nama Guru</label>
                <div class="input-wrapper">
                    <input type="password" id="password" name="password" class="form-control" required placeholder="Masukkan password">
                    <button type="button" class="toggle-password" onclick="togglePasswordVisibility()" title="Tampilkan/Sembunyikan Password">
                        👁️
                    </button>
                </div>
            </div>

            <button type="submit" name="login" class="btn-submit">Masuk Sekarang</button>
        </form>

        <div class="auth-footer">
            Belum memiliki akun? <a href="register.php">Daftar Akun Baru</a>
        </div>
    </div>

    <script>
        function togglePasswordVisibility() {
            const passInput = document.getElementById('password');
            if (passInput.type === 'password') {
                passInput.type = 'text';
            } else {
                passInput.type = 'password';
            }
        }
    </script>
</body>
</html>
