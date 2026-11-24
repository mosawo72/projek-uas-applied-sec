-- Skema Database SQLite untuk Toko Kue / Torta Shop (OWASP Compliant)
PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'customer', -- 'administrator', 'pegawai', 'customer'
    avatar TEXT,
    active INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    active INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    category_id INTEGER NOT NULL REFERENCES categories(id) ON DELETE RESTRICT,
    name TEXT NOT NULL,
    description TEXT,
    price INTEGER NOT NULL CHECK(price > 0),
    stock INTEGER NOT NULL DEFAULT 0 CHECK(stock >= 0),
    image TEXT,
    active INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id),
    total_amount INTEGER NOT NULL CHECK(total_amount >= 0),
    status TEXT NOT NULL DEFAULT 'Menunggu Pembayaran', -- 'Menunggu Pembayaran', 'Lunas', 'Dibatalkan'
    payment_method TEXT,
    paid_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS order_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    product_id INTEGER NOT NULL REFERENCES products(id),
    product_name TEXT NOT NULL,
    price INTEGER NOT NULL,
    qty INTEGER NOT NULL CHECK(qty > 0),
    subtotal INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS purchases (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    supplier TEXT NOT NULL,
    product_id INTEGER NOT NULL REFERENCES products(id),
    product_name TEXT NOT NULL,
    qty INTEGER NOT NULL CHECK(qty > 0),
    cost INTEGER NOT NULL CHECK(cost > 0),
    total INTEGER NOT NULL,
    status TEXT NOT NULL DEFAULT 'Dipesan', -- 'Dipesan', 'Diterima'
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    received_at DATETIME
);

CREATE TABLE IF NOT EXISTS audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    action TEXT NOT NULL,
    details TEXT,
    ip_address TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS login_attempts (
    ip_key TEXT PRIMARY KEY,
    attempts INTEGER NOT NULL DEFAULT 1,
    last_attempt INTEGER NOT NULL
);

-- Seed Data Awal
INSERT OR IGNORE INTO categories (id, name, active) VALUES 
(1, 'Kue Cokelat', 1),
(2, 'Kue Buah & Tart', 1),
(3, 'Cheesecake', 1);

INSERT OR IGNORE INTO products (id, category_id, name, description, price, stock, image, active) VALUES 
(1, 1, 'Chocolate Ganache Deluxe', 'Kue cokelat leleh dengan lapisan ganache belgia yang lembut dan pekat.', 165000, 15, 'cokelat.jpg', 1),
(2, 2, 'Strawberry Shortcake', 'Kue bolu spons vanila lembut dengan krim segar dan buah stroberi segar pilihan.', 185000, 10, 'stroberi.jpg', 1),
(3, 3, 'Classic New York Cheesecake', 'Cheesecake panggang klasik yang creamy dengan dasar biskuit gurih dan mentega.', 175000, 12, 'keju.jpg', 1);
