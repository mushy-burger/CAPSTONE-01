<?php
/**
 * Parts reservation service.
 *
 * Lifecycle:
 *  Deposit paid       → partsReserveForBooking()  → status = 'held'
 *  Booking cancelled  → partsReleaseForBooking()  → status = 'released'
 *  Job completed      → partsConsumeForBooking()  → deducts real stock, status = 'consumed'
 *
 * Design rules:
 *  - Reservations are soft holds — they do NOT decrement stock immediately.
 *    Stock is only physically deducted when the job is complete.
 *  - If no service_material_rules exist for the booking's services, we fall
 *    back to booking_products rows (the snapshot captured at booking time).
 *  - All three actions are idempotent: calling twice is safe.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

/**
 * Reserve parts for a successfully paid booking.
 *
 * Reads service_material_rules filtered by the vehicle's CC and the booking's
 * services. Falls back to booking_products if no rules match.
 *
 * @return int  Number of reservation rows created/updated.
 */
function partsReserveForBooking(int $bookingId): int {
    // Get vehicle CC for rule filtering
    $booking = fetchOne(
        "SELECT b.vehicle_id, cv.cc
         FROM bookings b
         LEFT JOIN customer_vehicles cv ON cv.id = b.vehicle_id
         WHERE b.id = ?",
        [$bookingId]
    );
    $cc = $booking ? (int)($booking['cc'] ?? 0) : 0;

    // Service IDs on this booking
    $serviceIds = array_map('intval', array_column(
        fetchAllRows("SELECT DISTINCT service_id FROM booking_services WHERE booking_id = ?", [$bookingId]),
        'service_id'
    ));

    $reservations = [];

    if ($serviceIds && $cc > 0) {
        // Pull material rules for these services + this CC
        $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
        $rules = fetchAllRows(
            "SELECT product_id, SUM(quantity) AS qty
             FROM service_material_rules
             WHERE service_id IN ($placeholders)
               AND product_id IS NOT NULL
               AND cc_min <= ? AND cc_max >= ?
             GROUP BY product_id",
            array_merge($serviceIds, [$cc, $cc])
        );
        foreach ($rules as $rule) {
            $reservations[(int)$rule['product_id']] = max(1, (int)ceil((float)$rule['qty']));
        }
    }

    // Fallback to booking_products snapshot
    if (!$reservations) {
        $bps = fetchAllRows(
            "SELECT product_id, COUNT(*) AS qty FROM booking_products WHERE booking_id = ? GROUP BY product_id",
            [$bookingId]
        );
        foreach ($bps as $bp) {
            $reservations[(int)$bp['product_id']] = (int)$bp['qty'];
        }
    }

    if (!$reservations) {
        return 0;
    }

    $db = getDB();
    $ownsTransaction = !$db->inTransaction();
    if ($ownsTransaction) $db->beginTransaction();
    try {
        ksort($reservations, SORT_NUMERIC);
        foreach ($reservations as $productId => $qty) {
            $productStmt = $db->prepare("SELECT id, name, stock FROM products WHERE id = ? AND status != 'archived' FOR UPDATE");
            $productStmt->execute([$productId]);
            $product = $productStmt->fetch();
            if (!$product) throw new RuntimeException('Reserved product no longer exists.');
            $heldStmt = $db->prepare("SELECT COALESCE(SUM(quantity), 0) FROM parts_reservations WHERE product_id = ? AND status = 'held' AND booking_id <> ?");
            $heldStmt->execute([$productId, $bookingId]);
            $available = max(0, (int)$product['stock'] - (int)ceil((float)$heldStmt->fetchColumn()));
            if ((int)$qty > $available) throw new RuntimeException($product['name'] . ' has only ' . $available . ' available for reservation.');
            $db->prepare(
                "INSERT INTO parts_reservations (booking_id, product_id, quantity, status)
                 VALUES (?, ?, ?, 'held')
                 ON DUPLICATE KEY UPDATE quantity = IF(status = 'held', VALUES(quantity), quantity)"
            )->execute([$bookingId, $productId, $qty]);
        }
        if ($ownsTransaction) $db->commit();
    } catch (Throwable $e) {
        if ($ownsTransaction && $db->inTransaction()) $db->rollBack();
        throw $e;
    }
    return count($reservations);
}

/**
 * Release (un-hold) parts for a cancelled booking.
 *
 * @return int  Rows updated.
 */
function partsReleaseForBooking(int $bookingId): int {
    $stmt = getDB()->prepare(
        "UPDATE parts_reservations SET status = 'released'
         WHERE booking_id = ? AND status = 'held'"
    );
    $stmt->execute([$bookingId]);
    return $stmt->rowCount();
}

/**
 * Consume reserved parts on job completion: deduct stock and mark rows consumed.
 *
 * Also triggers a PO check per product so low-stock alerts fire immediately.
 *
 * @return int  Number of products whose stock was decremented.
 */
function partsConsumeForBooking(int $bookingId): int {
    $db = getDB();
    $ownsTransaction = !$db->inTransaction();
    if ($ownsTransaction) {
        $db->beginTransaction();
    }

    try {
        $stmt = $db->prepare(
            "SELECT product_id, quantity FROM parts_reservations
             WHERE booking_id = ? AND status = 'held'
             FOR UPDATE"
        );
        $stmt->execute([$bookingId]);
        $reservations = $stmt->fetchAll();

        if (!$reservations) {
            if ($ownsTransaction) {
                $db->rollBack();
            }
            return 0;
        }

        $productIds = [];
        foreach ($reservations as $reservation) {
            $productId = (int)$reservation['product_id'];
            $quantity = max(1, (int)ceil((float)$reservation['quantity']));
            $stockStmt = $db->prepare(
                "UPDATE products
                 SET stock = stock - ?,
                     status = CASE
                       WHEN stock = 0 THEN 'out_of_stock'
                       WHEN stock <= min_stock THEN 'low_stock'
                       ELSE 'available'
                     END
                 WHERE id = ? AND stock >= ?"
            );
            $stockStmt->execute([$quantity, $productId, $quantity]);
            if ($stockStmt->rowCount() !== 1) throw new RuntimeException('Insufficient physical stock for a reserved part.');
            $productIds[] = $productId;
        }

        $stmt = $db->prepare(
            "UPDATE parts_reservations
             SET status = 'consumed'
             WHERE booking_id = ? AND status = 'held'"
        );
        $stmt->execute([$bookingId]);

        if ($ownsTransaction) {
            $db->commit();
        }
    } catch (Throwable $e) {
        if ($ownsTransaction && $db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }

    // PO generation is intentionally non-fatal and runs after stock is durable.
    require_once __DIR__ . '/PurchaseOrderService.php';
    foreach (array_unique($productIds) as $productId) {
        try {
            poCheckAndGenerateForProduct($productId);
        } catch (Throwable $e) {
            error_log("PO generation failed for product {$productId}: " . $e->getMessage());
        }
    }

    return count($reservations);
}

/**
 * Return all reservations for a booking with product details.
 */
function partsGetForBooking(int $bookingId): array {
    return fetchAllRows(
        "SELECT pr.*, p.name AS product_name, p.stock AS current_stock
         FROM parts_reservations pr
         JOIN products p ON p.id = pr.product_id
         WHERE pr.booking_id = ?
         ORDER BY pr.status, p.name",
        [$bookingId]
    );
}
