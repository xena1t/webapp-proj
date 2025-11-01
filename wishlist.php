<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Wishlist';
require_once __DIR__ . '/includes/header.php';

$userId = get_authenticated_user_id();
$wishlistItems = $userId ? fetch_wishlist_items($userId) : [];
?>
<section class="container wishlist-section">
    <header class="wishlist-header">
        <span class="badge">Saved items</span>
        <h1 class="section-title">Your wishlist</h1>
        <p class="section-subtitle">Keep track of products you love and move them to your cart when you&apos;re ready to upgrade.</p>
    </header>

    <?php if (!$userId): ?>
        <div class="notice" role="alert">
            <p>Please <a href="login.php">log in</a> or <a href="register.php">create an account</a> to start a wishlist.</p>
        </div>
    <?php elseif (empty($wishlistItems)): ?>
        <div class="empty-state">
            <h2>No favorites yet</h2>
            <p>Browse the <a href="products.php">catalog</a> and tap the heart icon on any product to add it to your wishlist.</p>
        </div>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($wishlistItems as $product): ?>
                <?php $imageUrl = asset_url((string) $product['image_url']); ?>
                <article class="product-card" data-product-card>
                    <div class="product-media">
                        <img src="<?= htmlspecialchars($imageUrl) ?>" alt="<?= htmlspecialchars($product['name']) ?>" loading="lazy">
                        <button class="wishlist-toggle active" type="button" title="Remove from wishlist"
                            data-wishlist-toggle
                            data-product-id="<?= (int) $product['id'] ?>"
                            aria-pressed="true">
                            <span class="sr-only">Toggle wishlist for <?= htmlspecialchars($product['name']) ?></span>
                        </button>
                    </div>
                    <div class="product-info">
                        <span class="product-category"><?= htmlspecialchars($product['category']) ?></span>
                        <h3><?= htmlspecialchars($product['name']) ?></h3>
                        <p><?= htmlspecialchars($product['tagline'] ?? $product['description']) ?></p>
                        <div class="product-meta">
                            <span class="product-price"><?= format_price((float) $product['price']) ?></span>
                            <a class="btn-tertiary" href="product.php?id=<?= $product['id'] ?>">View details</a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
