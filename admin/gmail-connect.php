<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
bootstrapDirectAuthContext(['admin', 'qa']);
requireAdminOnly();
require_once __DIR__ . '/../includes/GmailService.php';

$gmail = new GmailService();
try {
    $state = bin2hex(random_bytes(32));
    $_SESSION['gmail_oauth_state'] = $state;
    $_SESSION['gmail_oauth_return'] = baseUrl('admin/purchase-orders.php');
    header('Location: ' . $gmail->authorizationUrl($state));
    exit;
} catch (Throwable $e) {
    error_log('Gmail OAuth start failed: ' . $e->getMessage());
    flashMessage('po_error', $e->getMessage());
    redirect(baseUrl('admin/purchase-orders.php'));
}
