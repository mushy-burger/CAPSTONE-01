<?php
/**
 * Service + product catalog for the staff booking form.
 *
 * GET ?type_id=&cc= — returns the same compatible-service catalog the customer
 * booking page uses (services with labor fees and their selectable products,
 * filtered by motorcycle type and engine cc). Staff/admin only.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
requireAdminOrStaff();

header('Content-Type: application/json; charset=UTF-8');

$typeId = (int)($_GET['type_id'] ?? 0);
$cc     = (int)($_GET['cc'] ?? 0);

if ($typeId <= 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Missing motorcycle type.']);
    exit;
}

$catalog = getBookingServiceCatalog($typeId, $cc);

echo json_encode([
    'ok' => true,
    'services' => array_map(static fn(array $service) => [
        'id'        => (int)$service['id'],
        'name'      => (string)$service['name'],
        'labor_fee' => (float)($service['labor_fee'] ?? 0),
        'products'  => array_map(static fn(array $product) => [
            'id'    => (int)$product['id'],
            'name'  => (string)$product['name'],
            'brand' => (string)($product['brand'] ?? ''),
            'price' => (float)($product['price'] ?? 0),
            'stock' => (int)($product['stock'] ?? 0),
        ], $service['products'] ?? []),
    ], $catalog),
]);
