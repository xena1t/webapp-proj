<?php
require_once __DIR__ . '/includes/functions.php';

start_session();
if (!is_user_logged_in()) {
    $_SESSION['flash_error'] = 'Please log in to view your orders.';
    header('Location: login.php');
    exit;
}

$user = get_authenticated_user();
$orders = fetch_orders_for_user((int) $user['id']);
$pageTitle = 'My Orders';

require_once __DIR__ . '/includes/header.php';
?>
<section class="container">
    <h1 class="section-title">Order history</h1>
    <p class="text-muted" style="margin-bottom: 2rem;">Welcome back <?= htmlspecialchars($user['username']) ?>. Here’s a summary of your recent TechMart orders.</p>

    <?php if (empty($orders)): ?>
        <div class="notice">
            <p>You haven't placed any orders yet. <a href="products.php">Browse the catalog</a> to get started.</p>
        </div>
    <?php else: ?>
        <div class="order-history">
            <?php foreach ($orders as $order): ?>
                <article class="order-history-card">
                    <header class="order-history-header">
                        <div>
                            <h2>Order #<?= htmlspecialchars((string) $order['id']) ?></h2>
                            <p class="text-muted">Placed on <?= date('F j, Y g:i A', strtotime($order['created_at'])) ?></p>
                        </div>
                        <div class="order-history-status">
                            <span class="status-pill">Status: <?= htmlspecialchars($order['status']) ?></span>
                        </div>
                    </header>
                    <div class="order-history-body">
                        <div class="order-history-summary">
                            <p><strong>Total paid:</strong> <?= format_price((float) $order['total']) ?></p>
                            <?php if (!empty($order['discount_amount']) && (float) $order['discount_amount'] > 0): ?>
                                <p>Discount saved: −<?= format_price((float) $order['discount_amount']) ?><?php if (!empty($order['promo_code'])): ?> with code <?= htmlspecialchars($order['promo_code']) ?><?php endif; ?></p>
                            <?php endif; ?>
                            <p><strong>Ship to:</strong><br><?= nl2br(htmlspecialchars($order['shipping_address'])) ?></p>
                        </div>
                        <div class="order-history-items">
                            <h3>Items in this order</h3>
                            <?php if (!empty($order['items'])): ?>
                                <ul>
                                    <?php foreach ($order['items'] as $item): ?>
                                        <li>
                                            <span class="item-name"><?= htmlspecialchars($item['name']) ?></span>
                                            <span class="item-meta">× <?= (int) $item['quantity'] ?> — <?= format_price((float) $item['line_total']) ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted">Item details are unavailable for this order.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <footer class="order-history-footer">
                        <a class="btn-secondary" href="order-status.php?order=<?= urlencode((string) $order['id']) ?>&amp;email=<?= urlencode($order['customer_email']) ?>">Track this order</a>
                        <span class="text-muted">Updates sent to <?= htmlspecialchars($order['customer_email']) ?></span>
                    </footer>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
