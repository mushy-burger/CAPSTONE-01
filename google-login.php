<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$google = require __DIR__ . '/config/google.php';

if (empty($google['client_id']) || empty($google['client_secret']) || empty($google['redirect_uri'])) {
    flashMessage('auth_error', 'Google sign-in is not configured yet. Add GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET in your local .env file.');
    redirect(baseUrl('login.php'));
}

$state = bin2hex(random_bytes(24));
$_SESSION['google_oauth_states'][$state] = [
    'ctx' => currentAuthContext(),
    'created_at' => time(),
];

$params = [
    'client_id' => $google['client_id'],
    'redirect_uri' => $google['redirect_uri'],
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'state' => $state,
    'access_type' => 'online',
    'prompt' => 'select_account',
];

redirect('https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params));
