<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function currentAuthContext(): string {
    $ctx = $_POST['ctx'] ?? $_GET['ctx'] ?? '';
    $ctx = is_string($ctx) ? $ctx : '';
    return preg_match('/^[a-zA-Z0-9_-]{8,64}$/', $ctx) ? $ctx : 'default';
}

function getAuthContext(): array {
    $key = currentAuthContext();
    if (isset($_SESSION['auth_contexts'][$key])) {
        return $_SESSION['auth_contexts'][$key];
    }

    return [];
}

function isLoggedIn(): bool {
    return isset(getAuthContext()['user_id']);
}

function getCurrentUser(): ?array {
    $auth = getAuthContext();
    if (!isset($auth['user_id'])) {
        return null;
    }

    return [
        'id' => (int)$auth['user_id'],
        'name' => $auth['user_name'],
        'role' => $auth['user_role'],
        'email' => $auth['user_email'] ?? '',
    ];
}

function requireLogin(string $redirect = ''): void {
    if (!isLoggedIn()) {
        require_once __DIR__ . '/functions.php';
        header('Location: ' . ($redirect ?: baseUrl('login.php')));
        exit;
    }
}

function requireRole(string $role, string $redirect = ''): void {
    requireLogin();
    $currentUser = getCurrentUser();
    if (!$currentUser || $currentUser['role'] !== $role) {
        require_once __DIR__ . '/functions.php';
        header('Location: ' . ($redirect ?: baseUrl('index.php')));
        exit;
    }
}

function requireAdminOrStaff(): void {
    requireLogin();
    $currentUser = getCurrentUser();
    if (!$currentUser || !in_array($currentUser['role'], ['admin', 'staff'], true)) {
        require_once __DIR__ . '/functions.php';
        header('Location: ' . baseUrl('index.php'));
        exit;
    }
}

function loginUser(array $user): void {
    session_regenerate_id(false);
    $key = currentAuthContext();

    if ($key === 'default') {
        $key = 'tab_' . bin2hex(random_bytes(16));
        $_GET['ctx'] = $key;
        $_POST['ctx'] = $key;
    }

    $_SESSION['auth_contexts'][$key] = [
        'user_id' => (int)$user['id'],
        'user_name' => $user['name'],
        'user_role' => $user['role'],
        'user_email' => $user['email'],
    ];
}

function logoutUser(): void {
    $key = currentAuthContext();
    unset($_SESSION['auth_contexts'][$key]);

    if ($key === 'default') {
        unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_role'], $_SESSION['user_email']);
    }
}

function getCartCount(): int {
    $currentUser = getCurrentUser();
    if (!$currentUser) {
        return 0;
    }

    require_once __DIR__ . '/db.php';
    $stmt = getDB()->prepare("SELECT COALESCE(SUM(quantity),0) FROM cart_items WHERE user_id = ?");
    $stmt->execute([$currentUser['id']]);
    return (int)$stmt->fetchColumn();
}
