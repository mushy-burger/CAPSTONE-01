<?php
/** Focused, rollback-only proof of paid service-part availability. */
if (PHP_SAPI !== 'cli') exit("CLI only\n");
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/PartsReservationService.php';

$db = getDB();
$productId = (int)($argv[1] ?? 59);
$bookingId = (int)($argv[2] ?? $db->query('SELECT id FROM bookings ORDER BY id LIMIT 1')->fetchColumn());
$serviceId = (int)($argv[3] ?? 33);
$product = fetchOne('SELECT id, name, stock FROM products WHERE id = ?', [$productId]);
if (!$product || !$bookingId) exit("Fixture missing\n");
$db->beginTransaction();
try {
    $db->prepare('DELETE FROM parts_reservations WHERE booking_id = ? AND product_id = ?')->execute([$bookingId, $productId]);
    $db->prepare('UPDATE products SET stock = 1 WHERE id = ?')->execute([$productId]);
    // Simulate Account A's successful payment hold.
    $db->prepare("INSERT INTO parts_reservations (booking_id, product_id, quantity, status) VALUES (?, ?, 1, 'held')")
       ->execute([$bookingId, $productId]);
    $rows = getServiceProducts($serviceId, 150);
    $row = array_values(array_filter($rows, static fn(array $r): bool => (int)$r['id'] === $productId))[0] ?? null;
    if (!$row || (int)$row['available_stock'] !== 0) throw new RuntimeException('API/query did not return available_stock=0.');
    $catalog = [['id' => $serviceId, 'name' => 'Change Oil', 'labor_fee' => 0, 'products' => $rows]];
    $selection = calculateBookingSelection($catalog, [$serviceId], [$serviceId => $productId]);
    if (!$selection['errors'] || $selection['products']) throw new RuntimeException('Direct service selection was not rejected.');
    echo "PASS paid hold -> service query available=0 -> selection rejected ({$product['name']})\n";
    $db->rollBack();
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    fwrite(STDERR, "FAIL: {$e->getMessage()}\n");
    exit(1);
}
