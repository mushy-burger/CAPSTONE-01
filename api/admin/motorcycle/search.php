<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/MotorcycleApiService.php';

header('Content-Type: application/json; charset=UTF-8');

requireRole('admin');

function respondAdminMotorcycle(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

$type = cleanMotorcycleLabel($_GET['type'] ?? '');
$brand = cleanMotorcycleLabel($_GET['brand'] ?? '');
$model = cleanMotorcycleLabel($_GET['model'] ?? '');

if ($type === '' || $brand === '' || $model === '') {
    respondAdminMotorcycle([
        'success' => false,
        'message' => 'Type, brand, and model are required.',
    ], 422);
}

$service = new MotorcycleApiService();
$result = $service->searchMotorcycle($type, $brand, $model);

respondAdminMotorcycle($result, $result['success'] ? 200 : 404);
