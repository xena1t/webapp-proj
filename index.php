<?php
$pageTitle = 'Home';
require_once __DIR__ . '/includes/header.php';
$featuredProducts = fetch_featured_products();
$categories = fetch_categories();

$userId = get_authenticated_user_id();
$wishlistIds = $userId ? fetch_wishlist_product_ids($userId) : [];
$primaryFeature = $featuredProducts[0] ?? null;
$primaryImage = $primaryFeature ? asset_url((string) $primaryFeature['image_url']) : 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=900&q=80';
?>

<section class="home-hero">
    <div class="home-hero-copy">
        <span class="badge">This week&apos;s drop</span>
        <h1>Build a studio-grade battlestation.</h1>
        <p>We scout the latest performance laptops, AI-ready GPUs, and color-accurate displays so you can create, compete, and ship faster.</p>
        <div class="hero-actions">
            <a class="btn-primary" href="products.php">Shop the collection</a>
            <a class="btn-secondary" href="#featured">Discover featured gear</a>
        </div>
        <dl class="hero-metrics">
            <div>
                <dt>48hr</dt>
                <dd>express delivery in major cities</dd>
            </div>
            <div>
                <dt>25k+</dt>
                <dd>creators and pros trust TechMart</dd>
            </div>
            <div>
                <dt>4.9<span aria-hidden="true">★</span></dt>
                <dd>average rating across 3k reviews</dd>
            </div>
        </dl>
    </div>
    <div class="home-hero-visual">
        <div class="hero-card">
            <img src="<?= htmlspecialchars($primaryImage) ?>" alt="Featured setup inspiration" loading="lazy">
            <?php if ($primaryFeature): ?>
                <div class="hero-card-meta">
                    <span><?= htmlspecialchars($primaryFeature['category']) ?></span>
                    <strong><?= htmlspecialchars($primaryFeature['name']) ?></strong>
                    <span><?= format_price((float) $primaryFeature['price']) ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="container" id="featured" data-carousel-container style="margin-top: 4rem;">
    <header class="section-heading">
        <div>
            <h2 class="section-title">Featured drops</h2>
            <p class="section-subtitle">Handpicked devices engineered for creative pros and competitive gamers.</p>
        </div>
        <div class="section-controls">
            <button class="carousel-btn" type="button" data-carousel-prev aria-label="Scroll previous">&#8592;</button>
            <button class="carousel-btn" type="button" data-carousel-next aria-label="Scroll next">&#8594;</button>
        </div>
    </header>
    <div class="featured-carousel" data-carousel role="region" aria-label="Featured products carousel">
        <div class="featured-track" data-carousel-track role="list" tabindex="0">
            <?php foreach ($featuredProducts as $product): ?>
                <?php $imageUrl = asset_url((string) $product['image_url']); ?>
                <?php $inWishlist = $userId ? in_array((int) $product['id'], $wishlistIds, true) : false; ?>
                <article class="product-card" data-product-card role="listitem">
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
    </div>
</section>

<section class="container" style="margin-top: 5rem;">
    <h2 class="section-title">Shop by category</h2>
    <div class="cards-grid">
        <?php foreach ($categories as $category): ?>
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
    <div class="value-grid">
        <article class="value-card">
            <h3>Concierge support</h3>
            <p>Chat with hardware experts who understand workloads from 4K editing to triple-A gaming. Get personalized recommendations in under 12 hours.</p>
        </article>
        <article class="value-card">
            <h3>Fast, insured delivery</h3>
            <p>Every order is insured, tracked, and delivered within 48 hours in major cities—complete with zero-contact options.</p>
        </article>
        <article class="value-card">
            <h3>Sustainable upgrades</h3>
            <p>Recycle your previous-gen tech and save up to 15% on next-gen devices with our Circular Tech Trade-in program.</p>
        </article>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
