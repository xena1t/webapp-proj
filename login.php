<?php
require_once __DIR__ . '/includes/functions.php'; // contains $pdo and session_start()
// require_once __DIR__ . '/includes/csrf.php';
$pageTitle = 'Login';
require_once __DIR__ . '/includes/header.php';

// Redirect if already logged in
if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}
?>

<section class="container" style="max-width:480px;margin-top:3rem;">
    <h2 class="section-title">Sign in to your account</h2>
    <form method="post" action="handle_login.php" class="card" style="padding:1.5rem;">
        <label>Email</label>
        <input type="email" name="email" required class="input" placeholder="you@example.com">
        <label>Password</label>
        <input type="password" name="password" required class="input" placeholder="••••••••">
        <br/>
        <button type="submit" class="btn-primary" style="margin-top:1rem;">Login</button>
        <p style="margin-top:1rem;">Don’t have an account?
            <a href="register.php" class="btn-link">Register</a>
        </p>
        <?php if (!empty($_SESSION['flash_error'])): ?>
            <p class="error" style="color:#c00;margin-top:1rem;">
                <?= htmlspecialchars($_SESSION['flash_error']) ?>
            </p>
            <?php unset($_SESSION['flash_error']); endif; ?>
    </form>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
