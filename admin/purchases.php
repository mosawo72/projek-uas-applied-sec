<?php
// admin/purchases.php - Pembelian & Pemesanan ke Supplier (PO)
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';

require_staff();

$error = '';
$products = $pdo->query("SELECT id, name, stock FROM products WHERE active = 1 ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Token CSRF tidak valid.';
    } else {
        $action = $_POST['action'] ?? '';

        // 1. Buat Purchase Order (PO)
        if ($action === 'create_po') {
            $supplier = trim($_POST['supplier'] ?? '');
            $productId = (int)($_POST['product_id'] ?? 0);
            $qty = (int)($_POST['qty'] ?? 0);
            $cost = (int)($_POST['cost'] ?? 0);

            if (empty($supplier) || $productId <= 0 || $qty <= 0 || $cost <= 0) {
                $error = 'Nama pemasok, produk, jumlah, dan harga beli per unit wajib diisi.';
            } else {
                $stmtP = $pdo->prepare("SELECT name FROM products WHERE id = ?");
                $stmtP->execute([$productId]);
                $prodName = $stmtP->fetchColumn();

                $total = $qty * $cost;
                $stmt = $pdo->prepare("INSERT INTO purchases (supplier, product_id, product_name, qty, cost, total, status) VALUES (?, ?, ?, ?, ?, ?, 'Dipesan')");
                $stmt->execute([$supplier, $productId, $prodName, $qty, $cost, $total]);
                $poId = (int)$pdo->lastInsertId();

                log_audit($pdo, $_SESSION['user']['id'], 'PO_CREATED', "PO #$poId ke $supplier untuk $prodName ($qty unit)");
                $_SESSION['flash_success'] = "Purchase Order #$poId berhasil dibuat.";
                header('Location: /admin/purchases.php');
                exit;
            }
        }

        // 2. Terima Barang dari PO (Tambah Stok Otomatis)
        if ($action === 'receive_po') {
            $poId = (int)($_POST['po_id'] ?? 0);

            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("SELECT * FROM purchases WHERE id = ? AND status = 'Dipesan'");
                $stmt->execute([$poId]);
                $po = $stmt->fetch();

                if (!$po) {
                    throw new Exception("Data PO tidak ditemukan atau sudah pernah diterima sebelumnya.");
                }

                // Tambahkan stok ke produk
                $updateStock = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
                $updateStock->execute([$po['qty'], $po['product_id']]);

                // Update status PO
                $updatePo = $pdo->prepare("UPDATE purchases SET status = 'Diterima', received_at = CURRENT_TIMESTAMP WHERE id = ?");
                $updatePo->execute([$poId]);

                $pdo->commit();
                log_audit($pdo, $_SESSION['user']['id'], 'PO_RECEIVED', "PO #$poId diterima (+{$po['qty']} stok)");
                $_SESSION['flash_success'] = "Barang PO #$poId berhasil diterima dan stok produk telah ditambah {$po['qty']} unit.";

            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['flash_error'] = $e->getMessage();
            }

            header('Location: /admin/purchases.php');
            exit;
        }
    }
}

$purchases = $pdo->query("SELECT * FROM purchases ORDER BY id DESC")->fetchAll();

$pageTitle = 'Pembelian & PO';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
    <aside class="admin-sidebar">
        <h3>Kelola Toko</h3>
        <ul class="admin-nav">
            <li><a href="/admin/index.php">Dashboard</a></li>
            <li><a href="/admin/products.php">Tambah Barang</a></li>
            <li><a href="/admin/categories.php">Tambah Kategori</a></li>
            <li><a href="/admin/purchases.php" class="active">Pembelian (PO)</a></li>
            <li><a href="/admin/report.php">Laporan Penjualan</a></li>
            <?php if (is_admin()): ?>
                <li><a href="/admin/users.php">Master Pengguna</a></li>
            <?php endif; ?>
        </ul>
    </aside>

    <div class="admin-main">
        <h2>Pembelian & Pemesanan ke Supplier (Purchase Order)</h2>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <!-- Form Buat PO -->
        <div class="admin-card mb-4">
            <h3>Buat Pesanan Baru ke Pemasok (PO)</h3>
            <form method="POST" action="/admin/purchases.php" class="form-grid-2">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_po">

                <div class="form-group">
                    <label for="supplier">Nama Pemasok / Supplier</label>
                    <input type="text" id="supplier" name="supplier" required placeholder="PT Sumber Rasa / Toko Bahan">
                </div>

                <div class="form-group">
                    <label for="product_id">Pilih Barang</label>
                    <select name="product_id" id="product_id" required>
                        <option value="">-- Pilih Barang --</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= e($p['name']) ?> (Stok saat ini: <?= $p['stock'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="qty">Jumlah Pesanan</label>
                    <input type="number" id="qty" name="qty" min="1" required placeholder="10">
                </div>

                <div class="form-group">
                    <label for="cost">Harga Beli per Unit (Rp)</label>
                    <input type="number" id="cost" name="cost" min="1" required placeholder="80000">
                </div>

                <div class="form-actions span-2">
                    <button type="submit" class="btn btn-primary">Kirim PO ke Pemasok</button>
                </div>
            </form>
        </div>

        <!-- Tabel Daftar PO -->
        <div class="admin-card">
            <h3>Daftar Purchase Order</h3>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>PO ID</th>
                            <th>Pemasok</th>
                            <th>Barang</th>
                            <th>Jumlah</th>
                            <th>Harga Beli</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Aksi Penerimaan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($purchases)): ?>
                            <tr><td colspan="8" class="text-center">Belum ada transaksi pembelian.</td></tr>
                        <?php else: ?>
                            <?php foreach ($purchases as $po): ?>
                                <tr>
                                    <td>#<?= $po['id'] ?></td>
                                    <td><strong><?= e($po['supplier']) ?></strong></td>
                                    <td><?= e($po['product_name']) ?></td>
                                    <td><?= $po['qty'] ?></td>
                                    <td><?= rp($po['cost']) ?></td>
                                    <td><?= rp($po['total']) ?></td>
                                    <td>
                                        <span class="badge <?= $po['status'] === 'Diterima' ? 'badge-success' : 'badge-warning' ?>">
                                            <?= e($po['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($po['status'] === 'Dipesan'): ?>
                                            <form method="POST" action="/admin/purchases.php" onsubmit="return confirm('Konfirmasi bahwa barang dari supplier sudah sampai dan masuk gudang?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="receive_po">
                                                <input type="hidden" name="po_id" value="<?= $po['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-success">Terima Barang</button>
                                            </form>
                                        <?php else: ?>
                                            <small class="muted">Diterima pada <?= e($po['received_at']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
