<?php
// Buffer all page output so redirects issued after partial rendering
// (e.g. POST handlers on pages that print the sidebar first) can still
// send headers. redirect() discards the buffer before redirecting.
// Note: php.ini's output_buffering=4096 buffer auto-flushes when full,
// so an explicit UNLIMITED buffer is stacked on top of it here.
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function validAuthContext(string $ctx): bool {
    return preg_match('/^[a-zA-Z0-9_-]{8,64}$/', $ctx) === 1;
}

function currentAuthContext(): string {
    $candidates = [
        $_POST['ctx'] ?? '',
        $_GET['ctx'] ?? '',
        $_SERVER['HTTP_X_AUTH_CONTEXT'] ?? '',
    ];

    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    if (is_string($referrer) && $referrer !== '') {
        $referrerParts = parse_url($referrer);
        if (is_array($referrerParts)) {
            $referrerHost = $referrerParts['host'] ?? '';
            $currentHost = $_SERVER['HTTP_HOST'] ?? '';
            if ($referrerHost === '' || strcasecmp($referrerHost, $currentHost) === 0) {
                parse_str($referrerParts['query'] ?? '', $referrerQuery);
                $candidates[] = $referrerQuery['ctx'] ?? '';
            }
        }
    }

    foreach ($candidates as $ctx) {
        $ctx = is_string($ctx) ? $ctx : '';
        if (validAuthContext($ctx)) {
            return $ctx;
        }
    }

    return 'default';
}

/**
 * Directly typed admin URLs have no tab context. Recover one only when every
 * eligible context in this session belongs to the same user and role.
 */
