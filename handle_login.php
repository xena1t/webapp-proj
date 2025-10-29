<?php
require_once __DIR__ . '/includes/functions.php'; // must set $pdo and start_session()
require_once __DIR__ .'/includes/header.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

// sanitize
$email = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    $_SESSION['flash_error'] = 'Please fill in both fields.';
    header('Location: login.php');
    exit;
}

try {
    $pdo = get_db_connection();
    // check database connection
    if (!isset($pdo)) {
        throw new RuntimeException('Database connection not initialized.');
    }

    $stmt = $pdo->prepare('SELECT id, username, email, password FROM users WHERE email = ? LIMIT 1');
    $stmt->execute(params: [$email]);
    $user = $stmt->fetch();


    if (!$user) {
        $_SESSION['flash_error'] = 'Account not found.';
        header('Location: login.php');
        exit;
    }
    // echo "<script>console.log('DEBUG PW (LOCAL ONLY): ' + " . json_encode($password) . ");</script>";
    if ($password !== $user['password']) {
        $_SESSION['flash_error'] = 'Incorrect password.';
        header('Location: login.php');
        exit;
    }
    // success — store session
    $_SESSION['user'] = [
        'id'       => $user['id'],
        'username' => $user['username'],
        'email'    => $user['email']
    ];

    header('Location: index.php');
    exit;

} catch (PDOException $e) {
    // database-related failure
    error_log('[PDOException] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    $_SESSION['flash_error'] = 'A database error occurred. Please try again later.';
    header('Location: login.php');
    exit;

} catch (Throwable $e) {
    // Log and show full stack info
    error_log('[Throwable] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    $_SESSION['flash_error'] =
        'Error: ' . htmlspecialchars($e->getMessage()) .
        ' in ' . basename($e->getFile()) .
        ' line ' . $e->getLine();
    header('Location: login.php');
    exit;
}
// } catch (Throwable $e) {
//     // generic failure
//     error_log('[Throwable] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
//     $_SESSION['flash_error'] = 'An unexpected error occurred. Please try again later.';
//     header('Location: login.php');
//     exit;
// }
