<?php
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'Register';
require_once __DIR__ . '/includes/header.php';

// Redirect if already logged in
if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}
?>

<section class="container" style="max-width:480px;margin-top:3rem;">
    <h2 class="section-title">Create an Account</h2>
    <form method="post" action="handle_register.php" class="card" style="padding:1.5rem;">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required class="input" placeholder="Your name">

        <label for="email">Email</label>
        <input type="email" id="email" name="email" required class="input" placeholder="you@example.com">

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required class="input" placeholder="••••••••">

        <label for="confirm_password">Confirm Password</label>
        <input type="password" id="confirm_password" name="confirm_password" required class="input"
            placeholder="••••••••">

        <br />
        <button type="submit" class="btn-primary" style="margin-top:1rem;">Register</button>

        <?php if (!empty($_SESSION['flash_errors'])): ?>
            <div class="error-box">
                <ul>
                    <?php foreach ($_SESSION['flash_errors'] as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php unset($_SESSION['flash_errors']); ?>
        <?php endif; ?>

    </form>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>