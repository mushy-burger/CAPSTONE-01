<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/BookingSlots.php';

header('Content-Type: application/json; charset=UTF-8');

requireLogin();
$user = getCurrentUser();

function respondSlots(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

$date = trim($_GET['date'] ?? '');
$dt = DateTime::createFromFormat('Y-m-d', $date);
if (!$dt || $dt->format('Y-m-d') !== $date) {
    respondSlots(['ok' => false, 'message' => 'Invalid date.'], 422);
}
if ($date < date('Y-m-d')) {
    respondSlots(['ok' => false, 'message' => 'Date is in the past.'], 422);
}

// When editing, the customer's own booking should not count against the slot
$excludeBookingId = (int)($_GET['exclude_booking_id'] ?? 0);
if ($excludeBookingId > 0) {
    $own = fetchOne("SELECT id FROM bookings WHERE id = ? AND user_id = ?", [$excludeBookingId, (int)$user['id']]);
    if (!$own) {
        $excludeBookingId = 0;
    }
}

$availability = bookingSlotAvailability($date, $excludeBookingId ?: null);
$labels = bookingTimeSlots();

$slots = [];
foreach ($availability as $slot => $remaining) {
    $slots[] = [
        'value' => $slot,
        'label' => $labels[$slot],
        'remaining' => $remaining,
    ];
}

respondSlots(['ok' => true, 'max' => BOOKING_MAX_PER_SLOT, 'slots' => $slots]);
