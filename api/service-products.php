<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

requireLogin();
ensureMultiServiceBookingSchema();

$user = getCurrentUser();
$serviceId = (int)($_GET['service_id'] ?? 0);
$vehicleId = (int)($_GET['vehicle_id'] ?? 0);

if (!$user || $serviceId <= 0 || $vehicleId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing service or motorcycle.']);
    exit;
}

$vehicle = fetchOne(
    "SELECT id, type_id, cc
     FROM customer_vehicles
     WHERE id = ? AND user_id = ?",
    [$vehicleId, $user['id']]
);

if (!$vehicle) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Motorcycle not found.']);
    exit;
}

$catalog = getBookingServiceCatalog((int)$vehicle['type_id'], (int)$vehicle['cc']);
$service = null;
foreach ($catalog as $catalogService) {
    if ((int)$catalogService['id'] === $serviceId) {
        $service = $catalogService;
        break;
    }
}

if (!$service) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Service is not compatible with this motorcycle.']);
    exit;
}

$products = array_map(static function (array $product): array {
    $image = trim((string)($product['image'] ?? ''));
    $imageUrl = '';
    if ($image !== '' && file_exists(__DIR__ . '/../uploads/' . $image)) {
        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $image)));
        $imageUrl = baseUrl('uploads/' . $encodedPath);
    }

    return [
        'id' => (int)$product['id'],
        'name' => (string)$product['name'],
        'brand' => (string)($product['brand'] ?? ''),
        'description' => (string)($product['description'] ?? ''),
        'price' => (float)($product['price'] ?? 0),
        'stock' => (int)($product['stock'] ?? 0),
        'category_name' => (string)($product['category_name'] ?? ''),
        'image_url' => $imageUrl,
    ];
}, $service['products'] ?? []);

echo json_encode([
    'success' => true,
    'service' => [
        'id' => (int)$service['id'],
        'name' => (string)$service['name'],
        'labor_fee' => (float)($service['labor_fee'] ?? 0),
        'category_name' => (string)($service['required_category'] ?? ''),
    ],
    'products' => $products,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
