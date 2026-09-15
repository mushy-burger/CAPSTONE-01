<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
bootstrapDirectAuthContext(['admin', 'qa']);
requireAdminOnly();
require_once __DIR__ . '/../includes/GmailService.php';

$expected = (string)($_SESSION['gmail_oauth_state'] ?? '');
$received = (string)($_GET['state'] ?? '');
unset($_SESSION['gmail_oauth_state']);
$returnTo = safeLocalPath($_SESSION['gmail_oauth_return'] ?? null) ?: baseUrl('admin/purchase-orders.php');
unset($_SESSION['gmail_oauth_return']);

if ($expected === '' || $received === '' || !hash_equals($expected, $received)) {
    http_response_code(400);
    exit('Invalid Gmail OAuth state.');
}
if (!empty($_GET['error'])) {
    flashMessage('po_error', 'Gmail authorization was cancelled or denied.');
    redirect($returnTo);
}

try {
    (new GmailService())->exchangeCode((string)($_GET['code'] ?? ''));
    flashMessage('po_success', 'Gmail connected successfully.');
} catch (Throwable $e) {
    error_log('Gmail OAuth callback failed: ' . $e->getMessage());
    flashMessage('po_error', 'Gmail connection failed. Check server configuration and try again.');
}
redirect($returnTo);
