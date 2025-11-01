<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';

$pageTitle = 'Checkout';

start_session();
if (!is_user_logged_in()) {
    $_SESSION['flash_error'] = 'Please log in to view your cart.';
    header('Location: login.php');
    exit;
}

$userId = get_authenticated_user_id();
$authenticatedUser = get_authenticated_user();
$checkoutEmail = isset($authenticatedUser['email']) ? trim((string) $authenticatedUser['email']) : '';

$appliedDiscount = null;
$discountMessage = null;
$discountError = null;
if (isset($_SESSION['checkout_discount']) && is_array($_SESSION['checkout_discount'])) {
    $potentialDiscount = $_SESSION['checkout_discount'];
    if (isset($potentialDiscount['code'], $potentialDiscount['email'])) {
        $validation = validate_discount_code($potentialDiscount['code'], $potentialDiscount['email'], $userId);
        if ($validation['valid']) {
            $appliedDiscount = $validation['discount'];
            $_SESSION['checkout_discount'] = $appliedDiscount;
        } else {
            $discountError = $validation['message'];
            unset($_SESSION['checkout_discount']);
        }
    }
}

$cartItems = fetch_cart_items();
$orderErrors = [];
$orderSuccess = null;

$formData = [
    'name' => sanitize_string($_POST['name'] ?? ''),
    'address' => sanitize_string($_POST['address'] ?? ''),
    'payment_method' => sanitize_string($_POST['payment_method'] ?? ''),
    'promo' => sanitize_string($_POST['promo'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_cart']) && isset($_POST['quantities']) && is_array($_POST['quantities'])) {
        foreach ($_POST['quantities'] as $productId => $quantity) {
            update_cart_item((int) $productId, (int) $quantity);
        }
        header('Location: checkout.php');
        exit;
    }

    if (isset($_POST['apply_discount'])) {
        $promo = $formData['promo'];
        $emailForDiscount = filter_var($checkoutEmail, FILTER_VALIDATE_EMAIL) ? $checkoutEmail : null;

        if ($promo === '') {
            $discountError = 'Enter your discount code to apply it.';
        } elseif (!$emailForDiscount) {
            $discountError = 'We could not verify the email on your account. Please sign out and back in before applying the discount.';
        } else {
            $validation = validate_discount_code($promo, $emailForDiscount, $userId);
            if ($validation['valid']) {
                $appliedDiscount = $validation['discount'];
                $_SESSION['checkout_discount'] = $appliedDiscount;
                $discountMessage = 'Discount code applied successfully.';
            } else {
                $discountError = $validation['message'];
                unset($_SESSION['checkout_discount']);
                $appliedDiscount = null;
            }
        }
    } elseif (isset($_POST['remove_discount'])) {
        unset($_SESSION['checkout_discount']);
        $appliedDiscount = null;
        $discountMessage = 'Discount removed.';
    }

    if (isset($_POST['place_order']) && !isset($_POST['apply_discount']) && !isset($_POST['remove_discount'])) {
        if (empty($cartItems)) {
            $orderErrors[] = 'Your cart is empty. Add products before checking out.';
        } else {
            $name = $formData['name'];
            $email = filter_var($checkoutEmail, FILTER_VALIDATE_EMAIL) ? $checkoutEmail : false;
            $address = $formData['address'];
            $payment = $formData['payment_method'];
            $promo = $formData['promo'];
            $discountForOrder = null;

            if ($name === '') {
                $orderErrors[] = 'Please provide your full name.';
            } elseif (!preg_match("/^[\\p{L}\\s'\-]{2,}$/u", $name)) {
                $orderErrors[] = 'Your name should be at least two characters and contain only letters, spaces, hyphens, or apostrophes.';
            }
            if (!$email) {
                $orderErrors[] = 'We could not determine the email tied to your account. Please sign in again.';
            }
            if ($address === '') {
                $orderErrors[] = 'Please provide a shipping address.';
            } elseif (mb_strlen($address) < 10) {
                $orderErrors[] = 'Your shipping address should be at least 10 characters long so we can deliver accurately.';
            }
            if (!$payment) {
                $orderErrors[] = 'Please choose a payment method.';
            }

            if (!$orderErrors) {
                if ($promo !== '') {
                    if (!$email) {
                        $orderErrors[] = 'Enter your email before applying a discount code.';
                    } else {
                        $promoValidation = validate_discount_code($promo, $email, $userId);
                        if ($promoValidation['valid']) {
                            $discountForOrder = $promoValidation['discount'];
                            $_SESSION['checkout_discount'] = $discountForOrder;
                            $appliedDiscount = $discountForOrder;
                        } else {
                            $orderErrors[] = $promoValidation['message'];
                        }
                    }
                } elseif ($appliedDiscount) {
                    $promoValidation = validate_discount_code($appliedDiscount['code'], $email ?: $appliedDiscount['email'], $userId);
                    if ($promoValidation['valid']) {
                        $discountForOrder = $promoValidation['discount'];
                        $_SESSION['checkout_discount'] = $discountForOrder;
                        $appliedDiscount = $discountForOrder;
                    } else {
                        $orderErrors[] = $promoValidation['message'];
                        unset($_SESSION['checkout_discount']);
                        $appliedDiscount = null;
                    }
                }
            }

            if (!$orderErrors) {
                $totalsForOrder = calculate_cart_totals($cartItems, $discountForOrder ?? $appliedDiscount);
                $pdo = get_db_connection();

                try {
                    $pdo->beginTransaction();

                    $orderStmt = $pdo->prepare('INSERT INTO orders (user_id, customer_name, customer_email, shipping_address, total, discount_amount, status, promo_code) VALUES (:user_id, :name, :email, :address, :total, :discount, :status, :promo)');
                    $orderStmt->execute([
                        'user_id' => $userId,
                        'name' => $name,
                        'email' => $email,
                        'address' => $address,
                        'total' => $totalsForOrder['total'],
                        'discount' => $totalsForOrder['discount'],
                        'status' => 'Processing',
                        'promo' => $discountForOrder['code'] ?? ($appliedDiscount['code'] ?? null),
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

                    if ($discountForOrder) {
                        mark_discount_code_redeemed((int) $discountForOrder['id'], $orderId, $userId, $pdo);
                    }

                    $pdo->commit();

                    $orderSuccess = [
                        'id' => $orderId,
                        'customer_name' => $name,
                        'customer_email' => $email,
                        'total' => $totalsForOrder['total'],
                        'discount_amount' => $totalsForOrder['discount'],
                        'promo_code' => $discountForOrder['code'] ?? ($appliedDiscount['code'] ?? null),
                    ];

                    send_order_confirmation($orderSuccess, $cartItems);
                    clear_cart();
                    unset($_SESSION['checkout_discount']);

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
}

$totals = calculate_cart_totals($cartItems, $appliedDiscount);

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

        <?php if ($discountMessage): ?>
            <div class="notice" style="margin-top: 1rem;"><?= htmlspecialchars($discountMessage) ?></div>
        <?php endif; ?>
        <?php if ($discountError): ?>
            <div class="notice error" style="margin-top: 1rem;"><?= htmlspecialchars($discountError) ?></div>
        <?php endif; ?>

        <section style="margin-top: 2rem;">
            <h2 class="section-title">Checkout</h2>
            <form method="post" class="form-grid" novalidate>
                <input type="hidden" name="place_order" value="1">
                <div>
                    <label for="name">Full name</label>
                    <input type="text" id="name" name="name" value="<?= htmlspecialchars($formData['name']) ?>" required minlength="2" pattern="^[A-Za-zÀ-ÖØ-öø-ÿ'\-\s]{2,}$" title="Use at least two letters. Hyphens, apostrophes, and spaces are allowed.">
                </div>
                <div>
                    <label for="email">Email address</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($checkoutEmail) ?>" required readonly>
                </div>
                <div>
                    <label for="address">Shipping address</label>
                    <textarea id="address" name="address" required minlength="10" title="Provide the full street address for delivery."><?= htmlspecialchars($formData['address']) ?></textarea>
                </div>
                <div>
                    <label for="payment_method">Payment method</label>
                    <select id="payment_method" name="payment_method" required>
                        <option value="">Select</option>
                        <option value="Card" <?= ($formData['payment_method'] === 'Card') ? 'selected' : '' ?>>Credit/Debit Card</option>
                        <option value="PayNow" <?= ($formData['payment_method'] === 'PayNow') ? 'selected' : '' ?>>PayNow</option>
                        <option value="Bank Transfer" <?= ($formData['payment_method'] === 'Bank Transfer') ? 'selected' : '' ?>>Bank Transfer</option>
                    </select>
                </div>
                <div>
                    <label for="promo">Discount code</label>
                    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
                        <input type="text" id="promo" name="promo" value="<?= htmlspecialchars($appliedDiscount['code'] ?? $formData['promo']) ?>" style="flex:1 1 220px;">
                        <?php if ($appliedDiscount): ?>
                            <button type="submit" class="btn-secondary" name="remove_discount" value="1">Remove</button>
                        <?php else: ?>
                            <button type="submit" class="btn-secondary" name="apply_discount" value="1">Apply</button>
                        <?php endif; ?>
                    </div>
                    <p class="text-muted" style="margin-top: 0.5rem;">Use the one-time code from your welcome newsletter email.</p>
                    <?php if ($appliedDiscount): ?>
                        <p class="text-muted">Applied: <?= htmlspecialchars($appliedDiscount['code']) ?> (<?= number_format((float) $appliedDiscount['percent']) ?>% off)</p>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn-primary">Place order</button>
            </form>
        </section>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
