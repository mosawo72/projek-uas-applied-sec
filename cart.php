<?php
// cart.php - Manajemen Keranjang Belanja Pelanggan
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security.php';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Token CSRF tidak valid.';
        header('Location: /cart.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    $productId = (int)($_POST['product_id'] ?? 0);
    $qty = (int)($_POST['qty'] ?? 1);

    if ($action === 'add' && $productId > 0) {
        $stmt = $pdo->prepare("SELECT id, stock FROM products WHERE id = ? AND active = 1");
        $stmt->execute([$productId]);
        $prod = $stmt->fetch();

        if ($prod) {
            $currentQty = $_SESSION['cart'][$productId] ?? 0;
            $newQty = $currentQty + $qty;
            if ($newQty > (int)$prod['stock']) {
                $newQty = (int)$prod['stock'];
                $_SESSION['flash_error'] = 'Jumlah disesuaikan dengan sisa stok maksimal.';
            }
            $_SESSION['cart'][$productId] = $newQty;
            $_SESSION['flash_success'] = 'Kue ditambahkan ke keranjang.';
        }
    } elseif ($action === 'update' && $productId > 0) {
        if ($qty <= 0) {
            unset($_SESSION['cart'][$productId]);
        } else {
            $_SESSION['cart'][$productId] = $qty;
        }
        $_SESSION['flash_success'] = 'Keranjang diperbarui.';
    } elseif ($action === 'remove' && $productId > 0) {
        unset($_SESSION['cart'][$productId]);
        $_SESSION['flash_success'] = 'Kue dihapus dari keranjang.';
    } elseif ($action === 'clear') {
        $_SESSION['cart'] = [];
        $_SESSION['flash_success'] = 'Keranjang dikosongkan.';
    }

    header('Location: /cart.php');
    exit;
}

// Ambil item produk dalam keranjang
$cartItems = [];
$totalAmount = 0;

if (!empty($_SESSION['cart'])) {
    $ids = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $products = $stmt->fetchAll();

    foreach ($products as $p) {
        $qty = $_SESSION['cart'][$p['id']];
        $subtotal = $qty * (int)$p['price'];
        $totalAmount += $subtotal;
        $cartItems[] = [
            'product' => $p,
            'qty' => $qty,
            'subtotal' => $subtotal
        ];
    }
}

$pageTitle = 'Keranjang Belanja';
require_once __DIR__ . '/includes/header.php';
?>

<div class="cart-page-wrap">
    <h2>Keranjang Belanja Anda</h2>

    <?php if (empty($cartItems)): ?>
        <div class="empty-state">
            <p>Keranjang belanja Anda masih kosong.</p>
            <a href="/products.php" class="btn btn-primary">Mulai Belanja Kue</a>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Kue</th>
                        <th>Harga</th>
                        <th>Jumlah</th>
                        <th>Subtotal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cartItems as $item): ?>
                        <tr>
                            <td>
                                <strong><?= e($item['product']['name']) ?></strong>
                            </td>
                            <td><?= rp($item['product']['price']) ?></td>
                            <td>
                                <form method="POST" action="/cart.php" class="inline-qty-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="product_id" value="<?= (int)$item['product']['id'] ?>">
                                    <input type="number" name="qty" value="<?= (int)$item['qty'] ?>" min="1" max="<?= (int)$item['product']['stock'] ?>" style="width:70px;">
                                    <button type="submit" class="btn btn-sm btn-outline">Ubah</button>
                                </form>
                            </td>
                            <td><strong><?= rp($item['subtotal']) ?></strong></td>
                            <td>
                                <form method="POST" action="/cart.php" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="product_id" value="<?= (int)$item['product']['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Hapus item ini?')">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="cart-summary-box">
            <div class="cart-total">
                <span>Total Belanja:</span>
                <h3><?= rp($totalAmount) ?></h3>
            </div>
            <div class="cart-buttons">
                <a href="/products.php" class="btn btn-outline">Tambah Kue Lain</a>
                <a href="/checkout.php" class="btn btn-primary btn-lg">Lanjut ke Pembayaran / Checkout &rarr;</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
