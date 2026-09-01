<?php
/**
 * Parts reservation service.
 *
 * Lifecycle:
 *  Booking confirmed  → partsReserveForBooking()  → status = 'held'
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
 * Reserve parts for a confirmed booking.
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

    $stmt = getDB()->prepare(
        "INSERT INTO parts_reservations (booking_id, product_id, quantity, status)
         VALUES (?, ?, ?, 'held')
         ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), status = 'held'"
    );
    foreach ($reservations as $productId => $qty) {
        $stmt->execute([$bookingId, $productId, $qty]);
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
    $reservations = fetchAllRows(
        "SELECT product_id, quantity FROM parts_reservations
         WHERE booking_id = ? AND status = 'held'",
        [$bookingId]
    );

    if (!$reservations) {
        return 0;
    }

    $count = 0;
    foreach ($reservations as $r) {
        $pid = (int)$r['product_id'];
        $qty = max(1, (int)ceil((float)$r['quantity']));

        getDB()->prepare(
            "UPDATE products
             SET stock = GREATEST(0, stock - ?),
                 status = CASE
                   WHEN GREATEST(0, stock - ?) = 0 THEN 'out_of_stock'
                   WHEN GREATEST(0, stock - ?) <= min_stock THEN 'low_stock'
                   ELSE 'available'
                 END
             WHERE id = ?"
        )->execute([$qty, $qty, $qty, $pid]);

        // Trigger PO auto-generation check (silently)
        try {
            require_once __DIR__ . '/PurchaseOrderService.php';
            poCheckAndGenerateForProduct($pid);
        } catch (Throwable $e) {
            // Never let PO generation fail a job completion
        }

        $count++;
    }

    // Mark rows consumed
    getDB()->prepare(
        "UPDATE parts_reservations SET status = 'consumed' WHERE booking_id = ? AND status = 'held'"
    )->execute([$bookingId]);

    return $count;
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
