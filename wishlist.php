<?php
require_once __DIR__ . '/includes/functions.php';

start_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? trim((string) $_POST['action']) : 'add';
    $productId = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
    $returnTo = isset($_POST['return_to']) ? trim((string) $_POST['return_to']) : '';
    $redirectTarget = 'wishlist.php';

    if ($returnTo !== '') {
        if (!str_starts_with($returnTo, '/')) {
            $returnTo = '/' . ltrim($returnTo, '/');
        }
        $redirectTarget = $returnTo;
    }

    if (!is_user_logged_in()) {
        $_SESSION['wishlist_feedback'] = [
            'type' => 'error',
            'message' => 'Please log in to manage your wishlist.',
        ];
    } elseif ($productId <= 0) {
        $_SESSION['wishlist_feedback'] = [
            'type' => 'error',
            'message' => 'Invalid product selection.',
        ];
    } else {
        try {
            if ($action === 'remove') {
                remove_from_wishlist($productId);
                $_SESSION['wishlist_feedback'] = [
                    'type' => 'success',
                    'message' => 'Removed from your wishlist.',
                ];
            } else {
                add_to_wishlist($productId);
                $_SESSION['wishlist_feedback'] = [
                    'type' => 'success',
                    'message' => 'Saved to your wishlist.',
                ];
            }
        } catch (Throwable $exception) {
            error_log('Wishlist update failed: ' . $exception->getMessage());
            $_SESSION['wishlist_feedback'] = [
                'type' => 'error',
                'message' => 'We could not update your wishlist. Please try again.',
            ];
        }
    }

    header('Location: ' . $redirectTarget);
    exit;
}

$pageTitle = 'Wishlist';
require_once __DIR__ . '/includes/header.php';

$feedback = $_SESSION['wishlist_feedback'] ?? null;
if ($feedback !== null) {
    unset($_SESSION['wishlist_feedback']);
}

$currentWishlistUrl = $_SERVER['REQUEST_URI'] ?? '/wishlist.php';
if (!str_starts_with($currentWishlistUrl, '/')) {
    $currentWishlistUrl = '/' . ltrim($currentWishlistUrl, '/');
}
?>
<section class="container wishlist-section">
    <header style="margin-bottom: 2.5rem;">
        <span class="badge">Wishlist</span>
        <h1 class="section-title">Your saved products</h1>
        <p class="section-subtitle">Revisit the devices you've bookmarked and move them to your cart when you're ready.</p>
    </header>

    <?php if ($feedback): ?>
        <div class="notice<?= $feedback['type'] === 'error' ? ' error' : '' ?>" role="alert">
            <?= htmlspecialchars($feedback['message']) ?>
        </div>
    <?php endif; ?>

    <?php if (!is_user_logged_in()): ?>
        <div class="notice" role="alert">
            Please <a href="login.php">log in</a> or <a href="register.php">create an account</a> to build your wishlist.
        </div>
    <?php else: ?>
        <?php $wishlistItems = fetch_wishlist_items(); ?>
        <?php if (empty($wishlistItems)): ?>
            <p class="text-muted">Your wishlist is empty. <a href="products.php">Explore our catalog</a> and start saving your favorites.</p>
        <?php else: ?>
            <div class="cards-grid wishlist-grid">
                <?php foreach ($wishlistItems as $item): ?>
                    <?php $imageUrl = asset_url((string) $item['image_url']); ?>
                    <article class="card">
                        <div class="card-content">
                            <span class="badge"><?= htmlspecialchars($item['category']) ?></span>
                            <h3><?= htmlspecialchars($item['name']) ?></h3>
                            <p><?= htmlspecialchars($item['tagline'] ?? $item['description']) ?></p>
                            <div class="product-price"><?= format_price((float) $item['price']) ?></div>
                            <div class="card-actions">
                                <a class="btn-secondary" href="product.php?id=<?= $item['id'] ?>">View details</a>
                                <form class="wishlist-form" method="post" action="wishlist.php">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>">
                                    <input type="hidden" name="return_to" value="<?= htmlspecialchars($currentWishlistUrl) ?>">
                                    <button type="submit" class="btn-ghost active" aria-pressed="true">Remove</button>
                                </form>
                            </div>
                            <div class="product-image">
                                <img src="<?= htmlspecialchars($imageUrl) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
