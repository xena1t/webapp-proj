<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';

$pageTitle = 'Checkout';

$cartItems = fetch_cart_items();
$totals = calculate_cart_totals($cartItems);
$orderErrors = [];
$orderSuccess = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_cart']) && isset($_POST['quantities']) && is_array($_POST['quantities'])) {
        foreach ($_POST['quantities'] as $productId => $quantity) {
            update_cart_item((int) $productId, (int) $quantity);
        }
        header('Location: checkout.php');
        exit;
    }

    if (isset($_POST['place_order'])) {
        if (empty($cartItems)) {
            $orderErrors[] = 'Your cart is empty. Add products before checking out.';
        } else {
            $name = sanitize_string($_POST['name'] ?? '');
            $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
            $address = sanitize_string($_POST['address'] ?? '');
            $payment = sanitize_string($_POST['payment_method'] ?? '');
            $promo = sanitize_string($_POST['promo'] ?? '');

            if (!$name) {
                $orderErrors[] = 'Please provide your full name.';
            }
            if (!$email) {
                $orderErrors[] = 'Please provide a valid email address.';
            }
            if (!$address) {
                $orderErrors[] = 'Please provide a shipping address.';
            }
            if (!$payment) {
                $orderErrors[] = 'Please choose a payment method.';
            }

            if (!$orderErrors) {
                $pdo = get_db_connection();

                try {
                    $pdo->beginTransaction();

                    $orderStmt = $pdo->prepare('INSERT INTO orders (customer_name, customer_email, shipping_address, total, status, promo_code) VALUES (:name, :email, :address, :total, :status, :promo)');
                    $orderStmt->execute([
                        'name' => $name,
                        'email' => $email,
                        'address' => $address,
                        'total' => $totals['total'],
                        'status' => 'Processing',
                        'promo' => $promo,
                    ]);

                    $orderId = (int) $pdo->lastInsertId();

                    $itemStmt = $pdo->prepare('INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (:order_id, :product_id, :quantity, :price)');
                    $stockStmt = $pdo->prepare('UPDATE products SET stock = stock - :quantity WHERE id = :product_id AND stock >= :quantity');

                    foreach ($cartItems as $item) {
                        if ($item['quantity'] > $item['stock']) {
                            throw new RuntimeException('Insufficient stock for ' . $item['name']);
                        }

                        $stockStmt->execute([
                            'quantity' => $item['quantity'],
                            'product_id' => $item['id'],
                        ]);

                        if ($stockStmt->rowCount() === 0) {
                            throw new RuntimeException('Unable to reserve stock for ' . $item['name'] . '. Please update your cart and try again.');
                        }

                        $itemStmt->execute([
                            'order_id' => $orderId,
                            'product_id' => $item['id'],
                            'quantity' => $item['quantity'],
                            'price' => $item['price'],
                        ]);
                    }

                    $pdo->commit();

                    $orderSuccess = [
                        'id' => $orderId,
                        'customer_name' => $name,
                        'customer_email' => $email,
                        'total' => $totals['total'],
                    ];

                    send_order_confirmation($orderSuccess, $cartItems);
                    clear_cart();

                    header('Location: order-status.php?order=' . $orderId . '&email=' . urlencode($email));
                    exit;
                } catch (Throwable $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $orderErrors[] = 'Unable to place order: ' . $exception->getMessage();
                }
            }
        }
    }

    $cartItems = fetch_cart_items();
    $totals = calculate_cart_totals($cartItems);
}

require_once __DIR__ . '/includes/header.php';
?>
<section class="container">
    <h1 class="section-title">Your cart</h1>
    <?php if ($orderErrors): ?>
        <div class="notice" style="margin-bottom: 1.5rem;">
            <strong>We spotted some issues:</strong>
            <ul>
                <?php foreach ($orderErrors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (empty($cartItems)): ?>
        <p>Your cart is empty. <a href="products.php">Continue shopping</a>.</p>
    <?php else: ?>
        <form method="post">
            <table class="cart-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cartItems as $item): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($item['name']) ?></strong>
                                <p class="text-muted"><?= htmlspecialchars($item['category']) ?></p>
                            </td>
                            <td><?= format_price((float) $item['price']) ?></td>
                            <td>
                                <input type="number" name="quantities[<?= $item['id'] ?>]" min="0" max="<?= (int) $item['stock'] ?>" value="<?= $item['quantity'] ?>">
                            </td>
                            <td><?= format_price((float) $item['line_total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div style="margin-top: 1rem; display:flex; gap:1rem;">
                <button type="submit" name="update_cart" value="1" class="btn-secondary">Update cart</button>
                <a class="btn-secondary" href="products.php">Continue shopping</a>
            </div>
        </form>

        <div class="cart-summary">
            <h2>Order summary</h2>
            <p>Subtotal: <?= format_price((float) $totals['subtotal']) ?></p>
            <p>Discounts: <?= format_price((float) $totals['discount']) ?></p>
            <p><strong>Total: <?= format_price((float) $totals['total']) ?></strong></p>
        </div>

        <section style="margin-top: 2rem;">
            <h2 class="section-title">Checkout</h2>
            <form method="post" class="form-grid" novalidate>
                <input type="hidden" name="place_order" value="1">
                <div>
                    <label for="name">Full name</label>
                    <input type="text" id="name" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                </div>
                <div>
                    <label for="email">Email address</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>
                <div>
                    <label for="address">Shipping address</label>
                    <textarea id="address" name="address" required><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                </div>
                <div>
                    <label for="payment_method">Payment method</label>
                    <select id="payment_method" name="payment_method" required>
                        <option value="">Select</option>
                        <option value="Card" <?= (($_POST['payment_method'] ?? '') === 'Card') ? 'selected' : '' ?>>Credit/Debit Card</option>
                        <option value="PayNow" <?= (($_POST['payment_method'] ?? '') === 'PayNow') ? 'selected' : '' ?>>PayNow</option>
                        <option value="Bank Transfer" <?= (($_POST['payment_method'] ?? '') === 'Bank Transfer') ? 'selected' : '' ?>>Bank Transfer</option>
                    </select>
                </div>
                <div>
                    <label for="promo">Promo code (optional)</label>
                    <input type="text" id="promo" name="promo" value="<?= htmlspecialchars($_POST['promo'] ?? '') ?>">
                </div>
                <button type="submit" class="btn-primary">Place order</button>
            </form>
        </section>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
