<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Memastikan user sudah login
 */
function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
}

/**
 * Memeriksa apakah user memiliki role yang diizinkan
 */
function require_role(array $allowed_roles): void
{
    require_login();

    $role = $_SESSION['role'] ?? '';
    if (!in_array($role, $allowed_roles, true)) {
        http_response_code(403);
        exit('Akses ditolak. Anda tidak memiliki izin untuk halaman ini.');
    }
}

/**
 * Menghasilkan token CSRF unik per sesi
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Menghasilkan input hidden untuk token CSRF
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Memverifikasi validitas token CSRF pada request POST
 */
function verify_csrf(): void
{
    $submitted_token = $_POST['csrf_token'] ?? '';
    $session_token = $_SESSION['csrf_token'] ?? '';

    if (!$session_token || !$submitted_token || !hash_equals($session_token, $submitted_token)) {
        http_response_code(419);
        exit('Permintaan tidak valid (CSRF token mismatch). Silakan muat ulang halaman.');
    }
}

/**
 * Escaping string untuk pencegahan XSS
 */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitasi string input dasar
 */
function sanitize_input(?string $value): string
{
    return trim(strip_tags((string) $value));
}
