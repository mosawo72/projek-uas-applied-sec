<?php
// product_detail.php - Rincian Produk
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT p.*, c.name as category_name 
                       FROM products p 
                       JOIN categories c ON p.category_id = c.id 
                       WHERE p.id = ? AND p.active = 1 AND c.active = 1");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    header("Location: /products.php");
    exit;
}

$pageTitle = $product['name'];
require_once __DIR__ . '/includes/header.php';

$imgSrc = '/assets/img/cokelat.jpg';
if (!empty($product['image'])) {
    $imgSrc = file_exists(BASE_DIR . '/uploads/' . $product['image']) 
              ? '/uploads/' . e($product['image']) 
              : '/assets/img/' . e($product['image']);
}
?>

<div class="product-detail-wrap">
    <div class="detail-gallery">
        <img src="<?= $imgSrc ?>" alt="<?= e($product['name']) ?>" class="detail-img">
    </div>
    <div class="detail-info">
        <span class="badge badge-accent"><?= e($product['category_name']) ?></span>
        <h1><?= e($product['name']) ?></h1>
        <p class="detail-price"><?= rp($product['price']) ?></p>

        <div class="detail-desc">
            <p><?= nl2br(e($product['description'] ?? 'Kue segar pilihan terbaik dari Torta Shop.')) ?></p>
        </div>

        <p class="muted">Ketersediaan Stok: <strong><?= (int)$product['stock'] ?> unit</strong></p>

        <?php if ((int)$product['stock'] > 0): ?>
            <form method="POST" action="/cart.php" class="detail-buy-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                
                <div class="form-group inline-qty">
                    <label for="qty">Jumlah:</label>
                    <input type="number" id="qty" name="qty" value="1" min="1" max="<?= (int)$product['stock'] ?>" required>
                </div>

                <button type="submit" class="btn btn-primary btn-lg">Tambah ke Keranjang</button>
            </form>
        <?php else: ?>
            <div class="alert alert-danger">Maaf, persediaan stok saat ini sedang habis.</div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
