<?php
// admin/users.php - Master Pengguna & Pengaturan Role (Khusus Administrator)
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';

require_admin(); // Strict Administrator only (OWASP A01: Broken Access Control)

$error = '';
$editUser = null;

if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT id, name, email, role, active FROM users WHERE id = ?");
    $stmt->execute([$editId]);
    $editUser = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Token CSRF tidak valid.';
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $role = $_POST['role'] ?? 'customer';
        $active = isset($_POST['active']) ? (int)$_POST['active'] : 1;

        if (!in_array($role, ['administrator', 'pegawai', 'customer'])) {
            $error = 'Peran pengguna tidak valid.';
        } elseif ($id === (int)$_SESSION['user']['id'] && $role !== 'administrator') {
            $error = 'Anda tidak dapat menurunkan peran akun Anda sendiri.';
        } else {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, role = ?, active = ? WHERE id = ?");
            $stmt->execute([$name, $role, $active, $id]);
            log_audit($pdo, $_SESSION['user']['id'], 'USER_ROLE_UPDATE', "Ubah user #$id role menjadi $role");
            $_SESSION['flash_success'] = 'Data pengguna berhasil diperbarui.';
            header('Location: /admin/users.php');
            exit;
        }
    }
}

$users = $pdo->query("SELECT id, name, email, role, active, created_at FROM users ORDER BY id ASC")->fetchAll();

$pageTitle = 'Master Pengguna';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
    <aside class="admin-sidebar">
        <h3>Kelola Toko</h3>
        <ul class="admin-nav">
            <li><a href="/admin/index.php">Dashboard</a></li>
            <li><a href="/admin/products.php">Tambah Barang</a></li>
            <li><a href="/admin/categories.php">Tambah Kategori</a></li>
            <li><a href="/admin/purchases.php">Pembelian (PO)</a></li>
            <li><a href="/admin/report.php">Laporan Penjualan</a></li>
            <li><a href="/admin/users.php" class="active">Master Pengguna</a></li>
        </ul>
    </aside>

    <div class="admin-main">
        <h2>Manajemen Master Pengguna & Hak Akses</h2>
        <p class="muted">Hanya dapat diakses oleh Administrator untuk mengelola peran (Administrator, Pegawai, Customer).</p>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($editUser): ?>
            <div class="admin-card mb-4">
                <h3>Ubah Hak Akses & Status: <?= e($editUser['name']) ?></h3>
                <form method="POST" action="/admin/users.php" class="form-grid-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $editUser['id'] ?>">

                    <div class="form-group">
                        <label for="name">Nama Lengkap</label>
                        <input type="text" id="name" name="name" value="<?= e($editUser['name']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Email (Akun)</label>
                        <input type="text" value="<?= e($editUser['email']) ?>" disabled>
                    </div>

                    <div class="form-group">
                        <label for="role">Peran (Role)</label>
                        <select name="role" id="role" required>
                            <option value="customer" <?= $editUser['role'] === 'customer' ? 'selected' : '' ?>>Customer (Pelanggan)</option>
                            <option value="pegawai" <?= $editUser['role'] === 'pegawai' ? 'selected' : '' ?>>Pegawai (Staf Toko)</option>
                            <option value="administrator" <?= $editUser['role'] === 'administrator' ? 'selected' : '' ?>>Administrator (Akses Penuh)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="active">Status Akun</label>
                        <select name="active" id="active">
                            <option value="1" <?= $editUser['active'] ? 'selected' : '' ?>>Aktif</option>
                            <option value="0" <?= !$editUser['active'] ? 'selected' : '' ?>>Nonaktif / Blokir</option>
                        </select>
                    </div>

                    <div class="form-actions span-2">
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                        <a href="/admin/users.php" class="btn btn-outline">Batal</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <div class="admin-card">
            <h3>Daftar Pengguna Terdaftar</h3>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>Peran</th>
                            <th>Status</th>
                            <th>Terdaftar</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?= $u['id'] ?></td>
                                <td><strong><?= e($u['name']) ?></strong></td>
                                <td><?= e($u['email']) ?></td>
                                <td>
                                    <span class="role-tag role-<?= e($u['role']) ?>">
                                        <?= ucfirst(e($u['role'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= $u['active'] ? 'badge-success' : 'badge-danger' ?>">
                                        <?= $u['active'] ? 'Aktif' : 'Nonaktif' ?>
                                    </span>
                                </td>
                                <td><small><?= e($u['created_at']) ?></small></td>
                                <td>
                                    <?php if ($u['id'] === $_SESSION['user']['id']): ?>
                                        <span class="muted">Akun Anda</span>
                                    <?php else: ?>
                                        <a href="/admin/users.php?edit=<?= $u['id'] ?>" class="btn btn-sm btn-outline">Ubah</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
