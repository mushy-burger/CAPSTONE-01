<?php
/**
 * Shop-order delivery tracking.
 *
 * Payment and order fulfillment remain separate from delivery state.
 */
require_once __DIR__ . '/db.php';

function isOnlineShopOrder(array $order): bool {
    $reference = strtoupper(trim((string)($order['payment_reference'] ?? '')));
    $method = strtolower(trim((string)($order['payment_method'] ?? '')));

    return !str_starts_with($reference, 'POS-')
        && !in_array($method, ['cash', 'gcash', 'card', 'bank_transfer', 'other'], true);
}

function orderDeliveryStatus(array $order): string {
    if (($order['delivery_status'] ?? '') === 'delivered') {
        return 'delivered';
    }

    return trim((string)($order['tracking_url'] ?? '')) !== '' ? 'delivering' : 'pending';
}

function validateOrderTrackingUrl(string $url): string {
    $url = trim($url);
    if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
        throw new RuntimeException('Enter a valid tracking URL.');
    }

    $parts = parse_url($url);
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true) || empty($parts['host'])) {
        throw new RuntimeException('Tracking links must use http:// or https://.');
    }

    return $url;
}

function orderDeliveryLoadForUpdate(PDO $db, int $orderId): array {
    $stmt = $db->prepare(
        "SELECT id, user_id, payment_method, payment_reference, payment_status,
                tracking_url, delivery_status, delivered_at
         FROM orders
         WHERE id = ?
         FOR UPDATE"
    );
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order || !isOnlineShopOrder($order)) {
        throw new RuntimeException('Shop order not found.');
    }
    if (($order['payment_status'] ?? '') !== 'paid') {
        throw new RuntimeException('Only paid shop orders can receive delivery tracking.');
    }

    return $order;
}

function orderDeliveryNotifyCustomer(int $userId, int $orderId, string $event): void {
    if ($userId <= 0) {
        return;
    }

    $messages = [
        'tracking_added' => 'Your order is on the way. Tracking information is now available.',
        'delivered' => 'Your order has been marked as delivered.',
    ];
    if (!isset($messages[$event])) {
        return;
    }

    $type = 'order_delivery_' . $event;
    $message = $messages[$event];
    $actionUrl = 'cart.php?tab=orders#order-' . $orderId;

    getDB()->prepare(
        "INSERT INTO notifications (user_id, type, message, booking_id, action_url)
         SELECT ?, ?, ?, NULL, ?
         WHERE NOT EXISTS (
           SELECT 1 FROM notifications
           WHERE user_id = ? AND type = ? AND action_url = ?
         )"
    )->execute([$userId, $type, $message, $actionUrl, $userId, $type, $actionUrl]);
}

function saveOrderTrackingLink(int $orderId, string $url): array {
    $url = validateOrderTrackingUrl($url);
    $db = getDB();
    $db->beginTransaction();

    try {
        $order = orderDeliveryLoadForUpdate($db, $orderId);
        $previousStatus = orderDeliveryStatus($order);
        $newStatus = $previousStatus === 'delivered' ? 'delivered' : 'delivering';

        $db->prepare(
            "UPDATE orders
             SET tracking_url = ?, delivery_status = ?
             WHERE id = ?"
        )->execute([$url, $newStatus, $orderId]);

        $db->commit();

        $firstTrackingLink = $previousStatus === 'pending';
        if ($firstTrackingLink) {
            orderDeliveryNotifyCustomer((int)$order['user_id'], $orderId, 'tracking_added');
        }

        return ['status' => $newStatus, 'first_tracking_link' => $firstTrackingLink];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function markOrderDelivered(int $orderId): bool {
    $db = getDB();
    $db->beginTransaction();

    try {
        $order = orderDeliveryLoadForUpdate($db, $orderId);
        if (orderDeliveryStatus($order) === 'delivered') {
            $db->commit();
            return false;
        }
        if (trim((string)($order['tracking_url'] ?? '')) === '') {
            throw new RuntimeException('Save a tracking link before marking this order delivered.');
        }

        $stmt = $db->prepare(
            "UPDATE orders
             SET delivery_status = 'delivered',
                 delivered_at = COALESCE(delivered_at, NOW())
             WHERE id = ? AND delivery_status <> 'delivered'"
        );
        $stmt->execute([$orderId]);
        $changed = $stmt->rowCount() === 1;
        $db->commit();

        if ($changed) {
            orderDeliveryNotifyCustomer((int)$order['user_id'], $orderId, 'delivered');
        }

        return $changed;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}
