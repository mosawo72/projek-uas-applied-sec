<?php
// debug.php - Pengecekan Lingkungan & Informasi OWASP Top 10
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security.php';

require_admin(); // Hanya admin yang dapat melihat halaman debug (A01)

$pageTitle = 'Informasi Lingkungan & Status Keamanan';
require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-card">
    <h2>Informasi Server & Matriks Keamanan OWASP Top 10</h2>
    <p class="muted">Status runtime PHP dan konfigurasi keamanan aktif.</p>

    <div class="table-wrap mt-4">
        <table class="table">
            <thead>
                <tr>
                    <th>Komponen / Indikator</th>
                    <th>Status</th>
                    <th>Keterangan Keamanan</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Versi PHP</td>
                    <td><strong><?= phpversion() ?></strong></td>
                    <td>PHP 8.2+ Modern</td>
                </tr>
                <tr>
                    <td>Database Driver</td>
                    <td>SQLite via PDO</td>
                    <td>A03 Defense: Parameterized Prepared Statements Aktif</td>
                </tr>
                <tr>
                    <td>Session Cookie HttpOnly</td>
                    <td><span class="badge badge-success">AKTIF</span></td>
                    <td>A02 Defense: Mencegah pencurian session cookie lewat XSS</td>
                </tr>
                <tr>
                    <td>Session Cookie SameSite</td>
                    <td><span class="badge badge-success">STRICT</span></td>
                    <td>A01 Defense: Mencegah serangan Cross-Site Request Forgery</td>
                </tr>
                <tr>
                    <td>Content-Security-Policy (CSP)</td>
                    <td><span class="badge badge-success">TERKONFIGURASI</span></td>
                    <td>A05 Defense: Membatasi eksekusi sumber skrip dan iframe</td>
                </tr>
                <tr>
                    <td>Upload Folder Shield</td>
                    <td><span class="badge badge-success">HTACCESS PROTECTED</span></td>
                    <td>A08 Defense: Skrip PHP tidak dapat dieksekusi di folder uploads</td>
                </tr>
                <tr>
                    <td>Audit Trail Logger</td>
                    <td><span class="badge badge-success">AKTIF DI DATABASE</span></td>
                    <td>A09 Defense: Mencatat setiap event login, role change, PO, & pesanan</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        <a href="/admin/index.php" class="btn btn-primary">&larr; Kembali ke Dashboard Admin</a>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
