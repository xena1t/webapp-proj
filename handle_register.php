<?php
require_once __DIR__ . '/includes/functions.php'; // must call session_start() and define get_db_connection()
require_once __DIR__ . '/includes/header.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit;
}

// sanitize
$username = trim($_POST['username'] ?? '');
$email    = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';
$confirm  = $_POST['confirm_password'] ?? '';

$errors = [];

// validate fields
if ($username === '' || $email === '' || $password === '' || $confirm === '') {
    $errors[] = 'All fields are required.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email address.';
}
if (!preg_match('/^[A-Za-z0-9._-]{3,32}$/', $username)) {
    $errors[] = 'Username must be 3–32 characters and contain only letters, numbers, ., _, or -.';
}
if ($password !== $confirm) {
    $errors[] = 'Passwords do not match.';
}
if (strlen($password) < 6) {
    $errors[] = 'Password must be at least 6 characters.';
}

try {
    $pdo = get_db_connection();
    if (!$pdo instanceof PDO) {
        throw new RuntimeException('Database connection not initialized.');
    }

    // check username
    $stmt = $pdo->prepare('SELECT 1 FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    if ($stmt->fetchColumn()) {
        $errors[] = 'Username already taken.';
    }

    // check email
    $stmt = $pdo->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetchColumn()) {
        $errors[] = 'Email already registered.';
    }

    // if any errors, redirect back with them
    if (!empty($errors)) {
        $_SESSION['flash_errors'] = $errors;
        header('Location: register.php');
        exit;
    }

    // store password (plain text now, hash later)
    $passwordToStore = $password;

    $stmt = $pdo->prepare('INSERT INTO users (username, email, password) VALUES (?, ?, ?)');
    $stmt->execute([$username, $email, $passwordToStore]);

    // auto-login
    $user_id = $pdo->lastInsertId();
    $_SESSION['user'] = [
        'id'       => $user_id,
        'username' => $username,
        'email'    => $email,
        'is_admin' => 0
    ];

    header('Location: index.php');
    exit;

} catch (Throwable $e) {
    error_log('[Registration Error] ' . $e->getMessage());
    $_SESSION['flash_errors'] = ['A system error occurred. Please try again later.'];
    header('Location: register.php');
    exit;
}
