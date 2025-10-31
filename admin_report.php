<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Sales Report';
start_session();

if (!is_user_logged_in()) {
    $_SESSION['flash_error'] = 'Please log in to access sales reports.';
    header('Location: login.php');
    exit;
}

if (!is_user_admin()) {
    http_response_code(403);
    require_once __DIR__ . '/includes/header.php'; ?>
    <section class="container">
        <h1 class="section-title">Access denied</h1>
        <p>You do not have permission to view this report.</p>
    </section>
<?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$filters = [
    'start_date' => trim($_GET['start_date'] ?? ''),
    'end_date' => trim($_GET['end_date'] ?? ''),
    'category' => trim($_GET['category'] ?? ''),
    'status' => trim($_GET['status'] ?? ''),
    'promo_code' => trim($_GET['promo_code'] ?? ''),
    'has_discount' => trim($_GET['has_discount'] ?? ''),
    'min_total' => trim($_GET['min_total'] ?? ''),
];

$errors = [];
$reportRows = [];
$summary = [
    'orders_count' => 0,
    'revenue' => 0.0,
    'discounts' => 0.0,
    'items_sold' => 0,
];
$reportError = null;
$download = isset($_GET['download']) && $_GET['download'] === '1';

$validatedStart = null;
$validatedEnd = null;
if ($filters['start_date'] !== '') {
    $validatedStart = DateTime::createFromFormat('Y-m-d', $filters['start_date']);
    if (!$validatedStart) {
        $errors[] = 'Start date must be in YYYY-MM-DD format.';
    }
}
if ($filters['end_date'] !== '') {
    $validatedEnd = DateTime::createFromFormat('Y-m-d', $filters['end_date']);
    if (!$validatedEnd) {
        $errors[] = 'End date must be in YYYY-MM-DD format.';
    }
}

$categoryOptions = fetch_categories(false);

try {
    $pdo = get_db_connection();
    $whereClauses = [];
    $params = [];

    if ($validatedStart) {
        $whereClauses[] = 'o.created_at >= :start_date';
        $params['start_date'] = $validatedStart->format('Y-m-d') . ' 00:00:00';
    }
    if ($validatedEnd) {
        $whereClauses[] = 'o.created_at <= :end_date';
        $params['end_date'] = $validatedEnd->format('Y-m-d') . ' 23:59:59';
    }
    if ($filters['status'] !== '') {
        $whereClauses[] = 'o.status = :status';
        $params['status'] = $filters['status'];
    }
    if ($filters['promo_code'] !== '') {
        $whereClauses[] = 'o.promo_code = :promo_code';
        $params['promo_code'] = $filters['promo_code'];
    }
    if ($filters['min_total'] !== '') {
        if (is_numeric($filters['min_total'])) {
            $whereClauses[] = 'o.total >= :min_total';
            $params['min_total'] = (float) $filters['min_total'];
        } else {
            $errors[] = 'Minimum total must be a number.';
        }
    }
    if ($filters['has_discount'] === 'with') {
        $whereClauses[] = 'o.discount_amount > 0';
    } elseif ($filters['has_discount'] === 'without') {
        $whereClauses[] = 'o.discount_amount = 0';
    }
    if ($filters['category'] !== '') {
        $whereClauses[] = 'EXISTS (SELECT 1 FROM order_items oi2 INNER JOIN products p2 ON p2.id = oi2.product_id WHERE oi2.order_id = o.id AND p2.category = :category)';
        $params['category'] = $filters['category'];
    }

    $whereSql = $whereClauses ? ' WHERE ' . implode(' AND ', $whereClauses) : '';

    // Summary
    if (!$errors) {
        $summaryStmt = $pdo->prepare('SELECT COUNT(*) AS orders_count, COALESCE(SUM(o.total),0) AS revenue, COALESCE(SUM(o.discount_amount),0) AS discounts FROM orders o' . $whereSql);
        $summaryStmt->execute($params);
        $summaryRow = $summaryStmt->fetch();
        if ($summaryRow) {
            $summary['orders_count'] = (int) $summaryRow['orders_count'];
            $summary['revenue'] = (float) $summaryRow['revenue'];
            $summary['discounts'] = (float) $summaryRow['discounts'];
        }

        $itemsStmt = $pdo->prepare('SELECT COALESCE(SUM(oi.quantity),0) AS items_sold FROM orders o LEFT JOIN order_items oi ON oi.order_id = o.id' . $whereSql);
        $itemsStmt->execute($params);
        $itemsRow = $itemsStmt->fetch();
        if ($itemsRow) {
            $summary['items_sold'] = (int) $itemsRow['items_sold'];
        }

        $detailSql = 'SELECT o.id, o.customer_name, o.customer_email, o.status, o.total, o.discount_amount, o.promo_code, o.created_at, COALESCE(SUM(oi.quantity),0) AS items_sold
            FROM orders o
            LEFT JOIN order_items oi ON oi.order_id = o.id'
            . $whereSql .
            ' GROUP BY o.id, o.customer_name, o.customer_email, o.status, o.total, o.discount_amount, o.promo_code, o.created_at
              ORDER BY o.created_at DESC';

        if (!$download) {
            $detailSql .= ' LIMIT 250';
        }

        $detailStmt = $pdo->prepare($detailSql);
        $detailStmt->execute($params);
        $reportRows = $detailStmt->fetchAll();

        if ($download) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="sales-report-' . date('Ymd-His') . '.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Order ID', 'Customer', 'Email', 'Status', 'Items', 'Total', 'Discount', 'Promo Code', 'Placed']);
            foreach ($reportRows as $row) {
                fputcsv($output, [
                    $row['id'],
                    $row['customer_name'],
                    $row['customer_email'],
                    $row['status'],
                    $row['items_sold'],
                    number_format((float) $row['total'], 2, '.', ''),
                    number_format((float) $row['discount_amount'], 2, '.', ''),
                    $row['promo_code'],
                    $row['created_at'],
                ]);
            }
            fclose($output);
            exit;
        }
    }

    $statusStmt = $pdo->query('SELECT DISTINCT status FROM orders ORDER BY status');
    $statusOptions = $statusStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $exception) {
    $reportError = 'Unable to load sales data right now. Please try again later.';
    $statusOptions = [];
}

