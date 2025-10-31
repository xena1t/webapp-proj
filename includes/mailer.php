<?php
require_once __DIR__ . '/functions.php';

function send_order_confirmation(array $order, array $items): void
{
    $to = $order['customer_email'];
    $subject = 'Order #' . $order['id'] . ' Confirmation - ' . SITE_NAME;

    $lines = [
        'Hi ' . $order['customer_name'] . ',',
        '',
        'Thanks for shopping with ' . SITE_NAME . '! We have received your order #' . $order['id'] . '.',
        'Order summary:',
    ];

    foreach ($items as $item) {
        $lines[] = sprintf('- %s x%d — %s', $item['name'], $item['quantity'], format_price((float) $item['line_total']));
    }

    $lines[] = '';
    if (!empty($order['discount_amount'])) {
        $discountLine = 'Discount applied: -' . format_price((float) $order['discount_amount']);
        if (!empty($order['promo_code'])) {
            $discountLine .= ' (code ' . $order['promo_code'] . ')';
        }
        $lines[] = $discountLine;
    }
    $lines[] = 'Total charged: ' . format_price((float) $order['total']);

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $trackingUrl = sprintf('%s://%s/order-status.php?order=%s&email=%s',
        $scheme,
        $host,
        urlencode($order['id']),
        urlencode($order['customer_email'])
    );

    $lines[] = 'You can track your order anytime at: ' . $trackingUrl;
    $lines[] = '';
    $lines[] = 'We will send another email when your order ships. Have questions? Reply to this email and our specialists will help.';
    $lines[] = '';
    $lines[] = '— ' . SITE_NAME . ' Team';

    $body = implode("\n", $lines);

    $headers = [
        'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM_ADDRESS . '>',
        'Content-Type: text/plain; charset=UTF-8',
    ];

    @mail($to, $subject, $body, implode("\r\n", $headers));
}

function send_newsletter_discount_email(string $email, string $code, bool $isReminder = false): void
{
    $to = $email;
    $subject = $isReminder
        ? 'Your ' . SITE_NAME . ' welcome discount code'
        : 'Welcome to ' . SITE_NAME . ' — here’s your 10% code';

    $lines = [
        'Hi there,',
        '',
    ];

    if ($isReminder) {
        $lines[] = 'Here’s a quick reminder of your exclusive 10% welcome code with ' . SITE_NAME . ':';
    } else {
        $lines[] = 'Thanks for joining the ' . SITE_NAME . ' newsletter! As promised, here’s your exclusive 10% discount code:';
    }

    $lines[] = 'Code: ' . $code;
    $lines[] = '';
    $lines[] = 'Apply it during checkout to save on your next order. The code is tied to this email address, so be sure to use it when signing in.';
    $lines[] = '';
    $lines[] = 'Need ideas on what to pick up? We’ll send curated drops and product guides straight to your inbox soon!';
    $lines[] = '';
    $lines[] = '— ' . SITE_NAME . ' Team';

    $body = implode("\n", $lines);

    $headers = [
        'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM_ADDRESS . '>',
        'Content-Type: text/plain; charset=UTF-8',
    ];

    @mail($to, $subject, $body, implode("\r\n", $headers));
}
