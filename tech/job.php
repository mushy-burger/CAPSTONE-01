<?php
$pageTitle = 'Job Detail';
require_once __DIR__ . '/../includes/tech-sidebar.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/JobService.php';
require_once __DIR__ . '/../includes/NotificationService.php';

$bookingId = (int)($_GET['id'] ?? 0);

// Fetch the booking — must be assigned to this technician
$booking = fetchOne(
    "SELECT
        b.*,
        u.name AS customer_name,
        u.email AS customer_email,
        u.phone AS customer_phone,
        CONCAT(mb.name, ' ', mm.name) AS vehicle_name,
        mt.name AS type_name,
        cv.cc,
        cv.plate_number,
        cv.year,
        mb.name AS brand_name,
        mm.name AS model_name
     FROM bookings b
     JOIN users u ON u.id = b.user_id
     LEFT JOIN customer_vehicles cv ON cv.id = b.vehicle_id
     LEFT JOIN motorcycle_brands mb ON mb.id = cv.brand_id
     LEFT JOIN motorcycle_models mm ON mm.id = cv.model_id
     LEFT JOIN motorcycle_types mt ON mt.id = cv.type_id
     WHERE b.id = ? AND b.technician_id = ?",
    [$bookingId, $currentUser['id']]
);

if (!$booking) {
    flashMessage('tech_error', 'Job not found or not assigned to you.');
    redirect(baseUrl('tech/index.php'));
}

$services = fetchAllRows(
    "SELECT * FROM booking_services WHERE booking_id = ? ORDER BY id",
    [$bookingId]
);
$products = fetchAllRows(
    "SELECT * FROM booking_products WHERE booking_id = ? ORDER BY id",
    [$bookingId]
);

// ---------- POST HANDLER ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Save tech notes
    if ($action === 'save_notes') {
        $notes = trim($_POST['tech_notes'] ?? '');
        getDB()->prepare("UPDATE bookings SET tech_notes = ? WHERE id = ? AND technician_id = ?")
               ->execute([$notes, $bookingId, $currentUser['id']]);
        flashMessage('tech_success', 'Notes saved.');
        redirect(baseUrl('tech/job.php?id=' . $bookingId));
    }

    // Save estimated service duration (shown to the customer)
    if ($action === 'save_duration') {
        $parsed = jobParseDuration($_POST['duration_hours'] ?? '', $_POST['duration_minutes'] ?? '');
        if (!$parsed['ok']) {
            flashMessage('tech_error', $parsed['error']);
        } else {
            $saved = jobSaveEstimate($bookingId, (int)$currentUser['id'], $parsed['minutes']);
            if ($saved['ok']) {
                flashMessage('tech_success', 'Estimated duration saved: ' . formatDurationMinutes($parsed['minutes']) . '. The customer can now see it.');
            } else {
                flashMessage('tech_error', $saved['error']);
            }
        }
        redirect(baseUrl('tech/job.php?id=' . $bookingId));
    }

    // START JOB — requires an estimate; actual_start_time comes from the DB clock
    if ($action === 'start_job') {
        $estimate = null;
        // The estimate may be entered on the same submit as Start.
        if (($_POST['duration_hours'] ?? '') !== '' || ($_POST['duration_minutes'] ?? '') !== '') {
            $parsed = jobParseDuration($_POST['duration_hours'] ?? '', $_POST['duration_minutes'] ?? '');
            if (!$parsed['ok']) {
                flashMessage('tech_error', $parsed['error']);
                redirect(baseUrl('tech/job.php?id=' . $bookingId));
            }
            $estimate = $parsed['minutes'];
        }

        $started = jobStart($bookingId, (int)$currentUser['id'], $estimate);
        if ($started['ok']) {
            flashMessage('tech_success', "Job #$bookingId started at " . notifyFormatTime($started['started_at']) . '.');
        } else {
            flashMessage('tech_error', $started['error']);
        }
        redirect(baseUrl('tech/job.php?id=' . $bookingId));
    }

    // COMPLETE JOB — records completed_at, then notifies the customer.
    if ($action === 'complete_job') {
        $done = jobComplete($bookingId, (int)$currentUser['id']);

        if (!$done['ok']) {
            flashMessage('tech_error', $done['error']);
            redirect(baseUrl('tech/job.php?id=' . $bookingId));
        }

        // --- Everything below is post-completion. The job is already saved as
        // --- completed; none of it may undo that, so each part is isolated.
        try {
            $bookedProducts = fetchAllRows(
                "SELECT product_id FROM booking_products WHERE booking_id = ?",
                [$bookingId]
            );
            foreach ($bookedProducts as $bp) {
                getDB()->prepare(
                    "UPDATE products SET stock = GREATEST(0, stock - 1) WHERE id = ?"
                )->execute([$bp['product_id']]);
                getDB()->prepare(
                    "UPDATE products SET status = CASE
                       WHEN stock = 0 THEN 'out_of_stock'
                       WHEN stock <= min_stock THEN 'low_stock'
                       ELSE 'available'
                     END WHERE id = ?"
                )->execute([$bp['product_id']]);
            }

            notifyAllStaff(
                "Job #{$bookingId} has been marked as Completed by {$currentUser['name']}.",
                'completion',
                $bookingId
            );
        } catch (Throwable $e) {
            // Stock/staff-notice trouble must not affect the completed job.
            smsLogLine("Post-completion housekeeping failed for booking {$bookingId}: " . $e->getMessage());
        }

        // Customer notification: failure here is logged, never fatal.
        $delivery = ['sms' => 'skipped', 'email' => 'skipped'];
        try {
            $delivery = notifyJobCompleted($bookingId);
        } catch (Throwable $e) {
            smsLogLine("Completion notification failed for booking {$bookingId}: " . $e->getMessage());
        }

        $note = techDeliveryNote($delivery);
        flashMessage('tech_success', "Job #$bookingId marked as Completed. Great work!" . ($note !== '' ? ' ' . $note : ''));
        redirect(baseUrl('tech/job.php?id=' . $bookingId));
    }

}