require_once __DIR__ . '/includes/header.php';
?>
<section class="container">
    <h1 class="section-title">Sales report</h1>
    <p class="text-muted">Filter by date, category, and discount usage, or export the data for deeper analysis.</p>

    <?php if ($errors): ?>
        <div class="notice error" style="margin-bottom: 1.5rem;">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="get" class="form-grid" style="margin-bottom: 2rem;">
        <div>
            <label for="start_date">Start date</label>
            <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($filters['start_date']) ?>">
        </div>
        <div>
            <label for="end_date">End date</label>
            <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($filters['end_date']) ?>">
        </div>
        <div>
            <label for="category">Category</label>
            <select id="category" name="category">
                <option value="">All categories</option>
                <?php foreach ($categoryOptions as $category): ?>
                    <option value="<?= htmlspecialchars($category) ?>" <?= $filters['category'] === $category ? 'selected' : '' ?>><?= htmlspecialchars($category) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="status">Order status</label>
            <select id="status" name="status">
                <option value="">All statuses</option>
                <?php foreach ($statusOptions as $status): ?>
                    <option value="<?= htmlspecialchars($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="promo_code">Promo code</label>
            <input type="text" id="promo_code" name="promo_code" value="<?= htmlspecialchars($filters['promo_code']) ?>">
        </div>
        <div>
            <label for="min_total">Minimum total ($)</label>
            <input type="number" step="0.01" id="min_total" name="min_total" value="<?= htmlspecialchars($filters['min_total']) ?>">
        </div>
        <div>
            <label for="has_discount">Discount usage</label>
            <select id="has_discount" name="has_discount">
                <option value="" <?= $filters['has_discount'] === '' ? 'selected' : '' ?>>All orders</option>
                <option value="with" <?= $filters['has_discount'] === 'with' ? 'selected' : '' ?>>With discount</option>
                <option value="without" <?= $filters['has_discount'] === 'without' ? 'selected' : '' ?>>Without discount</option>
            </select>
        </div>
        <div class="form-actions" style="align-self: flex-end; display: flex; gap: 1rem;">
            <button type="submit" class="btn-primary">Apply filters</button>
            <a class="btn-secondary" href="admin_report.php">Reset</a>
            <button type="submit" class="btn-secondary" name="download" value="1">Download CSV</button>
        </div>
    </form>

    <div class="metrics-grid" style="margin-bottom: 2rem;">
        <article class="metric-card">
            <h3>Total revenue</h3>
            <p class="metric-value"><?= format_price($summary['revenue']) ?></p>
            <p class="metric-caption">Across <?= number_format($summary['orders_count']) ?> orders</p>
        </article>
        <article class="metric-card">
            <h3>Discounts granted</h3>
            <p class="metric-value">−<?= format_price($summary['discounts']) ?></p>
            <p class="metric-caption">Newsletter incentives redeemed</p>
        </article>
        <article class="metric-card">
            <h3>Items sold</h3>
            <p class="metric-value"><?= number_format($summary['items_sold']) ?></p>
            <p class="metric-caption">Total units shipped in selected range</p>
        </article>
    </div>

    <?php if ($reportError): ?>
        <div class="notice error"><?= htmlspecialchars($reportError) ?></div>
    <?php elseif (empty($reportRows)): ?>
        <p class="text-muted">No orders matched your filters.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="cart-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Status</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Discount</th>
                        <th>Promo code</th>
                        <th>Placed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportRows as $row): ?>
                        <tr>
                            <td>#<?= htmlspecialchars((string) $row['id']) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($row['customer_name']) ?></strong><br>
                                <span class="text-muted"><?= htmlspecialchars($row['customer_email']) ?></span>
                            </td>
                            <td><?= htmlspecialchars($row['status']) ?></td>
                            <td><?= number_format((int) $row['items_sold']) ?></td>
                            <td><?= format_price((float) $row['total']) ?></td>
                            <td><?= $row['discount_amount'] > 0 ? '−' . format_price((float) $row['discount_amount']) : '—' ?></td>
                            <td><?= $row['promo_code'] ? htmlspecialchars($row['promo_code']) : '—' ?></td>
                            <td><?= date('M j, Y g:i A', strtotime($row['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/includes/footer.php';
