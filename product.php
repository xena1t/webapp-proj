<?php
require_once __DIR__ . '/includes/functions.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$product = fetch_product($id);

if (!$product) {
    $pageTitle = 'Product Not Found';
    require_once __DIR__ . '/includes/header.php';
    http_response_code(404);
    echo '<div class="container"><h1>Product not found</h1><p>The item you are looking for may have been retired.</p></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$pageTitle = $product['name'] . ' Details';
require_once __DIR__ . '/includes/header.php';

$feedbackMessage = null;
$feedbackClass = 'notice';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quantity = isset($_POST['quantity']) ? (int) $_POST['quantity'] : 1;
    if ($quantity < 1) {
        $feedbackMessage = 'Please choose a valid quantity.';
        $feedbackClass = 'notice error';
    } elseif ($quantity > (int) $product['stock']) {
        $feedbackMessage = 'We only have ' . (int) $product['stock'] . ' units available right now.';
        $feedbackClass = 'notice error';
    } elseif (!is_user_logged_in()) {
        $feedbackMessage = 'Please log in to add items to your cart.';
        $feedbackClass = 'notice error';
    } else {
        try {
            add_to_cart($product['id'], $quantity);
            $feedbackMessage = 'Added to your cart! <a href="checkout.php">Review cart</a>';
        } catch (Throwable $exception) {
            $feedbackMessage = 'Unable to add this product to your cart. Please try again.';
            $feedbackClass = 'notice error';
        }
    }
}

$specs = [];
if (!empty($product['spec_json'])) {
    $decoded = json_decode($product['spec_json'], true);
    if (is_array($decoded)) {
        $specs = $decoded;
    }
}
$wishlistProductIds = get_wishlist_product_ids();
$isProductInWishlist = in_array((int) $product['id'], $wishlistProductIds, true);
$currentDetailUrl = $_SERVER['REQUEST_URI'] ?? '/product.php?id=' . $product['id'];
if (!str_starts_with($currentDetailUrl, '/')) {
    $currentDetailUrl = '/' . ltrim($currentDetailUrl, '/');
}
?>
<?php $productImage = asset_url((string) $product['image_url']); ?>
<section class="container product-detail">
    <div>
        <img src="<?= htmlspecialchars($productImage) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
    </div>
    <div class="product-summary">
        <span class="badge"><?= htmlspecialchars($product['category']) ?></span>
        <h1><?= htmlspecialchars($product['name']) ?></h1>
        <p><?= nl2br(htmlspecialchars($product['description'])) ?></p>
        <div class="product-price"><?= format_price((float) $product['price']) ?></div>
        <p>
            <?php if ((int)$product['stock'] > 0): ?>
                Stock remaining: <?= (int)$product['stock'] ?>
            <?php else: ?>
                <span class="text-danger"><strong>Sold Out</strong></span>
            <?php endif; ?>
        </p>

        <?php if ($feedbackMessage): ?>
            <div class="<?= $feedbackClass ?>" style="margin: 1rem 0;"><?= $feedbackMessage ?></div>
        <?php endif; ?>

        <?php if ((int)$product['stock'] > 0): ?>
            <form method="post" class="form-grid" action="product.php?id=<?= $product['id'] ?>">
                <label for="quantity">Quantity</label>
                <input type="number"
                    name="quantity"
                    id="quantity"
                    min="1"
                    max="<?= (int)$product['stock'] ?>"
                    value="1"
                    required>
                <button type="submit" class="btn-primary">Add to cart</button>
            </form>
        <?php else: ?>
            <form class="form-grid">
                <input type="number" value="0" disabled>
                <button type="button" class="btn-secondary" disabled>Sold Out</button>
            </form>
        <?php endif; ?>

        <?php if (is_user_logged_in()): ?>
            <form class="wishlist-inline" method="post" action="wishlist.php">
                <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                <input type="hidden" name="return_to" value="<?= htmlspecialchars($currentDetailUrl) ?>">
                <?php if ($isProductInWishlist): ?>
                    <input type="hidden" name="action" value="remove">
                    <button type="submit" class="btn-ghost wishlist-button active" aria-pressed="true">
                        <span class="heart-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <path d="M12 21s-6.716-4.73-9.33-9.138C-0.443 7.454 1.63 3 5.545 3 7.74 3 9.56 4.45 12 7.09 14.44 4.45 16.26 3 18.455 3c3.915 0 5.988 4.454 2.875 8.862C18.716 16.27 12 21 12 21z" />
                            </svg>
                        </span>
                        <span class="wishlist-button__label">Remove from wishlist</span>
                    </button>
                <?php else: ?>
                    <input type="hidden" name="action" value="add">
                    <button type="submit" class="btn-ghost wishlist-button" aria-pressed="false">
                        <span class="heart-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <path d="M12 21s-6.716-4.73-9.33-9.138C-0.443 7.454 1.63 3 5.545 3 7.74 3 9.56 4.45 12 7.09 14.44 4.45 16.26 3 18.455 3c3.915 0 5.988 4.454 2.875 8.862C18.716 16.27 12 21 12 21z" />
                            </svg>
                        </span>
                        <span class="wishlist-button__label">Add to wishlist</span>
                    </button>
                <?php endif; ?>
            </form>
        <?php else: ?>
            <p class="wishlist-hint"><a href="login.php">Log in</a> to save this item to your wishlist.</p>
        <?php endif; ?>

    </div>
</section>

<section class="container" style="margin-top: 3rem;">
    <h2 class="section-title">Technical specifications</h2>
    <?php if (!empty($specs)): ?>
        <div class="table-responsive">
            <table>
                <tbody>
                    <?php foreach ($specs as $label => $value): ?>
                        <tr>
                            <th><?= htmlspecialchars($label) ?></th>
                            <td><?= htmlspecialchars($value) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p>Detailed specifications will be announced shortly.</p>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
