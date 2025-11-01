<?php
$pageTitle = 'Products';
require_once __DIR__ . '/includes/header.php';
$selectedCategory = isset($_GET['category']) ? trim((string) $_GET['category']) : null;
if ($selectedCategory === '') {
    $selectedCategory = null;
}
$searchQuery = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
if ($searchQuery === '') {
    $searchQuery = null;
}
$availableCategories = fetch_categories();
if ($selectedCategory && !in_array($selectedCategory, $availableCategories, true)) {
    $selectedCategory = null;
}
$limit = null;
$catalogError = null;
try {
    $products = fetch_products_by_category($selectedCategory, $limit, true, false, $searchQuery);
} catch (Throwable $exception) {
    error_log('Unable to load catalog: ' . $exception->getMessage());
    $catalogError = 'We couldn\'t load products from the catalog right now. Please try again shortly.';
    $products = [];
}
$wishlistProductIds = get_wishlist_product_ids();
$currentListUrl = $_SERVER['REQUEST_URI'] ?? '/products.php';
if (!str_starts_with($currentListUrl, '/')) {
    $currentListUrl = '/' . ltrim($currentListUrl, '/');
}
?>
<section class="container">
    <header style="margin-bottom: 2.5rem;">
        <span class="badge">Catalog</span>
        <h1 class="section-title">Discover precision-crafted technology</h1>
        <p class="section-subtitle">Filter by category to find devices engineered for productivity, play, or creative workflows.</p>
        <div class="filter-bar">
            <form class="filter-form" method="get">
                <input type="hidden" name="q" value="<?= htmlspecialchars($searchQuery ?? '') ?>">
                <label for="categoryFilter">Category</label>
                <select id="categoryFilter" name="category" onchange="this.form.submit()">
                    <option value="">All categories</option>
                    <?php foreach ($availableCategories as $category): ?>
                        <option value="<?= htmlspecialchars($category) ?>" <?= $selectedCategory === $category ? 'selected' : '' ?>><?= htmlspecialchars($category) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <?php if ($searchQuery): ?>
            <p class="search-summary">Showing results for “<?= htmlspecialchars($searchQuery) ?>”.</p>
        <?php endif; ?>
    </header>
    <?php if ($catalogError): ?>
        <div class="notice" role="alert"><?= htmlspecialchars($catalogError) ?></div>
    <?php elseif (empty($products)): ?>
        <p>No products were found. Please adjust your filters.</p>
    <?php else: ?>
        <div class="cards-grid">
            <?php foreach ($products as $product): ?>
                <?php $imageUrl = asset_url((string) $product['image_url']); ?>
                <?php $productId = (int) $product['id']; ?>
                <?php $isInWishlist = in_array($productId, $wishlistProductIds, true); ?>
                <article class="card">
                    <div class="card-content">
                        <h3><?= htmlspecialchars($product['name']) ?></h3>
                        <p><?= htmlspecialchars($product['tagline'] ?? $product['description']) ?></p>
                        <div class="product-price"><?= format_price((float) $product['price']) ?></div>
                        <div class="card-actions">
                            <a class="btn-secondary" href="product.php?id=<?= $product['id'] ?>">View details</a>
                            <form class="wishlist-form" method="post" action="wishlist.php">
                                <input type="hidden" name="product_id" value="<?= $productId ?>">
                                <input type="hidden" name="return_to" value="<?= htmlspecialchars($currentListUrl) ?>">
                                <?php if ($isInWishlist): ?>
                                    <input type="hidden" name="action" value="remove">
                                    <button type="submit" class="btn-ghost active" aria-pressed="true">Remove from wishlist</button>
                                <?php else: ?>
                                    <input type="hidden" name="action" value="add">
                                    <button type="submit" class="btn-ghost" aria-pressed="false">Add to wishlist</button>
                                <?php endif; ?>
                            </form>
                        </div>
                        <div class="product-image">
                            <img src="<?= htmlspecialchars($imageUrl) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
