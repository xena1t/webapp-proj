<?php
$pageTitle = 'Customer Reviews';
require_once __DIR__ . '/includes/functions.php';

start_session();

$reviews = [];
$averageRating = null;
$ratingCounts = array_fill(1, 5, 0);

try {
    $pdo = get_db_connection();
    $stmt = $pdo->query(
        'SELECT r.id, r.order_id, r.reviewer_name, r.reviewer_email, r.rating, r.comments, r.created_at,
                o.customer_name, o.customer_email,
                GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR ", ") AS product_names
           FROM order_reviews r
           INNER JOIN orders o ON o.id = r.order_id
           LEFT JOIN order_items oi ON oi.order_id = r.order_id
           LEFT JOIN products p ON p.id = oi.product_id
       GROUP BY r.id
       ORDER BY r.created_at DESC'
    );
    $reviews = $stmt->fetchAll();

    if ($reviews) {
        $totalRating = 0;
        foreach ($reviews as $row) {
            $rating = (int) $row['rating'];
            $totalRating += $rating;
            if (isset($ratingCounts[$rating])) {
                $ratingCounts[$rating]++;
            }
        }
        $averageRating = $totalRating / count($reviews);
    }
} catch (Throwable $exception) {
    error_log('Failed to fetch reviews: ' . $exception->getMessage());
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
    <header class="section-heading">
        <h1 class="section-title">What the TechMart community is saying</h1>
        <p class="section-subtitle">Verified customers share how their gear is performing in the real world.</p>
    </header>

    <div class="reviews-summary">
        <div class="summary-card">
            <h2><?= $averageRating !== null ? number_format($averageRating, 1) : '–' ?><span aria-hidden="true">★</span></h2>
            <p class="summary-label">Average rating</p>
            <p class="summary-meta">Based on <?= count($reviews) ?> verified review<?= count($reviews) === 1 ? '' : 's' ?></p>
        </div>
        <div class="summary-card">
            <h3>Rating breakdown</h3>
            <dl class="rating-breakdown">
                <?php for ($i = 5; $i >= 1; $i--): ?>
                    <div class="breakdown-row">
                        <dt><?= $i ?> star<?= $i > 1 ? 's' : '' ?></dt>
                        <dd>
                            <div class="breakdown-bar" aria-hidden="true">
                                <?php
                                $count = $ratingCounts[$i] ?? 0;
                                $width = count($reviews) > 0 ? ($count / count($reviews)) * 100 : 0;
                                ?>
                                <span style="width: <?= number_format($width, 2) ?>%"></span>
                            </div>
                            <span class="sr-only"><?= $count ?> review<?= $count === 1 ? '' : 's' ?> rated <?= $i ?></span>
                            <span aria-hidden="true" class="breakdown-count"><?= $count ?></span>
                        </dd>
                    </div>
                <?php endfor; ?>
            </dl>
        </div>
    </div>

    <div class="table-responsive" style="margin-top: 2rem;">
        <?php if (!$reviews): ?>
            <div class="empty-state">
                <p>No reviews have been published yet. Be the first to <a href="review.php">share your experience</a>.</p>
            </div>
        <?php else: ?>
            <table aria-describedby="reviewTableCaption">
                <caption id="reviewTableCaption" class="sr-only">Customer reviews with product details and ratings</caption>
                <thead>
                    <tr>
                        <th scope="col">Reviewer</th>
                        <th scope="col">Products</th>
                        <th scope="col">Rating</th>
                        <th scope="col">Review</th>
                        <th scope="col">Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reviews as $review): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($review['reviewer_name']) ?></strong>
                                <div class="reviewer-email"><?= htmlspecialchars($review['reviewer_email']) ?></div>
                                <div class="review-order">Order #<?= (int) $review['order_id'] ?></div>
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
                                <?php
                                $submittedAt = new DateTime($review['created_at']);
                                ?>
                                <time datetime="<?= $submittedAt->format(DATE_ATOM) ?>">
                                    <?= $submittedAt->format('M j, Y g:i A') ?>
                                </time>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
