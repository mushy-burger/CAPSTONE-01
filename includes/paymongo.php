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

function paymongoCreateCheckoutSession(array $order, array $items, array $customer): array {
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
                'payment_method_types' => ['card', 'gcash', 'maya', 'qrph'],
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
