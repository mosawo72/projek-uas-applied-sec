<?php
// register.php - Pendaftaran Akun Pelanggan dengan Standar Password Minimal 12 Karakter (OWASP A07)
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security.php';

if (is_logged_in()) {
    header('Location: /index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Validasi keamanan formulir gagal (CSRF). Silakan muat ulang.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['password_confirm'] ?? '');

        if (empty($name) || empty($email) || empty($password)) {
            $error = 'Semua bidang formulir wajib diisi.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Format alamat email tidak valid.';
        } elseif (strlen($password) < 12) {
            $error = 'Kata sandi minimal harus 12 karakter untuk mematuhi kebijakan keamanan OWASP.';
        } elseif ($password !== $confirm) {
            $error = 'Konfirmasi kata sandi tidak cocok.';
        } else {
            // Cek apakah email sudah terdaftar
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Alamat email ini sudah digunakan oleh akun lain.';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $insert = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'customer')");
                $insert->execute([$name, $email, $hashedPassword]);
                $newId = (int)$pdo->lastInsertId();

                log_audit($pdo, $newId, 'USER_REGISTERED', 'Pendaftaran mandiri akun customer');
                $_SESSION['flash_success'] = 'Pendaftaran berhasil! Silakan masuk dengan akun baru Anda.';
                header('Location: /login.php');
                exit;
            }
        }
    }
}

$pageTitle = 'Daftar Akun Baru';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-card-wrap">
    <div class="auth-card">
        <h2>Daftar Akun Pelanggan</h2>
        <p class="muted">Bergabunglah untuk memesan kue nikmat dari Torta Shop.</p>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="/register.php" class="form-stack">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="name">Nama Lengkap</label>
                <input type="text" id="name" name="name" required value="<?= e($_POST['name'] ?? '') ?>" placeholder="Nama Anda">
            </div>

            <div class="form-group">
                <label for="email">Alamat Email</label>
                <input type="email" id="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>" placeholder="email@contoh.com">
            </div>

            <div class="form-group">
                <label for="password">Kata Sandi (Min. 12 Karakter)</label>
                <input type="password" id="password" name="password" minlength="12" required placeholder="Minimal 12 karakter aman">
            </div>

            <div class="form-group">
                <label for="password_confirm">Konfirmasi Kata Sandi</label>
                <input type="password" id="password_confirm" name="password_confirm" minlength="12" required placeholder="Ulangi kata sandi">
            </div>

            <button type="submit" class="btn btn-primary btn-block">Daftar Akun</button>
        </form>

        <div class="auth-footer">
            <p>Sudah punya akun? <a href="/login.php">Masuk di sini</a></p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
