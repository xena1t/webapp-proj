<?php
$pageTitle = 'Contact Us';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';

start_session();

$errors = [];
$successMessage = null;
$formValues = [
    'name' => '',
    'email' => '',
    'topic' => '',
    'message' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formValues['name'] = sanitize_string($_POST['name'] ?? '');
    $formValues['email'] = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL) ? trim((string) $_POST['email']) : '';
    $formValues['topic'] = sanitize_string($_POST['topic'] ?? '');
    $formValues['message'] = trim((string)($_POST['message'] ?? ''));

    if ($formValues['name'] === '') {
        $errors[] = 'Please share your name so we know how to address you.';
    }
    if ($formValues['email'] === '') {
        $errors[] = 'A valid email address helps us get back to you quickly.';
    }
    if ($formValues['topic'] === '') {
        $errors[] = 'Let us know what your message is about.';
    }
    if ($formValues['message'] === '') {
        $errors[] = 'Tell us a little more about how we can help.';
    }

    if (!$errors) {
        $emailBody = [
            'New contact request from TechMart storefront.',
            '',
            'Name: ' . $formValues['name'],
            'Email: ' . $formValues['email'],
            'Topic: ' . $formValues['topic'],
            '',
            $formValues['message'],
        ];
        $emailBody = implode("\n", $emailBody);

        $sent = deliver_mail('support@techmart.local', 'Contact request from ' . $formValues['name'], $emailBody);

        if ($sent) {
            $successMessage = 'Thanks for reaching out! Our support team will reply within one business day.';
            $formValues = [
                'name' => '',
                'email' => '',
                'topic' => '',
                'message' => '',
            ];
        } else {
            $errors[] = 'We could not send your message right now. Please try again later or email support@techmart.local.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<section class="container">
    <h1 class="section-title">Contact us</h1>
    <p class="section-subtitle">We are here to help with product guidance, order questions, and partnership ideas.</p>

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
            <label for="contactName">Name</label>
            <input type="text" id="contactName" name="name" required value="<?= htmlspecialchars($formValues['name']) ?>">
        </div>
        <div>
            <label for="contactEmail">Email</label>
            <input type="email" id="contactEmail" name="email" required value="<?= htmlspecialchars($formValues['email']) ?>">
        </div>
        <div>
            <label for="contactTopic">Topic</label>
            <input type="text" id="contactTopic" name="topic" required value="<?= htmlspecialchars($formValues['topic']) ?>">
        </div>
        <div style="grid-column: 1 / -1;">
            <label for="contactMessage">How can we help?</label>
            <textarea id="contactMessage" name="message" rows="6" required><?= htmlspecialchars($formValues['message']) ?></textarea>
        </div>
        <button type="submit" class="btn-primary" style="grid-column: 1 / -1;">Send message</button>
    </form>

    <div class="cards-grid" style="margin-top: 4rem;">
        <article class="card">
            <div class="card-content">
                <h2>Visit our experience lab</h2>
                <p>123 Innovation Drive<br>Digital City, 456789</p>
                <p>Monday to Saturday, 10 AM – 7 PM</p>
            </div>
        </article>
        <article class="card">
            <div class="card-content">
                <h2>Need immediate support?</h2>
                <p>Email <a href="mailto:support@techmart.local">support@techmart.local</a> or call +1 (800) 555-0199.</p>
            </div>
        </article>
        <article class="card">
            <div class="card-content">
                <h2>Partner with TechMart</h2>
                <p>Brands and creators can reach our partnerships team at <a href="mailto:partners@techmart.local">partners@techmart.local</a>.</p>
            </div>
        </article>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
