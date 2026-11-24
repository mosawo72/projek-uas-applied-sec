# Torta Shop — Toko Penjualan & Pembelian Patiseri Kue

Aplikasi web e-commerce dan manajemen toko kue yang dibangun menggunakan **PHP Native (Modern PDO)** dengan mematuhi **Guideline OWASP Top 10**.

---

## 🚀 Panduan Menjalankan Aplikasi

Aplikasi menggunakan server bawaan PHP (*Built-in Web Server*):

1. Pastikan Anda berada di direktori proyek ini.
2. Jalankan perintah:
   ```bash
   C:\xampp\php\php.exe -S 127.0.0.1:8000
   ```
   *(atau cukup `php -S 127.0.0.1:8000` jika PHP sudah terdaftar di PATH lingkungan Anda).*
3. Buka browser dan akses: [http://127.0.0.1:8000](http://127.0.0.1:8000)

---

## 🔑 Akun Login Bawaan (Demo)

| Role / Peran | Alamat Email | Kata Sandi | Deskripsi Hak Akses |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin@torta.id` | `Admin12345678!` | Akses Penuh: Master User (Ubah Role), Master Barang, Kategori, PO Pemasok, Laporan PDF. |
| **Pegawai** | `pegawai@torta.id` | `Pegawai12345678!` | Akses Toko: Master Barang, Master Kategori, Pembelian/PO, Konfirmasi Pembayaran Kasir, Laporan PDF. |
| **Customer** | `customer@torta.id` | `Customer12345678!` | Akses Pelanggan: Belanja Katalog, Keranjang, Checkout Pemesanan, Riwayat & Batalkan Pesanan. |

*(Anda juga dapat mendaftarkan akun baru langsung via menu **Daftar** di pojok kanan atas).*

---

## 📂 Struktur Folder Proyek

```text
📁 d:\Projek applied security\
├── 📁 admin/                         # Panel Khusus Administrator & Pegawai
│   ├── index.php                     # Dashboard & Operasional Toko
│   ├── products.php                  # Master Barang (CRUD & Upload Foto)
│   ├── categories.php                # Master Kategori
│   ├── users.php                     # Master User & Pengaturan Role (Admin Only)
│   ├── purchases.php                 # Pembelian ke Pemasok / Supplier (PO)
│   └── report.php                    # Laporan Penjualan (Cetak PDF)
├── 📁 assets/                        # CSS, JS, Gambar Statis
│   ├── css/style.css                 # Desain modern & media print cetak PDF
│   ├── js/main.js                    # Skrip interaksi UI
│   └── img/                          # Aset gambar kue dan banner
├── 📁 database/                      # Skema Database & Migrasi
│   ├── schema.sql                    # Skema SQLite / SQL
│   └── torta.sqlite                  # Berkas database runtime SQLite
├── 📁 docs/                          # Folder Catatan & Dokumentasi
├── 📁 includes/                      # Modul & Fungsi Inti (Security & DB)
│   ├── db.php                        # Koneksi Database PDO (Anti SQLi)
│   ├── auth.php                      # RBAC, Otentikasi & Verifikasi Role
│   ├── security.php                  # CSRF, XSS filter, Brute-Force Rate Limiter
│   ├── header.php                    # Layout Header & Navbar Navigasi
│   └── footer.php                    # Layout Footer
├── 📁 uploads/                       # Folder Upload Foto Barang & Avatar
│   └── .htaccess                     # Proteksi pencegahan eksekusi PHP (A08)
├── cart.php                          # Keranjang Belanja
├── checkout.php                      # Checkout & Pemesanan Kue
├── config.php                        # Konfigurasi Aplikasi & Session Security
├── debug.php                         # Ringkasan Status Server & Indikator OWASP
├── index.php                         # Beranda & Katalog Kue Unggulan
├── login.php                         # Halaman Masuk
├── logout.php                        # Keluar Sesi & Penghapusan Cookie
├── orders.php                        # Riwayat Pesanan Customer & Pembatalan
├── product_detail.php                # Rincian Produk & Masuk Keranjang
├── products.php                      # Katalog Lengkap & Filter Kategori
├── profile.php                       # Profil Pengguna & Edit Info
├── register.php                      # Pendaftaran Akun (Password Policy >= 12 char)
├── search.php                        # Pencarian Barang Bebas SQLi
├── upload_avatar.php                 # Handler Unggah Foto Profil
└── README.md                         # Dokumentasi Proyek
```

---

## 🛡️ Matriks Kepatuhan OWASP Top 10

1. **A01: Broken Access Control:**
   - Role-Based Access Control (RBAC) ketat via `require_admin()` dan `require_staff()` di folder `/admin/`.
   - Pencegahan IDOR / BOLA: Customer hanya dapat melihat dan membatalkan pesanan miliknya sendiri.
   - CSRF Token divalidasi di setiap formulir mutasi POST.

2. **A02: Cryptographic Failures:**
   - Password di-hash menggunakan algoritma standar `PASSWORD_BCRYPT` dengan random salt bawaan.
   - Session Cookie dikonfigurasi dengan flag `HttpOnly`, `SameSite=Strict`, dan perlindungan session fixation.

3. **A03: Injection:**
   - Semua query interaksi database menggunakan **PDO Parameterized Prepared Statements** (`execute([$param])`), sehingga 100% kebal terhadap SQL Injection.
   - Output string di-escape menggunakan fungsi pembantu `e()` (`htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`) untuk menangkal Cross-Site Scripting (XSS).

4. **A04: Insecure Design & Rate Limiting:**
   - Proteksi brute force login via tabel `login_attempts` dengan batas maksimal 5 kali percobaan per alamat IP (cool-down 15 menit).

5. **A05: Security Misconfiguration:**
   - Header keamanan dikirimkan di setiap respon: `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: no-referrer`, dan `Content-Security-Policy`.

6. **A07: Identification and Authentication Failures:**
   - Validasi ketat saat pendaftaran: Kata sandi wajib memiliki panjang minimal 12 karakter.
   - Pembaruan sesi otomatis (`session_regenerate_id(true)`) setelah login berhasil.

7. **A08: Software and Data Integrity Failures (File Upload):**
   - File upload divalidasi MIME type asli menggunakan `finfo` dan `getimagesize()` (bukan sekadar membaca ekstensi nama file).
   - Folder `/uploads` diproteksi dengan `.htaccess` agar berkas skrip PHP tidak dapat dieksekusi langsung (*Anti Web Shell*).

8. **A09: Security Logging and Monitoring Failures:**
   - Semua event penting (login gagal, login berhasil, registrasi, pergantian role, pembuatan PO, pembatalan order) dicatat ke tabel `audit_logs`.
