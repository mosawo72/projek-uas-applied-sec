<?php
// admin/export_pdf.php - Generator File PDF Menggunakan Standar FPDF Resmi
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/fpdf/fpdf.php';

require_staff();

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

function formatRp($val) {
    return 'Rp ' . number_format((float)$val, 0, ',', '.');
}

class PDF_Laporan extends FPDF {
    public $periode;
    public $printedBy;

    function Header() {
        // Judul Toko
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 8, 'TORTA SHOP', 0, 1, 'L');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, 'Patiseri & Toko Kue Segar Indonesia', 0, 1, 'L');
        $this->Ln(2);

        // Judul Dokumen
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 6, 'LAPORAN PENJUALAN', 0, 1, 'R');
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 4, 'Periode: ' . $this->periode, 0, 1, 'R');
        $this->Cell(0, 4, 'Dicetak: ' . date('d/m/Y H:i') . ' WIB oleh ' . $this->printedBy, 0, 1, 'R');
        $this->Ln(3);

        // Garis Pembatas Header
        $this->SetDrawColor(40, 40, 40);
        $this->SetLineWidth(0.6);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(5);

        // Header Tabel
        $this->SetFillColor(240, 240, 240);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(20, 8, 'No. Order', 1, 0, 'C', true);
        $this->Cell(38, 8, 'Tanggal Lunas', 1, 0, 'C', true);
        $this->Cell(57, 8, 'Nama Pelanggan', 1, 0, 'L', true);
        $this->Cell(35, 8, 'Metode Bayar', 1, 0, 'C', true);
        $this->Cell(40, 8, 'Total Penjualan', 1, 1, 'R', true);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Halaman ' . $this->PageNo() . ' - Torta Shop Applied Security', 0, 0, 'C');
    }
}

$pdf = new PDF_Laporan('P', 'mm', 'A4');
$pdf->periode = ($startDate ?: 'Awal') . ' s/d ' . ($endDate ?: 'Sekarang');
$pdf->printedBy = $_SESSION['user']['name'] ?? 'Staff';
$pdf->SetMargins(10, 15, 10);
$pdf->AddPage();

$pdf->SetFont('Arial', '', 9);

if (empty($sales)) {
    $pdf->Cell(190, 12, 'Tidak ada transaksi penjualan lunas pada periode ini.', 1, 1, 'C');
} else {
    foreach ($sales as $s) {
        $pdf->Cell(20, 7, '#' . $s['id'], 1, 0, 'C');
        $pdf->Cell(38, 7, substr($s['paid_at'] ?? $s['created_at'], 0, 16), 1, 0, 'C');
        $pdf->Cell(57, 7, ' ' . substr($s['customer_name'], 0, 28), 1, 0, 'L');
        $pdf->Cell(35, 7, $s['payment_method'], 1, 0, 'C');
        $pdf->Cell(40, 7, formatRp($s['total_amount']) . ' ', 1, 1, 'R');
    }
}

// Baris Total Penjualan
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(245, 245, 245);
$pdf->Cell(150, 8, 'TOTAL PENDAPATAN LUNAS :  ', 1, 0, 'R', true);
$pdf->Cell(40, 8, formatRp($totalRevenue) . ' ', 1, 1, 'R', true);

// Tanda Tangan
$pdf->Ln(15);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(130, 5, '', 0, 0);
$pdf->Cell(60, 5, 'Disetujui oleh Administrator,', 0, 1, 'C');
$pdf->Ln(20);
$pdf->Cell(130, 5, '', 0, 0);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(60, 5, '( ________________________ )', 0, 1, 'C');

// Download PDF Attachment
$fileName = 'laporan-penjualan-' . date('Ymd-His') . '.pdf';
$pdf->Output('D', $fileName);
exit;
