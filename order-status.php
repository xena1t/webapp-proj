<?php
$pageTitle = 'Order Status';
require_once __DIR__ . '/includes/header.php';

$order = null;
$orderItems = [];
$statusError = null;
$trackingDetails = [];
$trackingSummary = [];
$deliveryCountdownText = null;
$expectedDeliveryLabel = null;
$courierMeta = null;

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

        if (!function_exists('format_interval_human')) {
            function format_interval_human(DateInterval $interval): string
            {
                $parts = [];

                if ($interval->d > 0) {
                    $parts[] = $interval->d . ' day' . ($interval->d === 1 ? '' : 's');
                }

                if ($interval->h > 0) {
                    $parts[] = $interval->h . ' hr' . ($interval->h === 1 ? '' : 's');
                }

                if ($interval->i > 0 && count($parts) < 2) {
                    $parts[] = $interval->i . ' min' . ($interval->i === 1 ? '' : 's');
                }

                if (!$parts) {
                    return 'under an hour';
                }

                return implode(' ', $parts);
            }
        }

        $trackingStages = [
            'Order Confirmed',
            'Packed',
            'Shipped',
            'Out for Delivery',
            'Delivered',
        ];

        $statusToStage = [
            'pending' => 1,
            'pending payment' => 1,
            'processing' => 1,
            'confirmed' => 1,
            'packed' => 2,
            'preparing shipment' => 2,
            'shipped' => 3,
            'in transit' => 3,
            'dispatched' => 3,
            'out for delivery' => 4,
            'out-for-delivery' => 4,
            'delivered' => 5,
            'completed' => 5,
        ];

        $normalizedStatus = strtolower(trim((string) $order['status']));
        $stagePosition = $statusToStage[$normalizedStatus] ?? 1;
        $stageCount = count($trackingStages);
        if ($stagePosition < 1) {
            $stagePosition = 1;
        }
        if ($stagePosition > $stageCount) {
            $stagePosition = $stageCount;
        }

        $stageOffsetsHours = [0, 18, 48, 84, 120];
        $baseTimestamp = new DateTimeImmutable($order['created_at']);
        $stageProgressPercent = $stageCount > 1 ? (($stagePosition - 1) / ($stageCount - 1)) * 100 : 100;
        $now = new DateTimeImmutable('now');

        foreach ($trackingStages as $index => $stageLabel) {
            $offsetHours = $stageOffsetsHours[$index] ?? ($index * 24);
            $timestamp = $baseTimestamp->modify("+{$offsetHours} hours");

            $isCompleted = ($index + 1) < $stagePosition;
            $isCurrent = ($index + 1) === $stagePosition;
            $isReached = ($index + 1) <= $stagePosition;

            $trackingDetails[] = [
                'label' => $stageLabel,
                'timestamp' => $timestamp,
                'isCompleted' => $isCompleted,
                'isCurrent' => $isCurrent,
                'isReached' => $isReached,
            ];
        }

        $expectedDelivery = $trackingDetails[$stageCount - 1]['timestamp'];
        $isDelivered = in_array($normalizedStatus, ['delivered', 'completed'], true);

        if ($isDelivered) {
            $deliveredAt = !empty($order['updated_at']) ? new DateTimeImmutable($order['updated_at']) : $expectedDelivery;
            $expectedDeliveryLabel = 'Delivered on ' . $deliveredAt->format('F j, Y g:i A');
        } else {
            $expectedDeliveryLabel = 'Estimated arrival: ' . $expectedDelivery->format('l, F j');

            if ($expectedDelivery > $now) {
                $deliveryCountdownText = '≈ ' . format_interval_human($now->diff($expectedDelivery));
            } else {
                $deliveryCountdownText = 'Past due by ' . format_interval_human($expectedDelivery->diff($now));
            }
        }

        $couriers = [
            ['name' => 'Ninja Van ', 'code' => 'NJ', 'url' => 'https://tracking.example.com/aurora?tracking={tracking}'],
            ['name' => 'Shopee Express Logistics', 'code' => 'SPL', 'url' => 'https://tracking.example.com/northwind/{tracking}'],
            ['name' => 'LalaMove', 'code' => 'LLM', 'url' => 'https://tracking.example.com/velocity?code={tracking}'],
        ];
        $courier = $couriers[$order['id'] % count($couriers)];
        $trackingId = strtoupper($courier['code']) . '-' . str_pad((string) $order['id'], 8, '0', STR_PAD_LEFT);
        $trackingUrl = str_replace('{tracking}', urlencode($trackingId), $courier['url']);

        $courierMeta = [
            'name' => $courier['name'],
            'id' => $trackingId,
            'url' => $trackingUrl,
            'progressPercent' => $stageProgressPercent,
        ];

        $trackingSummary = [
            'stagePosition' => $stagePosition,
            'stageCount' => $stageCount,
        ];
    } else {
        $statusError = 'We could not find an order with those details. Please double-check your information.';
    }
}
?>
<section class="container">
    <!-- <h1 class="section-title">Track your order</h1>
    <p>Enter your order number and email to view the latest updates.</p> -->

    <!-- <form method="post" class="form-grid" style="margin-top: 2rem; max-width: 520px;">
        <div>
            <label for="order">Order number</label>
            <input type="number" id="order" name="order" required value="<?= $orderId ? htmlspecialchars((string) $orderId) : '' ?>">
        </div>
        <div>
            <label for="email">Email address</label>
            <input type="email" id="email" name="email" required value="<?= $orderEmail ? htmlspecialchars($orderEmail) : '' ?>">
        </div>
        <button type="submit" class="btn-primary">Check status</button>
    </form> -->

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

            <?php if ($trackingDetails && $courierMeta): ?>
                <div class="order-tracking">
                    <div class="tracking-header">
                        <h3>Delivery progress</h3>
                        <p class="tracking-eta"><?= htmlspecialchars($expectedDeliveryLabel) ?><?php if ($deliveryCountdownText): ?> <span class="tracking-countdown">(<?= htmlspecialchars($deliveryCountdownText) ?>)</span><?php endif; ?></p>
                    </div>

                    <div class="tracking-progress">
                        <div class="tracking-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) round($courierMeta['progressPercent']) ?>">
                            <span style="width: <?= (float) $courierMeta['progressPercent'] ?>%"></span>
                        </div>
                        <ol class="tracking-timeline">
                            <?php foreach ($trackingDetails as $detail): ?>
                                <?php
                                $timestampLabel = $detail['timestamp']->format('M j, g:i A');
                                $statusLabel = $detail['isCompleted'] ? 'Completed' : ($detail['isCurrent'] && !$isDelivered ? 'In progress' : 'Estimated');
                                ?>
                                <li class="tracking-stage<?= $detail['isCompleted'] ? ' is-complete' : '' ?><?= $detail['isCurrent'] ? ' is-current' : '' ?><?= $detail['isReached'] ? ' is-reached' : '' ?>">
                                    <div class="stage-indicator" aria-hidden="true">
                                        <span class="stage-dot"></span>
                                    </div>
                                    <div class="stage-content">
                                        <p class="stage-label"><?= htmlspecialchars($detail['label']) ?></p>
                                        <p class="stage-meta"><?= htmlspecialchars($statusLabel) ?> · <?= htmlspecialchars($timestampLabel) ?></p>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </div>

                    <div class="tracking-footer">
                        <div class="tracking-meta">
                            <div>
                                <span class="meta-label">Courier</span>
                                <span class="meta-value"><?= htmlspecialchars($courierMeta['name']) ?></span>
                            </div>
                            <div>
                                <span class="meta-label">Tracking ID</span>
                                <div class="tracking-id">
                                    <code><?= htmlspecialchars($courierMeta['id']) ?></code>
                                    <button type="button" class="btn-ghost btn-copy" data-copy="<?= htmlspecialchars($courierMeta['id']) ?>">Copy</button>
                                </div>
                            </div>
                        </div>
                        <div class="tracking-actions">
                            <a class="btn-secondary" href="https://www.ninjavan.co/en-sg" rel="https://www.ninjavan.co/en-sg">Open courier site</a>
                            <a class="btn-primary" href="mailto:support@techmart.local?subject=Delivery%20help%20for%20order%20%23<?= urlencode((string) $order['id']) ?>">Contact delivery support</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
