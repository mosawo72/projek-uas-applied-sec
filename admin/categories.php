<?php
// admin/categories.php - Master Kategori (CRUD)
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';

require_staff();

$error = '';
$editCategory = null;

// Handle Edit Fetch
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$editId]);
    $editCategory = $stmt->fetch();
}

// Handle Form Submission (Tambah / Edit / Toggle Status)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Token CSRF tidak valid.';
    } else {
        $action = $_POST['action'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $id = (int)($_POST['id'] ?? 0);
        $active = isset($_POST['active']) ? (int)$_POST['active'] : 1;

        if ($action === 'create' || $action === 'update') {
            if (empty($name)) {
                $error = 'Nama kategori wajib diisi.';
            } else {
                // Cek nama kembar
                $check = $pdo->prepare("SELECT id FROM categories WHERE name = ? COLLATE NOCASE AND id != ?");
                $check->execute([$name, $id]);
                if ($check->fetch()) {
                    $error = 'Nama kategori sudah digunakan.';
                } else {
                    if ($action === 'create') {
                        $stmt = $pdo->prepare("INSERT INTO categories (name, active) VALUES (?, ?)");
                        $stmt->execute([$name, $active]);
                        log_audit($pdo, $_SESSION['user']['id'], 'CATEGORY_CREATE', "Tambah kategori: $name");
                        $_SESSION['flash_success'] = 'Kategori baru berhasil ditambahkan.';
                    } else {
                        $stmt = $pdo->prepare("UPDATE categories SET name = ?, active = ? WHERE id = ?");
                        $stmt->execute([$name, $active, $id]);
                        log_audit($pdo, $_SESSION['user']['id'], 'CATEGORY_UPDATE', "Ubah kategori ID: $id");
                        $_SESSION['flash_success'] = 'Kategori berhasil diperbarui.';
                    }
                    header('Location: /admin/categories.php');
                    exit;
                }
            }
        }
    }
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();

$pageTitle = 'Master Kategori';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
    <aside class="admin-sidebar">
        <h3>Kelola Toko</h3>
        <ul class="admin-nav">
            <li><a href="/admin/index.php">Dashboard</a></li>
            <li><a href="/admin/products.php">Tambah Barang</a></li>
            <li><a href="/admin/categories.php" class="active">Tambah Kategori</a></li>
            <li><a href="/admin/purchases.php">Pembelian (PO)</a></li>
            <li><a href="/admin/report.php">Laporan Penjualan</a></li>
            <?php if (is_admin()): ?>
                <li><a href="/admin/users.php">Master Pengguna</a></li>
            <?php endif; ?>
        </ul>
    </aside>

    <div class="admin-main">
        <h2>Manajemen Master Kategori</h2>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="admin-grid-split">
            <!-- Form Tambah / Edit -->
            <div class="admin-card">
                <h3><?= $editCategory ? 'Ubah Kategori' : 'Tambah Kategori Baru' ?></h3>
                <form method="POST" action="/admin/categories.php" class="form-stack">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="<?= $editCategory ? 'update' : 'create' ?>">
                    <?php if ($editCategory): ?>
                        <input type="hidden" name="id" value="<?= $editCategory['id'] ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="name">Nama Kategori</label>
                        <input type="text" id="name" name="name" required value="<?= e($editCategory['name'] ?? $_POST['name'] ?? '') ?>" placeholder="Misal: Kue Tart, Macaron...">
                    </div>

                    <div class="form-group">
                        <label for="active">Status</label>
                        <select name="active" id="active">
                            <option value="1" <?= ($editCategory['active'] ?? 1) == 1 ? 'selected' : '' ?>>Aktif</option>
                            <option value="0" <?= ($editCategory['active'] ?? 1) == 0 ? 'selected' : '' ?>>Nonaktif</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary"><?= $editCategory ? 'Simpan Perubahan' : 'Tambah Kategori' ?></button>
                    <?php if ($editCategory): ?>
                        <a href="/admin/categories.php" class="btn btn-outline">Batal</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Tabel Daftar Kategori -->
            <div class="admin-card">
                <h3>Daftar Kategori</h3>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nama Kategori</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td><?= $cat['id'] ?></td>
                                    <td><strong><?= e($cat['name']) ?></strong></td>
                                    <td>
                                        <span class="badge <?= $cat['active'] ? 'badge-success' : 'badge-danger' ?>">
                                            <?= $cat['active'] ? 'Aktif' : 'Nonaktif' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="/admin/categories.php?edit=<?= $cat['id'] ?>" class="btn btn-sm btn-outline">Ubah</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
