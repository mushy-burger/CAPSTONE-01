<?php
/**
 * One-off: give every product that has no identification code a MotoTrack one.
 *
 * Products created before the barcode/QR feature have no code, so they cannot
 * be scanned at the counter. This assigns the standard MT-P-<id> code to each
 * of them and leaves products that already have a code untouched.
 *
 * Run from the command line:
 *   php database/backfill_product_codes.php          (preview only)
 *   php database/backfill_product_codes.php --apply  (write the codes)
 */
require_once __DIR__ . '/../includes/ProductCodes.php';

$apply = in_array('--apply', $argv ?? [], true);

$pending = fetchAllRows(
    "SELECT p.id, p.name
     FROM products p
     LEFT JOIN product_codes pc ON pc.product_id = p.id
     WHERE pc.id IS NULL
     ORDER BY p.id"
);

if (!$pending) {
    echo "Every product already has an identification code. Nothing to do.\n";
    exit;
}

echo ($apply ? "Assigning" : "Would assign") . " codes to " . count($pending) . " product(s):\n\n";

$done = 0;
foreach ($pending as $product) {
    $productId = (int)$product['id'];
    $code = mtxProductCode($productId);
    printf("  %-34s -> %s\n", mb_strimwidth($product['name'], 0, 32, '…'), $code);

    if ($apply) {
        mtxAssignGeneratedCode($productId, 'both');
        $done++;
    }
}

echo "\n";
if ($apply) {
    echo "Done. $done code(s) assigned.\n";
    echo "Print labels from Products -> the row menu -> Print label.\n";
} else {
    echo "Preview only. Re-run with --apply to write these codes.\n";
}
