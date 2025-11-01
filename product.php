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
$stockCount = (int) $product['stock'];
$stockStatusClass = 'product-stock';
if ($stockCount <= 0) {
    $stockStatusClass .= ' is-out';
} elseif ($stockCount < 5) {
    $stockStatusClass .= ' is-low';
}
?>
<?php $productImage = asset_url((string) $product['image_url']); ?>
<section class="container product-detail">
    <div class="product-media">
        <div class="product-media__frame">
            <img src="<?= htmlspecialchars($productImage) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
        </div>
    </div>
    <div class="product-summary">
        <span class="badge"><?= htmlspecialchars($product['category']) ?></span>
        <h1><?= htmlspecialchars($product['name']) ?></h1>
        <p class="product-description"><?= nl2br(htmlspecialchars($product['description'])) ?></p>
        <div class="product-price"><?= format_price((float) $product['price']) ?></div>
        <p class="<?= $stockStatusClass ?>">
            <?php if ($stockCount <= 0): ?>
                <span class="text-danger"><strong>Sold Out</strong></span>
            <?php elseif ($stockCount < 5): ?>
                Only <?= $stockCount ?> unit<?= $stockCount === 1 ? '' : 's' ?> left
            <?php else: ?>
                Stock remaining: <?= $stockCount ?>
            <?php endif; ?>
        </p>

        <?php if ($feedbackMessage): ?>
            <div class="<?= $feedbackClass ?>" style="margin: 1rem 0;"><?= $feedbackMessage ?></div>
        <?php endif; ?>

        <div class="product-actions">
            <?php if ($stockCount > 0): ?>
                <form method="post" class="form-grid" action="product.php?id=<?= $product['id'] ?>">
                    <label for="quantity">Quantity</label>
                    <input type="number" name="quantity" id="quantity" min="1" max="<?= $stockCount ?>" value="1" required>
                    <button type="submit" class="btn-primary">Add to cart</button>
                </form>
            <?php else: ?>
                <form class="form-grid" aria-disabled="true">
                    <label for="sold-out-quantity">Quantity</label>
                    <input type="number" id="sold-out-quantity" value="0" disabled>
                    <button type="button" class="btn-secondary" disabled>Sold Out</button>
                </form>
            <?php endif; ?>

            <?php if (is_user_logged_in()): ?>
                <br />
                <form method="post" class="form-grid" action="wishlist.php">
                    <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                    <input type="hidden" name="return_to" value="<?= htmlspecialchars($currentDetailUrl) ?>">

                    <?php if ($isProductInWishlist): ?>
                        <input type="hidden" name="action" value="remove">
                        <button type="submit" class="btn-secondary">
                            ❤️ Remove from wishlist
                        </button>
                    <?php else: ?>
                        <input type="hidden" name="action" value="add">
                        <button type="submit" class="btn-primary">
                            ♡ Add to wishlist
                        </button>
                    <?php endif; ?>

                </form>
            <?php else: ?>
                <form class="form-grid" aria-disabled="true">
                    <button type="button" class="btn-secondary" disabled>
                        Log in to add to wishlist
                    </button>
                </form>
            <?php endif; ?>

        </div>

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