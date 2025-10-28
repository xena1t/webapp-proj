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
    } else {
        add_to_cart($product['id'], $quantity);
        $feedbackMessage = 'Added to your cart! <a href="checkout.php">Review cart</a>';
    }
}

$specs = [];
if (!empty($product['spec_json'])) {
    $decoded = json_decode($product['spec_json'], true);
    if (is_array($decoded)) {
        $specs = $decoded;
    }
}
?>
<section class="container product-detail">
    <div>
        <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
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