<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

if (!isLoggedIn()) {
    echo json_encode(['count' => 0, 'notifications' => []]);
    exit;
}

$user = getCurrentUser();
$userId = (int)$user['id'];

// Mark as read if requested
if (isset($_GET['mark_read'])) {
    getDB()->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0")
           ->execute([$userId]);
}

$count = getUnreadNotificationCount($userId);

$notifications = fetchAllRows(
    "SELECT id, type, message, booking_id, is_read, created_at
     FROM notifications
     WHERE user_id = ?
     ORDER BY created_at DESC
     LIMIT 15",
    [$userId]
);

echo json_encode([
    'count'         => $count,
    'notifications' => $notifications,
]);
