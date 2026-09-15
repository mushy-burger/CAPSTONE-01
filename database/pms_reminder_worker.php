<?php
/**
 * PMS (Preventive Maintenance System) reminder worker.
 *
 * Run periodically by Windows Task Scheduler (e.g., daily at 8 AM):
 *
 *   php database/pms_reminder_worker.php
 *   php database/pms_reminder_worker.php --dry          (preview only)
 *   php database/pms_reminder_worker.php --interval=60  (override days)
 *
 * Logic:
 *   1. Find vehicles whose last_service_date + interval <= today
 *   2. Filter out any that already got a PMS reminder in the last 30 days
 *   3. Send SMS + email to the vehicle owner with a booking reminder
 *   4. Log the notification in the `notifications` table (type = 'pms_reminder')
 *
 * Safety:
 *   - Only sends if pms_reminder_enabled = 1 in site_settings
 *   - One reminder per vehicle per 30-day cooldown window
 *   - A single send failure does NOT stop the batch
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This worker is command-line only.\n");
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/SmsProvider.php';
require_once __DIR__ . '/../includes/mail.php';

// ---- Parse args ----
$dryRun         = false;
$intervalOverride = null;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry' || $arg === '--dry-run') {
        $dryRun = true;
    } elseif (preg_match('/^--interval=(\d+)$/', $arg, $m)) {
        $intervalOverride = max(1, min(730, (int)$m[1]));
    }
}

// ---- Settings ----
$enabled = (int)(fetchOne("SELECT value FROM site_settings WHERE `key` = 'pms_reminder_enabled'")['value'] ?? 1);
if (!$enabled) {
    echo "PMS reminders are disabled in site settings. Exiting.\n";
    exit(0);
}

$intervalDays = $intervalOverride
    ?? (int)(fetchOne("SELECT value FROM site_settings WHERE `key` = 'pms_reminder_interval_days'")['value'] ?? 90);

$shopName = fetchOne("SELECT value FROM site_settings WHERE `key` = 'shop_name'")['value'] ?? 'MotoTrack';

echo "MotoTrack PMS Reminder Worker\n";
echo "------------------------------\n";
echo "Server time : " . (fetchOne("SELECT NOW() AS now")['now'] ?? '') . "\n";
echo "Interval    : {$intervalDays} day(s)\n";
echo "Mode        : " . ($dryRun ? 'DRY RUN (nothing sent)' : 'SEND') . "\n\n";

// ---- Find due vehicles ----
// Due = last_service_date + interval <= today AND no PMS reminder sent in last 30 days
$due = fetchAllRows(
    "SELECT
        cv.id                    AS vehicle_id,
        cv.last_service_date,
        cv.last_service_booking_id,
        u.id                     AS user_id,
        u.name                   AS customer_name,
        u.email                  AS customer_email,
        u.phone                  AS customer_phone,
        CONCAT(mb.name, ' ', mm.name) AS vehicle_name
     FROM customer_vehicles cv
     JOIN users u ON u.id = cv.user_id
     JOIN motorcycle_brands mb ON mb.id = cv.brand_id
     JOIN motorcycle_models mm ON mm.id = cv.model_id
     WHERE cv.last_service_date IS NOT NULL
       AND DATE_ADD(cv.last_service_date, INTERVAL ? DAY) <= CURDATE()
       AND NOT EXISTS (
           SELECT 1 FROM notifications n
           WHERE n.user_id = cv.user_id
             AND n.booking_id = cv.id
             AND n.type = 'pms_reminder'
             AND n.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
       )
     ORDER BY cv.last_service_date ASC",
    [$intervalDays]
);

if (!$due) {
    echo "No PMS reminders are due.\n";
    exit(0);
}

echo count($due) . " PMS reminder(s) due:\n\n";

$sent   = 0;
$failed = 0;

foreach ($due as $row) {
    $lastDate  = date('F j, Y', strtotime($row['last_service_date']));
    $dueDate   = date('F j, Y', strtotime('+' . $intervalDays . ' days', strtotime($row['last_service_date'])));
    $phone     = trim($row['customer_phone'] ?? '');
    $email     = trim($row['customer_email'] ?? '');
    $vehicle   = $row['vehicle_name'];

    printf("  Vehicle #%-5d %-20s %-22s (last service: %s)\n",
        $row['vehicle_id'],
        mb_strimwidth($vehicle, 0, 18, '…'),
        mb_strimwidth($row['customer_name'], 0, 20, '…'),
        $lastDate
    );

    if ($dryRun) {
        echo "             (dry run — not sent)\n";
        continue;
    }

    $smsSent   = false;
    $emailSent = false;

    // --- SMS ---
    if ($phone !== '' && smsIsValidPhNumber($phone)) {
        $smsBody = "{$shopName}: Hi {$row['customer_name']}, your {$vehicle} is due for its PMS check!\n"
                 . "Last service: {$lastDate}. Visit us at your convenience to keep your motorcycle in top shape.";
        try {
            $result = smsSend($phone, $smsBody);
            $smsSent = $result['status'] === 'sent';
            echo "             SMS: " . $result['status'] . "\n";
        } catch (Throwable $e) {
            echo "             SMS: ERROR — " . mb_substr($e->getMessage(), 0, 80) . "\n";
        }
    } else {
        echo "             SMS: skipped (no valid phone)\n";
    }

    // --- Email ---
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $emailSubject = "{$shopName} – Time for Your Motorcycle's PMS Check!";
        $emailBody = "Hello {$row['customer_name']},\n\n"
                   . "Your {$vehicle} is due for a Preventive Maintenance Service (PMS).\n\n"
                   . "Last Service: {$lastDate}\n"
                   . "Recommended Next Service: {$dueDate}\n\n"
                   . "Regular PMS keeps your motorcycle running smoothly and prevents costly repairs. "
                   . "Contact us or book online to schedule your service.\n\n"
                   . "Thank you for choosing {$shopName}!\n";
        try {
            $ok = sendNotificationEmail($email, $emailSubject, $emailBody);
            $emailSent = $ok;
            echo "             Email: " . ($ok ? 'sent' : 'failed') . "\n";
        } catch (Throwable $e) {
            echo "             Email: ERROR — " . mb_substr($e->getMessage(), 0, 80) . "\n";
        }
    } else {
        echo "             Email: skipped (no valid email)\n";
    }

    // --- Log the notification to prevent re-send within cooldown ---
    if ($smsSent || $emailSent) {
        try {
            getDB()->prepare(
                "INSERT INTO notifications (user_id, type, message, booking_id)
                 VALUES (?, 'pms_reminder', ?, ?)"
            )->execute([
                $row['user_id'],
                "PMS reminder sent for {$vehicle} (last service: {$lastDate}).",
                $row['vehicle_id'], // we borrow booking_id column to store vehicle_id
            ]);
            $sent++;
        } catch (Throwable $e) {
            echo "             Log ERROR: " . mb_substr($e->getMessage(), 0, 80) . "\n";
        }
    } else {
        $failed++;
    }
}

echo "\nDone. Sent: {$sent}   Skipped/Failed: {$failed}\n";
exit(0);
