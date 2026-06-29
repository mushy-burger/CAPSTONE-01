<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/paymongo.php';
require_once __DIR__ . '/includes/mail.php';

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
    $order = fetchOne("SELECT * FROM orders WHERE id = ?", [$orderId]);
    $wasPaid = ($order['payment_status'] ?? '') === 'paid';
    $items = fetchAllRows(
        "SELECT oi.quantity, oi.product_id, oi.price, p.name
         FROM order_items oi
         JOIN products p ON p.id = oi.product_id
         WHERE oi.order_id = ?",
        [$orderId]
    );

    fulfillPaidOrder($orderId, $checkoutSessionId);

    if (!$wasPaid && $order) {
        $customer = fetchOne("SELECT name, email FROM users WHERE id = ?", [$order['user_id']]);
        if ($customer && $customer['email']) {
            try {
                sendOrderEmail(
                    $customer['email'],
                    $customer['name'],
                    $orderId,
                    (float)$order['total'],
                    $items,
                    (string)($order['payment_method'] ?? 'paymongo')
                );
            } catch (Throwable) {
                /* Non-fatal: email failure should not break webhook confirmation. */
            }
        }
    }

    http_response_code(200);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
