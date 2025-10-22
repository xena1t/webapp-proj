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
