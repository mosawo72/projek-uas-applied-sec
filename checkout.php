<?php
// checkout.php - Proses Pemesanan / Checkout Transaksi Penjualan
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security.php';

require_login(); // Hanya customer/pengguna login yang bisa checkout (A01)

if (empty($_SESSION['cart'])) {
    header('Location: /cart.php');
    exit;
}

$user = current_user();
$error = '';

// Ambil item produk dalam keranjang
$ids = array_keys($_SESSION['cart']);
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders) AND active = 1");
$stmt->execute($ids);
$products = $stmt->fetchAll();

$cartItems = [];
$totalAmount = 0;
foreach ($products as $p) {
    $qty = (int)$_SESSION['cart'][$p['id']];
    if ($qty > (int)$p['stock']) {
        $error = "Stok {$p['name']} tidak mencukupi (tersedia: {$p['stock']}).";
        break;
    }
    $subtotal = $qty * (int)$p['price'];
    $totalAmount += $subtotal;
    $cartItems[] = [
        'product' => $p,
        'qty' => $qty,
        'subtotal' => $subtotal
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Validasi CSRF token gagal.';
    } else {
        $paymentMethod = $_POST['payment_method'] ?? 'Tunai di Kasir';
        if (!in_array($paymentMethod, ['Tunai di Kasir', 'Transfer Bank'])) {
            $paymentMethod = 'Tunai di Kasir';
        }

        try {
            $pdo->beginTransaction();

            // Insert ke tabel orders
            $stmtOrder = $pdo->prepare("INSERT INTO orders (user_id, total_amount, status, payment_method) VALUES (?, ?, 'Menunggu Pembayaran', ?)");
            $stmtOrder->execute([$user['id'], $totalAmount, $paymentMethod]);
            $orderId = (int)$pdo->lastInsertId();

            // Insert order_items dan kurangi stok produk
            $stmtItem = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, price, qty, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtStock = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");

            foreach ($cartItems as $item) {
                $pId = (int)$item['product']['id'];
                $qty = (int)$item['qty'];

                $stmtItem->execute([
                    $orderId,
                    $pId,
                    $item['product']['name'],
                    $item['product']['price'],
                    $qty,
                    $item['subtotal']
                ]);

                $stmtStock->execute([$qty, $pId, $qty]);
                if ($stmtStock->rowCount() === 0) {
                    throw new Exception("Stok produk {$item['product']['name']} berubah dan tidak mencukupi.");
                }
            }

            $pdo->commit();

            // Kosongkan keranjang
            $_SESSION['cart'] = [];
            log_audit($pdo, $user['id'], 'ORDER_CREATED', "Pesanan #$orderId dibuat dengan total $totalAmount");

            $_SESSION['flash_success'] = "Pesanan #$orderId berhasil dibuat! Silakan selesaikan pembayaran.";
            header('Location: /orders.php');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

$pageTitle = 'Konfirmasi Pesanan (Checkout)';
require_once __DIR__ . '/includes/header.php';
?>

<div class="checkout-page-wrap">
    <h2>Konfirmasi & Checkout Pesanan</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="checkout-grid">
        <div class="checkout-form-box">
            <h3>Informasi Pemesan</h3>
            <p><strong>Nama:</strong> <?= e($user['name']) ?></p>
            <p><strong>Email:</strong> <?= e($user['email']) ?></p>

            <form method="POST" action="/checkout.php" class="form-stack">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="payment_method">Metode Pembayaran</label>
                    <select name="payment_method" id="payment_method" required>
                        <option value="Tunai di Kasir">Bayar Tunai di Kasir Toko</option>
                        <option value="Transfer Bank">Transfer Bank / Virtual Account</option>
                    </select>
                </div>

                <div class="alert alert-info">
                    Pesanan Anda akan dicatat dan stok kue langsung dicadangkan. Silakan ambil kue atau konfirmasi pelunasan pada kasir kami.
                </div>

                <button type="submit" class="btn btn-primary btn-lg btn-block">Konfirmasi & Buat Pesanan</button>
            </form>
        </div>

        <div class="checkout-summary-box">
            <h3>Ringkasan Pesanan</h3>
            <ul class="order-summary-list">
                <?php foreach ($cartItems as $item): ?>
                    <li>
                        <span><?= e($item['product']['name']) ?> (x<?= $item['qty'] ?>)</span>
                        <strong><?= rp($item['subtotal']) ?></strong>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="order-total-line">
                <span>Total Tagihan:</span>
                <h3><?= rp($totalAmount) ?></h3>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