function bootstrapDirectAuthContext(array $allowedRoles = ['admin', 'qa']): void {
    if (currentAuthContext() !== 'default' || isLoggedIn()) {
        return;
    }

    $contexts = $_SESSION['auth_contexts'] ?? [];
    if (!is_array($contexts)) {
        return;
    }

    $eligible = [];
    $identities = [];
    foreach ($contexts as $key => $context) {
        if (!is_string($key) || !validAuthContext($key) || !is_array($context)) {
            continue;
        }
        $userId = (int)($context['user_id'] ?? 0);
        $role = (string)($context['user_role'] ?? '');
        if ($userId < 1 || !in_array($role, $allowedRoles, true)) {
            continue;
        }
        $eligible[$key] = true;
        $identities[$userId . ':' . $role] = true;
    }

    if (count($eligible) === 0 || count($identities) !== 1) {
        return;
    }

    $key = (string)array_key_last($eligible);
    $_GET['ctx'] = $key;
    $_POST['ctx'] = $key;
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

/**
 * Validate a caller-supplied return path. Only same-site, absolute-path
 * URLs are allowed (leading single "/", no scheme, no protocol-relative
 * "//host", no CR/LF), and never the auth pages themselves, to avoid both
 * open-redirects and login loops. Returns the path, or null if unsafe.
 */
function safeLocalPath(?string $path): ?string {
    if (!is_string($path) || $path === '') {
        return null;
    }
    if ($path[0] !== '/' || (isset($path[1]) && $path[1] === '/')) {
        return null;
    }
    if (preg_match('/[\r\n\t\x00]/', $path) === 1) {
        return null;
    }
    if (preg_match('#/(login|logout|register|forgot-password|reset-password|verify-otp)\.php#i', $path) === 1) {
        return null;
    }
    return $path;
}

function requireLogin(string $redirect = ''): void {
    if (!isLoggedIn()) {
        require_once __DIR__ . '/functions.php';
        if ($redirect !== '') {
            header('Location: ' . $redirect);
            exit;
        }
        // Preserve the page the visitor was trying to reach so login can
        // return them to it instead of dropping them on the homepage.
        $loginUrl = baseUrl('login.php');
        $next = safeLocalPath($_SERVER['REQUEST_URI'] ?? null);
        if ($next !== null) {
            $loginUrl .= (strpos($loginUrl, '?') !== false ? '&' : '?') . 'next=' . rawurlencode($next);
        }
        header('Location: ' . $loginUrl);
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
    if (!$currentUser || !in_array($currentUser['role'], ['admin', 'staff', 'qa'], true)) {
        require_once __DIR__ . '/functions.php';
        header('Location: ' . baseUrl('index.php'));
        exit;
    }
}

function requireAdminOnly(): void {
    requireLogin();
    $currentUser = getCurrentUser();
    if (!$currentUser || !in_array($currentUser['role'], ['admin', 'qa'], true)) {
        require_once __DIR__ . '/functions.php';
        header('Location: ' . baseUrl('index.php'));
        exit;
    }
}

function requireStaff(): void {
    requireLogin();
    $currentUser = getCurrentUser();
    if (!$currentUser || !in_array($currentUser['role'], ['staff', 'qa'], true)) {
        require_once __DIR__ . '/functions.php';
        header('Location: ' . baseUrl('index.php'));
        exit;
    }
}

function requireTechnician(): void {
    requireLogin();
    $currentUser = getCurrentUser();
    if (!$currentUser || !in_array($currentUser['role'], ['technician', 'qa'], true)) {
        require_once __DIR__ . '/functions.php';
        header('Location: ' . baseUrl('index.php'));
        exit;
    }
}

function requireQA(): void {
    requireLogin();
    $currentUser = getCurrentUser();
    if (!$currentUser || $currentUser['role'] !== 'qa') {
        require_once __DIR__ . '/functions.php';
        header('Location: ' . baseUrl('index.php'));
        exit;
    }
}

// -------------------------------------------------------
// Notification helpers
// -------------------------------------------------------
function createNotification(int $userId, string $message, string $type = 'booking', ?int $bookingId = null): void {
    require_once __DIR__ . '/db.php';
    getDB()->prepare(
        "INSERT INTO notifications (user_id, type, message, booking_id) VALUES (?, ?, ?, ?)"
    )->execute([$userId, $type, $message, $bookingId]);
}

function notifyAllStaff(string $message, string $type = 'booking', ?int $bookingId = null): void {
    require_once __DIR__ . '/db.php';
    $staffUsers = getDB()->query("SELECT id FROM users WHERE role = 'staff' AND is_active = 1")->fetchAll();
    foreach ($staffUsers as $u) {
        createNotification((int)$u['id'], $message, $type, $bookingId);
    }
}

function getUnreadNotificationCount(int $userId): int {
    require_once __DIR__ . '/db.php';
    $stmt = getDB()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function loginUser(array $user): void {
    session_regenerate_id(false);
    $key = currentAuthContext();

    if ($key === 'default') {
        $key = 'tab_' . bin2hex(random_bytes(16));
    }

    $_GET['ctx'] = $key;
    $_POST['ctx'] = $key;

    $_SESSION['auth_contexts'][$key] = [
        'user_id' => (int)$user['id'],
        'user_name' => $user['name'],
        'user_role' => $user['role'],
        'user_email' => $user['email'],
    ];
    $_SESSION['last_auth_context'] = $key;
}

function logoutUser(): void {
    $key = currentAuthContext();
    unset($_SESSION['auth_contexts'][$key]);
    if (($_SESSION['last_auth_context'] ?? '') === $key) {
        unset($_SESSION['last_auth_context']);
    }

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

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function requireCsrfToken(): void {
    $provided = (string)($_POST['csrf_token'] ?? '');
    if ($provided === '' || !hash_equals(csrfToken(), $provided)) {
        http_response_code(400);
        exit('Invalid CSRF token.');
    }
}

/**
 * QA accounts may inspect every panel but must never mutate application data.
 * Enforce this centrally so direct POST requests cannot bypass disabled UI.
 */
function enforceQAReadOnlyRequest(): void {
    $currentUser = getCurrentUser();
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (!$currentUser || $currentUser['role'] !== 'qa' || in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
        return;
    }

    http_response_code(403);
    $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
    if (str_contains($accept, 'application/json')) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => 'QA mode is read-only. No changes were saved.']);
    } else {
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'QA mode is read-only. No changes were saved.';
    }
    exit;
}

enforceQAReadOnlyRequest();
