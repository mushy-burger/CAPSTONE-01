<?php
$pageTitle = 'Job Detail';
require_once __DIR__ . '/../includes/tech-sidebar.php';
require_once __DIR__ . '/../includes/db.php';

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

    // Update job status
    if ($action === 'update_status') {
        $newStatus = $_POST['status'] ?? '';
        $allowedTransitions = [
            'confirmed'   => ['in_progress'],
            'in_progress' => ['completed'],
        ];
        $current = $booking['status'];
        if (isset($allowedTransitions[$current]) && in_array($newStatus, $allowedTransitions[$current], true)) {
            getDB()->prepare("UPDATE bookings SET status = ? WHERE id = ? AND technician_id = ?")
                   ->execute([$newStatus, $bookingId, $currentUser['id']]);

            // If completed, notify all staff
            if ($newStatus === 'completed') {
                notifyAllStaff(
                    "Job #{$bookingId} has been marked as Completed by {$currentUser['name']}.",
                    'completion',
                    $bookingId
                );
                flashMessage('tech_success', "Job #$bookingId marked as Completed. Great work!");
            } else {
                flashMessage('tech_success', "Job #$bookingId is now In Progress.");
            }
        } else {
            flashMessage('tech_error', 'Invalid status transition.');
        }
        redirect(baseUrl('tech/job.php?id=' . $bookingId));
    }
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

<?php if ($flash): ?><div class="alert success"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if ($flashErr): ?><div class="alert error"><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>

<div style="display:flex;align-items:center;gap:14px;margin-bottom:22px;">
  <a href="<?= baseUrl('tech/index.php') ?>" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Queue</a>
  <h1 style="margin:0;font-size:1.5rem;">Job #<?= $bookingId ?></h1>
  <span class="status-pill" style="--status-color:<?= $color ?>;">
    <?= ucfirst(str_replace('_', ' ', $booking['status'])) ?>
  </span>
</div>

<div class="job-detail-grid">

  <!-- LEFT: Job Info -->
  <div style="display:grid;gap:20px;">

    <!-- Customer & Schedule Card -->
    <section class="admin-card" style="padding:22px;">
      <h2 style="margin:0 0 16px;font-size:1.05rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;">Schedule</h2>
      <div class="detail-row"><span>Date</span><strong><?= htmlspecialchars(date('l, F j, Y', strtotime($booking['scheduled_date']))) ?></strong></div>
      <div class="detail-row"><span>Time</span><strong><?= $booking['scheduled_time'] ? htmlspecialchars(date('g:i A', strtotime($booking['scheduled_time']))) : 'Not set' ?></strong></div>

      <h2 style="margin:20px 0 16px;font-size:1.05rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;">Customer</h2>
      <div class="detail-row"><span>Name</span><strong><?= htmlspecialchars($booking['customer_name']) ?></strong></div>
      <div class="detail-row"><span>Email</span><?= htmlspecialchars($booking['customer_email']) ?></div>
      <?php if ($booking['customer_phone']): ?>
        <div class="detail-row"><span>Phone</span><?= htmlspecialchars($booking['customer_phone']) ?></div>
      <?php endif; ?>
    </section>

    <!-- Motorcycle Card -->
    <section class="admin-card" style="padding:22px;">
      <h2 style="margin:0 0 16px;font-size:1.05rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;">Motorcycle</h2>
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
    <section class="admin-card" style="padding:22px;">
      <h2 style="margin:0 0 16px;font-size:1.05rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;">Services & Materials</h2>
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
    <section class="admin-card" style="padding:22px;">
      <h2 style="margin:0 0 10px;font-size:1.05rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;">Customer Notes</h2>
      <p style="margin:0;"><?= nl2br(htmlspecialchars($booking['notes'])) ?></p>
    </section>
    <?php endif; ?>
  </div>

  <!-- RIGHT: Actions Panel -->
  <div style="display:grid;gap:20px;align-content:start;">

    <!-- Status Update Card -->
    <?php if ($booking['status'] !== 'completed' && $booking['status'] !== 'cancelled'): ?>
    <section class="admin-card" style="padding:22px;">
      <h2 style="margin:0 0 16px;font-size:1.05rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;">Update Status</h2>

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

      <?php if ($booking['status'] === 'confirmed'): ?>
        <form method="post" style="margin-top:18px;">
          <?= authContextField() ?>
          <input type="hidden" name="action" value="update_status">
          <input type="hidden" name="status" value="in_progress">
          <button type="submit" class="btn btn-primary" style="width:100%;">
            <i class="fas fa-play"></i> Start Job (Mark In Progress)
          </button>
        </form>
      <?php elseif ($booking['status'] === 'in_progress'): ?>
        <form method="post" style="margin-top:18px;" onsubmit="return confirm('Mark this job as Completed?');">
          <?= authContextField() ?>
          <input type="hidden" name="action" value="update_status">
          <input type="hidden" name="status" value="completed">
          <button type="submit" class="btn btn-primary" style="width:100%;background:#15803d;border-color:#15803d;">
            <i class="fas fa-flag-checkered"></i> Complete Job
          </button>
        </form>
      <?php endif; ?>
    </section>
    <?php else: ?>
    <section class="admin-card" style="padding:22px;text-align:center;">
      <i class="fas fa-check-circle" style="font-size:2.5rem;color:#15803d;margin-bottom:10px;display:block;"></i>
      <strong>Job Completed</strong>
      <p class="subtext" style="margin:6px 0 0;">This job has been marked as completed.</p>
    </section>
    <?php endif; ?>

    <!-- Tech Notes Card -->
    <section class="admin-card" style="padding:22px;">
      <h2 style="margin:0 0 12px;font-size:1.05rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;">
        <i class="fas fa-sticky-note"></i> Tech Notes
      </h2>
      <form method="post">
        <?= authContextField() ?>
        <input type="hidden" name="action" value="save_notes">
        <textarea name="tech_notes" rows="6" placeholder="Write your job notes, observations, parts used, issues encountered..."
                  style="width:100%;border:1px solid var(--line);border-radius:6px;padding:10px 12px;font-size:.9rem;resize:vertical;"><?= htmlspecialchars($booking['tech_notes'] ?? '') ?></textarea>
        <button type="submit" class="btn btn-primary" style="width:100%;margin-top:10px;">
          <i class="fas fa-save"></i> Save Notes
        </button>
      </form>
    </section>

  </div>
</div>

</main></div></div></body></html>
