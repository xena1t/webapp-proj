<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Sales Dashboard';
start_session();

if (!is_user_logged_in()) {
    $_SESSION['flash_error'] = 'Please log in to access the admin reports.';
    header('Location: login.php');
    exit;
}

if (!is_user_admin()) {
    http_response_code(403);
    require_once __DIR__ . '/includes/header.php'; ?>
    <section class="container">
        <h1 class="section-title">Access denied</h1>
        <p>You do not have permission to view this page.</p>
    </section>
<?php require_once __DIR__ . '/includes/footer.php';
    exit;
}

$summary = [
    'total_orders' => 0,
    'total_revenue' => 0.0,
    'average_order_value' => 0.0,
    'total_items_sold' => 0,
];
$productSales = [];
$monthlyRevenue = [];
$recentOrders = [];
$reportError = null;

try {
    $pdo = get_db_connection();

    $summaryStmt = $pdo->query('SELECT COUNT(*) AS total_orders, COALESCE(SUM(total), 0) AS total_revenue FROM orders');
    $summaryRow = $summaryStmt->fetch();
    if ($summaryRow) {
        $summary['total_orders'] = (int) $summaryRow['total_orders'];
        $summary['total_revenue'] = (float) $summaryRow['total_revenue'];
        $summary['average_order_value'] = $summary['total_orders'] > 0
            ? $summary['total_revenue'] / $summary['total_orders']
            : 0.0;
    }

    $itemsStmt = $pdo->query('SELECT COALESCE(SUM(quantity), 0) AS total_items_sold FROM order_items');
    $itemsRow = $itemsStmt->fetch();
    if ($itemsRow) {
        $summary['total_items_sold'] = (int) $itemsRow['total_items_sold'];
    }

    $productStmt = $pdo->query(
        'SELECT p.id, p.name, p.category,
                COALESCE(SUM(oi.quantity), 0) AS quantity_sold,
                COALESCE(SUM(oi.quantity * oi.unit_price), 0) AS revenue_generated,
                MAX(o.created_at) AS last_sold_at
           FROM order_items oi
           INNER JOIN products p ON p.id = oi.product_id
           INNER JOIN orders o ON o.id = oi.order_id
          GROUP BY p.id, p.name, p.category
          ORDER BY quantity_sold DESC, revenue_generated DESC'
    );
    $productSales = $productStmt->fetchAll();

    $monthlyStmt = $pdo->query(
        "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key,
                DATE_FORMAT(created_at, '%M %Y') AS month_label,
                COUNT(*) AS orders_count,
                COALESCE(SUM(total), 0) AS revenue
           FROM orders
          GROUP BY month_key, month_label
          ORDER BY month_key DESC
          LIMIT 12"
    );
    $monthlyRevenue = $monthlyStmt->fetchAll();

    $ordersStmt = $pdo->query(
        'SELECT o.id, o.customer_order_number, o.customer_name, o.customer_email, o.status, o.total, o.created_at,
                COALESCE(SUM(oi.quantity), 0) AS item_count
           FROM orders o
           LEFT JOIN order_items oi ON oi.order_id = o.id
          GROUP BY o.id, o.customer_order_number, o.customer_name, o.customer_email, o.status, o.total, o.created_at
          ORDER BY o.created_at DESC
          LIMIT 20'
    );
    $recentOrders = $ordersStmt->fetchAll();
} catch (Throwable $exception) {
    $reportError = 'Unable to load sales data right now. Please try again later.';
}

