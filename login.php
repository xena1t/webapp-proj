<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Member Login';
$errors = [];

if (is_member_logged_in()) {
    header('Location: index.php#catalog');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (!$email) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($password === '') {
        $errors[] = 'Please enter your password.';
    }

    if (!$errors) {
        $member = authenticate_member($email, $password);
        if ($member) {
            login_member($member);
            header('Location: index.php#catalog');
            exit;
        }

        $errors[] = 'The email or password you entered is incorrect.';
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<section class="container auth-section">
    <div class="auth-card">
        <h1>Member login</h1>
        <p class="auth-intro">Sign in to unlock early access to drops, concierge support, and exclusive bundles crafted for insiders.</p>

        <?php if ($errors): ?>
            <div class="notice error" role="alert">
                <strong>We couldn&apos;t sign you in:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" class="form-grid auth-form" novalidate>
            <div>
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autocomplete="email">
            </div>
            <div>
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn-primary">Sign in</button>
        </form>
        <p class="auth-hint">Need an account? Contact our support team to join the TechMart membership program.</p>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
