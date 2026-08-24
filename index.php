<?php
session_start();
$nav_role = $_SESSION['role'] ?? 'admin';
require_once 'koneksi.php';

// AMBIL DATA FOTO USER UNTUK HEADER
$foto_user = '';
if (isset($_SESSION['user_id'])) {
    try {
        $stmtFoto = $pdo->prepare("SELECT foto FROM users WHERE id = :id LIMIT 1");
        $stmtFoto->execute([':id' => $_SESSION['user_id']]);
        $userData = $stmtFoto->fetch(PDO::FETCH_ASSOC);
        if ($userData && !empty($userData['foto'])) {
            $foto_user = $userData['foto'];
        }
    } catch (Exception $e) {
        $foto_user = '';
    }
}

try { $total_siswa = $pdo->query("SELECT COUNT(DISTINCT nis) FROM siswa")->fetchColumn(); } catch (Exception $e) { $total_siswa = 0; }
try { $total_guru = $pdo->query("SELECT COUNT(*) FROM guru")->fetchColumn(); } catch (Exception $e) { $total_guru = 0; }
try { $total_mapel = $pdo->query("SELECT COUNT(*) FROM mapel")->fetchColumn(); } catch (Exception $e) { $total_mapel = 0; }
try { $total_jurusan = $pdo->query("SELECT COUNT(*) FROM jurusan")->fetchColumn(); } catch (Exception $e) { $total_jurusan = 0; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard & Portal - Pemantauan Sekolah</title>
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

        .header-left h1 { color: var(--primary); font-size: 18px; font-weight: 800; letter-spacing: 0.5px; text-transform: uppercase; line-height: 1.2; }
        .header-left p { color: var(--text-muted); font-size: 11px; font-weight: 600; letter-spacing: 0.5px; margin-bottom: 2px; text-transform: uppercase; }

        .header-right { display: flex; align-items: center; gap: 12px; }

        .lang-select-box select {
            background: var(--bg-main); color: var(--text-main); border: 1px solid var(--border);
            padding: 6px 10px; border-radius: 6px; font-size: 12px; font-weight: 600;
            outline: none; cursor: pointer;
        }

        .user-badge {
            display: flex; align-items: center; gap: 8px;
            background: var(--bg-main); padding: 5px 12px 5px 5px;
            border-radius: 30px; border: 1px solid var(--border);
        }
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
            border: 2px solid #fff; /* Opsional: memberikan sedikit jarak dengan background */
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

        /* STATS GRID */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; width: 100%; }
        .card { 
            background: var(--bg-card); border-radius: 10px; padding: 20px; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.02); border: 1px solid var(--border); 
            border-left: 4px solid var(--primary); transition: transform 0.2s; text-decoration: none; color: inherit; display: block;
        }
        .card:hover { transform: translateY(-3px); }
        .card.siswa { border-left-color: #3b82f6; }
        .card.guru { border-left-color: #10b981; }
        .card.mapel { border-left-color: #f59e0b; }
        .card.jurusan { border-left-color: #8b5cf6; }
        .card h3 { font-size: 12px; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .card .value { font-size: 28px; font-weight: 800; color: var(--primary); margin-top: 6px; line-height: 1; }

        /* TENTANG SEKOLAH (GRID 1:1) */
        .school-profile-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; align-items: center; width: 100%; }
        .school-img-container { width: 100%; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.06); }
        .school-img-container img { width: 100%; height: auto; aspect-ratio: 16/9; object-fit: cover; display: block; }
        .school-desc p { font-size: 14px; color: var(--text-main); line-height: 1.7; margin-bottom: 14px; }
        .school-desc p:last-child { margin-bottom: 0; }
        .school-desc strong { color: var(--primary); font-weight: 700; }

        /* PORTAL GRID BAWAH */
        .portal-grid { display: grid; grid-template-columns: 1.4fr 1fr; gap: 25px; width: 100%; }
        
        /* TATA TERTIB */
        .rules-list { list-style-type: none; width: 100%; }
        .rules-list li { 
            padding: 12px 14px; border-bottom: 1px solid var(--border); font-size: 13.5px; 
            display: flex; align-items: flex-start; gap: 14px; border-radius: 6px;
        }
        .rules-list li:hover { background-color: var(--bg-main); }
        .rules-list li:last-child { border-bottom: none; }
        .badge-num { 
            background-color: #eff6ff; color: var(--accent); font-weight: 800; font-size: 12px; 
            width: 26px; height: 26px; border-radius: 6px; display: inline-flex; align-items: center; 
            justify-content: center; flex-shrink: 0; 
        }
        .rules-list strong { color: var(--primary); }

        /* VISI & PENGUMUMAN */
        .vision-box { 
            background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; 
            padding: 20px; border-radius: 10px; font-size: 14px; line-height: 1.6; text-align: center; 
            box-shadow: 0 4px 15px rgba(30, 41, 59, 0.15); margin-bottom: 25px;
        }
        .vision-box strong { display: block; font-size: 15px; font-style: italic; letter-spacing: 0.3px; }

        .announcement-card { 
            background-color: var(--bg-main); border: 1px solid var(--border); border-left: 4px solid var(--accent); 
            padding: 14px; border-radius: 6px; margin-bottom: 14px; 
        }
        .announcement-card:last-child { margin-bottom: 0; }
        .announcement-card .date { font-size: 11px; color: var(--text-muted); font-weight: 700; margin-bottom: 4px; text-transform: uppercase; }
        .announcement-card h4 { font-size: 14px; color: var(--primary); margin-bottom: 6px; line-height: 1.3; }
        .announcement-card p { font-size: 13px; color: var(--text-main); line-height: 1.4; }

        /* RESPONSIVE LAYOUT */
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 992px) { 
            .portal-grid { grid-template-columns: 1fr; } 
            .school-profile-grid { grid-template-columns: 1fr; }
            .main-container, .header-content, .navbar-inner { padding-left: 20px; padding-right: 20px; }
        }
        @media (max-width: 768px) { 
            .stats-grid { grid-template-columns: 1fr; }
            .header-content { flex-direction: column; gap: 12px; align-items: flex-start; padding: 12px 20px; }
            .navbar-menu a.nav-link.nav-logout { margin-left: 0; }
        }
    </style>
</head>
<body>

    <header class="header-main">
        <div class="header-accent-line"></div>
        <div class="header-content">
            <div class="header-left">
                <p data-translate="sub_header">Sistem Informasi Akademik</p>
                <h1 data-translate="main_title">Portal Utama Sekolah</h1>
            </div>
            
            <div class="header-right">
                <div class="lang-select-box">
                    <select id="langSelect" onchange="changeLanguage(this.value)">
                        <option value="id" selected>🇮🇩 ID</option>
                        <option value="en">🇬🇧 EN</option>
                        <option value="jp">🇯🇵 JP</option>
                        <option value="kr">🇰🇷 KR</option>
                    </select>
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
        <span class="user-status" style="color: #10b981; font-size: 9px; font-weight: 600;">● Online</span>
    </div>
</div>

                <div class="header-date">
                    📅 <?php echo date('d M Y'); ?>
                </div>
            </div>
        </div>
    </header>

    <nav class="navbar-menu">
        <div class="navbar-inner">
            <ul>
                <li><a href="index.php" class="nav-link active" data-translate="nav_home">Beranda</a></li>
                <?php if ($nav_role !== 'siswa'): ?>
                <li><a href="siswa.php" class="nav-link" data-translate="nav_siswa">Data Siswa</a></li>
                <li><a href="guru.php" class="nav-link" data-translate="nav_guru">Data Guru</a></li>
                <li><a href="mapel.php" class="nav-link" data-translate="nav_mapel">Data Mapel</a></li>
                <li><a href="jurusan.php" class="nav-link" data-translate="nav_jurusan">Data Jurusan</a></li>
                 <li><a href="bahan_ajar.php" class="nav-link" data-translate="nav_bahan_ajar">Bahan Ajar</a></li>
                <?php endif; ?>
                <li><a href="tugas.php" class="nav-link" data-translate="nav_tugas">Tugas</a></li>
                <?php if ($nav_role === 'siswa'): ?>
    <!-- Kode card / menu Pelajaran taruh di sini -->
    <div class="menu-card">
        <li><a href="pelajaran.php" class="nav-link" data-translate="nav_pelajaran">Pelajaran</a></li>
        <!-- dst... -->
    </div>
<?php endif; ?>
                <li><a href="profile.php" class="nav-link" data-translate="nav_profile">Profil Saya</a></li>
                <li><a href="logout.php" class="nav-link nav-logout" data-translate="nav_logout">Logout</a></li>
            </ul>
        </div>
    </nav>

    <main class="main-container">
        
        <section class="content-box">
            <h2 class="section-title"><span data-translate="sec_summary">Ringkasan Data Akademik</span></h2>
            <div class="stats-grid">
                <a href="siswa.php" class="card siswa">
                    <h3 data-translate="card_siswa">Total Siswa</h3>
                    <div class="value"><?php echo $total_siswa; ?></div>
                </a>
                <a href="guru.php" class="card guru">
                    <h3 data-translate="card_guru">Total Guru</h3>
                    <div class="value"><?php echo $total_guru; ?></div>
                </a>
                <a href="mapel.php" class="card mapel">
                    <h3 data-translate="card_mapel">Mata Pelajaran</h3>
                    <div class="value"><?php echo $total_mapel; ?></div>
                </a>
                <a href="jurusan.php" class="card jurusan">
                    <h3 data-translate="card_jurusan">Kompetensi / Jurusan</h3>
                    <div class="value"><?php echo $total_jurusan; ?></div>
                </a>
            </div>
        </section>

        <section class="content-box">
            <h2 class="section-title" data-translate="sec_about">Tentang Lingkungan Sekolah</h2>
            <div class="school-profile-grid">
                <div class="school-img-container">
                    <img src="image/Sekolah.jpg" alt="Gedung Sekolah">
                </div>
                <div class="school-desc">
                    <p data-translate="school_desc_1"><strong>Sistem Informasi Akademik Sekolah</strong> berdedikasi tinggi dalam mencetak generasi muda yang unggul, berdaya saing global, serta memiliki penguasaan teknologi yang matang di era digital modern saat ini.</p>
                    <p data-translate="school_desc_2">Didukung oleh tenaga pendidik profesional, fasilitas laboratorium dan ruang kelas yang memadai, serta lingkungan belajar yang kondusif, sekolah kami terus berkomitmen memberikan layanan pendidikan terbaik serta kemudahan akses administrasi melalui sistem terpadu ini.</p>
                </div>
            </div>
        </section>

        <div class="portal-grid">
            
            <section class="content-box" style="margin-bottom: 0;">
                <div class="section-title">
                    <span data-translate="sec_rules">Tata Tertib & Peraturan Sekolah</span>
                    <span style="font-size: 12px; font-weight: 600; color: var(--text-muted); background: var(--bg-main); padding: 4px 10px; border-radius: 20px;">T.A. 2025/2026</span>
                </div>
                <ul class="rules-list">
                    <li>
                        <span class="badge-num">1</span>
                        <div><strong data-translate="rule_1_title">Kehadiran & Jam Masuk:</strong> <span data-translate="rule_1_desc">Siswa dan Tenaga Pengajar wajib hadir sebelum pukul 07.00 WIB. Gerbang sekolah ditutup tepat pada pukul 07.00 WIB.</span></div>
                    </li>
                    <li>
                        <span class="badge-num">2</span>
                        <div><strong data-translate="rule_2_title">Seragam Sekolah:</strong> <span data-translate="rule_2_desc">Seluruh siswa wajib mengenakan seragam rapi dan lengkap dengan atribut sesuai dengan ketentuan jadwal hari yang berlaku.</span></div>
                    </li>
                    <li>
                        <span class="badge-num">3</span>
                        <div><strong data-translate="rule_3_title">Kedisiplinan & Etika:</strong> <span data-translate="rule_3_desc">Seluruh warga sekolah wajib saling menghormati, menjaga kebersihan lingkungan sekolah, serta melarang keras segala bentuk perundungan (bullying).</span></div>
                    </li>
                    <li>
                        <span class="badge-num">4</span>
                        <div><strong data-translate="rule_4_title">Penggunaan Alat Elektronik:</strong> <span data-translate="rule_4_desc">HP/Gawai hanya digunakan untuk kepentingan pembelajaran atas izin guru mata pelajaran yang bersangkutan.</span></div>
                    </li>
                    <li>
                        <span class="badge-num">5</span>
                        <div><strong data-translate="rule_5_title">Administrasi & Data:</strong> <span data-translate="rule_5_desc">Perubahan data diri siswa maupun guru wajib diselesaikan melalui petugas tata usaha/sistem pendataan paling lambat akhir semester.</span></div>
                    </li>
                </ul>
            </section>

            <aside>
                <div class="vision-box">
                    <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; letter-spacing: 1px; margin-bottom: 6px; opacity: 0.8;" data-translate="vision_title">Visi Sekolah</div>
                    <strong data-translate="vision_desc">"Terwujudnya Generasi Unggul, Berkarakter, Berketerampilan Digital, dan Berprestasi."</strong>
                </div>

                <div class="content-box" style="padding: 20px; margin-bottom: 0;">
                    <h2 class="section-title" style="font-size: 15px; margin-bottom: 14px;" data-translate="sec_announcement">Pengumuman Terbaru</h2>
                    
                    <div class="announcement-card">
                        <div class="date">📅 15 Agustus 2025</div>
                        <h4 data-translate="announcement_1_title">Pelaksanaan Penilaian Tengah Semester (PTS)</h4>
                        <p data-translate="announcement_1_desc">Diberitahukan kepada seluruh siswa agar memperbarui data keikutsertaan mata pelajaran sebelum pelaksanaan PTS minggu depan.</p>
                    </div>
                    
                    <div class="announcement-card" style="border-left-color: #10b981;">
                        <div class="date">📅 02 Agustus 2025</div>
                        <h4 data-translate="announcement_2_title">Pembaruan Data Pokok Guru & Siswa</h4>
                        <p data-translate="announcement_2_desc">Pengisian dan pengeditan data siswa serta guru kelas XI telah dibuka melalui menu Data Siswa dan Data Guru.</p>
                    </div>
                </div>
            </aside>

        </div>
    </main>

    <!-- Script Kamus Terjemahan & Fungsi Ganti Bahasa -->
    <script>
        const translations = {
            id: {
                sub_header: "Sistem Informasi Akademik",
                main_title: "Portal Utama Sekolah",
                status_active: "Aktif",
                nav_home: "Beranda",
                nav_siswa: "Data Siswa",
                nav_guru: "Data Guru",
                nav_mapel: "Data Mapel",
                nav_jurusan: "Data Jurusan",
                nav_bahan_ajar: "Bahan ajar",
                nav_tugas: "Tugas",
                nav_pelajaran: "Pelajaran",
                nav_profile: "Profil Saya",
                nav_logout: "Keluar",
                sec_summary: "Ringkasan Data Akademik",
                card_siswa: "Total Siswa",
                card_guru: "Total Guru",
                card_mapel: "Mata Pelajaran",
                card_jurusan: "Kompetensi / Jurusan",
                sec_about: "Tentang Lingkungan Sekolah",
                school_desc_1: "Sistem Informasi Akademik Sekolah berdedikasi tinggi dalam mencetak generasi muda yang unggul, berdaya saing global, serta memiliki penguasaan teknologi yang matang di era digital modern saat ini.",
                school_desc_2: "Didukung oleh tenaga pendidik profesional, fasilitas laboratorium dan ruang kelas yang memadai, serta lingkungan belajar yang kondusif, sekolah kami terus berkomitmen memberikan layanan pendidikan terbaik serta kemudahan akses administrasi melalui sistem terpadu ini.",
                sec_rules: "Tata Tertib & Peraturan Sekolah",
                rule_1_title: "Kehadiran & Jam Masuk:",
                rule_1_desc: "Siswa dan Tenaga Pengajar wajib hadir sebelum pukul 07.00 WIB. Gerbang sekolah ditutup tepat pada pukul 07.00 WIB.",
                rule_2_title: "Seragam Sekolah:",
                rule_2_desc: "Seluruh siswa wajib mengenakan seragam rapi dan lengkap dengan atribut sesuai dengan ketentuan jadwal hari yang berlaku.",
                rule_3_title: "Kedisiplinan & Etika:",
                rule_3_desc: "Seluruh warga sekolah wajib saling menghormati, menjaga kebersihan lingkungan sekolah, serta melarang keras segala bentuk perundungan (bullying).",
                rule_4_title: "Penggunaan Alat Elektronik:",
                rule_4_desc: "HP/Gawai hanya digunakan untuk kepentingan pembelajaran atas izin guru mata pelajaran yang bersangkutan.",
                rule_5_title: "Administrasi & Data:",
                rule_5_desc: "Perubahan data diri siswa maupun guru wajib diselesaikan melalui petugas tata usaha/sistem pendataan paling lambat akhir semester.",
                vision_title: "Visi Sekolah",
                vision_desc: "\"Terwujudnya Generasi Unggul, Berkarakter, Berketerampilan Digital, dan Berprestasi.\"",
                sec_announcement: "Pengumuman Terbaru",
                announcement_1_title: "Pelaksanaan Penilaian Tengah Semester (PTS)",
                announcement_1_desc: "Diberitahukan kepada seluruh siswa agar memperbarui data keikutsertaan mata pelajaran sebelum pelaksanaan PTS minggu depan.",
                announcement_2_title: "Pembaruan Data Pokok Guru & Siswa",
                announcement_2_desc: "Pengisian dan pengeditan data siswa serta guru kelas XI telah dibuka melalui menu Data Siswa dan Data Guru."
            },
            en: {
                sub_header: "Academic Information System",
                main_title: "Main School Portal",
                status_active: "Active",
                nav_home: "Home",
                nav_siswa: "Student Data",
                nav_guru: "Teacher Data",
                nav_mapel: "Subjects",
                nav_jurusan: "Majors",
                nav_bahan_ajar: "Teaching materials",
                nav_tugas: "Assignments",
                nav_pelajaran: "lesson",
                nav_profile: "My Profile",
                nav_logout: "Logout",
                sec_summary: "Academic Data Summary",
                card_siswa: "Total Students",
                card_guru: "Total Teachers",
                card_mapel: "Subjects",
                card_jurusan: "Competency / Major",
                sec_about: "About the School Environment",
                school_desc_1: "The School Academic Information System is highly dedicated to producing outstanding young generations with global competitiveness and a mature mastery of technology in today's modern digital era.",
                school_desc_2: "Supported by professional educators, adequate laboratory and classroom facilities, and a conducive learning environment, our school remains committed to providing the best educational services and easy administrative access through this integrated system.",
                sec_rules: "School Rules & Regulations",
                rule_1_title: "Attendance & Entry Time:",
                rule_1_desc: "Students and teachers must arrive before 07:00 AM WIB. The school gates close sharp at 07:00 AM WIB.",
                rule_2_title: "School Uniform:",
                rule_2_desc: "All students are required to wear neat uniforms complete with attributes according to the applicable daily schedule.",
                rule_3_title: "Discipline & Ethics:",
                rule_3_desc: "All school members must respect one another, maintain a clean school environment, and strictly prohibit all forms of bullying.",
                rule_4_title: "Use of Electronic Devices:",
                rule_4_desc: "Mobile phones/devices are only to be used for learning purposes with the permission of the respective subject teacher.",
                rule_5_title: "Administration & Data:",
                rule_5_desc: "Changes to personal student or teacher data must be completed through the administration staff/data system no later than the end of the semester.",
                vision_title: "School Vision",
                vision_desc: "\"The Realization of an Outstanding Generation with Character, Digital Skills, and Achievement.\"",
                sec_announcement: "Latest Announcements",
                announcement_1_title: "Mid-Semester Assessment (PTS) Implementation",
                announcement_1_desc: "All students are requested to update their subject participation data prior to next week's PTS implementation.",
                announcement_2_title: "Master Data Update for Teachers & Students",
                announcement_2_desc: "Filling and editing of grade 11 student and teacher data has been opened via the Student Data and Teacher Data menus."
            },
            jp: {
                sub_header: "学校教育情報システム",
                main_title: "メインスクールポータル",
                status_active: "アクティブ",
                nav_home: "ホーム",
                nav_siswa: "生徒データ",
                nav_guru: "教師データ",
                nav_mapel: "科目",
                nav_jurusan: "専攻",
                nav_bahan_ajar: "教材",
                nav_tugas: "課題",
                nav_pelajaran: "授業",
                nav_profile: "プロフィール",
                nav_logout: "ログアウト",
                sec_summary: "学術データ概要",
                card_siswa: "総生徒数",
                card_guru: "総教員数",
                card_mapel: "科目数",
                card_jurusan: "専門分野 / 学科",
                sec_about: "学校環境について",
                school_desc_1: "当校の学術情報システムは、現代のデジタル社会において、グローバルな競争力を持ち、優れた技術力を備えた優秀な若い世代を育成することに全力を注いでいます。",
                school_desc_2: "専門的な教育スタッフ、充実した実験室や教室の施設、そして快適な学習環境に支えられ、当校はこの統合システムを通じて最高の教育サービスと便利な管理アクセスの提供に努めています。",
                sec_rules: "校則と規制",
                rule_1_title: "出席と入室時間:",
                rule_1_desc: "生徒および教職員は午前7時00分（WIB）前までに登校・出勤してください。校門は午前7時00分に厳に閉鎖されます。",
                rule_2_title: "制服:",
                rule_2_desc: "すべての生徒は、その曜日の時間割の規定に従い、きちんと整えられた制服および着用義務のある校章等を身につける必要があります。",
                rule_3_title: "規律と倫理:",
                rule_3_desc: "すべての学校関係者は互いに尊重し合い、校内の美化を心がけ、あらゆる形態のいじめを厳に禁止します。",
                rule_4_title: "電子機器の使用:",
                rule_4_desc: "携帯電話や電子機器は、担当教員の許可がある場合のみ学習目的で使用できます。",
                rule_5_title: "管理とデータ:",
                rule_5_desc: "生徒および教員の個人情報の変更は、遅くとも学期末までに事務局／データ管理システムを通じて手続きを完了してください。",
                vision_title: "学校のビジョン",
                vision_desc: "「優れた人間性、デジタルスキル、そして実績を備えた次世代の育成。」",
                sec_announcement: "最新のお知らせ",
                announcement_1_title: "前期中間試験（PTS）の実施について",
                announcement_1_desc: "来週の中間試験実施の前に、全生徒は受講科目データの更新を行ってください。",
                announcement_2_title: "教員および生徒のマスターデータの更新",
                announcement_2_desc: "11年生の生徒および教員データの入力・編集が、生徒データおよび教師データメニューから可能になりました。"
            },
            kr: {
                sub_header: "학교 학술 정보 시스템",
                main_title: "메인 학교 포털",
                status_active: "활성",
                nav_home: "홈",
                nav_siswa: "학생 데이터",
                nav_guru: "교사 데이터",
                nav_mapel: "과목",
                nav_jurusan: "전공",
                nav_bahan_ajar: "교육 자료",
                nav_tugas: "과제",
                nav_pelajaran: "수업",
                nav_profile: "내 프로필",
                nav_logout: "로그아웃",
                sec_summary: "학업 데이터 요약",
                card_siswa: "총 학생 수",
                card_guru: "총 교사 수",
                card_mapel: "과목 수",
                card_jurusan: "전공/계열",
                sec_about: "학교 환경 소개",
                school_desc_1: "학교 학술 정보 시스템은 현대 디지털 시대에 발맞추어 뛰어난 역량과 글로벌 경쟁력을 갖추고 기술을 능숙하게 다루는 인재 양성에 헌신하고 있습니다.",
                school_desc_2: "전문 교직원, 충분한 실험실 및 교실 시설, 그리고 최적의 학습 환경을 바탕으로 통합 시스템을 통해 최고의 교육 서비스와 편리한 행정 접근성을 제공하기 위해 지속적으로 노력하고 있습니다.",
                sec_rules: "학교 규칙 및 규정",
                rule_1_title: "출석 및 입실 시간:",
                rule_1_desc: "학생 및 교직원은 오전 7시 00분(WIB) 이전까지 입실해야 합니다. 학교 정문은 오전 7시 00분에 정각에 폐쇄됩니다.",
                rule_2_title: "학교 교복:",
                rule_2_desc: "모든 학생은 해당 요일의 시간표 규정에 맞는 단정한 교복과 부착물을 착용해야 합니다.",
                rule_3_title: "규율 및 윤리:",
                rule_3_desc: "모든 학교 구성원은 서로 존중하고 학교 환경을 청결하게 유지해야 하며, 모든 형태의 학교 폭력(따돌림)을 엄격히 금지합니다.",
                rule_4_title: "전자기기 사용:",
                rule_4_desc: "휴대전화 및 전자기기는 해당 과목 교사의 허가 하에 학습 목적으로만 사용할 수 있습니다.",
                rule_5_title: "행정 및 데이터:",
                rule_5_desc: "학생 및 교사의 개인 정보 변경 사항은 늦어도 학기 말까지 행정실/데이터 시스템을 통해 처리해야 합니다.",
                vision_title: "학교 비전",
                vision_desc: "\"탁월한 인성과 디지털 역량, 그리고 성취감을 갖춘 인재 양성.\"",
                sec_announcement: "최신 공지사항",
                announcement_1_title: "중간고사(PTS) 실시 안내",
                announcement_1_desc: "다음 주 중간고사 실시 전에 모든 학생은 수강 과목 데이터를 업데이트해 주시기 바랍니다.",
                announcement_2_title: "교사 및 학생 기본 데이터 업데이트",
                announcement_2_desc: "11학년 학생 및 교사 데이터의 입력 및 편집이 학생 데이터 및 교사 데이터 메뉴를 통해 시작되었습니다."
            }
        };

        function changeLanguage(lang) {
            const elements = document.querySelectorAll('[data-translate]');
            elements.forEach(el => {
                const key = el.getAttribute('data-translate');
                if (translations[lang] && translations[lang][key]) {
                    el.innerText = translations[lang][key];
                }
            });
            localStorage.setItem('selected_lang', lang);
        }

        window.addEventListener('DOMContentLoaded', () => {
            const savedLang = localStorage.getItem('selected_lang') || 'id';
            document.getElementById('langSelect').value = savedLang;
            changeLanguage(savedLang);
        });
    </script>
</body>
</html>