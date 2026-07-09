<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// One time slot accepts at most this many active (non-cancelled) bookings,
// regardless of which services they contain.
const BOOKING_MAX_PER_SLOT = 3;

/** Shop operating hours: slot value (24h HH:MM) => customer-facing label. */
function bookingTimeSlots(): array {
    return [
        '08:00' => '8:00 AM',
        '09:00' => '9:00 AM',
        '10:00' => '10:00 AM',
        '11:00' => '11:00 AM',
        '12:00' => '12:00 PM',
        '13:00' => '1:00 PM',
        '14:00' => '2:00 PM',
        '15:00' => '3:00 PM',
        '16:00' => '4:00 PM',
        '17:00' => '5:00 PM',
    ];
}

/**
 * Remaining capacity per slot for a date. Counts all non-cancelled bookings
 * on that date+time (never per service).
 *
 * @param int|null $excludeBookingId Excluded from counts (editing your own booking).
 * @return array<string, int> slot 'HH:MM' => remaining (0..BOOKING_MAX_PER_SLOT)
 */
function bookingSlotAvailability(string $date, ?int $excludeBookingId = null): array {
    $sql = "SELECT TIME_FORMAT(scheduled_time, '%H:%i') AS slot, COUNT(*) AS n
            FROM bookings
            WHERE scheduled_date = ? AND status != 'cancelled' AND scheduled_time IS NOT NULL";
    $params = [$date];
    if ($excludeBookingId) {
        $sql .= " AND id != ?";
        $params[] = $excludeBookingId;
    }
    $sql .= " GROUP BY slot";

    $counts = [];
    foreach (fetchAllRows($sql, $params) as $row) {
        $counts[$row['slot']] = (int)$row['n'];
    }

    $availability = [];
    foreach (array_keys(bookingTimeSlots()) as $slot) {
        $availability[$slot] = max(0, BOOKING_MAX_PER_SLOT - ($counts[$slot] ?? 0));
    }
    return $availability;
}
