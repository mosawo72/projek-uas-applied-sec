<?php
// includes/security.php - Helper Keamanan Komprehensif (OWASP Top 10)

declare(strict_types=1);
require_once __DIR__ . '/../config.php';

// A01: CSRF Token Generator & Validator
function get_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

// A03: Sanitasi Output (Mencegah XSS)
function e(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// Format Rupiah
function rp(int|float $angka): string {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

// A04: Rate Limiting Percobaan Login (Anti Brute Force)
function check_login_rate_limit(PDO $pdo, string $ip): bool {
    $now = time();
    $window = 900; // 15 menit
    $maxAttempts = 5;

    // Bersihkan yang sudah kadaluarsa
    $pdo->prepare("DELETE FROM login_attempts WHERE last_attempt < ?")->execute([$now - $window]);

    $stmt = $pdo->prepare("SELECT attempts FROM login_attempts WHERE ip_key = ?");
    $stmt->execute([$ip]);
    $row = $stmt->fetch();

    if ($row && (int)$row['attempts'] >= $maxAttempts) {
        return false; // Terkunci
    }
    return true;
}

function record_failed_login(PDO $pdo, string $ip): void {
    $now = time();
    $stmt = $pdo->prepare("INSERT INTO login_attempts (ip_key, attempts, last_attempt) VALUES (?, 1, ?)
        ON CONFLICT(ip_key) DO UPDATE SET attempts = attempts + 1, last_attempt = ?");
    $stmt->execute([$ip, $now, $now]);
}

function reset_login_attempts(PDO $pdo, string $ip): void {
    $pdo->prepare("DELETE FROM login_attempts WHERE ip_key = ?")->execute([$ip]);
}

// A09: Security Audit Logging
function log_audit(PDO $pdo, ?int $userId, string $action, string $details = ''): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $action, $details, $ip]);
}

// A08: File Upload Sanitization & Validator
function validate_and_upload_image(array $file, string $targetFolder = UPLOAD_DIR): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    // Maksimal 2MB
    if ($file['size'] > 2 * 1024 * 1024) {
        throw new Exception("Ukuran gambar melebihi batas 2MB.");
    }

    // Cek MIME type dengan finfo asli (bukan ekstensi)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    if (!array_key_exists($mime, $allowedMimes)) {
        throw new Exception("Format berkas tidak valid. Hanya JPG, PNG, dan WebP yang diizinkan.");
    }

    // Re-verify struktur gambar asli
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        throw new Exception("Berkas bukan gambar yang valid.");
    }

    $ext = $allowedMimes[$mime];
    $newFileName = bin2hex(random_bytes(16)) . '.' . $ext;
    $destination = $targetFolder . '/' . $newFileName;

    if (!is_dir($targetFolder)) {
        mkdir($targetFolder, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception("Gagal menyimpan gambar di server.");
    }

    return $newFileName;
}
