<?php
$envPath = dirname(__DIR__) . '/.env';
if (function_exists('loadEnvFile')) {
    loadEnvFile($envPath);
}

$clientID = getenv('GOOGLE_CLIENT_ID') ?: '';
$clientSecret = getenv('GOOGLE_CLIENT_SECRET') ?: '';
$appUrl = rtrim(getenv('APP_URL') ?: '', '/');

return [
    'client_id' => $clientID,
    'client_secret' => $clientSecret,
    'redirect_uri' => getenv('GOOGLE_REDIRECT_URI') ?: ($appUrl ? $appUrl . '/google-callback.php' : 'http://localhost/CAPSTONE-01/google-callback.php'),
];
