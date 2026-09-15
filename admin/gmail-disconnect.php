<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
bootstrapDirectAuthContext(['admin', 'qa']);
requireAdminOnly();
require_once __DIR__ . '/../includes/GmailService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(baseUrl('admin/purchase-orders.php'));
}
requireCsrfToken();
(new GmailService())->disconnect();
flashMessage('po_success', 'Gmail disconnected.');
redirect(baseUrl('admin/purchase-orders.php'));
