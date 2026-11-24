<?php
// login.php - Halaman Masuk dengan Proteksi Brute-Force & CSRF
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security.php';

if (is_logged_in()) {
    header('Location: /index.php');
    exit;
}

$error = '';
$ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Validasi keamanan formulir gagal (CSRF). Silakan muat ulang halaman.';
    } elseif (!check_login_rate_limit($pdo, $ip)) {
        $error = 'Terlalu banyak percobaan gagal. Akun dibatasi sementara selama 15 menit (OWASP A04).';
    } else {
        $email = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
        $password = (string)($_POST['password'] ?? '');

        if (empty($email) || empty($password)) {
            $error = 'Email dan kata sandi wajib diisi.';
        } else {
            // Prepared Statement (OWASP A03: SQLi)
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Login Berhasil
                reset_login_attempts($pdo, $ip);
                session_regenerate_id(true); // Anti Session Fixation (A07)

                $_SESSION['user'] = [
                    'id' => (int)$user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'avatar' => $user['avatar']
                ];

                log_audit($pdo, (int)$user['id'], 'LOGIN_SUCCESS', 'Pengguna masuk');
                $_SESSION['flash_success'] = 'Selamat datang kembali, ' . $user['name'] . '!';

                if (is_staff()) {
                    header('Location: /admin/index.php');
                } else {
                    header('Location: /index.php');
                }
                exit;
            } else {
                record_failed_login($pdo, $ip);
                log_audit($pdo, null, 'LOGIN_FAILED', 'Percobaan masuk gagal untuk email: ' . $email);
                $error = 'Email atau kata sandi tidak cocok.';
            }
        }
    }
}

$pageTitle = 'Masuk Akun';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-card-wrap">
    <div class="auth-card">
        <h2>Masuk ke Akun Anda</h2>
        <p class="muted">Silakan masukkan email dan kata sandi yang terdaftar.</p>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="/login.php" class="form-stack">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="email">Alamat Email</label>
                <input type="email" id="email" name="email" required autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>" placeholder="nama@email.com">
            </div>

            <div class="form-group">
                <label for="password">Kata Sandi</label>
                <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="••••••••">
            </div>

            <button type="submit" class="btn btn-primary btn-block">Masuk Sekarang</button>
        </form>

        <div class="auth-footer">
            <p>Belum memiliki akun? <a href="/register.php">Daftar Akun Baru</a></p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
