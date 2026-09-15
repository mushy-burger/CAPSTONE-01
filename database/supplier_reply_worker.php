<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once __DIR__ . '/../includes/PurchaseOrderService.php';

try {
    $result = poProcessSupplierReplies();
    echo "Supplier replies checked: {$result['seen']} matched, {$result['processed']} processed, {$result['skipped']} skipped.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Supplier reply check failed: {$e->getMessage()}\n");
    exit(1);
}
