<?php
// orders.php - Riwayat Pesanan Customer & Pembayaran / Pembatalan
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security.php';

require_login();
$user = current_user();

// Logika Pembatalan oleh Customer (Hanya order miliknya sendiri yang belum lunas - Anti IDOR/BOLA A01)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Token CSRF tidak valid.';
    } else {
        $orderId = (int)($_POST['order_id'] ?? 0);

        try {
            $pdo->beginTransaction();

            // Cek apakah order ini milik user ini dan statusnya masih Menunggu Pembayaran
            $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ? AND status = 'Menunggu Pembayaran'");
            $stmt->execute([$orderId, $user['id']]);
            $order = $stmt->fetch();

            if (!$order) {
                throw new Exception("Pesanan tidak ditemukan atau sudah tidak dapat dibatalkan.");
            }

            // Kembalikan stok produk
            $itemsStmt = $pdo->prepare("SELECT product_id, qty FROM order_items WHERE order_id = ?");
            $itemsStmt->execute([$orderId]);
            $items = $itemsStmt->fetchAll();

            $restoreStmt = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
            foreach ($items as $it) {
                $restoreStmt->execute([$it['qty'], $it['product_id']]);
            }

            // Update status pesanan
            $cancelStmt = $pdo->prepare("UPDATE orders SET status = 'Dibatalkan' WHERE id = ?");
            $cancelStmt->execute([$orderId]);

            $pdo->commit();
            log_audit($pdo, $user['id'], 'ORDER_CANCELLED', "Pesanan #$orderId dibatalkan oleh pengguna");
            $_SESSION['flash_success'] = "Pesanan #$orderId telah berhasil dibatalkan dan stok dikembalikan.";

        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['flash_error'] = $e->getMessage();
        }
    }
    header('Location: /orders.php');
    exit;
}

// Ambil riwayat pesanan khusus user yang sedang login (Horizontal Privilege Separation)
$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC");
$stmt->execute([$user['id']]);
$orders = $stmt->fetchAll();

$pageTitle = 'Riwayat Pesanan Saya';
require_once __DIR__ . '/includes/header.php';
?>

<div class="orders-page-wrap">
    <h2>Riwayat Pesanan Saya</h2>

    <?php if (empty($orders)): ?>
        <div class="empty-state">
            <p>Anda belum pernah melakukan pemesanan.</p>
            <a href="/products.php" class="btn btn-primary">Pesan Kue Sekarang</a>
        </div>
    <?php else: ?>
        <div class="orders-list">
            <?php foreach ($orders as $ord): 
                $itemsStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
                $itemsStmt->execute([$ord['id']]);
                $items = $itemsStmt->fetchAll();
            ?>
                <div class="order-card">
                    <div class="order-header">
                        <div>
                            <h3>Pesanan #<?= (int)$ord['id'] ?></h3>
                            <span class="muted">Dibuat pada: <?= e($ord['created_at']) ?></span>
                        </div>
                        <span class="status-pill status-<?= strtolower(str_replace(' ', '-', $ord['status'])) ?>">
                            <?= e($ord['status']) ?>
                        </span>
                    </div>

                    <div class="order-body">
                        <table class="table-sm">
                            <thead>
                                <tr>
                                    <th>Kue</th>
                                    <th>Harga</th>
                                    <th>Jumlah</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $it): ?>
                                    <tr>
                                        <td><?= e($it['product_name']) ?></td>
                                        <td><?= rp($it['price']) ?></td>
                                        <td><?= (int)$it['qty'] ?></td>
                                        <td><?= rp($it['subtotal']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="order-footer">
                        <div class="order-total-info">
                            <p class="muted">Metode: <strong><?= e($ord['payment_method']) ?></strong></p>
                            <h4>Total: <?= rp($ord['total_amount']) ?></h4>
                        </div>
                        <div class="order-actions">
                            <?php if ($ord['status'] === 'Menunggu Pembayaran'): ?>
                                <form method="POST" action="/orders.php" onsubmit="return confirm('Apakah Anda yakin ingin membatalkan pesanan ini?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="order_id" value="<?= (int)$ord['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Batalkan Pesanan</button>
                                </form>
                                <span class="badge badge-warning">Silakan bayar di kasir / transfer</span>
                            <?php elseif ($ord['status'] === 'Lunas'): ?>
                                <span class="badge badge-success">Lunas pada <?= e($ord['paid_at']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
