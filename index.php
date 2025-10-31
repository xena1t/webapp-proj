<?php
$pageTitle = 'Home';
require_once __DIR__ . '/includes/header.php';

$featuredProducts = fetch_featured_products();
$shopCategories = $categories ?? fetch_categories();
?>

<section class="hero">
  <div class="hero-content">
    <span class="badge">New arrivals</span>
    <h1>Elevate your setup with cutting-edge tech.</h1>
    <p>Discover curated laptops, custom PC components, and immersive peripherals—handpicked by enthusiasts for performance seekers.</p>
    <div class="hero-actions">
      <a class="btn-primary" href="products.php">Shop the collection</a>
      <a class="btn-secondary" href="#insights">Explore categories</a>
    </div>
    <div class="highlight">
      <div class="highlight-icon">★</div>
      <p>Trusted by over 25,000 creators worldwide for reliable gear, tailored support, and lightning-fast delivery.</p>
    </div>
  </div>
</section>

<section class="container" id="insights" style="margin-top: 4rem;">
    <h2 class="section-title">Featured drops</h2>
    <p class="section-subtitle">Handpicked devices engineered for creative pros and competitive gamers.</p>
    <div class="cards-grid">
        <?php foreach ($featuredProducts as $product): ?>
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
</section>

<section class="container" style="margin-top: 5rem;">
    <h2 class="section-title">Shop by category</h2>
    <div class="cards-grid">
        <?php foreach ($shopCategories as $category): ?>
            <article class="card">
                <div class="card-content">
                    <h3><?= htmlspecialchars($category) ?></h3>
                    <p>Explore best-in-class <?= strtolower(htmlspecialchars($category)) ?> curated for performance, reliability, and design.</p>
                    <a class="btn-secondary" href="products.php?category=<?= urlencode($category) ?>">Browse <?= htmlspecialchars($category) ?></a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="container" style="margin-top: 5rem;">
    <div class="cards-grid">
        <article class="card">
            <div class="card-content">
                <h3>Concierge support</h3>
                <p>Chat with hardware experts who understand workloads from 4K editing to triple-A gaming. Get personalized recommendations in under 12 hours.</p>
            </div>
        </article>
        <article class="card">
            <div class="card-content">
                <h3>Fast, insured delivery</h3>
                <p>Every order is insured, tracked, and delivered within 48 hours in major cities—complete with zero-contact options.</p>
            </div>
        </article>
        <article class="card">
            <div class="card-content">
                <h3>Sustainable upgrades</h3>
                <p>Recycle your previous-gen tech and save up to 15% on next-gen devices with our Circular Tech Trade-in program.</p>
            </div>
        </article>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
