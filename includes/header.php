<?php
require_once __DIR__ . '/functions.php';
start_session();
$categories = fetch_categories();
$activeCategory = isset($_GET['category']) ? trim((string) $_GET['category']) : null;
if ($activeCategory === '') {
    $activeCategory = null;
}
$searchQueryValue = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$currentPage = basename($_SERVER['PHP_SELF']);
$shopExpanded = $currentPage === 'products.php';
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
                                href="products.php" aria-expanded="<?= $shopExpanded ? 'true' : 'false' ?>"
                                data-toggle-submenu>
                                <span>Shop</span>
                                <span class="chevron" aria-hidden="true">▾</span>
                            </a>
                            <ul class="sidebar-submenu" <?= $shopExpanded ? '' : ' hidden' ?>>
                                <?php if ($categories): ?>
                                    <?php foreach ($categories as $category): ?>
                                        <li>
                                            <a class="sidebar-sublink <?= ($shopExpanded && $activeCategory === $category) ? 'active' : '' ?>"
                                                href="products.php?category=<?= urlencode($category) ?>">
                                                <?= htmlspecialchars($category) ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li><span class="sidebar-sublink text-muted">Catalog unavailable</span></li>
                                <?php endif; ?>
                            </ul>
                        </li>

                        <li><a class="sidebar-link <?= $currentPage === 'checkout.php' ? 'active' : '' ?>"
                                href="checkout.php">Cart</a></li>
                        <li><a class="sidebar-link <?= $currentPage === 'wishlist.php' ? 'active' : '' ?>"
                                href="wishlist.php">Wishlist</a></li>
                        <!-- <li><a class="sidebar-link <?= $currentPage === 'order-status.php' ? 'active' : '' ?>"
                                href="order-status.php">Order Status</a></li> -->
                        <?php if (is_user_logged_in()): ?>
                            <li><a class="sidebar-link <?= $currentPage === 'account_orders.php' ? 'active' : '' ?>"
                                    href="account_orders.php">My Orders</a></li>
                        <?php endif; ?>
                        <?php if (is_user_admin()): ?>
                            <li class="sidebar-dropdown">
                                <a class="sidebar-link <?= in_array($currentPage, ['admin.php', 'admin_sales.php', 'admin_report.php']) ? 'active' : '' ?>"
                                    href="admin.php"
                                    aria-expanded="<?= in_array($currentPage, ['admin.php', 'admin_sales.php', 'admin_report.php']) ? 'true' : 'false' ?>"
                                    data-toggle-submenu>
                                    <span>Admin Page</span>
                                    <span class="chevron" aria-hidden="true">▾</span>
                                </a>
                                <ul class="sidebar-submenu" <?= in_array($currentPage, ['admin.php', 'admin_sales.php', 'admin_report.php']) ? '' : ' hidden' ?>>
                                    <li><a class="sidebar-sublink <?= $currentPage === 'admin.php' ? 'active' : '' ?>"
                                            href="admin.php">Add & Delete Products</a></li>
                                    <li><a class="sidebar-sublink <?= $currentPage === 'admin_sales.php' ? 'active' : '' ?>"
                                            href="admin_sales.php">Sales</a></li>
                                    <li><a class="sidebar-sublink <?= $currentPage === 'admin_report.php' ? 'active' : '' ?>"
                                            href="admin_report.php">Sales Report</a></li>
                                </ul>
                            </li>
                        <?php endif; ?>`

                    </ul>
                </nav>
                <?php
                $isLoggedIn = is_user_logged_in();
                $authenticatedUser = $isLoggedIn ? get_authenticated_user() : null;

                $isSubscribed = false;
                if ($isLoggedIn && $authenticatedUser) {
                    try {
                        $pdo = get_db_connection();
                        $stmt = $pdo->prepare('SELECT 1 FROM newsletter_subscribers WHERE email = :email LIMIT 1');
                        $stmt->execute(['email' => strtolower(trim($authenticatedUser['email']))]);
                        $isSubscribed = (bool) $stmt->fetchColumn();
                    } catch (Throwable $e) {
                        $isSubscribed = false; // fallback if query fails
                    }
                }
                ?>

                <?php if (!$isSubscribed): ?>
                    <div class="sidebar-cta">
                        <p>Get early access to drops, restocks, and insider guides.</p>
                        <button class="btn-secondary sidebar-newsletter" type="button" data-open-modal>
                            Join the newsletter
                        </button>
                    </div>
                <?php endif; ?>

            </div>
        </aside>
        <div class="content-column">
            <header class="top-bar">
                <button class="sidebar-toggle" type="button" aria-controls="sidebar" aria-expanded="false">
                    <span class="sr-only">Toggle navigation</span>
                    ☰
                </button>
                <form class="top-search" action="products.php" method="get" role="search">
                    <label class="sr-only" for="topSearch">Search products</label>
                    <input type="search"
                        id="topSearch"
                        name="q"
                        placeholder="Search for products"
                        value="<?= htmlspecialchars($searchQueryValue) ?>"
                        aria-label="Search for products">
                    <?php if ($currentPage === 'products.php' && $activeCategory): ?>
                        <input type="hidden" name="category" value="<?= htmlspecialchars($activeCategory) ?>">
                    <?php endif; ?>
                    <button type="submit" class="btn-search">Search</button>
                </form>
                <div class="top-links">
                    <!-- <a class="top-link <?= $currentPage === 'wishlist.php' ? 'active' : '' ?>" href="wishlist.php">Wishlist</a> -->
                    <a class="top-link <?= $currentPage === 'checkout.php' ? 'active' : '' ?>" href="checkout.php">Checkout</a>
                    <!-- <a class="top-link <?= $currentPage === 'order-status.php' ? 'active' : '' ?>" href="order-status.php">Track order</a> -->
                    <a class="top-link <?= $currentPage === 'review.php' ? 'active' : '' ?>" href="review.php">Leave review</a>
                    <a class="top-link <?= $currentPage === 'about.php' ? 'active' : '' ?>" href="about.php">About</a>
                    <a class="top-link <?= $currentPage === 'contact.php' ? 'active' : '' ?>" href="contact.php">Contact</a>
                    <?php if (is_user_logged_in()): ?>
                        <a class="top-link <?= $currentPage === 'account_orders.php' ? 'active' : '' ?>"
                            href="account_orders.php">My orders</a>
                    <?php endif; ?>

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