<?php
$pageTitle = 'Order Status';
require_once __DIR__ . '/includes/header.php';

$order = null;
$orderItems = [];
$statusError = null;

$orderId = isset($_GET['order']) ? (int) $_GET['order'] : 0;
$orderEmail = isset($_GET['email']) ? filter_var($_GET['email'], FILTER_VALIDATE_EMAIL) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = isset($_POST['order']) ? (int) $_POST['order'] : 0;
    $orderEmail = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
}

if ($orderId && $orderEmail) {
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
    } else {
        $statusError = 'We could not find an order with those details. Please double-check your information.';
    }
}
?>
<section class="container">
    <h1 class="section-title">Track your order</h1>
    <p>Enter your order number and email to view the latest updates.</p>

    <form method="post" class="form-grid" style="margin-top: 2rem; max-width: 520px;">
        <div>
            <label for="order">Order number</label>
            <input type="number" id="order" name="order" required value="<?= $orderId ? htmlspecialchars((string) $orderId) : '' ?>">
        </div>
        <div>
            <label for="email">Email address</label>
            <input type="email" id="email" name="email" required value="<?= $orderEmail ? htmlspecialchars($orderEmail) : '' ?>">
        </div>
        <button type="submit" class="btn-primary">Check status</button>
    </form>

    <?php if ($statusError): ?>
        <div class="notice" style="margin-top: 2rem;">
            <?= htmlspecialchars($statusError) ?>
        </div>
    <?php endif; ?>

    <?php if ($order): ?>
        <div class="order-status-card" style="margin-top: 3rem;">
            <div class="status-pill">Status: <?= htmlspecialchars($order['status']) ?></div>
            <h2>Order #<?= htmlspecialchars((string) $order['id']) ?></h2>
            <p>Placed on <?= date('F j, Y g:i A', strtotime($order['created_at'])) ?></p>
            <p><strong>Shipping to:</strong><br><?= nl2br(htmlspecialchars($order['shipping_address'])) ?></p>
            <h3>Items</h3>
            <ul>
                <?php foreach ($orderItems as $item): ?>
                    <li><?= htmlspecialchars($item['name']) ?> × <?= (int) $item['quantity'] ?> — <?= format_price($item['unit_price'] * $item['quantity']) ?></li>
                <?php endforeach; ?>
            </ul>
            <?php if (!empty($order['discount_amount']) && (float) $order['discount_amount'] > 0): ?>
                <p>Discount applied: −<?= format_price((float) $order['discount_amount']) ?><?php if (!empty($order['promo_code'])): ?> using code <?= htmlspecialchars($order['promo_code']) ?><?php endif; ?></p>
            <?php endif; ?>
            <p><strong>Total paid:</strong> <?= format_price((float) $order['total']) ?></p>
            <p>We will email updates to <strong><?= htmlspecialchars($order['customer_email']) ?></strong>.</p>
            <p style="margin-top: 1.5rem;">
                <a class="btn-secondary" href="review.php?order=<?= htmlspecialchars(urlencode((string) $order['id']), ENT_QUOTES, 'UTF-8') ?>&email=<?= htmlspecialchars(urlencode((string) $order['customer_email']), ENT_QUOTES, 'UTF-8') ?>">Share a review of this order</a>
            </p>
        </div>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
