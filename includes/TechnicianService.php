<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// Labor split: technician takes this share of booking_services.labor_fee, shop keeps the rest.
const TECH_LABOR_SHARE = 0.60;

function techGetQualifiedServiceIds(int $technicianId): array {
    $rows = fetchAllRows(
        "SELECT service_id FROM technician_services WHERE technician_id = ?",
        [$technicianId]
    );
    return array_map('intval', array_column($rows, 'service_id'));
}

function techSetQualifiedServices(int $technicianId, array $serviceIds): void {
    $serviceIds = array_values(array_unique(array_filter(array_map('intval', $serviceIds))));
    $db = getDB();
    $db->beginTransaction();
    try {
        $db->prepare("DELETE FROM technician_services WHERE technician_id = ?")->execute([$technicianId]);
        if ($serviceIds) {
            $stmt = $db->prepare("INSERT INTO technician_services (technician_id, service_id) VALUES (?, ?)");
            foreach ($serviceIds as $sid) {
                $stmt->execute([$technicianId, $sid]);
            }
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

/**
 * Qualified service-id sets for many technicians at once (for dropdown warning markers).
 *
 * @return array<int, array<int, true>> technician_id => set of service ids
 */
function techQualificationMap(): array {
    $map = [];
    foreach (fetchAllRows("SELECT technician_id, service_id FROM technician_services") as $row) {
        $map[(int)$row['technician_id']][(int)$row['service_id']] = true;
    }
    return $map;
}

/** Service ids attached to a booking (from the booking_services snapshot rows). */
function techBookingServiceIds(int $bookingId): array {
    $rows = fetchAllRows(
        "SELECT DISTINCT service_id FROM booking_services WHERE booking_id = ?",
        [$bookingId]
    );
    return array_map('intval', array_column($rows, 'service_id'));
}

/**
 * Pick the fairest technician for a booking.
 *
 * Priority: (1) ready, (2) qualified for ALL the booking's services,
 * (3) fewest active bookings, (4) lowest earnings today, (5) waited longest
 * since last assignment (never-assigned counts as longest).
 *
 * @return array{id:int,name:string}|null null when no technician is eligible
 */
function techAutoAssignCandidate(int $bookingId): ?array {
    $serviceIds = techBookingServiceIds($bookingId);
    $requiredCount = count($serviceIds);

    $params = [];
    $qualificationSql = '';
    if ($requiredCount > 0) {
        $placeholders = implode(',', array_fill(0, $requiredCount, '?'));
        $qualificationSql =
            "AND (SELECT COUNT(DISTINCT ts.service_id) FROM technician_services ts
                  WHERE ts.technician_id = u.id AND ts.service_id IN ($placeholders)) = ?";
        $params = array_merge($serviceIds, [$requiredCount]);
    }

    $row = fetchOne(
        "SELECT u.id, u.name,
            (SELECT COUNT(*) FROM bookings ab
              WHERE ab.technician_id = u.id AND ab.status IN ('confirmed','in_progress')) AS active_jobs,
            COALESCE((SELECT SUM(bs.labor_fee) FROM bookings cb
              JOIN booking_services bs ON bs.booking_id = cb.id
              WHERE cb.technician_id = u.id AND cb.status = 'completed'
                AND cb.completed_at IS NOT NULL AND DATE(cb.completed_at) = CURDATE()), 0) AS labor_today,
            (SELECT MAX(ab2.assigned_at) FROM bookings ab2 WHERE ab2.technician_id = u.id) AS last_assigned_at
         FROM users u
         WHERE u.role = 'technician' AND u.is_active = 1 AND u.availability_status = 'ready'
           $qualificationSql
         ORDER BY active_jobs ASC,
                  labor_today ASC,
                  (last_assigned_at IS NULL) DESC,
                  last_assigned_at ASC,
                  u.id ASC
         LIMIT 1",
        $params
    );

    return $row ? ['id' => (int)$row['id'], 'name' => $row['name']] : null;
}

/** Technician's share of labor for their completed bookings, filtered by an optional completed_at window. */
function techEarningsBetween(int $technicianId, ?string $fromDate, ?string $toDate): float {
    $sql = "SELECT COALESCE(SUM(bs.labor_fee), 0) AS labor
            FROM bookings b
            JOIN booking_services bs ON bs.booking_id = b.id
            WHERE b.technician_id = ? AND b.status = 'completed'";
    $params = [$technicianId];
    if ($fromDate !== null) {
        $sql .= " AND b.completed_at IS NOT NULL AND DATE(b.completed_at) >= ?";
        $params[] = $fromDate;
    }
    if ($toDate !== null) {
        $sql .= " AND b.completed_at IS NOT NULL AND DATE(b.completed_at) <= ?";
        $params[] = $toDate;
    }
    $labor = (float)(fetchOne($sql, $params)['labor'] ?? 0);
    return $labor * TECH_LABOR_SHARE;
}

/** All-time technician earnings per service: [['service_name' =>, 'jobs' =>, 'earnings' =>], ...] */
function techEarningsPerService(int $technicianId): array {
    $rows = fetchAllRows(
        "SELECT bs.service_name, COUNT(*) AS jobs, COALESCE(SUM(bs.labor_fee), 0) AS labor
         FROM bookings b
         JOIN booking_services bs ON bs.booking_id = b.id
         WHERE b.technician_id = ? AND b.status = 'completed'
         GROUP BY bs.service_name
         ORDER BY labor DESC",
        [$technicianId]
    );
    return array_map(fn($r) => [
        'service_name' => $r['service_name'],
        'jobs' => (int)$r['jobs'],
        'earnings' => (float)$r['labor'] * TECH_LABOR_SHARE,
    ], $rows);
}
