<?php
$pageTitle = 'Booking Detail';
require_once __DIR__ . '/../includes/staff-sidebar.php';
require_once __DIR__ . '/../includes/db.php';

$bookingId = (int)($_GET['id'] ?? 0);
if ($bookingId <= 0) {
    redirect(baseUrl('staff/bookings.php'));
}

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
        tech.name AS technician_name,
        tech.email AS technician_email
     FROM bookings b
     JOIN users u ON u.id = b.user_id
     LEFT JOIN customer_vehicles cv ON cv.id = b.vehicle_id
     LEFT JOIN motorcycle_brands mb ON mb.id = cv.brand_id
     LEFT JOIN motorcycle_models mm ON mm.id = cv.model_id
     LEFT JOIN motorcycle_types mt ON mt.id = cv.type_id
     LEFT JOIN users tech ON tech.id = b.technician_id
     WHERE b.id = ?",
    [$bookingId]
);

if (!$booking) {
    flashMessage('bk_error', "Booking #$bookingId not found.");
    redirect(baseUrl('staff/bookings.php'));
}

$services = fetchAllRows("SELECT * FROM booking_services WHERE booking_id = ? ORDER BY id", [$bookingId]);
$products = fetchAllRows("SELECT * FROM booking_products WHERE booking_id = ? ORDER BY id", [$bookingId]);
$technicians = fetchAllRows("SELECT id, name FROM users WHERE role = 'technician' AND is_active = 1 ORDER BY name");

// ---------- POST HANDLER ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // REASSIGN TECHNICIAN
    if ($action === 'reassign_tech') {
        $newTechId = (int)($_POST['technician_id'] ?? 0);
        $newTech = $newTechId ? fetchOne("SELECT id, name FROM users WHERE id = ? AND role = 'technician' AND is_active = 1", [$newTechId]) : null;

        if (!$newTech) {
            flashMessage('bk_error', 'Please select a valid technician.');
        } else {
            getDB()->prepare("UPDATE bookings SET technician_id = ? WHERE id = ?")->execute([$newTechId, $bookingId]);

            // Notify the new technician
            $scheduledDate = date('M j, Y', strtotime($booking['scheduled_date']));
            createNotification(
                $newTechId,
                "You have been assigned to Booking #$bookingId for {$booking['customer_name']} on $scheduledDate.",
                'assignment',
                $bookingId
            );

            flashMessage('bk_success', "Booking #$bookingId reassigned to {$newTech['name']}.");
        }
        redirect(baseUrl('staff/booking-detail.php?id=' . $bookingId));
    }

    // CANCEL BOOKING
    if ($action === 'cancel_booking') {
        if (in_array($booking['status'], ['pending', 'confirmed'], true)) {
            getDB()->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?")->execute([$bookingId]);
            flashMessage('bk_success', "Booking #$bookingId has been cancelled.");
        } else {
            flashMessage('bk_error', 'Cannot cancel an in-progress or completed booking.');
        }
        redirect(baseUrl('staff/booking-detail.php?id=' . $bookingId));
    }

    // CONFIRM + ASSIGN
    if ($action === 'confirm_booking') {
        $techId = (int)($_POST['technician_id'] ?? 0);
        $tech = $techId ? fetchOne("SELECT id, name FROM users WHERE id = ? AND role = 'technician' AND is_active = 1", [$techId]) : null;

        if (!$tech) {
            flashMessage('bk_error', 'Please select a valid technician.');
        } elseif ($booking['status'] !== 'pending') {
            flashMessage('bk_error', 'Only pending bookings can be confirmed.');
        } else {
            getDB()->prepare("UPDATE bookings SET status = 'confirmed', technician_id = ? WHERE id = ?")->execute([$techId, $bookingId]);
            $scheduledDate = date('M j, Y', strtotime($booking['scheduled_date']));
            createNotification(
                $techId,
                "New job assigned: Booking #$bookingId for {$booking['customer_name']} on $scheduledDate.",
                'assignment',
                $bookingId
            );
            flashMessage('bk_success', "Booking #$bookingId confirmed and assigned to {$tech['name']}.");
        }
        redirect(baseUrl('staff/booking-detail.php?id=' . $bookingId));
    }
}

