<?php
// admin/report.php - Laporan Penjualan Siap Cetak PDF (Ketentuan: Laporan Penjualan dapat dicetak .pdf)
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';

require_staff();

// Kasir Konfirmasi Pembayaran
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_payment') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Token CSRF tidak valid.';
    } else {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE orders SET status = 'Lunas', paid_at = CURRENT_TIMESTAMP WHERE id = ? AND status = 'Menunggu Pembayaran'");
        $stmt->execute([$orderId]);

        if ($stmt->rowCount() > 0) {
            log_audit($pdo, $_SESSION['user']['id'], 'PAYMENT_CONFIRMED', "Konfirmasi lunas order #$orderId");
            $_SESSION['flash_success'] = "Pembayaran untuk pesanan #$orderId berhasil dikonfirmasi lunas.";
        }
    }
    header('Location: /admin/report.php');
    exit;
}

$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

$sql = "SELECT o.*, u.name as customer_name, u.email as customer_email 
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        WHERE o.status = 'Lunas'";
$params = [];

if (!empty($startDate)) {
    $sql .= " AND DATE(o.paid_at) >= ?";
    $params[] = $startDate;
}
if (!empty($endDate)) {
    $sql .= " AND DATE(o.paid_at) <= ?";
    $params[] = $endDate;
}

$sql .= " ORDER BY o.paid_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll();

$totalRevenue = array_sum(array_column($sales, 'total_amount'));

$pageTitle = 'Laporan Penjualan (PDF)';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout no-print-flex">
    <aside class="admin-sidebar no-print">
        <h3>Kelola Toko</h3>
        <ul class="admin-nav">
            <li><a href="/admin/index.php">Dashboard</a></li>
            <li><a href="/admin/products.php">Tambah Barang</a></li>
            <li><a href="/admin/categories.php">Tambah Kategori</a></li>
            <li><a href="/admin/purchases.php">Pembelian (PO)</a></li>
            <li><a href="/admin/report.php" class="active">Laporan Penjualan</a></li>
            <?php if (is_admin()): ?>
                <li><a href="/admin/users.php">Master Pengguna</a></li>
            <?php endif; ?>
        </ul>
    </aside>

    <div class="admin-main">
        <div class="section-header no-print">
            <h2>Laporan Penjualan Toko</h2>
            <div class="form-actions">
                <a href="/admin/export_pdf.php?start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>" class="btn btn-primary">Unduh File PDF</a>
                <button onclick="window.print()" class="btn btn-outline">Cetak / Print</button>
            </div>
        </div>

        <!-- Filter Periode (Disembunyikan saat dicetak) -->
        <div class="admin-card mb-4 no-print">
            <form method="GET" action="/admin/report.php" class="form-grid-3">
                <div class="form-group">
                    <label for="start_date">Dari Tanggal Pelunasan:</label>
                    <input type="date" id="start_date" name="start_date" value="<?= e($startDate) ?>">
                </div>
                <div class="form-group">
                    <label for="end_date">Sampai Tanggal:</label>
                    <input type="date" id="end_date" name="end_date" value="<?= e($endDate) ?>">
                </div>
                <div class="form-actions align-end">
                    <button type="submit" class="btn btn-outline">Filter Laporan</button>
                    <a href="/admin/report.php" class="btn btn-outline">Reset</a>
                </div>
            </form>
        </div>

        <!-- Lembar Laporan Resmi (Dioptimasi untuk Print ke PDF) -->
        <div class="report-paper">
            <div class="report-header">
                <div>
                    <h1>TORTA SHOP</h1>
                    <p>Patiseri & Toko Kue Segar Indonesia</p>
                </div>
                <div class="text-right">
                    <h2>LAPORAN PENJUALAN</h2>
                    <p class="muted">Periode: <?= $startDate ? e($startDate) : 'Awal' ?> s/d <?= $endDate ? e($endDate) : 'Sekarang' ?></p>
                    <p class="muted">Dicetak oleh: <?= e($_SESSION['user']['name']) ?> (<?= date('d/m/Y H:i') ?> WIB)</p>
                </div>
            </div>

            <div class="table-wrap">
                <table class="table report-table">
                    <thead>
                        <tr>
                            <th>No. Order</th>
                            <th>Tanggal Lunas</th>
                            <th>Pelanggan</th>
                            <th>Metode</th>
                            <th>Total Penjualan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sales)): ?>
                            <tr><td colspan="5" class="text-center">Tidak ada transaksi lunas pada periode ini.</td></tr>
                        <?php else: ?>
                            <?php foreach ($sales as $s): ?>
                                <tr>
                                    <td>#<?= $s['id'] ?></td>
                                    <td><?= e($s['paid_at'] ?? $s['created_at']) ?></td>
                                    <td><strong><?= e($s['customer_name']) ?></strong> (<?= e($s['customer_email']) ?>)</td>
                                    <td><?= e($s['payment_method']) ?></td>
                                    <td><?= rp($s['total_amount']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="4" class="text-right">TOTAL PENDAPATAN LUNAS:</th>
                            <th><?= rp($totalRevenue) ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="report-signature">
                <div class="signature-box">
                    <p>Disetujui oleh Administrator,</p>
                    <br><br><br>
                    <p><strong>( _________________________ )</strong></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
