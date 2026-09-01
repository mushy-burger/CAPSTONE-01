<?php
$pageTitle = 'Booking Detail';
require_once __DIR__ . '/../includes/staff-sidebar.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/NotificationService.php';
require_once __DIR__ . '/../includes/BookingDeposit.php';
require_once __DIR__ . '/../includes/PartsReservationService.php';
require_once __DIR__ . '/../includes/TechnicianService.php';

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
            // Release any held parts reservations
            try { partsReleaseForBooking($bookingId); } catch (Throwable $e) {}
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
        } elseif (!depositIsSettled($bookingId)) {
            // Deposit gate — mirrors the bookings list so neither path can skip it.
            flashMessage('bk_error', "This booking cannot be confirmed — the customer's reservation deposit has not been paid.");
        } else {
            // assigned_at is set here too, matching the bookings list confirm paths.
            getDB()->prepare("UPDATE bookings SET status = 'confirmed', technician_id = ?, assigned_at = NOW() WHERE id = ?")->execute([$techId, $bookingId]);
            $scheduledDate = date('M j, Y', strtotime($booking['scheduled_date']));
            createNotification(
                $techId,
                "New job assigned: Booking #$bookingId for {$booking['customer_name']} on $scheduledDate.",
                'assignment',
                $bookingId
            );

            // Tell the customer. Delivery failure must not undo the confirmation.
            $note = '';
            try {
                $delivery = notifyAppointmentConfirmed($bookingId);
                $parts = [];
                foreach (['sms' => 'SMS', 'email' => 'Email'] as $channel => $label) {
                    switch ($delivery[$channel] ?? '') {
                        case 'sent':      $parts[] = "$label sent"; break;
                        case 'failed':    $parts[] = "$label failed"; break;
                        case 'skipped':   $parts[] = "$label skipped"; break;
                        case 'duplicate': $parts[] = "$label already sent"; break;
                    }
                }
                $note = $parts ? ' Customer notification — ' . implode(', ', $parts) . '.' : '';
            } catch (Throwable $e) {
                smsLogLine("Confirmation notification failed for booking {$bookingId}: " . $e->getMessage());
                $note = ' Customer notification could not be sent (logged).';
            }

            // Reserve parts for this booking (non-fatal)
            try { partsReserveForBooking($bookingId); } catch (Throwable $e) {}

            flashMessage('bk_success', "Booking #$bookingId confirmed and assigned to {$tech['name']}." . $note);
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

// Qualification data for the technician dropdown (Feature 5)
$bookingServiceIds = techBookingServiceIds($bookingId);
$qualMap           = techQualificationMap();
$autoSuggest       = techAutoAssignCandidate($bookingId);

// Load reservation data for the parts card (Feature 4)
$reservedParts = partsGetForBooking($bookingId);

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

<div class="mtx-shell">

<?php if ($flash): ?><div class="alert success"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if ($flashErr): ?><div class="alert error"><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>

<header class="mtx-page-head">
  <div class="mtx-page-head-copy" style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
    <a href="<?= baseUrl('staff/bookings.php') ?>" class="mtx-btn mtx-btn--ghost mtx-btn--sm"><i class="fas fa-arrow-left"></i> Back to Bookings</a>
    <h1 style="margin:0;">Booking #<?= $bookingId ?></h1>
    <span class="mtx-pill" style="--pill-color:<?= $color ?>;font-size:.82rem;">
      <?= ucfirst(str_replace('_', ' ', $booking['status'])) ?>
    </span>
  </div>
</header>

<div class="job-detail-grid">

  <!-- LEFT COLUMN: Info cards -->
  <div class="mtx-stack">

    <!-- Customer -->
    <section class="mtx-card">
      <div class="mtx-card-head"><div><h2><i class="fas fa-user"></i> Customer</h2></div></div>
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

    <!-- Reservation deposit -->
    <?php
      $depositRow  = depositLatestRow($bookingId);
      $depositPaid = depositPaidRow($bookingId);
      $depositDue  = $depositPaid ? (float)$depositPaid['amount'] : depositAmount();
    ?>
    <?php if (depositIsRequired() || $depositRow): ?>
    <section class="mtx-card">
      <div class="mtx-card-head">
        <div><h2><i class="fas fa-hand-holding-dollar"></i> Reservation Deposit</h2></div>
        <span class="mtx-pill" style="--pill-color:<?= $depositPaid ? '#15803d' : '#b45309' ?>;">
          <?= htmlspecialchars(depositStatusLabel($depositRow)) ?>
        </span>
      </div>
      <div class="detail-row"><span>Amount</span><strong><?= formatPrice($depositDue) ?></strong></div>
      <div class="detail-row">
        <span>Payment Status</span>
        <strong style="color:<?= $depositPaid ? '#15803d' : '#b45309' ?>;"><?= htmlspecialchars(depositStatusLabel($depositRow)) ?></strong>
      </div>
      <?php if ($depositPaid): ?>
        <div class="detail-row"><span>Payment Method</span><strong>PayMongo</strong></div>
        <?php if (!empty($depositPaid['payment_reference'])): ?>
          <div class="detail-row"><span>Reference</span><span style="font-family:monospace;font-size:.85rem;"><?= htmlspecialchars($depositPaid['payment_reference']) ?></span></div>
        <?php endif; ?>
        <?php if (!empty($depositPaid['paid_at'])): ?>
          <div class="detail-row"><span>Paid At</span><?= htmlspecialchars(date('M j, Y g:i A', strtotime($depositPaid['paid_at']))) ?></div>
        <?php endif; ?>
      <?php else: ?>
        <p class="subtext" style="margin:10px 0 0;">
          <i class="fas fa-triangle-exclamation" style="color:#b45309;"></i>
          This booking cannot be confirmed until the customer pays the reservation deposit.
        </p>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <!-- Motorcycle -->
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

    <!-- Services & Products -->
    <section class="mtx-card">
      <div class="mtx-card-head"><div><h2><i class="fas fa-tools"></i> Services &amp; Products</h2></div></div>
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
      <div class="mtx-total-row"><span>Booking Total</span><span><?= formatPrice((float)$booking['total_amount']) ?></span></div>
    </section>

    <!-- Tech Notes (read-only) -->
    <?php if ($booking['tech_notes']): ?>
    <section class="mtx-card" style="border-left:4px solid #15803d;">
      <div class="mtx-card-head"><div><h2><i class="fas fa-sticky-note" style="color:#15803d;"></i> Technician Notes</h2></div></div>
      <p style="margin:0;white-space:pre-line;line-height:1.7;"><?= nl2br(htmlspecialchars($booking['tech_notes'])) ?></p>
    </section>
    <?php elseif (in_array($booking['status'], ['in_progress', 'completed'], true)): ?>
    <section class="mtx-card" style="opacity:.65;">
      <div class="mtx-card-head"><div><h2><i class="fas fa-sticky-note"></i> Technician Notes</h2></div></div>
      <p class="subtext" style="margin:0;">No notes written by the technician yet.</p>
    </section>
    <?php endif; ?>
  </div>

  <!-- RIGHT COLUMN: Actions -->
  <div class="mtx-stack">

    <!-- Technician assignment card (Feature 5 — Specialization UI) -->
    <section class="mtx-card">
      <div class="mtx-card-head"><div><h2><i class="fas fa-user-cog"></i> Technician</h2></div></div>

      <?php if ($booking['technician_name']): ?>
        <div style="display:flex;align-items:center;gap:10px;padding:12px;background:#f0fdf4;border-radius:10px;margin-bottom:14px;">
          <i class="fas fa-user-cog" style="color:#15803d;font-size:1.4rem;"></i>
          <div>
            <div style="font-weight:800;"><?= htmlspecialchars($booking['technician_name']) ?></div>
            <div class="subtext"><?= htmlspecialchars($booking['technician_email'] ?? '') ?></div>
          </div>
          <?php
            $assignedTechId = (int)($booking['technician_id'] ?? 0);
            $techQuals = $qualMap[$assignedTechId] ?? [];
            $allMatch  = !$bookingServiceIds || count(array_intersect(array_keys($techQuals), $bookingServiceIds)) === count($bookingServiceIds);
          ?>
          <span style="margin-left:auto;font-size:.8rem;font-weight:700;padding:4px 10px;border-radius:20px;
            background:<?= $allMatch ? 'rgba(21,128,61,.15)' : 'rgba(217,119,6,.15)' ?>;
            color:<?= $allMatch ? '#15803d' : '#d97706' ?>">
            <?= $allMatch ? '✓ Fully Qualified' : '⚠ Partial Match' ?>
          </span>
        </div>
      <?php else: ?>
        <p class="subtext" style="margin:0 0 14px;">No technician assigned yet.</p>
      <?php endif; ?>

      <?php if (in_array($booking['status'], ['pending', 'confirmed'], true)): ?>
        <?php if ($autoSuggest): ?>
          <div style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:rgba(37,99,235,.07);border:1px dashed rgba(37,99,235,.3);border-radius:10px;margin-bottom:12px;font-size:.83rem;">
            <i class="fas fa-wand-magic-sparkles" style="color:#2563eb;"></i>
            <span>Best match: <strong><?= htmlspecialchars($autoSuggest['name']) ?></strong></span>
            <button type="button" class="mtx-btn mtx-btn--ghost mtx-btn--xs" id="autoSuggestBtn"
                    data-tech-id="<?= (int)$autoSuggest['id'] ?>">
              Use Suggestion
            </button>
          </div>
        <?php endif; ?>

        <form method="post">
          <?= authContextField() ?>
          <input type="hidden" name="action" value="<?= $booking['status'] === 'pending' ? 'confirm_booking' : 'reassign_tech' ?>">
          <label class="mtx-field" style="margin-bottom:10px;">
            <span><?= $booking['status'] === 'pending' ? 'Assign &amp; Confirm' : 'Reassign Technician' ?></span>
            <select name="technician_id" id="techSelect" required>
              <option value="">— Select Technician —</option>
              <?php foreach ($technicians as $t):
                $tid   = (int)$t['id'];
                $quals = $qualMap[$tid] ?? [];
                $match = !$bookingServiceIds || count(array_intersect(array_keys($quals), $bookingServiceIds)) === count($bookingServiceIds);
                $badge = $match ? ' ✓' : ' ⚠';
              ?>
                <option value="<?= $tid ?>" <?= (int)($booking['technician_id'] ?? 0) === $tid ? 'selected' : '' ?>>
                  <?= htmlspecialchars($t['name']) . $badge ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <p style="font-size:.75rem;color:var(--muted);margin:-4px 0 10px;">✓ = qualified for all services on this booking &nbsp;⚠ = partial match</p>
          <button type="submit" class="mtx-btn mtx-btn--primary" style="width:100%;">
            <?php if ($booking['status'] === 'pending'): ?>
              <i class="fas fa-check"></i> Confirm &amp; Assign
            <?php else: ?>
              <i class="fas fa-sync-alt"></i> Reassign Tech
            <?php endif; ?>
          </button>
        </form>
      <?php endif; ?>
    </section>

    <!-- Reserved Parts card (Feature 4) -->
    <?php if ($reservedParts): ?>
    <section class="mtx-card" style="border-left:4px solid #d97706;">
      <div class="mtx-card-head">
        <div><h2><i class="fas fa-boxes-stacked" style="color:#d97706;"></i> Reserved Parts</h2></div>
        <?php
          $heldCount = count(array_filter($reservedParts, fn($r) => $r['status'] === 'held'));
        ?>
        <span class="mtx-pill" style="--pill-color:<?= $heldCount > 0 ? '#d97706' : '#15803d' ?>;">
          <?= $heldCount > 0 ? $heldCount . ' on hold' : 'All consumed' ?>
        </span>
      </div>
      <?php foreach ($reservedParts as $rp):
        $statusIcons = ['held' => '🔒', 'consumed' => '✅', 'released' => '🔓'];
        $statusColors = ['held' => '#d97706', 'consumed' => '#15803d', 'released' => '#6b7280'];
      ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--line);">
          <span style="font-size:.85rem;">
            <?= $statusIcons[$rp['status']] ?? '' ?>
            <strong><?= htmlspecialchars($rp['product_name']) ?></strong>
            <span style="color:var(--muted);">× <?= (int)$rp['quantity'] ?></span>
          </span>
          <span style="font-size:.75rem;font-weight:700;color:<?= $statusColors[$rp['status']] ?? '#6b7280' ?>;">
            <?= ucfirst($rp['status']) ?>
            <?php if ($rp['status'] === 'held'): ?>
              <span style="color:var(--muted);font-weight:400;">| stock: <?= (int)$rp['current_stock'] ?></span>
            <?php endif; ?>
          </span>
        </div>
      <?php endforeach; ?>
      <p style="font-size:.75rem;color:var(--muted);margin-top:10px;">
        <i class="fas fa-info-circle"></i>
        Parts are released if booking is cancelled, consumed when the job is completed.
      </p>
    </section>
    <?php endif; ?>

    <!-- Status card -->
    <section class="mtx-card">
      <div class="mtx-card-head"><div><h2><i class="fas fa-timeline"></i> Status Timeline</h2></div></div>
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
          <button type="submit" class="mtx-btn mtx-btn--ghost" style="width:100%;color:#b91c1c;border-color:#f3c1c1;">
            <i class="fas fa-times"></i> Cancel This Booking
          </button>
        </form>
      <?php endif; ?>
    </section>

  </div>
</div>

</div><!-- /.mtx-shell -->

<?= authContextScriptTag() ?>
<script>
(function(){
  var btn = document.getElementById('autoSuggestBtn');
  var sel = document.getElementById('techSelect');
  if (btn && sel) {
    btn.addEventListener('click', function(){
      sel.value = this.dataset.techId;
      sel.focus();
      // Highlight selection
      sel.style.outline = '2px solid #2563eb';
      setTimeout(function(){ sel.style.outline = ''; }, 1200);
    });
  }
})();
</script>
</main></div></div></body></html>