require_once __DIR__ . '/includes/header.php';
?>
<section class="container">
    <h1 class="section-title">Sales dashboard</h1>
    <p class="text-muted">Monitor revenue, order trends, and product performance across TechMart.</p>

    <?php if ($reportError): ?>
        <div class="notice" role="alert" style="margin-bottom: 1.5rem;">
            <?= htmlspecialchars($reportError) ?>
        </div>
    <?php endif; ?>

    <div class="tab-container" data-tab-container>
        <div class="tab-list" role="tablist">
            <button type="button" class="tab-button active" role="tab" aria-selected="true" aria-controls="tab-overview" data-tab="overview">Overview</button>
            <button type="button" class="tab-button" role="tab" aria-selected="false" aria-controls="tab-products" data-tab="products">By product</button>
            <button type="button" class="tab-button" role="tab" aria-selected="false" aria-controls="tab-months" data-tab="months">By month</button>
            <button type="button" class="tab-button" role="tab" aria-selected="false" aria-controls="tab-orders" data-tab="orders">Recent orders</button>
        </div>

        <section id="tab-overview" class="tab-panel active" role="tabpanel" data-tab-panel="overview">
            <div class="metrics-grid">
                <article class="metric-card">
                    <h3>Total revenue</h3>
                    <p class="metric-value"><?= format_price($summary['total_revenue']) ?></p>
                    <p class="metric-caption">Across <?= $summary['total_orders'] ?> orders</p>
                </article>
                <article class="metric-card">
                    <h3>Average order value</h3>
                    <p class="metric-value"><?= format_price($summary['average_order_value']) ?></p>
                    <p class="metric-caption">Mean revenue per completed order</p>
                </article>
                <article class="metric-card">
                    <h3>Items sold</h3>
                    <p class="metric-value"><?= number_format($summary['total_items_sold']) ?></p>
                    <p class="metric-caption">Total units sold across all products</p>
                </article>
            </div>

            <div class="card" style="margin-top: 2rem;">
                <h2>Top-performing products</h2>
                <?php if (empty($productSales)): ?>
                    <p class="text-muted">No sales have been recorded yet.</p>
                <?php else: ?>
                    <ul class="top-products">
                        <?php foreach (array_slice($productSales, 0, 5) as $product): ?>
                            <li>
                                <span>
                                    <strong><?= htmlspecialchars($product['name']) ?></strong>
                                    <em><?= htmlspecialchars($product['category']) ?></em>
                                </span>
                                <span><?= number_format($product['quantity_sold']) ?> sold · <?= format_price((float) $product['revenue_generated']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>

        <section id="tab-products" class="tab-panel" role="tabpanel" aria-hidden="true" data-tab-panel="products">
            <h2>Sales by product</h2>
            <?php if (empty($productSales)): ?>
                <p class="text-muted">No product sales data is available yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Units sold</th>
                                <th>Revenue</th>
                                <th>Last sold</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productSales as $product): ?>
                                <tr>
                                    <td><?= htmlspecialchars($product['name']) ?></td>
                                    <td><?= htmlspecialchars($product['category']) ?></td>
                                    <td><?= number_format($product['quantity_sold']) ?></td>
                                    <td><?= format_price((float) $product['revenue_generated']) ?></td>
                                    <td>
                                        <?php if ($product['last_sold_at']): ?>
                                            <?= date('M j, Y', strtotime($product['last_sold_at'])) ?>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section id="tab-months" class="tab-panel" role="tabpanel" aria-hidden="true" data-tab-panel="months">
            <h2>Revenue by month</h2>
            <?php if (empty($monthlyRevenue)): ?>
                <p class="text-muted">Monthly revenue will appear after your first orders are placed.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Orders</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($monthlyRevenue as $month): ?>
                                <tr>
                                    <td><?= htmlspecialchars($month['month_label']) ?></td>
                                    <td><?= number_format($month['orders_count']) ?></td>
                                    <td><?= format_price((float) $month['revenue']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section id="tab-orders" class="tab-panel" role="tabpanel" aria-hidden="true" data-tab-panel="orders">
            <h2>Most recent orders</h2>
            <?php if (empty($recentOrders)): ?>
                <p class="text-muted">Orders will appear here as soon as customers check out.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Customer</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Placed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $order): ?>
                                <tr>
                                    <?php
                                    $displayOrderNumber = isset($order['customer_order_number']) && (int) $order['customer_order_number'] > 0
                                        ? (int) $order['customer_order_number']
                                        : (int) $order['id'];
                                    ?>
                                    <td>#<?= htmlspecialchars((string) $displayOrderNumber) ?> <span class="text-muted">(ID <?= (int) $order['id'] ?>)</span></td>
                                    <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                    <td><?= htmlspecialchars($order['customer_email']) ?></td>
                                    <td><?= htmlspecialchars($order['status']) ?></td>
                                    <td><?= number_format($order['item_count']) ?></td>
                                    <td><?= format_price((float) $order['total']) ?></td>
                                    <td><?= date('M j, Y g:i A', strtotime($order['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php';
