<?php
function sendOtpEmail(string $email, string $otp): bool {
    $subject = 'MotoTrack password reset code';
    $message = "Your MotoTrack password reset code is: {$otp}\n\nThis code expires in 10 minutes.";
    $headers = "From: MotoTrack <no-reply@mototrack.local>\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    return @mail($email, $subject, $message, $headers);
}
