<?php
$pageTitle = 'Products';
require_once __DIR__ . '/includes/header.php';
$selectedCategory = isset($_GET['category']) ? trim((string) $_GET['category']) : null;
if ($selectedCategory === '') {
    $selectedCategory = null;
}
$availableCategories = fetch_categories();
if ($selectedCategory && !in_array($selectedCategory, $availableCategories, true)) {
    $selectedCategory = null;
}
$products = fetch_products_by_category($selectedCategory);
?>
<section class="container">
    <header style="margin-bottom: 2.5rem;">
        <span class="badge">Catalog</span>
        <h1 class="section-title">Discover precision-crafted technology</h1>
        <p class="section-subtitle">Filter by category to find devices engineered for productivity, play, or creative workflows.</p>
        <div class="filter-bar">
            <form class="filter-form" method="get">
                <label for="categoryFilter">Category</label>
                <select id="categoryFilter" name="category" onchange="this.form.submit()">
                    <option value="">All categories</option>
                    <?php foreach ($availableCategories as $category): ?>
                        <option value="<?= htmlspecialchars($category) ?>" <?= $selectedCategory === $category ? 'selected' : '' ?>><?= htmlspecialchars($category) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </header>
    <?php if (empty($products)): ?>
        <p>No products were found. Please adjust your filters.</p>
    <?php else: ?>
        <div class="cards-grid">
            <?php foreach ($products as $product): ?>
                <article class="card">
                    <div class="card-content">
                        <h3><?= htmlspecialchars($product['name']) ?></h3>
                        <p><?= htmlspecialchars($product['tagline'] ?? $product['description']) ?></p>
                        <div class="product-price"><?= format_price((float) $product['price']) ?></div>
                        <a class="btn-secondary" href="product.php?id=<?= $product['id'] ?>">View details</a>
                        <div class="product-image">
                            <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
