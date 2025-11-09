<?php
require_once __DIR__ . '/functions.php';

/**
 * Send a plain-text email using either the configured SMTP relay or PHP's mail().
 * Returns true when the transport reports success.
 */
function deliver_mail(string $to, string $subject, string $body, array $headers = []): bool
{
    $recipient = trim($to);
    if ($recipient === '' || preg_match('/[\r\n]/', $recipient)) {
        error_log('Refusing to send email: invalid recipient "' . $to . '"');
        return false;
    }

    $subject = trim($subject);

    $normalizedHeaders = [];
    $headerLookup = [];
    foreach ($headers as $line) {
        $line = trim((string) $line);
        if ($line === '') {
            continue;
        }
        if (preg_match('/^[^:]+:/', $line) !== 1) {
            continue;
        }
        $normalizedHeaders[] = $line;
        [$name, $value] = explode(':', $line, 2);
        $headerLookup[strtolower(trim($name))] = trim($value);
    }

    if (!isset($headerLookup['from'])) {
        $fromAddress = defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : 'no-reply@localhost';
        $fromName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : (defined('SITE_NAME') ? SITE_NAME : 'Storefront');
        $normalizedHeaders[] = 'From: ' . $fromName . ' <' . $fromAddress . '>';
        $headerLookup['from'] = $fromAddress;
    }

    if (!isset($headerLookup['mime-version'])) {
        $normalizedHeaders[] = 'MIME-Version: 1.0';
    }
    if (!isset($headerLookup['content-transfer-encoding'])) {
        $normalizedHeaders[] = 'Content-Transfer-Encoding: 8bit';
    }
    if (!isset($headerLookup['date'])) {
        $normalizedHeaders[] = 'Date: ' . date(DATE_RFC2822);
    }
    if (!isset($headerLookup['message-id'])) {
        $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
        try {
            $identifier = bin2hex(random_bytes(16));
        } catch (Throwable $e) {
            $identifier = str_replace('.', '', uniqid('', true));
        }
        $normalizedHeaders[] = 'Message-ID: <' . $identifier . '@' . $domain . '>';
    }

    $normalizedBody = preg_replace("/(?<!\r)\n/", "\r\n", $body);
    if ($normalizedBody === null) {
        $normalizedBody = $body;
    }
    $normalizedBody = rtrim($normalizedBody, "\r\n") . "\r\n";

    $smtpResult = smtp_send($recipient, $subject, $normalizedBody, $normalizedHeaders);
    if ($smtpResult === true) {
        return true;
    }

    if ($smtpResult === false) {
        error_log('SMTP delivery failed, falling back to mail() for ' . $recipient);
    }

    $headerString = implode("\r\n", $normalizedHeaders);
    $success = @mail($recipient, $subject, $normalizedBody, $headerString);
    if (!$success) {
        error_log('mail() failed to deliver message to ' . $recipient);
    }
    return $success;
}

/**
 * Attempt to send mail via a basic SMTP socket. Returns true on success, false on failure,
 * and null when SMTP is not configured.
 */
function smtp_send(string $to, string $subject, string $body, array $headers): ?bool
{
    if (!defined('SMTP_HOST') || SMTP_HOST === '') {
        return null;
    }

    $host = SMTP_HOST;
    $port = defined('SMTP_PORT') ? (int) SMTP_PORT : 25;
    $address = sprintf('%s:%d', $host, $port);

    $errno = 0;
    $errstr = '';
    $socket = @stream_socket_client($address, $errno, $errstr, 10, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        error_log(sprintf('SMTP connection to %s failed: %s (%d)', $address, $errstr, $errno));
        return false;
    }
    stream_set_timeout($socket, 10);

    $closeSocket = static function () use (&$socket): void {
        if (is_resource($socket)) {
            fclose($socket);
        }
    };

    try {
        $read = static function () use ($socket): string {
            $data = '';
            while (($line = fgets($socket, 515)) !== false) {
                $data .= $line;
                if (strlen($line) >= 4 && $line[3] === '-') {
                    continue;
                }
                break;
            }
            return $data;
        };

        $expect = static function (array $codes, string $context) use ($read): void {
            $response = $read();
            if ($response === '' || !preg_match('/^(\d{3})/', $response, $matches)) {
                throw new RuntimeException('Empty SMTP response during ' . $context);
            }
            $code = (int) $matches[1];
            if (!in_array($code, $codes, true)) {
                throw new RuntimeException('Unexpected SMTP response during ' . $context . ': ' . trim($response));
            }
        };

        $send = static function (string $command) use ($socket): void {
            fwrite($socket, $command . "\r\n");
        };

        $expect([220], 'connection');

        $serverName = $_SERVER['SERVER_NAME'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $send('EHLO ' . $serverName);
        $expect([250], 'EHLO');

        $fromAddress = defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : 'no-reply@localhost';
        $send('MAIL FROM:<' . $fromAddress . '>');
        $expect([250], 'MAIL FROM');

        $recipients = array_filter(array_map('trim', preg_split('/,/', $to)));
        if (empty($recipients)) {
            throw new RuntimeException('No valid SMTP recipient provided.');
        }
        foreach ($recipients as $recipient) {
            $send('RCPT TO:<' . $recipient . '>');
            $expect([250, 251], 'RCPT TO');
        }

        $send('DATA');
        $expect([354], 'DATA');

        $messageHeaders = array_merge(['Subject: ' . $subject], $headers);
        $message = implode("\r\n", $messageHeaders) . "\r\n\r\n" . $body;
        $message = preg_replace('/^\./m', '..', $message);
        $message = rtrim($message, "\r\n") . "\r\n";

        fwrite($socket, $message . ".\r\n");
        $expect([250], 'message body');

        $send('QUIT');
        $closeSocket();
        return true;
    } catch (Throwable $exception) {
        error_log('SMTP send failed: ' . $exception->getMessage());
        $closeSocket();
        return false;
    }
}

function send_order_confirmation(array $order, array $items): void
{
    $to = $order['customer_email'];
    $displayNumber = isset($order['customer_order_number']) && (int) $order['customer_order_number'] > 0
        ? (int) $order['customer_order_number']
        : (int) $order['id'];
    $subject = 'Order #' . $displayNumber . ' Confirmation - ' . SITE_NAME;

    $lines = [
        'Hi ' . $order['customer_name'] . ',',
        '',
        'Thanks for shopping with ' . SITE_NAME . '! We have received your order #' . $displayNumber . '.',
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
        urlencode((string) $displayNumber),
        urlencode($order['customer_email'])
    );

    $lines[] = 'You can track your order anytime at: ' . $trackingUrl;
    $lines[] = '';
    $lines[] = 'We will send another email when your order ships. Have questions? Reply to this email and our specialists will help.';
    $lines[] = '';
    $lines[] = '— ' . SITE_NAME . ' Team';

    $body = implode("\n", $lines);

    $headers = [
        'Content-Type: text/plain; charset=UTF-8',
    ];

    if (!deliver_mail($to, $subject, $body, $headers)) {
        error_log('Failed to send order confirmation for order #' . $displayNumber);
    }
}

function send_newsletter_discount_email(string $email, string $code, bool $isReminder = false): bool
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
        'Content-Type: text/plain; charset=UTF-8',
    ];

    if (!deliver_mail($to, $subject, $body, $headers)) {
        error_log('Failed to send newsletter discount email to ' . $email);
        return false;
    }

    return true;
}
