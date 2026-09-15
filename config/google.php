<?php
require_once __DIR__ . '/../includes/functions.php';

return [
    'client_id'     => envValue('GOOGLE_CLIENT_ID', ''),
    'client_secret' => envValue('GOOGLE_CLIENT_SECRET', ''),
    'redirect_uri'  => envValue('GOOGLE_REDIRECT_URI', '')
                       ?: (rtrim(envValue('APP_URL', ''), '/') . '/google-callback.php'),
];
