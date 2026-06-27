<?php
function sendOtpEmail(string $email, string $otp): bool {
    $subject = 'MotoTrack password reset code';
    $message = "Your MotoTrack password reset code is: {$otp}\n\nThis code expires in 10 minutes.";
    $headers = "From: MotoTrack <no-reply@mototrack.local>\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    return @mail($email, $subject, $message, $headers);
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
    $message .= "You can view your order at: mototrack.local\n\n";
    $message .= "Thank you for shopping with MotoTrack!\n";

    $headers  = "From: MotoTrack <no-reply@mototrack.local>\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    return @mail($email, $subject, $message, $headers);
}