/** Short, honest summary of what actually reached the customer. */
function techDeliveryNote(array $delivery): string {
    $parts = [];
    foreach (['sms' => 'SMS', 'email' => 'Email'] as $channel => $label) {
        switch ($delivery[$channel] ?? '') {
            case 'sent':      $parts[] = "$label sent"; break;
            case 'failed':    $parts[] = "$label failed"; break;
            case 'skipped':   $parts[] = "$label skipped"; break;
            case 'duplicate': $parts[] = "$label already sent"; break;
        }
    }
    return $parts ? 'Customer notification — ' . implode(', ', $parts) . '.' : '';
}

$flash    = getFlash('tech_success');
$flashErr = getFlash('tech_error');

$statusColor = [
    'confirmed'   => '#2563eb',
    'in_progress' => '#d97706',
    'completed'   => '#15803d',
    'cancelled'   => '#b91c1c',
];
$color = $statusColor[$booking['status']] ?? '#6b7280';

$pageTitle = 'Job #' . $bookingId;
?>

<div class="mtx-shell">

<?php if ($flash): ?><div class="alert success"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if ($flashErr): ?><div class="alert error"><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>

<header class="mtx-page-head">
  <div class="mtx-page-head-copy" style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
    <a href="<?= baseUrl('tech/index.php') ?>" class="mtx-btn mtx-btn--ghost mtx-btn--sm"><i class="fas fa-arrow-left"></i> Back to Queue</a>
    <h1 style="margin:0;">Job #<?= $bookingId ?></h1>
    <span class="mtx-pill" style="--pill-color:<?= $color ?>;font-size:.82rem;">
      <?= ucfirst(str_replace('_', ' ', $booking['status'])) ?>
    </span>
  </div>
</header>

