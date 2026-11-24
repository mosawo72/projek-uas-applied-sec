<?php
// admin/index.php - Dashboard Manajemen Toko (Khusus Administrator & Pegawai)
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';

require_staff(); // Role check (OWASP A01: Broken Access Control)

// Ringkasan Statistik Toko
$totalProducts = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE active = 1")->fetchColumn();
$totalOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$pendingOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'Menunggu Pembayaran'")->fetchColumn();
$totalRevenue = (int)$pdo->query("SELECT SUM(total_amount) FROM orders WHERE status = 'Lunas'")->fetchColumn();

// 5 Pesanan Terbaru yang Butuh Konfirmasi Pembayaran
$recentOrders = $pdo->query("SELECT o.*, u.name as customer_name 
                             FROM orders o 
                             JOIN users u ON o.user_id = u.id 
                             ORDER BY o.id DESC LIMIT 5")->fetchAll();

$pageTitle = 'Dashboard Manajemen Toko';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
    <aside class="admin-sidebar">
        <h3>Kelola Toko</h3>
        <ul class="admin-nav">
            <li><a href="/admin/index.php" class="active">Dashboard</a></li>
            <li><a href="/admin/products.php">Tambah Barang</a></li>
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
            <h2>Ringkasan Operasional Toko</h2>
            <span class="badge badge-accent">Hak Akses: <?= ucfirst(user_role()) ?></span>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h4>Total Pendapatan Lunas</h4>
                <p class="stat-value text-success"><?= rp($totalRevenue) ?></p>
            </div>
            <div class="stat-card">
                <h4>Menunggu Pembayaran</h4>
                <p class="stat-value text-warning"><?= $pendingOrders ?> pesanan</p>
            </div>
            <div class="stat-card">
                <h4>Total Transaksi</h4>
                <p class="stat-value"><?= $totalOrders ?></p>
            </div>
            <div class="stat-card">
                <h4>Kue Aktif</h4>
                <p class="stat-value"><?= $totalProducts ?> jenis</p>
            </div>
        </div>

        <div class="admin-card mt-4">
            <div class="section-header">
                <h3>Pesanan Terbaru & Kasir</h3>
                <a href="/admin/report.php" class="btn btn-sm btn-outline">Lihat Laporan Lengkap</a>
            </div>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Pelanggan</th>
                            <th>Total</th>
                            <th>Metode</th>
                            <th>Status</th>
                            <th>Aksi Kasir</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentOrders)): ?>
                            <tr><td colspan="6" class="text-center">Belum ada transaksi pesanan.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentOrders as $ro): ?>
                                <tr>
                                    <td>#<?= $ro['id'] ?></td>
                                    <td><strong><?= e($ro['customer_name']) ?></strong></td>
                                    <td><?= rp($ro['total_amount']) ?></td>
                                    <td><?= e($ro['payment_method']) ?></td>
                                    <td>
                                        <span class="status-pill status-<?= strtolower(str_replace(' ', '-', $ro['status'])) ?>">
                                            <?= e($ro['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($ro['status'] === 'Menunggu Pembayaran'): ?>
                                            <form method="POST" action="/admin/report.php" style="display:inline;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="confirm_payment">
                                                <input type="hidden" name="order_id" value="<?= $ro['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Konfirmasi bahwa uang pembayaran sudah diterima kasir?')">Konfirmasi Lunas</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="muted">—</span>
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
