<?php
session_start();
require_once 'koneksi.php';
require_login();

// 1. VALIDASI PARAMETER FILE
$requested_file = $_GET['file'] ?? '';
if (trim($requested_file) === '') {
    http_response_code(400);
    die('Parameter file tidak ditemukan.');
}

// Hapus awalan uploads/ jika dikirim lengkap
if (str_starts_with($requested_file, 'uploads/')) {
    $requested_file = substr($requested_file, 8);
} elseif (str_starts_with($requested_file, 'uploads\\')) {
    $requested_file = substr($requested_file, 8);
}

$upload_dir = realpath(__DIR__ . '/uploads');
if ($upload_dir === false) {
    http_response_code(500);
    die('Folder uploads tidak ditemukan di server.');
}

$target_path = realpath($upload_dir . DIRECTORY_SEPARATOR . $requested_file);

// Cegah Path Traversal: pastikan file berada di dalam folder uploads
if ($target_path === false || !str_starts_with($target_path, $upload_dir . DIRECTORY_SEPARATOR) || !is_file($target_path)) {
    http_response_code(404);
    die('File tidak ditemukan.');
}

// Hanya izinkan file PDF
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($target_path);
if ($mime !== 'application/pdf') {
    http_response_code(403);
    die('Tipe file tidak diizinkan untuk preview.');
}

// 2. OTORISASI ROLE
$nav_role    = $_SESSION['role'] ?? 'siswa';
$session_nis = $_SESSION['nis'] ?? null;

// Jika siswa mencoba membuka file pengumpulan tugas, pastikan itu miliknya
if ($nav_role === 'siswa' && !str_contains($target_path, 'bahan_ajar')) {
    $rel_path = 'uploads/' . basename($target_path);
    $stmt = $pdo->prepare("SELECT nis FROM pengumpulan_tugas WHERE path_file LIKE :path LIMIT 1");
    $stmt->execute([':path' => '%' . basename($target_path)]);
    $row = $stmt->fetch();

    if ($row && (string)$row['nis'] !== (string)$session_nis) {
        http_response_code(403);
        die('Anda tidak memiliki izin untuk melihat file ini.');
    }
}

$filename = basename($target_path);

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . filesize($target_path));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
header('X-Content-Type-Options: nosniff');

if (ob_get_level()) {
    ob_end_clean();
}

readfile($target_path);
exit();