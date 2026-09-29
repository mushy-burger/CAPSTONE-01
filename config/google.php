<?php
$envPath = dirname(__DIR__) . '/.env';
if (function_exists('loadEnvFile')) {
    loadEnvFile($envPath);
}

$clientID = getenv('GOOGLE_CLIENT_ID') ?: '';
$clientSecret = getenv('GOOGLE_CLIENT_SECRET') ?: '';
$redirectUri = trim((string)envValue('GOOGLE_REDIRECT_URI', ''));

return [
    'client_id' => $clientID,
    'client_secret' => $clientSecret,
    'redirect_uri' => $redirectUri !== '' ? $redirectUri : appUrl('google-callback.php'),
];
