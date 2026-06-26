<?php
require_once __DIR__ . '/../includes/functions.php';

return [
    'secret_key' => envValue('PAYMONGO_SECRET_KEY', ''),
    'public_key' => envValue('PAYMONGO_PUBLIC_KEY', ''),
    'webhook_secret' => envValue('PAYMONGO_WEBHOOK_SECRET', ''),
    'webhook_url' => envValue('PAYMONGO_WEBHOOK_URL', ''),
];
