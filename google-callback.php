<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';

$google = require __DIR__ . '/config/google.php';
$state = $_GET['state'] ?? '';
$code = $_GET['code'] ?? '';
$stored = $_SESSION['google_oauth_states'][$state] ?? null;

if (!$stored || (time() - (int)$stored['created_at']) > 600) {
    flashMessage('auth_error', 'Google sign-in expired. Please try again.');
    redirect(baseUrl('login.php'));
}

unset($_SESSION['google_oauth_states'][$state]);
$_GET['ctx'] = $stored['ctx'];

if ($code === '') {
    flashMessage('auth_error', 'Google did not return an authorization code.');
    redirect(baseUrl('login.php'));
}

function googlePost(string $url, array $data): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$status, json_decode($response ?: '[]', true) ?: []];
}

function googleGet(string $url, string $token): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$status, json_decode($response ?: '[]', true) ?: []];
}

[$tokenStatus, $token] = googlePost('https://oauth2.googleapis.com/token', [
    'code' => $code,
    'client_id' => $google['client_id'],
    'client_secret' => $google['client_secret'],
    'redirect_uri' => $google['redirect_uri'],
    'grant_type' => 'authorization_code',
]);

if ($tokenStatus !== 200 || empty($token['access_token'])) {
    flashMessage('auth_error', 'Google sign-in failed while requesting access.');
    redirect(baseUrl('login.php'));
}

[$profileStatus, $profile] = googleGet('https://openidconnect.googleapis.com/v1/userinfo', $token['access_token']);
if ($profileStatus !== 200 || empty($profile['email']) || empty($profile['sub'])) {
    flashMessage('auth_error', 'Google sign-in failed while reading your profile.');
    redirect(baseUrl('login.php'));
}

$email = strtolower(trim($profile['email']));
$name = trim($profile['name'] ?? $email);
$googleId = $profile['sub'];

$user = fetchOne("SELECT * FROM users WHERE google_id = ? OR email = ? LIMIT 1", [$googleId, $email]);

if ($user) {
    if ((int)($user['is_active'] ?? 1) !== 1) {
        flashMessage('auth_error', 'This account has been disabled. Please contact the shop.');
        redirect(baseUrl('login.php'));
    }

    $stmt = getDB()->prepare("UPDATE users SET google_id = ?, auth_provider = 'google', name = COALESCE(NULLIF(name, ''), ?) WHERE id = ?");
    $stmt->execute([$googleId, $name, $user['id']]);
    $user = fetchOne("SELECT * FROM users WHERE id = ?", [$user['id']]);
} else {
    $stmt = getDB()->prepare("INSERT INTO users (name, email, password, google_id, auth_provider, role) VALUES (?, ?, ?, ?, 'google', 'customer')");
    $stmt->execute([$name, $email, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), $googleId]);
    $user = fetchOne("SELECT * FROM users WHERE id = ?", [(int)getDB()->lastInsertId()]);
}

loginUser($user);
redirect(baseUrl('index.php'));