// Refresh after POST handling
$booking = fetchOne(
    "SELECT b.*, u.name AS customer_name, u.email AS customer_email, u.phone AS customer_phone,
            CONCAT(mb.name, ' ', mm.name) AS vehicle_name, mt.name AS type_name, cv.cc, cv.plate_number, cv.year,
            tech.name AS technician_name, tech.email AS technician_email
     FROM bookings b
     JOIN users u ON u.id = b.user_id
     LEFT JOIN customer_vehicles cv ON cv.id = b.vehicle_id
     LEFT JOIN motorcycle_brands mb ON mb.id = cv.brand_id
     LEFT JOIN motorcycle_models mm ON mm.id = cv.model_id
     LEFT JOIN motorcycle_types mt ON mt.id = cv.type_id
     LEFT JOIN users tech ON tech.id = b.technician_id
     WHERE b.id = ?",
    [$bookingId]
);

$flash    = getFlash('bk_success');
$flashErr = getFlash('bk_error');

$statusColor = [
    'pending'     => '#6b7280',
    'confirmed'   => '#2563eb',
    'in_progress' => '#d97706',
    'completed'   => '#15803d',
    'cancelled'   => '#b91c1c',
];
$color = $statusColor[$booking['status']] ?? '#6b7280';
$pageTitle = 'Booking #' . $bookingId;
?>

<?php if ($flash): ?><div class="alert success"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if ($flashErr): ?><div class="alert error"><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>

<div style="display:flex;align-items:center;gap:14px;margin-bottom:22px;flex-wrap:wrap;">
  <a href="<?= baseUrl('staff/bookings.php') ?>" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Bookings</a>
  <h1 style="margin:0;font-size:1.5rem;">Booking #<?= $bookingId ?></h1>
  <span class="status-pill" style="--status-color:<?= $color ?>;">
    <?= ucfirst(str_replace('_', ' ', $booking['status'])) ?>
  </span>
</div>