<div class="job-detail-grid">

  <!-- LEFT: Job Info -->
  <div class="mtx-stack">

    <!-- Customer & Schedule Card -->
    <section class="mtx-card">
      <div class="mtx-card-head"><div><h2><i class="fas fa-calendar"></i> Schedule &amp; Customer</h2></div></div>
      <div class="detail-row"><span>Date</span><strong><?= htmlspecialchars(date('l, F j, Y', strtotime($booking['scheduled_date']))) ?></strong></div>
      <div class="detail-row"><span>Time</span><strong><?= $booking['scheduled_time'] ? htmlspecialchars(date('g:i A', strtotime($booking['scheduled_time']))) : 'Not set' ?></strong></div>
      <div class="detail-row"><span>Name</span><strong><?= htmlspecialchars($booking['customer_name']) ?></strong></div>
      <div class="detail-row"><span>Email</span><?= htmlspecialchars($booking['customer_email']) ?></div>
      <?php if ($booking['customer_phone']): ?>
        <div class="detail-row"><span>Phone</span><?= htmlspecialchars($booking['customer_phone']) ?></div>
      <?php endif; ?>
    </section>

    <!-- Motorcycle Card -->
    <section class="mtx-card">
      <div class="mtx-card-head"><div><h2><i class="fas fa-motorcycle"></i> Motorcycle</h2></div></div>
      <?php if ($booking['vehicle_name']): ?>
        <div class="detail-row"><span>Model</span><strong><?= htmlspecialchars($booking['vehicle_name']) ?></strong></div>
        <div class="detail-row"><span>Type</span><?= htmlspecialchars($booking['type_name'] ?? '—') ?></div>
        <div class="detail-row"><span>Engine</span><?= (int)$booking['cc'] ?> cc</div>
        <?php if ($booking['year']): ?>
          <div class="detail-row"><span>Year</span><?= (int)$booking['year'] ?></div>
        <?php endif; ?>
        <?php if ($booking['plate_number']): ?>
          <div class="detail-row"><span>Plate</span><strong><?= htmlspecialchars($booking['plate_number']) ?></strong></div>
        <?php endif; ?>
      <?php else: ?>
        <p class="subtext">No vehicle on record.</p>
      <?php endif; ?>
    </section>

    <!-- Services & Products Card -->
    <section class="mtx-card">
      <div class="mtx-card-head"><div><h2><i class="fas fa-tools"></i> Services &amp; Materials</h2></div></div>
      <?php if ($services): ?>
        <div style="display:grid;gap:10px;">
          <?php foreach ($services as $svc): ?>
            <div class="detail-service-row">
              <strong><?= htmlspecialchars($svc['service_name']) ?></strong>
              <span><?= formatPrice((float)$svc['labor_fee']) ?> labor</span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="subtext">No services listed.</p>
      <?php endif; ?>

      <?php if ($products): ?>
        <h3 style="margin:16px 0 10px;font-size:.9rem;color:var(--muted);">Materials / Products</h3>
        <?php foreach ($products as $prod): ?>
          <div class="detail-service-row">
            <span><?= htmlspecialchars($prod['product_name']) ?></span>
            <span><?= formatPrice((float)$prod['product_price']) ?></span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--line);display:flex;justify-content:flex-end;">
        <strong>Total: <?= formatPrice((float)$booking['total_amount']) ?></strong>
      </div>
    </section>

    <?php if ($booking['notes']): ?>
    <!-- Customer Notes -->
    <section class="mtx-card">
      <div class="mtx-card-head"><div><h2><i class="fas fa-comment"></i> Customer Notes</h2></div></div>
      <p style="margin:0;"><?= nl2br(htmlspecialchars($booking['notes'])) ?></p>
    </section>
    <?php endif; ?>
  </div>

  <!-- RIGHT: Actions Panel -->
  <div class="mtx-stack">

    <!-- Status Update Card -->
    <?php if ($booking['status'] !== 'completed' && $booking['status'] !== 'cancelled'): ?>
    <section class="mtx-card">
      <div class="mtx-card-head"><div><h2><i class="fas fa-arrows-rotate"></i> Update Status</h2></div></div>

      <!-- Status Timeline -->
      <div class="status-timeline">
        <?php
          $steps = [
            'confirmed'   => ['label' => 'Confirmed', 'icon' => 'fa-check', 'color' => '#2563eb'],
            'in_progress' => ['label' => 'In Progress', 'icon' => 'fa-wrench', 'color' => '#d97706'],
            'completed'   => ['label' => 'Completed', 'icon' => 'fa-flag-checkered', 'color' => '#15803d'],
          ];
          $reached = false;
          foreach ($steps as $key => $step):
            $isCurrent = ($booking['status'] === $key);
            $isPast = !$reached && !$isCurrent;
            if ($isCurrent) $reached = true;
        ?>
          <div class="timeline-step <?= $isCurrent ? 'current' : ($isPast ? 'past' : 'future') ?>"
               style="--step-color:<?= $step['color'] ?>;">
            <div class="timeline-dot"><i class="fas <?= $step['icon'] ?>"></i></div>
            <span><?= $step['label'] ?></span>
          </div>
          <?php if ($key !== 'completed'): ?><div class="timeline-line"></div><?php endif; ?>
        <?php endforeach; ?>
      </div>

      <?php
        $estMins   = $booking['estimated_duration_minutes'] !== null ? (int)$booking['estimated_duration_minutes'] : null;
        $estFinish = jobEstimatedFinish($booking['actual_start_time'] ?? null, $estMins);
      ?>

      <?php if ($booking['status'] === 'confirmed'): ?>
        <!-- NOT STARTED: an estimate is required before the job can begin. -->
        <form method="post" style="margin-top:18px;">
          <?= authContextField() ?>
          <input type="hidden" name="action" value="start_job">
          <div class="mtx-est-block">
            <span class="mtx-est-label">Estimated service duration <em>*</em></span>
            <div style="display:flex;gap:10px;">
              <label class="mtx-field" style="flex:1;">
                <span>Hours</span>
                <input type="number" name="duration_hours" min="0" max="24" step="1"
                       value="<?= $estMins !== null ? intdiv($estMins, 60) : '' ?>" placeholder="0">
              </label>
              <label class="mtx-field" style="flex:1;">
                <span>Minutes</span>
                <input type="number" name="duration_minutes" min="0" max="59" step="1"
                       value="<?= $estMins !== null ? $estMins % 60 : '' ?>" placeholder="0">
              </label>
            </div>
            <p class="subtext" style="margin:8px 0 0;font-size:.78rem;">
              <i class="fas fa-circle-info"></i> Required. The start time is recorded automatically when you begin.
            </p>
          </div>
          <button type="submit" class="mtx-btn mtx-btn--primary" style="width:100%;margin-top:12px;">
            <i class="fas fa-play"></i> Start Job
          </button>
        </form>

      <?php elseif ($booking['status'] === 'in_progress'): ?>
        <!-- IN PROGRESS: show the live timing picture. -->
        <div class="mtx-est-block" style="margin-top:18px;">
          <div class="detail-row"><span>Estimated duration</span><strong><?= $estMins !== null ? htmlspecialchars(formatDurationMinutes($estMins)) : '—' ?></strong></div>
          <div class="detail-row"><span>Started</span><strong><?= htmlspecialchars(notifyFormatTime($booking['actual_start_time'] ?? null)) ?></strong></div>
          <div class="detail-row"><span>Estimated finish</span><strong><?= htmlspecialchars(notifyFormatTime($estFinish)) ?></strong></div>
        </div>
        <form method="post" style="margin-top:14px;" onsubmit="return confirm('Mark this job as Completed? The customer will be notified.');">
          <?= authContextField() ?>
          <input type="hidden" name="action" value="complete_job">
          <button type="submit" class="mtx-btn mtx-btn--primary" style="width:100%;background:#15803d;box-shadow:0 6px 16px rgba(21,128,61,.24);">
            <i class="fas fa-flag-checkered"></i> Complete Job
          </button>
        </form>
      <?php endif; ?>
    </section>
    <?php else: ?>
    <section class="mtx-card">
      <?php
        $estMins    = $booking['estimated_duration_minutes'] !== null ? (int)$booking['estimated_duration_minutes'] : null;
        $actualMins = jobActualMinutes($booking['actual_start_time'] ?? null, $booking['completed_at'] ?? null);
      ?>
      <div style="text-align:center;margin-bottom:14px;">
        <i class="fas fa-<?= $booking['status'] === 'completed' ? 'check-circle' : 'ban' ?>"
           style="font-size:2.5rem;color:<?= $booking['status'] === 'completed' ? '#15803d' : '#b91c1c' ?>;margin-bottom:10px;display:block;"></i>
        <strong><?= $booking['status'] === 'completed' ? 'Job Completed' : 'Job Cancelled' ?></strong>
      </div>
      <?php if ($booking['status'] === 'completed'): ?>
        <div class="detail-row"><span>Estimated duration</span><strong><?= $estMins !== null ? htmlspecialchars(formatDurationMinutes($estMins)) : '—' ?></strong></div>
        <div class="detail-row"><span>Started</span><strong><?= htmlspecialchars(notifyFormatTime($booking['actual_start_time'] ?? null)) ?></strong></div>
        <div class="detail-row"><span>Completed</span><strong><?= htmlspecialchars(notifyFormatTime($booking['completed_at'] ?? null)) ?></strong></div>
        <div class="detail-row"><span>Actual duration</span><strong><?= $actualMins !== null ? htmlspecialchars(formatDurationMinutes($actualMins)) : 'Not recorded' ?></strong></div>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <!-- Estimated Duration Card -->
    <?php
      $estMinutes = $booking['estimated_duration_minutes'] !== null ? (int)$booking['estimated_duration_minutes'] : null;
      // Confirmed jobs capture the estimate on the Start Job form above, so
      // this card is only for revising it once work is under way.
      $canEditDuration = $booking['status'] === 'in_progress';
    ?>
    <?php if ($canEditDuration || $estMinutes): ?>
    <section class="mtx-card">
      <div class="mtx-card-head"><div><h2><i class="fas fa-hourglass-half"></i> Estimated Service Duration</h2></div></div>
      <?php if ($estMinutes): ?>
        <div class="detail-row"><span>Current estimate</span><strong><?= htmlspecialchars(formatDurationMinutes($estMinutes)) ?></strong></div>
      <?php endif; ?>
      <?php if ($canEditDuration): ?>
        <form method="post" style="margin-top:12px;">
          <?= authContextField() ?>
          <input type="hidden" name="action" value="save_duration">
          <div style="display:flex;gap:10px;">
            <label class="mtx-field" style="flex:1;">
              <span>Hours</span>
              <input type="number" name="duration_hours" min="0" max="24" step="1" value="<?= $estMinutes !== null ? intdiv($estMinutes, 60) : '' ?>" placeholder="0">
            </label>
            <label class="mtx-field" style="flex:1;">
              <span>Minutes</span>
              <input type="number" name="duration_minutes" min="0" max="59" step="1" value="<?= $estMinutes !== null ? $estMinutes % 60 : '' ?>" placeholder="0">
            </label>
          </div>
          <p class="subtext" style="margin:8px 0 0;font-size:.78rem;">
            <i class="fas fa-eye"></i> This estimate will be shown to the customer on their booking.
          </p>
          <button type="submit" class="mtx-btn mtx-btn--primary" style="width:100%;margin-top:10px;">
            <i class="fas fa-save"></i> <?= $estMinutes ? 'Update Estimate' : 'Save Estimate' ?>
          </button>
        </form>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <!-- Tech Notes Card -->
    <section class="mtx-card">
      <div class="mtx-card-head"><div><h2><i class="fas fa-sticky-note"></i> Tech Notes</h2></div></div>
      <form method="post">
        <?= authContextField() ?>
        <input type="hidden" name="action" value="save_notes">
        <label class="mtx-field">
          <textarea name="tech_notes" rows="6" placeholder="Write your job notes, observations, parts used, issues encountered..."><?= htmlspecialchars($booking['tech_notes'] ?? '') ?></textarea>
        </label>
        <button type="submit" class="mtx-btn mtx-btn--primary" style="width:100%;margin-top:10px;">
          <i class="fas fa-save"></i> Save Notes
        </button>
      </form>
    </section>

  </div>
</div>

</div><!-- /.mtx-shell -->

<?= authContextScriptTag() ?>
</main></div></div></body></html>
