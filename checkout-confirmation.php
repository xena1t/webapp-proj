<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Order Confirmed';

$order = null;
$orderItems = [];
$loadError = null;
$discountAmount = 0.0;
$discountPercent = 0.0;
$subtotal = 0.0;

$orderId = isset($_GET['order']) ? (int) $_GET['order'] : 0;
$orderEmail = isset($_GET['email']) ? filter_var($_GET['email'], FILTER_VALIDATE_EMAIL) : null;

if ($orderId && $orderEmail) {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = :id AND customer_email = :email');
        $stmt->execute([
            'id' => $orderId,
            'email' => $orderEmail,
        ]);
        $order = $stmt->fetch();

        if ($order) {
            $itemsStmt = $pdo->prepare('SELECT oi.quantity, oi.unit_price, p.name FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = :order_id');
            $itemsStmt->execute(['order_id' => $orderId]);
            $orderItems = $itemsStmt->fetchAll();

            foreach ($orderItems as $item) {
                $subtotal += (int) $item['quantity'] * (float) $item['unit_price'];
            }

            $discountAmount = max($subtotal - (float) $order['total'], 0.0);
            if ($subtotal > 0 && $discountAmount > 0) {
                $discountPercent = round(($discountAmount / $subtotal) * 100, 1);
            }
        } else {
            $loadError = 'We could not find that order. Please double-check your confirmation email.';
        }
    } catch (Throwable $exception) {
        $loadError = 'We ran into a problem loading your order details. Please try again in a moment.';
    }
} else {
    $loadError = 'Missing order information. Use the link in your confirmation email to revisit this page.';
}

require_once __DIR__ . '/includes/header.php';
?>
<section class="container">
    <?php if ($order): ?>
        <div class="order-status-card" style="margin-top: 2rem;">
            <div class="status-pill">Order confirmed</div>
            <h1>Thanks, <?= htmlspecialchars($order['customer_name']) ?>!</h1>
            <p>We've emailed a receipt to <strong><?= htmlspecialchars($order['customer_email']) ?></strong>.</p>
            <p>Order <strong>#<?= htmlspecialchars((string) $order['id']) ?></strong> was placed on <?= date('F j, Y g:i A', strtotime($order['created_at'])) ?>.</p>

            <h2 style="margin-top: 2rem;">Items in your order</h2>
            <ul>
                <?php foreach ($orderItems as $item): ?>
                    <li><?= htmlspecialchars($item['name']) ?> × <?= (int) $item['quantity'] ?> — <?= format_price((float) $item['unit_price'] * (int) $item['quantity']) ?></li>
                <?php endforeach; ?>
            </ul>

            <div class="cart-summary" style="margin-top: 2rem;">
                <h2>Payment summary</h2>
                <p>Subtotal: <?= format_price($subtotal) ?></p>
                <?php if ($discountAmount > 0): ?>
                    <p>Newsletter savings<?= $discountPercent ? ' (' . htmlspecialchars((string) $discountPercent) . '%)' : '' ?>: -<?= format_price($discountAmount) ?></p>
                <?php endif; ?>
                <p><strong>Total charged: <?= format_price((float) $order['total']) ?></strong></p>
                <?php if (!empty($order['promo_code'])): ?>
                    <p class="text-muted">Promo code used: <?= htmlspecialchars($order['promo_code']) ?></p>
                <?php endif; ?>
            </div>

            <div style="margin-top: 2rem;">
                <h2>Shipping to</h2>
                <p><?= nl2br(htmlspecialchars($order['shipping_address'])) ?></p>
            </div>

            <div style="margin-top: 2rem; display: flex; gap: 1rem; flex-wrap: wrap;">
                <a class="btn-primary" href="order-status.php?order=<?= $order['id'] ?>&amp;email=<?= urlencode($order['customer_email']) ?>">Track your order</a>
                <a class="btn-secondary" href="products.php">Continue shopping</a>
            </div>
        </div>
    <?php else: ?>
        <div class="notice" style="margin-top: 2rem;">
            <?= htmlspecialchars($loadError) ?>
        </div>
        <p style="margin-top: 1.5rem;">
            <a class="btn-secondary" href="order-status.php">Head to the order lookup</a>
        </p>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
