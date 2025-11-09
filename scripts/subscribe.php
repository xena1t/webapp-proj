<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$authenticatedUser = get_authenticated_user();
if ($authenticatedUser === null) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please sign in to subscribe to the newsletter.']);
    exit;
}

$emailFromAccount = $authenticatedUser['email'] ?? '';
$validatedEmail = filter_var($emailFromAccount, FILTER_VALIDATE_EMAIL);
$normalizedEmail = $validatedEmail ? normalize_email($validatedEmail) : null;
$preference = sanitize_string($_POST['preference'] ?? '');
$budget = sanitize_string($_POST['budget'] ?? '');
$terms = isset($_POST['terms']);

if (!$normalizedEmail) {
    echo json_encode(['success' => false, 'message' => 'Your account email appears to be invalid.']);
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
            $emailSent = send_newsletter_discount_email($normalizedEmail, $discount['code'], true);
            if (!$emailSent) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'We found your welcome code but could not email it right now. Use ' . $discount['code'] . ' at checkout and try again later for a fresh copy in your inbox.',
                    'code' => $discount['code'],
                ]);
                exit;
            }

            echo json_encode([
                'success' => true,
                'message' => 'You are already subscribed! We just resent your welcome code (' . $discount['code'] . ') to your inbox.',
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

    $emailSent = send_newsletter_discount_email($normalizedEmail, $discount['code']);
    if (!$emailSent) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'We created your welcome code but could not send the email right now. Your code is ' . $discount['code'] . '. Please try again shortly so we can deliver it to your inbox.',
            'code' => $discount['code'],
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Welcome aboard! Your 10% code is ' . $discount['code'] . '. We emailed it to you as well.',
        'code' => $discount['code'],
    ]);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to save your subscription right now.']);
}
