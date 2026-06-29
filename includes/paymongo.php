<?php
require_once __DIR__ . '/functions.php';

function paymongoConfig(): array {
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config/paymongo.php';
    }
    return $config;
}

function paymongoIsConfigured(): bool {
    $config = paymongoConfig();
    return !empty($config['secret_key']) && !empty($config['public_key']);
}

function appUrl(string $path = ''): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return rtrim($scheme . '://' . $host . baseUrl($path), '/');
}

function paymongoApiRequest(string $method, string $path, ?array $payload = null): array {
    $config = paymongoConfig();
    if (empty($config['secret_key'])) {
        throw new RuntimeException('PayMongo secret key is missing. Add PAYMONGO_SECRET_KEY to your local .env file.');
    }

    $ch = curl_init('https://api.paymongo.com' . $path);
    $headers = [
        'Accept: application/json',
        'Authorization: Basic ' . base64_encode($config['secret_key'] . ':'),
    ];

    if ($payload !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES));
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
    ]);

    $raw = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        throw new RuntimeException('PayMongo request failed: ' . $curlError);
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('PayMongo returned an invalid response.');
    }

    if ($httpCode >= 400) {
        $detail = $decoded['errors'][0]['detail'] ?? 'PayMongo request failed.';
        throw new RuntimeException($detail);
    }

    return $decoded;
}

function paymongoNormalizePaymentMethod(string $method): string {
    $method = strtolower(trim($method));
    return match ($method) {
        'maya' => 'paymaya',
        'paymaya' => 'paymaya',
        'gcash' => 'gcash',
        'card' => 'card',
        'qrph' => 'qrph',
        default => 'paymongo',
    };
}

function paymongoPaymentMethodTypes(string $method = 'paymongo'): array {
    $method = paymongoNormalizePaymentMethod($method);
    if ($method === 'paymongo') {
        return ['card', 'gcash', 'paymaya', 'qrph'];
    }

    return [$method];
}

function paymongoCreateCheckoutSession(array $order, array $items, array $customer, string $paymentMethod = 'paymongo'): array {
    $lineItems = [];
    foreach ($items as $item) {
        $descriptionParts = array_filter([
            $item['brand'] ?? '',
            $item['description'] ?? '',
        ]);

        $lineItems[] = [
            'currency' => 'PHP',
            'amount' => (int)round(((float)$item['price']) * 100),
            'name' => $item['name'],
            'quantity' => (int)$item['quantity'],
            'description' => implode(' - ', $descriptionParts),
        ];
    }

    $payload = [
        'data' => [
            'attributes' => [
                'billing' => [
                    'name' => $customer['name'] ?? '',
                    'email' => $customer['email'] ?? '',
                    'phone' => $customer['phone'] ?? '',
                ],
                'customer_email' => $customer['email'] ?? '',
                'description' => 'MotoTrack order #' . (int)$order['id'],
                'line_items' => $lineItems,
                'metadata' => [
                    'order_id' => (string)(int)$order['id'],
                    'user_id' => (string)(int)$order['user_id'],
                ],
                'payment_method_types' => paymongoPaymentMethodTypes($paymentMethod),
                'reference_number' => 'MT-' . (int)$order['id'],
                'send_email_receipt' => true,
                'show_description' => true,
                'show_line_items' => true,
                'statement_descriptor' => 'MotoTrack',
                'success_url' => appUrl('payment-success.php?order_id=' . (int)$order['id']),
                'cancel_url' => appUrl('payment-cancelled.php?order_id=' . (int)$order['id']),
            ],
        ],
    ];

    return paymongoApiRequest('POST', '/v2/checkout_sessions', $payload);
}

function paymongoRetrieveCheckoutSession(string $checkoutSessionId): array {
    $checkoutSessionId = trim($checkoutSessionId);
    if ($checkoutSessionId === '') {
        throw new RuntimeException('Missing PayMongo checkout session id.');
    }

    return paymongoApiRequest('GET', '/v1/checkout_sessions/' . rawurlencode($checkoutSessionId));
}

