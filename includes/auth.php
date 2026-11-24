<?php
// includes/auth.php - Pengelolaan Autentikasi dan Otorisasi Berbasis Peran (RBAC - OWASP A01)

declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool {
    return current_user() !== null;
}

function user_role(): ?string {
    return $_SESSION['user']['role'] ?? null;
}

function is_admin(): bool {
    return user_role() === 'administrator';
}

function is_pegawai(): bool {
    return user_role() === 'pegawai';
}

function is_staff(): bool {
    return is_admin() || is_pegawai();
}

function require_login(): void {
    if (!is_logged_in()) {
        $_SESSION['flash_error'] = 'Silakan masuk terlebih dahulu.';
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

function require_staff(): void {
    require_login();
    if (!is_staff()) {
        http_response_code(403);
        die('<h1>403 Forbidden</h1><p>Anda tidak memiliki akses ke halaman ini.</p><a href="/index.php">Kembali ke Beranda</a>');
    }
}

function require_admin(): void {
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        die('<h1>403 Forbidden</h1><p>Hanya Administrator yang memiliki akses ke halaman ini.</p><a href="/index.php">Kembali ke Beranda</a>');
    }
}
