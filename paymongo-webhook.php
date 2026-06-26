<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/paymongo.php';

header('Content-Type: application/json');

$payload = file_get_contents('php://input') ?: '';
$signature = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '';

if (!verifyPaymongoWebhook($payload, $signature)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Invalid webhook signature.']);
    exit;
}

$event = json_decode($payload, true);
if (!is_array($event)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$eventType = $event['data']['attributes']['type'] ?? '';
if ($eventType !== 'checkout_session.payment.paid') {
    http_response_code(200);
    echo json_encode(['ok' => true, 'message' => 'Event ignored.']);
    exit;
}

$resource = $event['data']['attributes']['data'] ?? [];
$metadata = $resource['attributes']['metadata'] ?? [];
$orderId = (int)($metadata['order_id'] ?? 0);
$checkoutSessionId = $resource['id'] ?? '';

if ($orderId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Missing order metadata.']);
    exit;
}

try {
    getDB()->beginTransaction();

    $order = fetchOne("SELECT * FROM orders WHERE id = ? FOR UPDATE", [$orderId]);
    if (!$order) {
        throw new RuntimeException('Order not found.');
    }

    if (($order['payment_status'] ?? '') === 'paid') {
        getDB()->commit();
        http_response_code(200);
        echo json_encode(['ok' => true, 'message' => 'Order already processed.']);
        exit;
    }

    $items = fetchAllRows(
        "SELECT oi.quantity, oi.product_id, oi.price, p.name
         FROM order_items oi
         JOIN products p ON p.id = oi.product_id
         WHERE oi.order_id = ?",
        [$orderId]
    );

    $stockStmt = getDB()->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
    $statusStmt = getDB()->prepare(
        "UPDATE products
         SET status = CASE
           WHEN stock = 0 THEN 'out_of_stock'
           WHEN stock <= 5 THEN 'low_stock'
           ELSE 'available'
         END
         WHERE id = ?"
    );
    foreach ($items as $item) {
        $stockStmt->execute([$item['quantity'], $item['product_id'], $item['quantity']]);
        if ($stockStmt->rowCount() !== 1) {
            throw new RuntimeException($item['name'] . ' does not have enough stock.');
        }
        $statusStmt->execute([$item['product_id']]);
    }

    getDB()->prepare(
        "UPDATE orders
         SET payment_status = 'paid',
             status = 'processing',
             checkout_session_id = COALESCE(NULLIF(checkout_session_id, ''), ?),
             paid_at = NOW()
         WHERE id = ?"
    )->execute([$checkoutSessionId, $orderId]);

    getDB()->prepare("DELETE FROM cart_items WHERE user_id = ?")->execute([$order['user_id']]);
    getDB()->commit();

    http_response_code(200);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    if (getDB()->inTransaction()) {
        getDB()->rollBack();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
