<?php
/**
 * Customer notification layer.
 *
 * Booking and technician code calls the notify* functions here and nothing
 * else — it never touches Semaphore or mail() directly, so the SMS provider
 * can be replaced by editing SmsProvider.php alone.
 *
 *   Staff confirms / technician completes
 *            |
 *     NotificationService
 *        |          |
 *   SmsProvider   mail()          -> every attempt written to notification_log
 *   (Semaphore)   (existing)
 *
 * Two rules hold throughout:
 *  - Delivery NEVER affects booking state. Every send is wrapped so a provider
 *    outage cannot throw into a job transaction.
 *  - Delivery is idempotent per (booking, event, channel), enforced by a unique
 *    key, so double-clicking "Complete Job" cannot send a second SMS.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/SmsProvider.php';

const NOTIFY_APPOINTMENT_CONFIRMED = 'appointment_confirmed';
const NOTIFY_APPOINTMENT_REMINDER  = 'appointment_reminder';
const NOTIFY_JOB_STARTED           = 'job_started';
const NOTIFY_JOB_COMPLETED         = 'job_completed';
const NOTIFY_RATING_REQUEST        = 'rating_request';

/**
 * Everything the message templates need for one booking.
 *
 * Customer, technician and service names all come from the database — no
 * message content is hard-coded.
 */
function notifyLoadBookingContext(int $bookingId): ?array {
    $booking = fetchOne(
        "SELECT b.*,
                u.name  AS customer_name,
                u.email AS customer_email,
                u.phone AS customer_phone,
                t.name  AS technician_name
         FROM bookings b
         JOIN users u ON u.id = b.user_id
         LEFT JOIN users t ON t.id = b.technician_id
         WHERE b.id = ?",
        [$bookingId]
    );

    if (!$booking) {
        return null;
    }

    $services = fetchAllRows(
        "SELECT service_name FROM booking_services WHERE booking_id = ? ORDER BY id",
        [$bookingId]
    );
    $names = array_values(array_filter(array_map(
        static fn(array $r) => trim((string)$r['service_name']),
        $services
    )));

    $booking['service_list'] = $names ? implode(', ', $names) : 'Motorcycle service';
    return $booking;
}

/** Format a stored datetime for customers, in the shop's timezone. */
function notifyFormatTime(?string $datetime, string $format = 'g:i A'): string {
    if (!$datetime) {
        return '—';
    }
    try {
        // Stored values are already Manila wall-clock (MySQL NOW()), so they are
        // formatted as-is rather than converted a second time.
        return (new DateTime($datetime, new DateTimeZone('Asia/Manila')))->format($format);
    } catch (Throwable $e) {
        return '—';
    }
}

