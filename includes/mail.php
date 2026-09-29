<?php
require_once __DIR__ . '/functions.php';

function motoTrackMailHeaders(): ?string {
    $fromEmail = trim((string)envValue('MAIL_FROM_ADDRESS', ''));
    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        error_log('MotoTrack email skipped: MAIL_FROM_ADDRESS is not configured.');
        return null;
    }

    $fromName = trim(str_replace(["\r", "\n"], '', (string)envValue('MAIL_FROM_NAME', 'MotoTrack')));
    return "From: {$fromName} <{$fromEmail}>\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n";
}

function sendOtpEmail(string $email, string $otp): bool {
    $subject = 'MotoTrack password reset code';
    $message = "Your MotoTrack password reset code is: {$otp}\n\nThis code expires in 10 minutes.";
    $headers = motoTrackMailHeaders();
    if ($headers === null) {
        return false;
    }
    return @mail($email, $subject, $message, $headers);
}

/**
 * Plain-text notification email (appointment + service updates).
 *
 * Uses the same transport, From address and headers as the rest of MotoTrack's
 * mail so there is only one email system. Returns false rather than throwing,
 * because notification delivery must never interrupt a booking or job.
 */
function sendNotificationEmail(string $email, string $subject, string $body): bool {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $headers = motoTrackMailHeaders();
    if ($headers === null) {
        return false;
    }

    return @mail($email, $subject, $body, $headers);
}

function sendOrderEmail(string $email, string $name, int $orderId, float $total, array $items, string $paymentMethod): bool {
    $subject = "MotoTrack – Order #{$orderId} Confirmed";
    $itemLines = '';
    foreach ($items as $item) {
        $lineTotal = number_format((float)$item['price'] * (int)$item['quantity'], 2);
        $itemLines .= "  • {$item['name']} x{$item['quantity']} — PHP {$lineTotal}\n";
    }
    $message  = "Hi {$name},\n\n";
    $message .= "Thank you for your order! Here is your receipt:\n\n";
    $message .= "Order #: {$orderId}\n";
    $message .= "Date: " . date('F j, Y g:i A') . "\n";
    $message .= "Payment: " . ucfirst($paymentMethod) . "\n\n";
    $message .= "Items:\n{$itemLines}\n";
    $message .= "Total: PHP " . number_format($total, 2) . "\n\n";
    $message .= "You can view your order at: " . appUrl() . "\n\n";
    $message .= "Thank you for shopping with MotoTrack!\n";

    $headers = motoTrackMailHeaders();
    if ($headers === null) {
        return false;
    }
    return @mail($email, $subject, $message, $headers);
}
