<?php
// upload_avatar.php - Khusus Penanganan Upload Foto Profil (OWASP A08: File Upload Defense)
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security.php';

require_login();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Token CSRF tidak valid.';
        header('Location: /profile.php');
        exit;
    }

    if (!isset($_FILES['avatar_file'])) {
        $_SESSION['flash_error'] = 'Pilih berkas foto terlebih dahulu.';
        header('Location: /profile.php');
        exit;
    }

    try {
        // Validasi struktur biner gambar & MIME (Mencegah PHP Shell Injection / Polyglot file)
        $newFileName = validate_and_upload_image($_FILES['avatar_file'], UPLOAD_DIR);

        if ($newFileName) {
            // Hapus avatar lama jika ada
            $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
            $stmt->execute([$user['id']]);
            $oldAvatar = $stmt->fetchColumn();
            if ($oldAvatar && file_exists(UPLOAD_DIR . '/' . $oldAvatar)) {
                @unlink(UPLOAD_DIR . '/' . $oldAvatar);
            }

            // Simpan nama berkas baru ke database
            $update = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
            $update->execute([$newFileName, $user['id']]);

            $_SESSION['user']['avatar'] = $newFileName;
            log_audit($pdo, $user['id'], 'AVATAR_UPLOADED', 'Upload foto profil: ' . $newFileName);
            $_SESSION['flash_success'] = 'Foto profil berhasil diperbarui.';
        }
    } catch (Exception $e) {
        $_SESSION['flash_error'] = $e->getMessage();
    }
}

header('Location: /profile.php');
exit;
