<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// One time slot accepts at most this many active (non-cancelled) bookings,
// regardless of which services they contain.
const BOOKING_MAX_PER_SLOT = 3;
const BOOKING_SAME_DAY_LEAD_MINUTES = 30;

/**
 * Shop clock for booking rules. MySQL NOW() is application authority here;
 * scheduled booking dates and times are stored in this same local clock.
 */
function bookingServerNow(): DateTimeImmutable {
    $row = fetchOne("SELECT NOW() AS now_value");
    $timezone = new DateTimeZone('Asia/Manila');

    if (!empty($row['now_value'])) {
        try {
            return new DateTimeImmutable((string)$row['now_value'], $timezone);
        } catch (Throwable $e) {
            // Fall through to the configured shop timezone if database time is unavailable.
        }
    }

    return new DateTimeImmutable('now', $timezone);
}

/**
 * Time-rule state for a proposed slot. Future dates remain time-eligible;
 * same-day slots need at least BOOKING_SAME_DAY_LEAD_MINUTES notice.
 *
 * @return 'available'|'passed'|'too_soon'|'unavailable'
 */
function bookingSlotTimeState(string $date, string $time, ?DateTimeImmutable $now = null): string {
    $now ??= bookingServerNow();
    $today = $now->format('Y-m-d');

    if ($date < $today) {
        return 'unavailable';
    }
    if ($date > $today) {
        return 'available';
    }

    $slot = DateTimeImmutable::createFromFormat(
        '!Y-m-d H:i',
        $date . ' ' . $time,
        $now->getTimezone()
    );
    if (!$slot || $slot->format('Y-m-d H:i') !== $date . ' ' . $time) {
        return 'unavailable';
    }
    if ($slot < $now) {
        return 'passed';
    }

    return $slot >= $now->modify('+' . BOOKING_SAME_DAY_LEAD_MINUTES . ' minutes')
        ? 'available'
        : 'too_soon';
}

function bookingSlotMeetsLeadTime(string $date, string $time, ?DateTimeImmutable $now = null): bool {
    return bookingSlotTimeState($date, $time, $now) === 'available';
}

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
