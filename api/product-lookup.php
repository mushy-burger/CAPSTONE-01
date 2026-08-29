<?php
/**
 * Resolve a scanned barcode / QR code to a MotoTrack product.
 *
 * GET ?code=<scanned value> — returns the product the code is attached to.
 * Staff/admin only. This is the lookup a scanner drives at the counter:
 * code -> product_codes -> products.id -> product.
 *
 * GET ?check=<code>[&product_id=N] — validates a code before saving it,
 * so the product form can warn about a duplicate without a round trip
 * through the UNIQUE constraint.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/ProductCodes.php';
requireAdminOrStaff();

header('Content-Type: application/json; charset=UTF-8');

// --- Duplicate check for the product form ---------------------------------
if (isset($_GET['check'])) {
    $code = mtxNormalizeCode((string)$_GET['check']);
    $productId = (int)($_GET['product_id'] ?? 0);

    if ($code === '') {
        echo json_encode(['ok' => true, 'available' => false, 'error' => '']);
        exit;
    }

    $error = mtxValidateCode($code, $productId);
    echo json_encode([
        'ok'        => true,
        'code'      => $code,
        'available' => $error === '',
        'error'     => $error,
    ]);
    exit;
}

// --- Scanner lookup -------------------------------------------------------
$code = mtxNormalizeCode((string)($_GET['code'] ?? ''));
if ($code === '') {
    echo json_encode(['ok' => false, 'found' => false, 'error' => 'No code supplied.']);
    exit;
}

$product = mtxFindProductByCode($code);
if (!$product) {
    echo json_encode([
        'ok'    => true,
        'found' => false,
        'code'  => $code,
        'error' => 'No product is linked to code ' . $code . '.',
    ]);
    exit;
}

echo json_encode([
    'ok'      => true,
    'found'   => true,
    'code'    => $code,
    'product' => [
        'id'        => (int)$product['id'],
        'name'      => $product['name'],
        'brand'     => $product['brand'] ?: '',
        'category'  => $product['category_name'] ?: 'Uncategorized',
        'price'     => (float)$product['price'],
        'stock'     => (int)$product['stock'],
        'status'    => $product['status'],
        'code_type' => $product['code_type'],
        'sellable'  => $product['status'] !== 'out_of_stock' && (int)$product['stock'] > 0,
    ],
]);
