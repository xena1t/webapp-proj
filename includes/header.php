<?php
require_once __DIR__ . '/functions.php';
start_session();
$categories = fetch_categories();
$currentPage = basename($_SERVER['PHP_SELF']);
$computedTitle = $pageTitle ?? ucfirst(str_replace(['.php', '-'], ['', ' '], $currentPage));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?> | <?= htmlspecialchars($computedTitle) ?></title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <script defer src="/assets/js/app.js"></script>
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <a class="logo" href="/index.php">TechMart</a>
        <nav class="primary-nav" aria-label="Main">
            <ul>
                <li><a href="/index.php" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">Home</a></li>
                <li class="dropdown">
                    <button class="dropdown-toggle" aria-haspopup="true" aria-expanded="false">Categories</button>
                    <ul class="dropdown-menu">
                        <?php foreach ($categories as $category): ?>
                            <li><a href="/products.php?category=<?= urlencode($category) ?>"><?= htmlspecialchars($category) ?></a></li>
                        <?php endforeach; ?>
                        <li><a href="/products.php">All Products</a></li>
                    </ul>
                </li>
                <li><a href="/products.php" class="<?= $currentPage === 'products.php' ? 'active' : '' ?>">Shop</a></li>
                <li><a href="/checkout.php" class="<?= $currentPage === 'checkout.php' ? 'active' : '' ?>">Cart</a></li>
                <li><a href="/order-status.php" class="<?= $currentPage === 'order-status.php' ? 'active' : '' ?>">Order Status</a></li>
            </ul>
        </nav>
        <button class="mobile-nav-toggle" aria-controls="primary-menu" aria-expanded="false">
            <span class="sr-only">Toggle navigation</span>
            ☰
        </button>
    </div>
</header>
<main class="site-main">
