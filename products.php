<?php
// products.php - Katalog Lengkap & Filter Kategori
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security.php';

$categoryId = isset($_GET['category']) ? (int)$_GET['category'] : 0;

$categories = $pdo->query("SELECT * FROM categories WHERE active = 1 ORDER BY name ASC")->fetchAll();

if ($categoryId > 0) {
    $stmt = $pdo->prepare("SELECT p.*, c.name as category_name 
                           FROM products p 
                           JOIN categories c ON p.category_id = c.id 
                           WHERE p.active = 1 AND c.active = 1 AND p.category_id = ? 
                           ORDER BY p.name ASC");
    $stmt->execute([$categoryId]);
} else {
    $stmt = $pdo->query("SELECT p.*, c.name as category_name 
                         FROM products p 
                         JOIN categories c ON p.category_id = c.id 
                         WHERE p.active = 1 AND c.active = 1 
                         ORDER BY p.name ASC");
}
$products = $stmt->fetchAll();

$pageTitle = 'Katalog Lengkap Kue';
require_once __DIR__ . '/includes/header.php';
?>

<div class="catalog-layout">
    <aside class="catalog-sidebar">
        <h3>Kategori</h3>
        <ul class="filter-list">
            <li>
                <a href="/products.php" class="<?= $categoryId === 0 ? 'active' : '' ?>">Semua Kue</a>
            </li>
            <?php foreach ($categories as $cat): ?>
                <li>
                    <a href="/products.php?category=<?= (int)$cat['id'] ?>" class="<?= $categoryId === (int)$cat['id'] ? 'active' : '' ?>">
                        <?= e($cat['name']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </aside>

    <div class="catalog-content">
        <div class="section-header">
            <h2>Koleksi Kue Pilihan</h2>
            <span class="muted"><?= count($products) ?> varian ditemukan</span>
        </div>

        <?php if (empty($products)): ?>
            <div class="empty-state">
                <p>Belum ada kue pada kategori ini.</p>
                <a href="/products.php" class="btn btn-outline">Lihat Semua Kategori</a>
            </div>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($products as $item): ?>
                    <div class="product-card">
                        <div class="product-img-wrap">
                            <?php 
                            $imgSrc = '/assets/img/cokelat.jpg';
                            if (!empty($item['image'])) {
                                $imgSrc = file_exists(BASE_DIR . '/uploads/' . $item['image']) 
                                          ? '/uploads/' . e($item['image']) 
                                          : '/assets/img/' . e($item['image']);
                            }
                            ?>
                            <img src="<?= $imgSrc ?>" alt="<?= e($item['name']) ?>">
                            <span class="product-category-tag"><?= e($item['category_name']) ?></span>
                        </div>
                        <div class="product-card-body">
                            <h3><a href="/product_detail.php?id=<?= (int)$item['id'] ?>"><?= e($item['name']) ?></a></h3>
                            <p class="product-price"><?= rp($item['price']) ?></p>
                            <p class="product-stock muted">Stok: <?= (int)$item['stock'] ?></p>
                            <div class="product-card-actions">
                                <a href="/product_detail.php?id=<?= (int)$item['id'] ?>" class="btn btn-sm btn-outline">Detail</a>
                                <?php if ((int)$item['stock'] > 0): ?>
                                    <form method="POST" action="/cart.php" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="add">
                                        <input type="hidden" name="product_id" value="<?= (int)$item['id'] ?>">
                                        <input type="hidden" name="qty" value="1">
                                        <button type="submit" class="btn btn-sm btn-primary">+ Keranjang</button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge badge-danger">Habis</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
