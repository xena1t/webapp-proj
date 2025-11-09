<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Moderate Reviews';
start_session();

if (!is_user_logged_in()) {
    $_SESSION['flash_error'] = 'Please log in to access the admin panel.';
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

$errors = [];
$successMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $reviewId = filter_var($_POST['review_id'] ?? '', FILTER_VALIDATE_INT);
        if (!$reviewId) {
            $errors[] = 'A valid review ID is required.';
        } else {
            try {
                $pdo = get_db_connection();
                $stmt = $pdo->prepare('DELETE FROM order_reviews WHERE id = :id');
                $stmt->execute(['id' => $reviewId]);
                if ($stmt->rowCount() === 0) {
                    $errors[] = 'Review not found or already removed.';
                } else {
                    $successMessage = 'The review was removed successfully.';
                }
            } catch (Throwable $exception) {
                $errors[] = 'Failed to remove review: ' . $exception->getMessage();
            }
        }
    }
}

$reviews = [];
try {
    if (!isset($pdo)) {
        $pdo = get_db_connection();
    }
    $stmt = $pdo->query(
        'SELECT r.id, r.order_id, r.reviewer_name, r.reviewer_email, r.rating, r.comments, r.created_at,
                o.customer_order_number,
                GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR ", ") AS product_names
           FROM order_reviews r
           INNER JOIN orders o ON o.id = r.order_id
           LEFT JOIN order_items oi ON oi.order_id = r.order_id
           LEFT JOIN products p ON p.id = oi.product_id
       GROUP BY r.id
       ORDER BY r.created_at DESC'
    );
    $reviews = $stmt->fetchAll();
} catch (Throwable $exception) {
    $errors[] = 'Unable to load reviews: ' . $exception->getMessage();
}

$ratingImages = [
    1 => 'assets/images/ratings/1-star.svg',
    2 => 'assets/images/ratings/2-stars.svg',
    3 => 'assets/images/ratings/3-stars.svg',
    4 => 'assets/images/ratings/4-stars.svg',
    5 => 'assets/images/ratings/5-stars.svg',
];

require_once __DIR__ . '/includes/header.php';
?>
<section class="container">
    <h1 class="section-title">Moderate customer feedback</h1>
    <p class="section-subtitle">Remove reviews that violate community guidelines or were posted in error.</p>

    <?php if ($successMessage): ?>
        <div class="notice success" role="status" style="margin-top: 1.5rem;">
            <?= htmlspecialchars($successMessage) ?>
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="notice" role="alert" style="margin-top: 1.5rem;">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="table-responsive" style="margin-top: 2rem;">
        <?php if (!$reviews): ?>
            <div class="empty-state">
                <p>No reviews have been submitted yet.</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th scope="col">Reviewer</th>
                        <th scope="col">Products</th>
                        <th scope="col">Rating</th>
                        <th scope="col">Review</th>
                        <th scope="col">Submitted</th>
                        <th scope="col" class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reviews as $review): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($review['reviewer_name']) ?></strong>
                                <div class="reviewer-email"><?= htmlspecialchars($review['reviewer_email']) ?></div>
                                <?php
                                $orderNumber = isset($review['customer_order_number']) && (int) $review['customer_order_number'] > 0
                                    ? (int) $review['customer_order_number']
                                    : (int) $review['order_id'];
                                ?>
                                <div class="review-order">Order #<?= htmlspecialchars((string) $orderNumber) ?></div>
                            </td>
                            <td>
                                <?php if ($review['product_names']): ?>
                                    <?= htmlspecialchars($review['product_names']) ?>
                                <?php else: ?>
                                    <span class="text-muted">Products unavailable</span>
                                <?php endif; ?>
                            </td>
                            <td class="rating-cell">
                                <?php
                                $rating = (int) $review['rating'];
                                $imagePath = $ratingImages[$rating] ?? null;
                                if ($imagePath): ?>
                                    <img src="<?= htmlspecialchars(asset_url($imagePath)) ?>" alt="<?= $rating ?> out of 5 stars" class="rating-image">
                                <?php else: ?>
                                    <?= $rating ?> / 5
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($review['comments']): ?>
                                    <p><?= nl2br(htmlspecialchars($review['comments'])) ?></p>
                                <?php else: ?>
                                    <span class="text-muted">No written feedback provided.</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php $submittedAt = new DateTime($review['created_at']); ?>
                                <time datetime="<?= $submittedAt->format(DATE_ATOM) ?>">
                                    <?= $submittedAt->format('M j, Y g:i A') ?>
                                </time>
                            </td>
                            <td class="text-right">
                                <form method="post" onsubmit="return confirm('Remove this review? This cannot be undone.');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="review_id" value="<?= (int) $review['id'] ?>">
                                    <button type="submit" class="btn-secondary btn-sm">Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
