<?php
// profile.php - Halaman Profil Pengguna
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security.php';

require_login();
$user = current_user();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Validasi CSRF token gagal.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $password = (string)($_POST['new_password'] ?? '');

        if (empty($name)) {
            $error = 'Nama tidak boleh kosong.';
        } else {
            if (!empty($password)) {
                if (strlen($password) < 12) {
                    $error = 'Kata sandi baru minimal 12 karakter.';
                } else {
                    $hashed = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, password = ? WHERE id = ?");
                    $stmt->execute([$name, $hashed, $user['id']]);
                    $_SESSION['user']['name'] = $name;
                    $success = 'Nama dan kata sandi berhasil diperbarui.';
                }
            } else {
                $stmt = $pdo->prepare("UPDATE users SET name = ? WHERE id = ?");
                $stmt->execute([$name, $user['id']]);
                $_SESSION['user']['name'] = $name;
                $success = 'Profil nama berhasil diperbarui.';
            }
        }
    }
}

// Refresh avatar terbaru dari DB
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user['id']]);
$freshUser = $stmt->fetch();
$_SESSION['user']['avatar'] = $freshUser['avatar'];

$pageTitle = 'Profil Pengguna';
require_once __DIR__ . '/includes/header.php';
?>

<div class="profile-page-wrap">
    <h2>Pengaturan Profil Saya</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <div class="profile-grid">
        <div class="profile-avatar-box">
            <h3>Foto Profil (Avatar)</h3>
            <div class="avatar-large-wrap">
                <?php if (!empty($freshUser['avatar'])): ?>
                    <img src="/uploads/<?= e($freshUser['avatar']) ?>" class="avatar-large" alt="Avatar">
                <?php else: ?>
                    <div class="avatar-large-placeholder"><?= strtoupper(substr($freshUser['name'], 0, 1)) ?></div>
                <?php endif; ?>
            </div>

            <form method="POST" action="/upload_avatar.php" enctype="multipart/form-data" class="avatar-form">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="avatar_file">Ganti Foto (PNG, JPG, WebP max 2MB):</label>
                    <input type="file" id="avatar_file" name="avatar_file" accept="image/png,image/jpeg,image/webp" required>
                </div>
                <button type="submit" class="btn btn-outline btn-block">Unggah Avatar Baru</button>
            </form>
        </div>

        <div class="profile-info-box">
            <h3>Data Akun</h3>
            <form method="POST" action="/profile.php" class="form-stack">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label>Alamat Email (Permanen)</label>
                    <input type="email" value="<?= e($freshUser['email']) ?>" disabled>
                </div>

                <div class="form-group">
                    <label>Peran / Role</label>
                    <input type="text" value="<?= ucfirst(e($freshUser['role'])) ?>" disabled>
                </div>

                <div class="form-group">
                    <label for="name">Nama Lengkap</label>
                    <input type="text" id="name" name="name" value="<?= e($freshUser['name']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="new_password">Kata Sandi Baru (Kosongkan bila tidak diubah)</label>
                    <input type="password" id="new_password" name="new_password" minlength="12" placeholder="Minimal 12 karakter aman">
                </div>

                <button type="submit" class="btn btn-primary">Simpan Perubahan Profil</button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
