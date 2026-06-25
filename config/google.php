<?php
$envPath = dirname(__DIR__) . '/.env';
if (function_exists('loadEnvFile')) {
    loadEnvFile($envPath);
}

$clientID = getenv('GOOGLE_CLIENT_ID') ?: '';
$clientSecret = getenv('GOOGLE_CLIENT_SECRET') ?: '';

return [
    'client_id' => $clientID,
    'client_secret' => $clientSecret,
    'redirect_uri' => 'http://localhost/CAPSTONE-01/CAPSTONE-01/google-callback.php',
];
