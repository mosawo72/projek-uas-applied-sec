<?php
// admin/products.php - Master Barang & Upload Gambar (CRUD)
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';

require_staff();

$error = '';
$editProduct = null;
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$editId]);
    $editProduct = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Token CSRF tidak valid.';
    } else {
        $action = $_POST['action'] ?? '';
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $price = (int)($_POST['price'] ?? 0);
        $stock = (int)($_POST['stock'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $active = isset($_POST['active']) ? (int)$_POST['active'] : 1;

        if (empty($name) || $categoryId <= 0 || $price <= 0) {
            $error = 'Nama, kategori, dan harga jual valid wajib diisi.';
        } else {
            try {
                $imageFileName = $editProduct['image'] ?? null;

                // Handle Upload Foto Produk jika ada berkas yang dikirim (A08)
                if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
                    $newImg = validate_and_upload_image($_FILES['image_file'], UPLOAD_DIR);
                    if ($newImg) {
                        $imageFileName = $newImg;
                    }
                }

                if ($action === 'create') {
                    $stmt = $pdo->prepare("INSERT INTO products (name, category_id, price, stock, description, image, active) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $categoryId, $price, $stock, $description, $imageFileName, $active]);
                    log_audit($pdo, $_SESSION['user']['id'], 'PRODUCT_CREATE', "Tambah kue: $name");
                    $_SESSION['flash_success'] = 'Barang kue baru berhasil ditambahkan.';
                } elseif ($action === 'update') {
                    $stmt = $pdo->prepare("UPDATE products SET name = ?, category_id = ?, price = ?, stock = ?, description = ?, image = ?, active = ? WHERE id = ?");
                    $stmt->execute([$name, $categoryId, $price, $stock, $description, $imageFileName, $active, $id]);
                    log_audit($pdo, $_SESSION['user']['id'], 'PRODUCT_UPDATE', "Ubah kue ID: $id");
                    $_SESSION['flash_success'] = 'Barang kue berhasil diperbarui.';
                }

                header('Location: /admin/products.php');
                exit;

            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
}

$products = $pdo->query("SELECT p.*, c.name as category_name 
                         FROM products p 
                         JOIN categories c ON p.category_id = c.id 
                         ORDER BY p.id DESC")->fetchAll();

$pageTitle = 'Master Barang';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
    <aside class="admin-sidebar">
        <h3>Kelola Toko</h3>
        <ul class="admin-nav">
            <li><a href="/admin/index.php">Dashboard</a></li>
            <li><a href="/admin/products.php" class="active">Tambah Barang</a></li>
            <li><a href="/admin/categories.php">Tambah Kategori</a></li>
            <li><a href="/admin/purchases.php">Pembelian (PO)</a></li>
            <li><a href="/admin/report.php">Laporan Penjualan</a></li>
            <?php if (is_admin()): ?>
                <li><a href="/admin/users.php">Master Pengguna</a></li>
            <?php endif; ?>
        </ul>
    </aside>

    <div class="admin-main">
        <div class="section-header">
            <h2>Manajemen Master Barang (Kue)</h2>
            <button onclick="document.getElementById('form-card').scrollIntoView({behavior:'smooth'})" class="btn btn-primary">+ Tambah Barang</button>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <!-- Form Input Barang -->
        <div class="admin-card" id="form-card">
            <h3><?= $editProduct ? 'Ubah Data Barang' : 'Tambah Barang Baru' ?></h3>
            <form method="POST" action="/admin/products.php" enctype="multipart/form-data" class="form-grid-2">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $editProduct ? 'update' : 'create' ?>">
                <?php if ($editProduct): ?>
                    <input type="hidden" name="id" value="<?= $editProduct['id'] ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="name">Nama Barang</label>
                    <input type="text" id="name" name="name" required value="<?= e($editProduct['name'] ?? '') ?>" placeholder="Nama kue">
                </div>

                <div class="form-group">
                    <label for="category_id">Kategori</label>
                    <select name="category_id" id="category_id" required>
                        <option value="">-- Pilih Kategori --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($editProduct['category_id'] ?? 0) == $cat['id'] ? 'selected' : '' ?>>
                                <?= e($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="price">Harga Jual (Rp)</label>
                    <input type="number" id="price" name="price" min="1000" required value="<?= $editProduct['price'] ?? '' ?>" placeholder="150000">
                </div>

                <div class="form-group">
                    <label for="stock">Stok Awal</label>
                    <input type="number" id="stock" name="stock" min="0" required value="<?= $editProduct['stock'] ?? 0 ?>">
                </div>

                <div class="form-group span-2">
                    <label for="description">Deskripsi</label>
                    <textarea id="description" name="description" rows="2" placeholder="Keterangan bahan dan rasa..."><?= e($editProduct['description'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label for="image_file">Unggah Foto Produk (JPG, PNG, WebP max 2MB)</label>
                    <input type="file" id="image_file" name="image_file" accept="image/png,image/jpeg,image/webp">
                </div>

                <div class="form-group">
                    <label for="active">Status</label>
                    <select name="active" id="active">
                        <option value="1" <?= ($editProduct['active'] ?? 1) == 1 ? 'selected' : '' ?>>Aktif</option>
                        <option value="0" <?= ($editProduct['active'] ?? 1) == 0 ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                </div>

                <div class="form-actions span-2">
                    <button type="submit" class="btn btn-primary"><?= $editProduct ? 'Simpan Perubahan' : 'Tambah Barang' ?></button>
                    <?php if ($editProduct): ?>
                        <a href="/admin/products.php" class="btn btn-outline">Batal Ubah</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Tabel Daftar Barang -->
        <div class="admin-card mt-4">
            <h3>Daftar Seluruh Barang</h3>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Foto</th>
                            <th>Nama Barang</th>
                            <th>Kategori</th>
                            <th>Harga</th>
                            <th>Stok</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $p): 
                            $imgSrc = '/assets/img/cokelat.jpg';
                            if (!empty($p['image'])) {
                                $imgSrc = file_exists(BASE_DIR . '/uploads/' . $p['image']) 
                                          ? '/uploads/' . e($p['image']) 
                                          : '/assets/img/' . e($p['image']);
                            }
                        ?>
                            <tr>
                                <td>
                                    <img src="<?= $imgSrc ?>" class="table-thumb" alt="Kue">
                                </td>
                                <td><strong><?= e($p['name']) ?></strong></td>
                                <td><?= e($p['category_name']) ?></td>
                                <td><?= rp($p['price']) ?></td>
                                <td><strong><?= $p['stock'] ?></strong></td>
                                <td>
                                    <span class="badge <?= $p['active'] ? 'badge-success' : 'badge-danger' ?>">
                                        <?= $p['active'] ? 'Aktif' : 'Nonaktif' ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="/admin/products.php?edit=<?= $p['id'] ?>" class="btn btn-sm btn-outline">Ubah</a>
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
