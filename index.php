<?php
// index.php - Halaman Utama & Katalog Unggulan
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security.php';

// Ambil kategori aktif
$categories = $pdo->query("SELECT * FROM categories WHERE active = 1 ORDER BY name ASC")->fetchAll();

// Ambil produk unggulan
$stmt = $pdo->query("SELECT p.*, c.name as category_name 
                     FROM products p 
                     JOIN categories c ON p.category_id = c.id 
                     WHERE p.active = 1 AND c.active = 1 
                     ORDER BY p.id DESC LIMIT 6");
$featuredProducts = $stmt->fetchAll();

$pageTitle = 'Beranda Patiseri Kue Pilihan';
require_once __DIR__ . '/includes/header.php';
?>

<section class="hero-banner">
    <div class="hero-content">
        <h1>Sepotong Kue,<br><em>Sejuta Cerita Bahagia.</em></h1>
        <p>Kue tart, cheesecake, dan bolu segar buatan tangan setiap hari. Dibuat dari bahan alami berkualitas tinggi untuk momen istimewa Anda.</p>
        <div class="hero-buttons">
            <a href="/products.php" class="btn btn-primary">Lihat Semua Kue</a>
            <a href="/search.php" class="btn btn-outline">Cari Rasa Favorit</a>
        </div>
    </div>
    <div class="hero-image">
        <img src="/assets/img/hero.jpg" alt="Kue Torta Shop">
    </div>
</section>

<section class="section">
    <div class="section-header">
        <div>
            <h2>Kue Pilihan Unggulan</h2>
            <p class="muted">Kue paling disukai pelanggan minggu ini.</p>
        </div>
        <a href="/products.php" class="btn btn-sm btn-outline">Lihat Semua &rarr;</a>
    </div>

    <div class="product-grid">
        <?php foreach ($featuredProducts as $item): ?>
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
                    <p class="product-stock muted">Stok tersisa: <strong><?= (int)$item['stock'] ?></strong></p>
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
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