<div class="job-detail-grid">

  <!-- LEFT COLUMN: Info cards -->
  <div style="display:grid;gap:20px;">

    <!-- Customer -->
    <section class="admin-card" style="padding:22px;">
      <h2 style="margin:0 0 16px;font-size:1rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;">Customer</h2>
      <div class="detail-row"><span>Name</span><strong><?= htmlspecialchars($booking['customer_name']) ?></strong></div>
      <div class="detail-row"><span>Email</span><?= htmlspecialchars($booking['customer_email']) ?></div>
      <?php if ($booking['customer_phone']): ?>
        <div class="detail-row"><span>Phone</span><?= htmlspecialchars($booking['customer_phone']) ?></div>
      <?php endif; ?>
      <div class="detail-row"><span>Date</span><strong><?= htmlspecialchars(date('l, F j, Y', strtotime($booking['scheduled_date']))) ?></strong></div>
      <div class="detail-row"><span>Time</span><?= $booking['scheduled_time'] ? htmlspecialchars(date('g:i A', strtotime($booking['scheduled_time']))) : 'Not specified' ?></div>
      <?php if ($booking['notes']): ?>
        <div class="detail-row"><span>Customer Notes</span><?= nl2br(htmlspecialchars($booking['notes'])) ?></div>
      <?php endif; ?>
    </section>

    <!-- Motorcycle -->
    <section class="admin-card" style="padding:22px;">
      <h2 style="margin:0 0 16px;font-size:1rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;">Motorcycle</h2>
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

    <!-- Services & Products -->
    <section class="admin-card" style="padding:22px;">
      <h2 style="margin:0 0 16px;font-size:1rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;">Services & Products</h2>
      <?php if ($services): ?>
        <?php foreach ($services as $svc): ?>
          <div class="detail-service-row">
            <span><i class="fas fa-tools" style="color:#2563eb;margin-right:6px;"></i><?= htmlspecialchars($svc['service_name']) ?></span>
            <strong><?= formatPrice((float)$svc['labor_fee']) ?></strong>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
      <?php if ($products): ?>
        <div style="margin-top:12px;padding-top:10px;border-top:1px solid var(--line);color:var(--muted);font-size:.78rem;font-weight:900;text-transform:uppercase;letter-spacing:.05em;">Products</div>
        <?php foreach ($products as $prod): ?>
          <div class="detail-service-row">
            <span><i class="fas fa-box" style="color:#d97706;margin-right:6px;"></i><?= htmlspecialchars($prod['product_name']) ?></span>
            <strong><?= formatPrice((float)$prod['product_price']) ?></strong>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
      <div style="margin-top:14px;padding-top:12px;border-top:2px solid var(--line);display:flex;justify-content:space-between;">
        <span>Labor</span><strong><?= formatPrice((float)$booking['labor_total']) ?></strong>
      </div>
      <div style="display:flex;justify-content:space-between;">
        <span>Products</span><strong><?= formatPrice((float)$booking['products_total']) ?></strong>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:1.05rem;margin-top:6px;">
        <strong>Total</strong><strong style="color:#d71920;"><?= formatPrice((float)$booking['total_amount']) ?></strong>
      </div>
    </section>

    <!-- Tech Notes (read-only) -->
    <?php if ($booking['tech_notes']): ?>
    <section class="admin-card" style="padding:22px;border-left:4px solid #15803d;">
      <h2 style="margin:0 0 12px;font-size:1rem;color:#15803d;text-transform:uppercase;letter-spacing:.05em;">
        <i class="fas fa-sticky-note"></i> Technician Notes
      </h2>
      <p style="margin:0;white-space:pre-line;line-height:1.7;"><?= nl2br(htmlspecialchars($booking['tech_notes'])) ?></p>
    </section>
    <?php elseif (in_array($booking['status'], ['in_progress', 'completed'], true)): ?>
    <section class="admin-card" style="padding:22px;opacity:.6;">
      <h2 style="margin:0 0 8px;font-size:1rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;">Technician Notes</h2>
      <p class="subtext" style="margin:0;">No notes written by the technician yet.</p>
    </section>
    <?php endif; ?>
  </div>

  <!-- RIGHT COLUMN: Actions -->
  <div style="display:grid;gap:20px;align-content:start;">

    <!-- Technician assignment card -->
    <section class="admin-card" style="padding:22px;">
      <h2 style="margin:0 0 16px;font-size:1rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;">Technician</h2>

      <?php if ($booking['technician_name']): ?>
        <div style="display:flex;align-items:center;gap:10px;padding:12px;background:#f0fdf4;border-radius:8px;margin-bottom:14px;">
          <i class="fas fa-user-cog" style="color:#15803d;font-size:1.4rem;"></i>
          <div>
            <div style="font-weight:900;"><?= htmlspecialchars($booking['technician_name']) ?></div>
            <div class="subtext"><?= htmlspecialchars($booking['technician_email'] ?? '') ?></div>
          </div>
        </div>
      <?php else: ?>
        <p class="subtext" style="margin:0 0 14px;">No technician assigned yet.</p>
      <?php endif; ?>

      <?php if (in_array($booking['status'], ['pending', 'confirmed'], true)): ?>
        <form method="post">
          <?= authContextField() ?>
          <input type="hidden" name="action" value="<?= $booking['status'] === 'pending' ? 'confirm_booking' : 'reassign_tech' ?>">
          <label style="display:flex;flex-direction:column;gap:6px;font-weight:700;font-size:.88rem;margin-bottom:10px;">
            <?= $booking['status'] === 'pending' ? 'Assign & Confirm' : 'Reassign Technician' ?>
            <select name="technician_id" required class="tech-select">
              <option value="">— Select Technician —</option>
              <?php foreach ($technicians as $t): ?>
                <option value="<?= (int)$t['id'] ?>" <?= (int)($booking['technician_id'] ?? 0) === (int)$t['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($t['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <button type="submit" class="btn btn-primary" style="width:100%;">
            <?php if ($booking['status'] === 'pending'): ?>
              <i class="fas fa-check"></i> Confirm & Assign
            <?php else: ?>
              <i class="fas fa-sync-alt"></i> Reassign Tech
            <?php endif; ?>
          </button>
        </form>
      <?php endif; ?>
    </section>

    <!-- Status card -->
    <section class="admin-card" style="padding:22px;">
      <h2 style="margin:0 0 14px;font-size:1rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;">Status Timeline</h2>
      <?php
        $timelineSteps = [
          ['label' => 'Pending',     'icon' => 'fa-clock',             'key' => 'pending',     'color' => '#6b7280'],
          ['label' => 'Confirmed',   'icon' => 'fa-check',             'key' => 'confirmed',   'color' => '#2563eb'],
          ['label' => 'In Progress', 'icon' => 'fa-wrench',            'key' => 'in_progress', 'color' => '#d97706'],
          ['label' => 'Completed',   'icon' => 'fa-flag-checkered',    'key' => 'completed',   'color' => '#15803d'],
        ];
        $stepOrder = ['pending' => 0, 'confirmed' => 1, 'in_progress' => 2, 'completed' => 3, 'cancelled' => -1];
        $currentIdx = $stepOrder[$booking['status']] ?? 0;
      ?>
      <div class="status-timeline" style="flex-direction:column;align-items:flex-start;gap:0;">
        <?php foreach ($timelineSteps as $i => $step):
          $isDone    = $i < $currentIdx;
          $isCurrent = $i === $currentIdx && $booking['status'] !== 'cancelled';
          $isFuture  = $i > $currentIdx;
          $dotStyle  = $isCurrent ? "background:{$step['color']};color:#fff;" : ($isDone ? "background:#15803d;color:#fff;" : "");
        ?>
          <div style="display:flex;align-items:center;gap:14px;padding:8px 0;">
            <div style="width:32px;height:32px;border-radius:50%;background:var(--line);display:grid;place-items:center;font-size:.8rem;flex-shrink:0;<?= $dotStyle ?>">
              <i class="fas <?= $step['icon'] ?>"></i>
            </div>
            <span style="font-size:.88rem;font-weight:<?= $isCurrent ? '900' : '600' ?>;color:<?= $isCurrent ? $step['color'] : ($isDone ? '#15803d' : 'var(--muted)') ?>;">
              <?= $step['label'] ?>
            </span>
            <?php if ($isCurrent): ?>
              <span style="font-size:.72rem;background:<?= $step['color'] ?>;color:#fff;border-radius:20px;padding:2px 8px;font-weight:900;">NOW</span>
            <?php endif; ?>
          </div>
          <?php if ($i < count($timelineSteps) - 1): ?>
            <div style="margin-left:15px;width:2px;height:14px;background:<?= $isDone ? '#15803d' : 'var(--line)' ?>;"></div>
          <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($booking['status'] === 'cancelled'): ?>
          <div style="display:flex;align-items:center;gap:14px;padding:8px 0;">
            <div style="width:32px;height:32px;border-radius:50%;background:#b91c1c;color:#fff;display:grid;place-items:center;font-size:.8rem;flex-shrink:0;">
              <i class="fas fa-times"></i>
            </div>
            <span style="font-size:.88rem;font-weight:900;color:#b91c1c;">Cancelled</span>
          </div>
        <?php endif; ?>
      </div>

      <?php if (in_array($booking['status'], ['pending', 'confirmed'], true)): ?>
        <hr style="margin:16px 0;border:none;border-top:1px solid var(--line);">
        <form method="post" onsubmit="return confirm('Cancel booking #<?= $bookingId ?>? This cannot be undone.');">
          <?= authContextField() ?>
          <input type="hidden" name="action" value="cancel_booking">
          <button type="submit" class="btn btn-outline" style="width:100%;color:#b91c1c;border-color:#b91c1c;">
            <i class="fas fa-times"></i> Cancel This Booking
          </button>
        </form>
      <?php endif; ?>
    </section>

  </div>
</div>

</main></div></div></body></html>
