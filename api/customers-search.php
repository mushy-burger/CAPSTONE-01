<?php
/**
 * Customer autocomplete for the staff booking form.
 *
 * GET ?q=<term> — searches active customer accounts by name, email, or phone.
 * Staff/admin only. Returns at most 15 matches so the full customer table is
 * never shipped to the browser.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
requireAdminOrStaff();

header('Content-Type: application/json; charset=UTF-8');

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 1) {
    echo json_encode(['ok' => true, 'customers' => []]);
    exit;
}

$like = '%' . $q . '%';
$customers = fetchAllRows(
    "SELECT id, name, email, phone
     FROM users
     WHERE role = 'customer' AND is_active = 1
       AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)
     ORDER BY name ASC
     LIMIT 15",
    [$like, $like, $like]
);

echo json_encode([
    'ok' => true,
    'customers' => array_map(static fn(array $c) => [
        'id'    => (int)$c['id'],
        'name'  => $c['name'],
        'email' => $c['email'],
        'phone' => $c['phone'] ?: '',
    ], $customers),
]);