/** Whole minutes between two stored timestamps. */
function notifyMinutesBetween(?string $start, ?string $end): ?int {
    if (!$start || !$end) {
        return null;
    }
    try {
        $a = new DateTime($start, new DateTimeZone('Asia/Manila'));
        $b = new DateTime($end, new DateTimeZone('Asia/Manila'));
        return max(0, (int)round(($b->getTimestamp() - $a->getTimestamp()) / 60));
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Has this exact notification already gone out on this channel?
 *
 * Checked before sending so a repeated action does not re-deliver; the unique
 * key on notification_log is the hard guarantee behind it.
 */
function notifyAlreadySent(int $bookingId, string $type, string $channel): bool {
    $row = fetchOne(
        "SELECT id FROM notification_log
         WHERE booking_id = ? AND notification_type = ? AND channel = ? AND status IN ('sent','skipped')
         LIMIT 1",
        [$bookingId, $type, $channel]
    );
    return $row !== null;
}

/**
 * Record one delivery attempt.
 *
 * Uses the database clock so log times match booking timestamps. A failed
 * write here is swallowed: logging must never break a booking.
 */
function notifyLog(
    ?int $bookingId,
    ?int $userId,
    string $type,
    string $channel,
    string $recipient,
    string $status,
    string $provider,
    ?string $messageId = null,
    ?string $error = null
): void {
    try {
        getDB()->prepare(
            "INSERT INTO notification_log
                (booking_id, user_id, notification_type, channel, recipient, provider, status, provider_message_id, error_message, sent_at)
             VALUES (?,?,?,?,?,?,?,?,?, NOW())
             ON DUPLICATE KEY UPDATE
                status              = VALUES(status),
                provider            = VALUES(provider),
                provider_message_id = VALUES(provider_message_id),
                error_message       = VALUES(error_message),
                sent_at             = NOW()"
        )->execute([
            $bookingId,
            $userId,
            $type,
            $channel,
            mb_substr($recipient, 0, 150),
            mb_substr($provider, 0, 40),
            $status,
            $messageId !== null ? mb_substr($messageId, 0, 80) : null,
            $error !== null ? mb_substr($error, 0, 255) : null,
        ]);
    } catch (Throwable $e) {
        // Never let logging failure surface into the booking flow.
        smsLogLine('notification_log write failed for booking ' . (int)$bookingId . ': ' . $e->getMessage());
    }
}

/**
 * Deliver one event over both channels.
 *
 * Channel rule: SMS only when a valid mobile number exists; email whenever an
 * address exists. Semaphore is never called without a usable number.
 *
 * @return array{sms:string,email:string} per-channel outcome
 *         ('sent' | 'failed' | 'skipped' | 'duplicate')
 */
function notifyDispatch(array $booking, string $type, string $smsBody, string $emailSubject, string $emailBody): array {
    $bookingId = (int)$booking['id'];
    $userId    = (int)$booking['user_id'];
    $phone     = trim((string)($booking['customer_phone'] ?? ''));
    $email     = trim((string)($booking['customer_email'] ?? ''));

    $outcome = ['sms' => 'skipped', 'email' => 'skipped'];

    // ---------------- SMS ----------------
    if ($phone === '' || !smsIsValidPhNumber($phone)) {
        // No usable mobile number: skip SMS entirely, do not call the provider.
        $outcome['sms'] = 'skipped';
        notifyLog(
            $bookingId, $userId, $type, 'sms', $phone !== '' ? $phone : '(none)',
            'skipped', 'none', null,
            $phone === '' ? 'No mobile number on file.' : 'Mobile number is not a valid PH number.'
        );
    } elseif (notifyAlreadySent($bookingId, $type, 'sms')) {
        $outcome['sms'] = 'duplicate';
    } else {
        try {
            $sent = smsSend($phone, $smsBody);
            $outcome['sms'] = $sent['status'];
            notifyLog(
                $bookingId, $userId, $type, 'sms', $phone,
                $sent['status'], $sent['provider'], $sent['message_id'], $sent['error']
            );
        } catch (Throwable $e) {
            // Defensive: smsSend() is written not to throw, but a provider
            // failure must never escape into the booking transaction.
            $outcome['sms'] = 'failed';
            notifyLog($bookingId, $userId, $type, 'sms', $phone, 'failed', 'semaphore', null, smsSafeError($e->getMessage()));
        }
    }

    // ---------------- Email ----------------
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $outcome['email'] = 'skipped';
        notifyLog(
            $bookingId, $userId, $type, 'email', $email !== '' ? $email : '(none)',
            'skipped', 'none', null, 'No valid email address on file.'
        );
    } elseif (notifyAlreadySent($bookingId, $type, 'email')) {
        $outcome['email'] = 'duplicate';
    } else {
        try {
            $ok = sendNotificationEmail($email, $emailSubject, $emailBody);
            $outcome['email'] = $ok ? 'sent' : 'failed';
            notifyLog(
                $bookingId, $userId, $type, 'email', $email,
                $ok ? 'sent' : 'failed', 'php-mail', null,
                $ok ? null : 'The mail server rejected or could not queue the message.'
            );
        } catch (Throwable $e) {
            $outcome['email'] = 'failed';
            notifyLog($bookingId, $userId, $type, 'email', $email, 'failed', 'php-mail', null, mb_substr($e->getMessage(), 0, 200));
        }
    }

    return $outcome;
}

// ---------------------------------------------------------------------------
// Events
// ---------------------------------------------------------------------------

/**
 * APPOINTMENT_CONFIRMED — staff confirmed the booking and a technician is known.
 */
function notifyAppointmentConfirmed(int $bookingId): array {
    $booking = notifyLoadBookingContext($bookingId);
    if (!$booking) {
        return ['sms' => 'skipped', 'email' => 'skipped'];
    }

    $dateText = notifyFormatTime($booking['scheduled_date'] . ' 00:00:00', 'F j, Y');
    $timeText = $booking['scheduled_time']
        ? notifyFormatTime($booking['scheduled_date'] . ' ' . $booking['scheduled_time'])
        : 'To be confirmed';
    $tech = $booking['technician_name'] ?: 'To be assigned';

    $sms = "MotoTrack: Your appointment has been confirmed.\n\n"
         . "Date: {$dateText}\n"
         . "Time: {$timeText}\n"
         . "Service: {$booking['service_list']}\n"
         . "Technician: {$tech}\n\n"
         . "Please arrive on time.";

    $email = "Hello {$booking['customer_name']},\n\n"
           . "Your MotoTrack appointment has been confirmed.\n\n"
           . "Booking Reference: #{$bookingId}\n"
           . "Date: {$dateText}\n"
           . "Time: {$timeText}\n"
           . "Service: {$booking['service_list']}\n"
           . "Technician: {$tech}\n";

    if ((float)$booking['total_amount'] > 0) {
        $email .= "Estimated Total: " . formatPrice((float)$booking['total_amount']) . "\n";
    }

    $email .= "\nPlease arrive on time. If you need to reschedule, contact us as early as you can.\n\n"
            . "Thank you for choosing MotoTrack.\n";

    return notifyDispatch($booking, NOTIFY_APPOINTMENT_CONFIRMED, $sms, "MotoTrack – Appointment Confirmed (#{$bookingId})", $email);
}

/**
 * JOB_COMPLETED — technician finished; report the real execution times.
 */
function notifyJobCompleted(int $bookingId): array {
    $booking = notifyLoadBookingContext($bookingId);
    if (!$booking) {
        return ['sms' => 'skipped', 'email' => 'skipped'];
    }

    $startedText   = notifyFormatTime($booking['actual_start_time']);
    $completedText = notifyFormatTime($booking['completed_at']);
    $actualMinutes = notifyMinutesBetween($booking['actual_start_time'], $booking['completed_at']);
    $actualText    = $actualMinutes !== null ? formatDurationMinutes($actualMinutes) : 'Not recorded';
    $estMinutes    = $booking['estimated_duration_minutes'] !== null ? (int)$booking['estimated_duration_minutes'] : null;
    $tech          = $booking['technician_name'] ?: 'MotoTrack technician';

    $sms = "MotoTrack: Your service has been completed.\n\n"
         . "Service: {$booking['service_list']}\n"
         . "Technician: {$tech}\n\n"
         . "Started: {$startedText}\n"
         . "Completed: {$completedText}\n"
         . "Actual Service Duration: {$actualText}\n\n"
         . "Thank you for choosing MotoTrack.";

    $email = "Hello {$booking['customer_name']},\n\n"
           . "Your motorcycle service has been completed.\n\n"
           . "Booking Reference: #{$bookingId}\n"
           . "Service: {$booking['service_list']}\n"
           . "Technician: {$tech}\n\n"
           . "Started: {$startedText}\n"
           . "Completed: {$completedText}\n"
           . "Actual Service Duration: {$actualText}\n";

    if ($estMinutes !== null) {
        $email .= "Estimated Duration: " . formatDurationMinutes($estMinutes) . "\n";
    }

    if ((float)$booking['total_amount'] > 0) {
        $email .= "\nBilling\n"
                . "Labor: " . formatPrice((float)$booking['labor_total']) . "\n"
                . "Parts / Materials: " . formatPrice((float)$booking['products_total']) . "\n"
                . "Total: " . formatPrice((float)$booking['total_amount']) . "\n";
    }

    $email .= "\nThank you for choosing MotoTrack.\n";

    return notifyDispatch($booking, NOTIFY_JOB_COMPLETED, $sms, "MotoTrack – Service Completed (#{$bookingId})", $email);
}

/**
 * APPOINTMENT_REMINDER — driven by the scheduled date/time, never by service
 * duration. Kept a distinct event so a passing appointment time can never be
 * mistaken for a completion.
 */
function notifyAppointmentReminder(int $bookingId): array {
    $booking = notifyLoadBookingContext($bookingId);
    if (!$booking) {
        return ['sms' => 'skipped', 'email' => 'skipped'];
    }

    $dateText = notifyFormatTime($booking['scheduled_date'] . ' 00:00:00', 'F j, Y');
    $timeText = $booking['scheduled_time']
        ? notifyFormatTime($booking['scheduled_date'] . ' ' . $booking['scheduled_time'])
        : 'To be confirmed';
    $tech = $booking['technician_name'] ?: 'To be assigned';

    $sms = "MotoTrack: Reminder for your upcoming appointment.\n\n"
         . "Date: {$dateText}\n"
         . "Time: {$timeText}\n"
         . "Service: {$booking['service_list']}\n"
         . "Technician: {$tech}\n\n"
         . "See you soon.";

    $email = "Hello {$booking['customer_name']},\n\n"
           . "This is a reminder for your upcoming MotoTrack appointment.\n\n"
           . "Booking Reference: #{$bookingId}\n"
           . "Date: {$dateText}\n"
           . "Time: {$timeText}\n"
           . "Service: {$booking['service_list']}\n"
           . "Technician: {$tech}\n\n"
           . "Please arrive on time.\n";

    return notifyDispatch($booking, NOTIFY_APPOINTMENT_REMINDER, $sms, "MotoTrack – Appointment Reminder (#{$bookingId})", $email);
}

/**
 * RATING_REQUEST — sent after job completion with a one-time rating link.
 *
 * Generates a unique token (SHA-256), stores it on the booking, then
 * dispatches SMS + email. Idempotency is handled by the token: if the
 * booking already has a token, we reuse it so the same link is sent.
 *
 * @return array{sms:string,email:string,token:string}
 */
function notifyRatingRequest(int $bookingId): array {
    $booking = notifyLoadBookingContext($bookingId);
    if (!$booking) {
        return ['sms' => 'skipped', 'email' => 'skipped', 'token' => ''];
    }

    // Generate or reuse token
    $existing = fetchOne("SELECT rating_token, rating_token_used FROM bookings WHERE id = ?", [$bookingId]);
    if ($existing && !empty($existing['rating_token']) && !(int)$existing['rating_token_used']) {
        $token = $existing['rating_token'];
    } else {
        $token = bin2hex(random_bytes(32)); // 64 hex chars
        getDB()->prepare("UPDATE bookings SET rating_token = ?, rating_token_used = 0 WHERE id = ?")
               ->execute([$token, $bookingId]);
    }

    $shopName   = fetchOne("SELECT value FROM site_settings WHERE `key` = 'shop_name'")['value'] ?? 'MotoTrack';
    $baseUrl    = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . baseUrl('rate-booking.php');
    $ratingUrl  = rtrim(preg_replace('/\.php.*$/', '.php', $baseUrl), '/') . '?token=' . $token;

    $sms = "{$shopName}: Your motorcycle service is complete! \n"
         . "Please rate your experience: {$ratingUrl}";

    $email = "Hello {$booking['customer_name']},\n\n"
           . "Your service at {$shopName} is now complete. We'd love to hear how we did!\n\n"
           . "Please take a moment to rate your experience:\n"
           . "{$ratingUrl}\n\n"
           . "This link is valid for one use only.\n\n"
           . "Thank you for choosing {$shopName}.\n";

    $result = notifyDispatch(
        $booking,
        NOTIFY_RATING_REQUEST,
        $sms,
        "{$shopName} – Please Rate Your Service (Booking #{$bookingId})",
        $email
    );
    $result['token'] = $token;
    return $result;
}
