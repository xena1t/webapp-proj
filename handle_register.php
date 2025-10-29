<?php
require_once __DIR__ . '/includes/functions.php'; // must set $pdo and start_session()
require_once __DIR__ . '/includes/header.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit;
}

// sanitize input
$username = trim($_POST['username'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

if ($username === '' || $email === '' || $password === '' || $confirm_password === '') {
    $_SESSION['flash_error'] = 'Please fill in all fields.';
    header('Location: register.php');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['flash_error'] = 'Invalid email address.';
    header('Location: register.php');
    exit;
}

if ($password !== $confirm_password) {
    $_SESSION['flash_error'] = 'Passwords do not match.';
    header('Location: register.php');
    exit;
}

try {
    $pdo = get_db_connection();

    // check if email exists
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $_SESSION['flash_error'] = 'Email already registered.';
        header('Location: register.php');
        exit;
    }

    // insert plain password (unsafe)
    $stmt = $pdo->prepare('INSERT INTO users (username, email, password) VALUES (?, ?, ?)');
    $stmt->execute([$username, $email, $password]);

    // auto-login after registration
    $user_id = $pdo->lastInsertId();
    $_SESSION['user'] = [
        'id' => $user_id,
        'username' => $username,
        'email' => $email
    ];

    header('Location: index.php');
    exit;
} catch (Throwable $e) {
    error_log('Registration error: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'An error occurred. Please try again later.';
    header('Location: register.php');
    exit;
}
