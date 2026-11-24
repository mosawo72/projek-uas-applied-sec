<?php
// config.php - Konfigurasi Utama Aplikasi Torta Shop

declare(strict_types=1);

define('APP_NAME', 'Torta Shop - Toko Kue Online');
define('APP_URL', 'http://127.0.0.1:8000');
define('BASE_DIR', __DIR__);
define('DB_FILE', BASE_DIR . '/database/torta.sqlite');
define('UPLOAD_DIR', BASE_DIR . '/uploads');

// Keamanan Sesi PHP (OWASP A07 & A02)
ini_set('session.cookie_httponly', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', '28800'); // 8 Jam

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Inisialisasi Security Headers (OWASP A05: Security Misconfiguration)
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: no-referrer");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self';");
