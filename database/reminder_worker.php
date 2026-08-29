<?php
/**
 * Appointment reminder worker.
 *
 * Run periodically by Windows Task Scheduler (see database/setup_reminder_task.md):
 *
 *   Windows Task Scheduler
 *          |
 *   php database/reminder_worker.php
 *          |
 *   find confirmed appointments inside the reminder window
 *          |
 *   NotificationService -> SMS + email
 *
 * Reminders are driven purely by the SCHEDULED date/time — never by service
 * duration — and are a distinct event type from confirmations and completions.
 *
 * Idempotency: notification_log has a unique key on
 * (booking_id, notification_type, channel), and notifyDispatch() checks it
 * before sending, so running this worker every 15 minutes (or twice at once)
 * cannot send a customer two reminders for the same appointment.
 *
 * Usage:
 *   php database/reminder_worker.php              send due reminders
 *   php database/reminder_worker.php --dry        list what would be sent
 *   php database/reminder_worker.php --hours=48   widen the look-ahead window
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This worker is command-line only.\n");
}

require_once __DIR__ . '/../includes/NotificationService.php';

// How far ahead to remind. Default: appointments within the next 24 hours.
$lookAheadHours = 24;
$dryRun = false;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry' || $arg === '--dry-run') {
        $dryRun = true;
    } elseif (preg_match('/^--hours=(\d+)$/', $arg, $m)) {
        $lookAheadHours = max(1, min(168, (int)$m[1]));
    }
}

// Timestamps come from the database clock, which is the app's authority
// (PHP's default timezone differs from MySQL's on this server).
$now = fetchOne("SELECT NOW() AS now")['now'] ?? '';

echo "MotoTrack appointment reminders\n";
echo "-------------------------------\n";
echo "Server time    : {$now}\n";
echo "Look-ahead     : {$lookAheadHours} hour(s)\n";
echo "Mode           : " . ($dryRun ? 'DRY RUN (nothing sent)' : 'SEND') . "\n\n";

/*
 * Due = confirmed, still upcoming, starting within the window, and no reminder
 * already logged for it. The NOT EXISTS clause means an interrupted run simply
 * resumes rather than re-sending.
 */
$due = fetchAllRows(
    "SELECT b.id,
            b.scheduled_date,
            b.scheduled_time,
            u.name  AS customer_name,
            u.phone AS customer_phone,
            u.email AS customer_email
     FROM bookings b
     JOIN users u ON u.id = b.user_id
     WHERE b.status = 'confirmed'
       AND CONCAT(b.scheduled_date, ' ', COALESCE(b.scheduled_time, '09:00:00')) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL ? HOUR)
       AND NOT EXISTS (
             SELECT 1 FROM notification_log nl
             WHERE nl.booking_id = b.id
               AND nl.notification_type = 'appointment_reminder'
               AND nl.status IN ('sent','skipped')
       )
     ORDER BY b.scheduled_date, b.scheduled_time",
    [$lookAheadHours]
);

if (!$due) {
    echo "No reminders are due.\n";
    exit(0);
}

echo count($due) . " reminder(s) due:\n\n";

$sent = 0;
$failed = 0;

foreach ($due as $booking) {
    $when = $booking['scheduled_date'] . ' ' . ($booking['scheduled_time'] ?? '09:00:00');
    printf("  #%-5d %-22s %s\n", $booking['id'], mb_strimwidth($booking['customer_name'], 0, 20, '…'), $when);

    if ($dryRun) {
        echo "         (dry run — not sent)\n";
        continue;
    }

    // A single failure must not stop the batch.
    try {
        $result = notifyAppointmentReminder((int)$booking['id']);
        printf("         SMS: %-9s Email: %s\n", $result['sms'], $result['email']);
        if ($result['sms'] === 'sent' || $result['email'] === 'sent') {
            $sent++;
        } else {
            $failed++;
        }
    } catch (Throwable $e) {
        $failed++;
        echo "         ERROR: " . mb_substr($e->getMessage(), 0, 120) . "\n";
        smsLogLine('Reminder worker failed for booking ' . (int)$booking['id'] . ': ' . $e->getMessage());
    }
}

echo "\nDone. Delivered: {$sent}   Problems: {$failed}\n";
exit(0);
