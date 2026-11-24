<?php
// includes/db.php - Manajemen Koneksi Database dengan PDO (OWASP A03: SQL Injection Defense)

declare(strict_types=1);
require_once __DIR__ . '/../config.php';

try {
    $dbPath = DB_FILE;
    $dbDir = dirname($dbPath);
    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0755, true);
    }

    $isNewDb = !file_exists($dbPath);
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("PRAGMA foreign_keys = ON; PRAGMA journal_mode = WAL;");

    // Jika database baru, inisialisasi tabel dari schema.sql
    if ($isNewDb || filesize($dbPath) === 0) {
        $sql = file_get_contents(BASE_DIR . '/database/schema.sql');
        $pdo->exec($sql);

        // Seed Akun Pengguna Default (Password Hashing via BCRYPT - OWASP A02)
        $users = [
            ['Administrator', 'admin@torta.id', 'Admin12345678!', 'administrator'],
            ['Pegawai Toko', 'pegawai@torta.id', 'Pegawai12345678!', 'pegawai'],
            ['Customer Demo', 'customer@torta.id', 'Customer12345678!', 'customer'],
        ];

        $stmt = $pdo->prepare("INSERT OR IGNORE INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        foreach ($users as $u) {
            $stmt->execute([
                $u[0],
                $u[1],
                password_hash($u[2], PASSWORD_BCRYPT),
                $u[3]
            ]);
        }
    }
} catch (PDOException $e) {
    die("Database Connection Error: " . htmlspecialchars($e->getMessage()));
}
