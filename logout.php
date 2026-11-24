<?php
// logout.php - Keluar Sesi dengan Aman (Anti Session Hijacking)
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    log_audit($pdo, (int)$_SESSION['user']['id'], 'LOGOUT', 'Pengguna keluar sesi');
}

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();
session_start();
$_SESSION['flash_success'] = 'Anda telah berhasil keluar.';
header('Location: /login.php');
exit;
