<?php
/**
 * Render a barcode / QR preview for the product form.
 *
 * GET ?code=<value>&symbology=qr|barcode|both
 *
 * Rendering stays on the server so the form preview and the printed label are
 * produced by the same code, and no barcode library is needed in the browser.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/ProductCodes.php';
requireAdminOrStaff();

header('Content-Type: application/json; charset=UTF-8');

$code = mtxNormalizeCode((string)($_GET['code'] ?? ''));
$symbology = in_array($_GET['symbology'] ?? '', ['qr', 'barcode', 'both'], true)
    ? $_GET['symbology']
    : 'both';

if ($code === '') {
    echo json_encode(['ok' => false, 'svg' => '', 'error' => 'No code supplied.']);
    exit;
}

// Reject anything unprintable before handing it to the renderers.
if (mb_strlen($code) > 64 || preg_match('/^[\x20-\x7E]+$/', $code) !== 1) {
    echo json_encode(['ok' => false, 'svg' => '', 'error' => 'That code cannot be rendered as a symbol.']);
    exit;
}

$svg = '';
if ($symbology === 'qr' || $symbology === 'both') {
    $svg .= mtxQrSvg($code, 120);
}
if ($symbology === 'barcode' || $symbology === 'both') {
    $svg .= mtxBarcodeSvg($code, 48, 1.4);
}

echo json_encode([
    'ok'   => $svg !== '',
    'code' => $code,
    'svg'  => $svg,
]);
