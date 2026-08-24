<?php
session_start();
require_once 'koneksi.php';
require_login();

// 1. VALIDASI PARAMETER FILE
if (!isset($_GET['file']) || trim($_GET['file']) === '') {
    http_response_code(400);
    die('Parameter file tidak ditemukan.');
}

$upload_dir = realpath(__DIR__ . '/uploads');
if ($upload_dir === false) {
    http_response_code(500);
    die('Folder uploads tidak ditemukan di server.');
}

$filename = basename($_GET['file']);
$filepath = $upload_dir . DIRECTORY_SEPARATOR . $filename;

$real_filepath = realpath($filepath);

if ($real_filepath === false || strpos($real_filepath, $upload_dir . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(404);
    die('File tidak ditemukan.');
}

// Hanya izinkan file PDF yang dipreview
$finfo = new finfo(FILEINFO_MIME_TYPE);
if ($finfo->file($real_filepath) !== 'application/pdf') {
    http_response_code(403);
    die('Tipe file tidak diizinkan untuk preview.');
}

$nav_role     = $_SESSION['role'] ?? 'siswa';
$session_nis  = $_SESSION['nis'] ?? null;

if ($nav_role === 'siswa') {
    $stmt = $pdo->prepare("SELECT nis FROM pengumpulan_tugas WHERE path_file = :path LIMIT 1");
    $stmt->execute([':path' => 'uploads/' . $filename]);
    $row = $stmt->fetch();

    if (!$row || (string)$row['nis'] !== (string)$session_nis) {
        http_response_code(403);
        die('Kamu tidak punya akses untuk melihat file ini.');
    }
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . filesize($real_filepath));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
header('X-Content-Type-Options: nosniff');

if (ob_get_level()) {
    ob_end_clean();
}

readfile($real_filepath);
exit();