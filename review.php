<?php
$pageTitle = 'Leave a Review';
require_once __DIR__ . '/includes/functions.php';

start_session();

$errors = [];
$successMessage = null;
$formValues = [
    'order_id' => isset($_GET['order']) ? (int) $_GET['order'] : 0,
    'email' => isset($_GET['email']) && filter_var($_GET['email'], FILTER_VALIDATE_EMAIL) ? trim((string) $_GET['email']) : '',
    'name' => '',
    'rating' => '',
    'comments' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formValues['order_id'] = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
    $formValues['email'] = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL) ? trim((string) $_POST['email']) : '';
    $formValues['name'] = sanitize_string($_POST['name'] ?? '');
    $formValues['rating'] = isset($_POST['rating']) ? (int) $_POST['rating'] : 0;
    $formValues['comments'] = trim((string) ($_POST['comments'] ?? ''));

    if ($formValues['order_id'] <= 0) {
        $errors[] = 'Please provide the order number from your confirmation email.';
    }
    if ($formValues['email'] === '') {
        $errors[] = 'Enter the email address used at checkout so we can verify your order.';
    }
    if ($formValues['name'] === '') {
        $errors[] = 'Let us know who we can thank for the feedback.';
    }
    if ($formValues['rating'] < 1 || $formValues['rating'] > 5) {
        $errors[] = 'Choose a rating between 1 and 5 stars.';
    }
    if ($formValues['comments'] !== '' && strlen($formValues['comments']) > 2000) {
        $errors[] = 'Reviews are limited to 2000 characters.';
    }

    if (!$errors) {
        $pdo = get_db_connection();

        $orderStmt = $pdo->prepare('SELECT id, customer_email, customer_name FROM orders WHERE id = :id LIMIT 1');
        $orderStmt->execute(['id' => $formValues['order_id']]);
        $order = $orderStmt->fetch();

        if (!$order) {
            $errors[] = 'We could not find an order with that number. Please double-check and try again.';
        } elseif (strcasecmp($order['customer_email'], $formValues['email']) !== 0) {
            $errors[] = 'The email does not match the one on file for that order.';
        } else {
            $existing = $pdo->prepare('SELECT id FROM order_reviews WHERE order_id = :order_id AND reviewer_email = :email LIMIT 1');
            $existing->execute([
                'order_id' => $formValues['order_id'],
                'email' => strtolower($formValues['email']),
            ]);

            if ($existing->fetch()) {
                $errors[] = 'It looks like you have already shared a review for this order. Thank you!';
            }
        }

        if (!$errors) {
            $insert = $pdo->prepare('INSERT INTO order_reviews (order_id, reviewer_name, reviewer_email, rating, comments) VALUES (:order_id, :name, :email, :rating, :comments)');
            $insert->execute([
                'order_id' => $formValues['order_id'],
                'name' => $formValues['name'],
                'email' => strtolower($formValues['email']),
                'rating' => $formValues['rating'],
                'comments' => $formValues['comments'] !== '' ? $formValues['comments'] : null,
            ]);

            $successMessage = 'Thanks for leaving a review! Your insights help the community make confident choices.';
            $formValues['rating'] = '';
            $formValues['comments'] = '';
            if ($formValues['name'] === '' && isset($order['customer_name'])) {
                $formValues['name'] = $order['customer_name'];
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<section class="container">
    <h1 class="section-title">Share your TechMart experience</h1>
    <p class="section-subtitle">Tell us how checkout went and how your gear is performing so far.</p>

    <?php if ($successMessage): ?>
        <div class="notice success" role="status" style="margin-top: 2rem;">
            <?= htmlspecialchars($successMessage) ?>
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="notice" role="alert" style="margin-top: 2rem;">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" class="form-grid" style="margin-top: 3rem; max-width: 640px;">
        <div>
            <label for="orderId">Order number</label>
            <input type="number" id="orderId" name="order_id" required value="<?= $formValues['order_id'] ? htmlspecialchars((string) $formValues['order_id']) : '' ?>">
        </div>
        <div>
            <label for="reviewEmail">Email used at checkout</label>
            <input type="email" id="reviewEmail" name="email" required value="<?= htmlspecialchars($formValues['email']) ?>">
        </div>
        <div>
            <label for="reviewName">Name</label>
            <input type="text" id="reviewName" name="name" required value="<?= htmlspecialchars($formValues['name']) ?>">
        </div>
        <div>
            <label for="reviewRating">Rating</label>
            <select id="reviewRating" name="rating" required>
                <option value="">Select</option>
                <?php for ($i = 5; $i >= 1; $i--): ?>
                    <option value="<?= $i ?>" <?= ((string) $formValues['rating'] === (string) $i) ? 'selected' : '' ?>><?= $i ?> star<?= $i > 1 ? 's' : '' ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div style="grid-column: 1 / -1;">
            <label for="reviewComments">Share more details (optional)</label>
            <textarea id="reviewComments" name="comments" rows="6" maxlength="2000" placeholder="Tell us about delivery, setup, and anything we could do better."><?= htmlspecialchars($formValues['comments']) ?></textarea>
        </div>
        <button type="submit" class="btn-primary" style="grid-column: 1 / -1;">Submit review</button>
    </form>

    <p style="margin-top: 2rem; max-width: 640px;">We review every submission to keep TechMart a trusted space for honest, constructive feedback. Reviews are linked to
        verified orders to protect our community.</p>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
