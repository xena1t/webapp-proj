<?php
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$emailInput = $_POST['email'] ?? '';
$email = filter_var($emailInput, FILTER_VALIDATE_EMAIL);
$normalizedEmail = $email ? normalize_email($email) : null;
$preference = sanitize_string($_POST['preference'] ?? '');
$budget = sanitize_string($_POST['budget'] ?? '');
$terms = isset($_POST['terms']);

if (!$normalizedEmail) {
    echo json_encode(['success' => false, 'message' => 'Please provide a valid email.']);
    exit;
}

if (!$preference || !$budget || !$terms) {
    echo json_encode(['success' => false, 'message' => 'Please complete all fields before subscribing.']);
    exit;
}

try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();

    $lookup = $pdo->prepare('SELECT id FROM newsletter_subscribers WHERE email = :email');
    $lookup->execute(['email' => $normalizedEmail]);
    $subscriber = $lookup->fetch();

    if ($subscriber) {
        $discount = find_active_discount_code_for_email($normalizedEmail, $pdo);
        $pdo->commit();

        if ($discount) {
            echo json_encode([
                'success' => true,
                'message' => 'You are already subscribed! Your welcome code is ' . $discount['code'] . '. Enjoy 10% off your next order.',
                'code' => $discount['code'],
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'message' => 'You are already subscribed and have used your welcome discount.',
            ]);
        }
        exit;
    }

    $insert = $pdo->prepare('INSERT INTO newsletter_subscribers (email, preference, budget_focus) VALUES (:email, :preference, :budget)');
    $insert->execute([
        'email' => $normalizedEmail,
        'preference' => $preference,
        'budget' => $budget,
    ]);

    $subscriberId = (int)$pdo->lastInsertId();
    $discount = issue_newsletter_discount_code($subscriberId, $normalizedEmail, 10.0, $pdo);
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Welcome aboard! Your 10% code is ' . $discount['code'] . '.',
        'code' => $discount['code'],
    ]);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to save your subscription right now.']);
}
