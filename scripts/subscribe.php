<?php
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
$preference = sanitize_string($_POST['preference'] ?? '');
$budget = sanitize_string($_POST['budget'] ?? '');
$terms = isset($_POST['terms']);

if (!$email) {
    echo json_encode(['success' => false, 'message' => 'Please provide a valid email.']);
    exit;
}

if (!$preference || !$budget || !$terms) {
    echo json_encode(['success' => false, 'message' => 'Please complete all fields before subscribing.']);
    exit;
}

try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM newsletter_subscribers WHERE email = :email');
    $stmt->execute(['email' => $email]);
    if ((int) $stmt->fetchColumn() > 0) {
        echo json_encode(['success' => true, 'message' => 'You are already subscribed! Use code WELCOME10 for 10% off.']);
        exit;
    }

    $insert = $pdo->prepare('INSERT INTO newsletter_subscribers (email, preference, budget_focus) VALUES (:email, :preference, :budget)');
    $insert->execute([
        'email' => $email,
        'preference' => $preference,
        'budget' => $budget,
    ]);

    echo json_encode(['success' => true, 'message' => 'Welcome aboard! Your 10% code is WELCOME10.']);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to save your subscription right now.']);
}
