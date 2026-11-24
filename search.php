<?php
// search.php - Pencarian Produk Aman dari SQL Injection (A03)
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security.php';

$query = trim($_GET['q'] ?? '');
$results = [];

if (!empty($query)) {
    // Prepared Statement dengan LIKE Parameterized (Anti SQLi)
    $stmt = $pdo->prepare("SELECT p.*, c.name as category_name 
                           FROM products p 
                           JOIN categories c ON p.category_id = c.id 
                           WHERE (p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?) 
                           AND p.active = 1 AND c.active = 1 
                           ORDER BY p.name ASC");
    $searchTerm = '%' . $query . '%';
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $results = $stmt->fetchAll();
}

$pageTitle = 'Pencarian Kue';
require_once __DIR__ . '/includes/header.php';
?>

<div class="search-page-wrap">
    <h2>Cari Kue Favorit Anda</h2>
    <form method="GET" action="/search.php" class="search-form-bar">
        <input type="text" name="q" value="<?= e($query) ?>" placeholder="Ketik nama kue, rasa cokelat, keju, stroberi..." required>
        <button type="submit" class="btn btn-primary">Cari Kue</button>
    </form>

    <?php if (!empty($query)): ?>
        <p class="search-info">Hasil pencarian untuk kata kunci: "<strong><?= e($query) ?></strong>" (<?= count($results) ?> ditemukan)</p>

        <?php if (empty($results)): ?>
            <div class="empty-state">
                <p>Tidak ditemukan kue yang cocok dengan pencarian Anda.</p>
            </div>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($results as $item): ?>
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
                            <div class="product-card-actions">
                                <a href="/product_detail.php?id=<?= (int)$item['id'] ?>" class="btn btn-sm btn-outline">Lihat</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
