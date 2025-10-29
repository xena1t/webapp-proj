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
    <link rel="stylesheet" href="assets/css/main.css">
    <script defer src="assets/js/app.js"></script>
</head>

<body>
    <div class="page-shell">
        <aside class="sidebar" id="sidebar" aria-label="Primary">
            <div class="sidebar-inner">
                <a class="logo" href="index.php">
                    <span class="logo-mark" aria-hidden="true">◎</span>
                    <span>TechMart</span>
                </a>
                <nav class="sidebar-nav" aria-label="Main navigation">
                    <ul>

                        <li><a class="sidebar-link" href="index.php">Home</a></li>
                        <li class="sidebar-dropdown">
                            <a class="sidebar-link <?= $currentPage === 'products.php' ? 'active' : '' ?>"
                                href="products.php" aria-expanded="false">
                                <span>Shop</span>
                                <span class="chevron" aria-hidden="true">▾</span>
                            </a>
                            <ul class="sidebar-submenu" hidden>
                                <?php foreach ($categories as $category): ?>
                                    <li>
                                        <a class="sidebar-sublink" href="products.php?category=<?= urlencode($category) ?>">
                                            <?= htmlspecialchars($category) ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </li>

                        <li><a class="sidebar-link <?= $currentPage === 'checkout.php' ? 'active' : '' ?>"
                                href="checkout.php">Cart</a></li>
                        <li><a class="sidebar-link <?= $currentPage === 'order-status.php' ? 'active' : '' ?>"
                                href="order-status.php">Order Status</a></li>
                    </ul>
                </nav>
                <div class="sidebar-cta">
                    <p>Get early access to drops, restocks, and insider guides.</p>
                    <button class="btn-secondary sidebar-newsletter" type="button" data-open-modal>Join the
                        newsletter</button>
                </div>
            </div>
        </aside>
        <div class="content-column">
            <header class="top-bar">
                <button class="sidebar-toggle" type="button" aria-controls="sidebar" aria-expanded="false">
                    <span class="sr-only">Toggle navigation</span>
                    ☰
                </button>
                <div class="top-links">
                    <a class="top-link" href="checkout.php">Checkout</a>
                    <a class="top-link" href="order-status.php">Track order</a>

                    <!-- top bar -->

                    <?php if (isset($_SESSION['user'])): ?>
                        <span class="welcome">
                            <i class="fa fa-user-circle"></i>
                            <em><i><?= htmlspecialchars($_SESSION['user']['username']) ?></i></em>
                        </span>
                        <a class="logout-link" href="logout.php">Logout</a>
                    <?php else: ?>
                        <a class="login-link" href="login.php">Login</a>
                    <?php endif; ?>


                </div>

            </header>
            <main class="site-main">