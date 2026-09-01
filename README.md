# 🏫 Portal Akademik Sekolah — Web Sistem Informasi Sekolah

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.x-blue?logo=php&logoColor=white" />
  <img src="https://img.shields.io/badge/MySQL-8.x-orange?logo=mysql&logoColor=white" />
  <img src="https://img.shields.io/badge/XAMPP-Latest-9B3FBF?logo=apache&logoColor=white" />
  <img src="https://img.shields.io/badge/Status-Active-brightgreen" />
  <img src="https://img.shields.io/badge/License-MIT-lightgrey" />
</p>

<p align="center">
  <strong>Sistem Informasi Akademik berbasis Web untuk manajemen data siswa, guru, mata pelajaran, jurusan, tugas, bahan ajar, dan penilaian secara terintegrasi.</strong>
</p>

---

## ✨ Fitur Utama

| Fitur | Keterangan |
|---|---|
| 🔐 **Multi-Role Login** | Admin, Guru, Siswa dengan hak akses berbeda |
| 📊 **Dashboard Dinamis** | Pie Chart, Bar Chart, Semicircle Gauge, KPI Cards |
| 🕒 **Live Clock Real-Time** | Jam digital berdetik tiap detik |
| 🌐 **Multilanguage** | Dukungan Bahasa ID, EN, JP, KR |
| 👨‍🎓 **Manajemen Siswa** | CRUD siswa dengan rekap nilai per siswa |
| 👨‍🏫 **Manajemen Guru** | CRUD data guru dengan mapping kelas & mapel |
| 📚 **Bahan Ajar** | Upload modul PDF, preview langsung di browser |
| 📝 **Manajemen Tugas** | Terbitkan tugas, kumpulkan berkas PDF, beri penilaian |
| 🏫 **Jurusan & Mapel** | Kelola jurusan dan mata pelajaran akademik |
| 📱 **Mobile Responsive** | Hamburger menu + Side Drawer animasi smooth |
| 🔒 **Keamanan** | Prepared Statement PDO, session protection |

---

## 🛠️ Teknologi

- **Backend**: PHP 8.x (Native, PDO)
- **Database**: MySQL 8.x
- **Frontend**: HTML5, CSS3, Vanilla JavaScript
- **Server**: XAMPP (Apache + MySQL)
- **Font**: Plus Jakarta Sans (Google Fonts)

---

## 🚀 Instalasi

1. Clone repo: `git clone https://github.com/Alfianax01/web-sekolah.git`
2. Pindahkan ke folder `htdocs` XAMPP
3. Import `db_sekolah.sql` ke phpMyAdmin
4. Edit `koneksi.php` sesuai konfigurasi database Anda
5. Akses: `http://localhost/web-sekolah`

---

## 📁 Struktur Folder

`
web-sekolah/
├── index.php          # Dashboard utama (multi-role)
├── login.php          # Halaman login
├── register.php       # Halaman registrasi
├── koneksi.php        # Koneksi database PDO
├── security.php       # Middleware keamanan & session
├── siswa.php          # Manajemen data siswa & nilai
├── guru.php           # Manajemen data guru
├── mapel.php          # Manajemen mata pelajaran
├── jurusan.php        # Manajemen jurusan
├── bahan_ajar.php     # Manajemen modul bahan ajar
├── tugas.php          # Manajemen tugas & pengumpulan
├── profile.php        # Halaman profil pengguna
├── preview_pdf.php    # Preview PDF bahan ajar
└── uploads/           # File upload (foto, PDF)
`

---

## 🔒 Keamanan

- ✅ PDO Prepared Statements — mencegah SQL Injection
- ✅ htmlspecialchars() — mencegah XSS
- ✅ Session-based Authentication
- ✅ Role-based Access Control
- ✅ File Upload Validation

---

## 👨‍💻 Developer

**Alfianax01** | GitHub: [@Alfianax01](https://github.com/Alfianax01)

> *Dibuat untuk keperluan pendidikan — MIT License*
