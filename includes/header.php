<?php
// includes/header.php - Komponen Header & Navbar
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/security.php';

$user = current_user();
$cartCount = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' - ' . APP_NAME : APP_NAME ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<header class="site-header">
    <div class="container header-wrap">
        <a href="/index.php" class="brand">
            <span class="brand-text">Torta <strong>Shop</strong></span>
        </a>

        <nav class="nav-links">
            <a href="/index.php">Beranda</a>
            <a href="/products.php">Katalog Kue</a>
            <a href="/search.php">Cari</a>
            
            <?php if ($user): ?>
                <a href="/orders.php">Pesanan Saya</a>
                <?php if (is_staff()): ?>
                    <a href="/admin/index.php" class="nav-admin-link">Kelola Toko</a>
                <?php endif; ?>
            <?php endif; ?>
        </nav>

        <div class="user-actions">
            <a href="/cart.php" class="cart-pill">
                Keranjang <span class="badge"><?= $cartCount ?></span>
            </a>

            <?php if ($user): ?>
                <div class="user-chip">
                    <a href="/profile.php" class="user-profile-link">
                        <?php if (!empty($user['avatar'])): ?>
                            <img src="/uploads/<?= e($user['avatar']) ?>" class="avatar-mini" alt="Avatar">
                        <?php else: ?>
                            <span class="avatar-placeholder"><?= strtoupper(substr($user['name'], 0, 1)) ?></span>
                        <?php endif; ?>
                        <span class="user-name"><?= e($user['name']) ?></span>
                        <span class="role-tag role-<?= e($user['role']) ?>"><?= ucfirst(e($user['role'])) ?></span>
                    </a>
                    <a href="/logout.php" class="btn btn-sm btn-outline">Keluar</a>
                </div>
            <?php else: ?>
                <a href="/login.php" class="btn btn-sm btn-outline">Masuk</a>
                <a href="/register.php" class="btn btn-sm btn-primary">Daftar</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<main class="container main-content">
    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="alert alert-success">
            <?= e($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger">
            <?= e($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
        </div>
    <?php endif; ?>
