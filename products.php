<?php
$pageTitle = 'Products';
require_once __DIR__ . '/includes/header.php';
$selectedCategory = isset($_GET['category']) ? trim((string) $_GET['category']) : null;
if ($selectedCategory === '') {
    $selectedCategory = null;
}
$searchTerm = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
if ($searchTerm === '') {
    $searchTerm = null;
}
$availableCategories = fetch_categories();
if ($selectedCategory && !in_array($selectedCategory, $availableCategories, true)) {
    $selectedCategory = null;
}
$limit = null;
$catalogError = null;
try {
    $products = fetch_products_by_category($selectedCategory, $limit, true, false, $searchTerm);
} catch (Throwable $exception) {
    error_log('Unable to load catalog: ' . $exception->getMessage());
    $catalogError = 'We couldn\'t load products from the catalog right now. Please try again shortly.';
    $products = [];
}
$userId = get_authenticated_user_id();
$wishlistIds = $userId ? fetch_wishlist_product_ids($userId) : [];
?>
<section class="container">
    <header style="margin-bottom: 2.5rem;">
        <span class="badge">Catalog</span>
        <h1 class="section-title">Discover precision-crafted technology</h1>
        <p class="section-subtitle">Filter by category or search to find devices engineered for productivity, play, or creative workflows.</p>
        <?php if ($searchTerm): ?>
            <p class="search-feedback">Showing results for <strong><?= htmlspecialchars($searchTerm) ?></strong> (<?= count($products) ?> match<?= count($products) === 1 ? '' : 'es' ?>).</p>
        <?php endif; ?>
        <div class="filter-bar">
            <form class="filter-form" method="get">
                <label for="categoryFilter">Category</label>
                <select id="categoryFilter" name="category" onchange="this.form.submit()">
                    <option value="">All categories</option>
                    <?php foreach ($availableCategories as $category): ?>
                        <option value="<?= htmlspecialchars($category) ?>" <?= $selectedCategory === $category ? 'selected' : '' ?>><?= htmlspecialchars($category) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($searchTerm): ?>
                    <input type="hidden" name="q" value="<?= htmlspecialchars($searchTerm) ?>">
                <?php endif; ?>
            </form>
        </div>
    </header>
    <?php if ($catalogError): ?>
        <div class="notice" role="alert"><?= htmlspecialchars($catalogError) ?></div>
    <?php elseif (empty($products)): ?>
        <div class="empty-state">
            <h2>No products found</h2>
            <p>Try adjusting your category filters<?= $searchTerm ? ' or updating your search query' : '' ?>.</p>
        </div>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($products as $product): ?>
                <?php $imageUrl = asset_url((string) $product['image_url']); ?>
                <?php $inWishlist = $userId ? in_array((int) $product['id'], $wishlistIds, true) : false; ?>
                <article class="product-card" data-product-card>
                    <div class="product-media">
                        <img src="<?= htmlspecialchars($imageUrl) ?>" alt="<?= htmlspecialchars($product['name']) ?>" loading="lazy">
                        <button class="wishlist-toggle" type="button" title="Save to wishlist"
                            data-wishlist-toggle
                            data-product-id="<?= (int) $product['id'] ?>"
                            aria-pressed="<?= $inWishlist ? 'true' : 'false' ?>">
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