function paymongoCheckoutSessionIsPaid(array $session): bool {
    $attributes = $session['data']['attributes'] ?? $session['attributes'] ?? [];
    $statusValues = [
        strtolower((string)($attributes['status'] ?? '')),
        strtolower((string)($attributes['payment_status'] ?? '')),
        strtolower((string)($attributes['payment_intent']['attributes']['status'] ?? '')),
    ];

    if (in_array('paid', $statusValues, true)) {
        return true;
    }

    if (!empty($attributes['paid_at'])) {
        return true;
    }

    $payments = $attributes['payments'] ?? [];
    if (is_array($payments)) {
        foreach ($payments as $payment) {
            $paymentAttributes = $payment['attributes'] ?? [];
            if (strtolower((string)($paymentAttributes['status'] ?? '')) === 'paid') {
                return true;
            }
        }
    }

    return false;
}

function paymongoCheckoutSessionOrderId(array $session): int {
    $attributes = $session['data']['attributes'] ?? $session['attributes'] ?? [];
    return (int)($attributes['metadata']['order_id'] ?? 0);
}

function fulfillPaidOrder(int $orderId, string $checkoutSessionId = ''): void {
    $db = getDB();
    $db->beginTransaction();

    try {
        $order = fetchOne("SELECT * FROM orders WHERE id = ? FOR UPDATE", [$orderId]);
        if (!$order) {
            throw new RuntimeException('Order not found.');
        }

        if (($order['payment_status'] ?? '') === 'paid') {
            $db->commit();
            return;
        }

        $items = fetchAllRows(
            "SELECT oi.quantity, oi.product_id, oi.cart_item_id, p.name
             FROM order_items oi
             JOIN products p ON p.id = oi.product_id
             WHERE oi.order_id = ?",
            [$orderId]
        );

        $stockStmt = $db->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
        $statusStmt = $db->prepare(
            "UPDATE products
             SET status = CASE
               WHEN stock = 0 THEN 'out_of_stock'
               WHEN stock <= 5 THEN 'low_stock'
               ELSE 'available'
             END
             WHERE id = ?"
        );

        foreach ($items as $item) {
            $stockStmt->execute([(int)$item['quantity'], (int)$item['product_id'], (int)$item['quantity']]);
            if ($stockStmt->rowCount() !== 1) {
                throw new RuntimeException($item['name'] . ' does not have enough stock.');
            }
            $statusStmt->execute([(int)$item['product_id']]);
        }

        $db->prepare(
            "UPDATE orders
             SET payment_status = 'paid',
                 status = 'completed',
                 checkout_session_id = COALESCE(NULLIF(checkout_session_id, ''), ?),
                 paid_at = NOW()
             WHERE id = ?"
        )->execute([$checkoutSessionId, $orderId]);

        $cartItemIds = array_values(array_unique(array_filter(array_map(
            static fn(array $item): int => (int)($item['cart_item_id'] ?? 0),
            $items
        ))));

        if ($cartItemIds) {
            $placeholders = implode(',', array_fill(0, count($cartItemIds), '?'));
            $params = array_merge([(int)$order['user_id']], $cartItemIds);
            $db->prepare("DELETE FROM cart_items WHERE user_id = ? AND id IN ($placeholders)")->execute($params);
        }

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function paymongoExtractSignature(string $header, string $key): string {
    foreach (explode(',', $header) as $part) {
        [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
        if (trim($k) === $key) {
            return trim($v);
        }
    }
    return '';
}

function verifyPaymongoWebhook(string $payload, string $signatureHeader): bool {
    $config = paymongoConfig();
    $secret = $config['webhook_secret'] ?? '';
    if ($secret === '' || $signatureHeader === '') {
        return false;
    }

    $timestamp = paymongoExtractSignature($signatureHeader, 't');
    $signature = paymongoExtractSignature($signatureHeader, 'te');
    if ($timestamp === '' || $signature === '') {
        return false;
    }

    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    return hash_equals($expected, $signature);
}
