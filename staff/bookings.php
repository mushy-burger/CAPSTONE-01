<?php
$pageTitle = 'Bookings';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/TechnicianService.php';
require_once __DIR__ . '/../includes/NotificationService.php';
require_once __DIR__ . '/../includes/BookingDeposit.php';
requireStaff();

/** Short, honest summary of what actually reached the customer. */
function bkDeliveryNote(array $delivery): string {
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

/**
 * Notify the customer that their appointment is confirmed.
 * Wrapped so a provider outage can never undo a confirmed booking.
 */
function bkNotifyConfirmed(int $bookingId): string {
    try {
        return bkDeliveryNote(notifyAppointmentConfirmed($bookingId));
    } catch (Throwable $e) {
        smsLogLine("Confirmation notification failed for booking {$bookingId}: " . $e->getMessage());
        return 'Customer notification could not be sent (logged).';
    }
}
$currentUser = getCurrentUser();

$validStatuses = ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'];

// Notifications stay unread until explicitly marked via the bell's
// "Mark all read" — keeps the unread-count badge meaningful.

// ---------- POST HANDLER ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $bookingId = (int)($_POST['booking_id'] ?? 0);

    // CONFIRM (MANUAL ASSIGN) — staff picks the technician themselves
    if ($action === 'confirm_booking' && $bookingId > 0) {
        $techId = (int)($_POST['technician_id'] ?? 0);

        // Verify the technician exists and is active
        $tech = $techId ? fetchOne(
            "SELECT id, name FROM users WHERE id = ? AND role = 'technician' AND is_active = 1",
            [$techId]
        ) : null;

        if (!$tech) {
            flashMessage('bk_error', 'Please select a valid technician before confirming.');
            redirect(baseUrl('staff/bookings.php'));
        }

        $booking = fetchOne("SELECT b.*, u.name AS customer_name FROM bookings b JOIN users u ON u.id = b.user_id WHERE b.id = ?", [$bookingId]);

        if (!$booking || $booking['status'] !== 'pending') {
            flashMessage('bk_error', 'This booking cannot be confirmed (it may have already been processed).');
            redirect(baseUrl('staff/bookings.php'));
        }

        // The reservation deposit must be settled before a booking is confirmed.
        if (!depositIsSettled($bookingId)) {
            flashMessage('bk_error', "Booking #$bookingId cannot be confirmed — the customer's reservation deposit has not been paid.");
            redirect(baseUrl('staff/bookings.php'));
        }

        getDB()->prepare(
            "UPDATE bookings SET status = 'confirmed', technician_id = ?, assigned_at = NOW() WHERE id = ?"
        )->execute([$techId, $bookingId]);

        // Notify the assigned technician
        $scheduledDate = date('M j, Y', strtotime($booking['scheduled_date']));
        createNotification(
            $techId,
            "New job assigned to you: Booking #$bookingId for {$booking['customer_name']} on $scheduledDate.",
            'assignment',
            $bookingId
        );

        $note = bkNotifyConfirmed($bookingId);
        flashMessage('bk_success', "Booking #$bookingId confirmed and manually assigned to {$tech['name']}." . ($note !== '' ? ' ' . $note : ''));
        redirect(baseUrl('staff/bookings.php'));
    }

    // CONFIRM (AUTO-ASSIGN) — system picks the fairest ready + qualified technician
    if ($action === 'confirm_auto' && $bookingId > 0) {
        $booking = fetchOne("SELECT b.*, u.name AS customer_name FROM bookings b JOIN users u ON u.id = b.user_id WHERE b.id = ?", [$bookingId]);

        if (!$booking || $booking['status'] !== 'pending') {
            flashMessage('bk_error', 'This booking cannot be confirmed (it may have already been processed).');
            redirect(baseUrl('staff/bookings.php'));
        }

        if (!depositIsSettled($bookingId)) {
            flashMessage('bk_error', "Booking #$bookingId cannot be confirmed — the customer's reservation deposit has not been paid.");
            redirect(baseUrl('staff/bookings.php'));
        }

        $tech = techAutoAssignCandidate($bookingId);
        if (!$tech) {
            flashMessage('bk_error', "No technician is currently Ready/On Site and qualified for all of booking #$bookingId's services. Use Manual Assign, or update technician availability/skills.");
            redirect(baseUrl('staff/bookings.php'));
        }

        getDB()->prepare(
            "UPDATE bookings SET status = 'confirmed', technician_id = ?, assigned_at = NOW() WHERE id = ?"
        )->execute([$tech['id'], $bookingId]);

        $scheduledDate = date('M j, Y', strtotime($booking['scheduled_date']));
        createNotification(
            $tech['id'],
            "New job assigned to you: Booking #$bookingId for {$booking['customer_name']} on $scheduledDate.",
            'assignment',
            $bookingId
        );

        $note = bkNotifyConfirmed($bookingId);
        flashMessage('bk_success', "Booking #$bookingId confirmed — auto-assigned to {$tech['name']}." . ($note !== '' ? ' ' . $note : ''));
        redirect(baseUrl('staff/bookings.php'));
    }

    // CANCEL BOOKING
    if ($action === 'cancel_booking' && $bookingId > 0) {
        $booking = fetchOne("SELECT * FROM bookings WHERE id = ?", [$bookingId]);
        if ($booking && in_array($booking['status'], ['pending', 'confirmed'], true)) {
            getDB()->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?")->execute([$bookingId]);
            flashMessage('bk_success', "Booking #$bookingId has been cancelled.");
        } else {
            flashMessage('bk_error', 'Only pending or confirmed bookings can be cancelled.');
        }
        redirect(baseUrl('staff/bookings.php'));
    }

    // GENERIC STATUS UPDATE (in_progress / completed)
    if ($action === 'update_status' && $bookingId > 0) {
        $newStatus = $_POST['status'] ?? '';
        if (in_array($newStatus, $validStatuses, true)) {
            if ($newStatus === 'completed') {
                getDB()->prepare("UPDATE bookings SET status = ?, completed_at = COALESCE(completed_at, NOW()) WHERE id = ?")
                       ->execute([$newStatus, $bookingId]);
            } else {
                getDB()->prepare("UPDATE bookings SET status = ? WHERE id = ?")->execute([$newStatus, $bookingId]);
            }
            flashMessage('bk_success', 'Booking status updated.');
        } else {
            flashMessage('bk_error', 'Invalid status.');
        }
        redirect(baseUrl('staff/bookings.php'));
    }
}

// ---------- CONFIRM QUICK-ACTION FROM DASHBOARD ----------
$preloadConfirmId = 0;
if (isset($_GET['action']) && $_GET['action'] === 'confirm' && isset($_GET['id'])) {
    $preloadConfirmId = (int)$_GET['id'];
}

// ---------- FILTERS ----------
$flash    = getFlash('bk_success');
$flashErr = getFlash('bk_error');
$statusFilter = $_GET['status'] ?? '';
$statusFilter = in_array($statusFilter, $validStatuses, true) ? $statusFilter : '';
$search = trim($_GET['q'] ?? '');

$where  = [];
$params = [];
if ($statusFilter !== '') {
    $where[]  = 'b.status = ?';
    $params[] = $statusFilter;
}
if ($search !== '') {
    $where[]  = '(u.name LIKE ? OR u.email LIKE ? OR b.id = ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = (int)$search;
}

$bookings = fetchAllRows(
    "SELECT
        b.*,
        u.name AS customer_name,
        u.email AS customer_email,
        u.phone AS customer_phone,
        CONCAT(mb.name, ' ', mm.name) AS vehicle_name,
        mt.name AS type_name,
        cv.cc,
        cv.plate_number,
        svc.services,
        prod.products,
        tech.name AS technician_name
     FROM bookings b
     JOIN users u ON u.id = b.user_id
     LEFT JOIN customer_vehicles cv ON cv.id = b.vehicle_id
     LEFT JOIN motorcycle_brands mb ON mb.id = cv.brand_id
     LEFT JOIN motorcycle_models mm ON mm.id = cv.model_id
     LEFT JOIN motorcycle_types mt ON mt.id = cv.type_id
     LEFT JOIN users tech ON tech.id = b.technician_id
     LEFT JOIN (
       SELECT booking_id, GROUP_CONCAT(service_name ORDER BY id SEPARATOR ', ') AS services
       FROM booking_services GROUP BY booking_id
     ) svc ON svc.booking_id = b.id
     LEFT JOIN (
       SELECT booking_id, GROUP_CONCAT(product_name ORDER BY id SEPARATOR ', ') AS products
       FROM booking_products GROUP BY booking_id
     ) prod ON prod.booking_id = b.id
     " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
     ORDER BY FIELD(b.status,'pending','confirmed','in_progress','completed','cancelled'), b.scheduled_date ASC",
    $params
);

// Technicians for assign dropdown (with availability for the manual-assign markers)
$technicians = fetchAllRows(
    "SELECT id, name, availability_status FROM users WHERE role = 'technician' AND is_active = 1 ORDER BY name"
);

// Qualification data for manual-assign warning markers: which services each tech
// can do, and which services each listed booking requires.
$techQualifications = techQualificationMap();
$bookingServiceIds = [];
foreach (fetchAllRows("SELECT booking_id, service_id FROM booking_services") as $bsRow) {
    $bookingServiceIds[(int)$bsRow['booking_id']][] = (int)$bsRow['service_id'];
}

$statusColor = [
    'pending'     => '#6b7280',
    'confirmed'   => '#2563eb',
    'in_progress' => '#d97706',
    'completed'   => '#15803d',
    'cancelled'   => '#b91c1c',
];

// KPI cards: overall booking counts by status (independent of the list filters)
$statusCounts = ['pending' => 0, 'confirmed' => 0, 'in_progress' => 0, 'completed' => 0, 'cancelled' => 0];
foreach (fetchAllRows("SELECT status, COUNT(*) AS n FROM bookings GROUP BY status") as $scRow) {
    if (isset($statusCounts[$scRow['status']])) {
        $statusCounts[$scRow['status']] = (int)$scRow['n'];
    }
}
$totalBookings = array_sum($statusCounts);
$statusPct = static fn(int $n): int => $totalBookings > 0 ? (int)round($n / $totalBookings * 100) : 0;

$bookingStatCards = [
    ['label' => 'Total Bookings', 'count' => $totalBookings,                'icon' => 'fa-layer-group',     'color' => '#d71920', 'desc' => 'All service bookings'],
    ['label' => 'Pending',        'count' => $statusCounts['pending'],      'icon' => 'fa-hourglass-half',  'color' => '#e8b93c', 'desc' => 'Awaiting confirmation'],
    ['label' => 'Confirmed',      'count' => $statusCounts['confirmed'],    'icon' => 'fa-calendar-check',  'color' => '#4f8df9', 'desc' => 'Assigned & scheduled'],
    ['label' => 'In Progress',    'count' => $statusCounts['in_progress'],  'icon' => 'fa-wrench',          'color' => '#f0883e', 'desc' => 'Being serviced now'],
    ['label' => 'Completed',      'count' => $statusCounts['completed'],    'icon' => 'fa-flag-checkered',  'color' => '#2fbf71', 'desc' => 'Finished jobs'],
    ['label' => 'Cancelled',      'count' => $statusCounts['cancelled'],    'icon' => 'fa-ban',             'color' => '#f16a6a', 'desc' => 'Called off'],
];

require_once __DIR__ . '/../includes/staff-sidebar.php';
?>

<div class="mtx-shell">

<header class="mtx-page-head">
  <div class="mtx-page-head-copy">
    <span class="eyebrow">Staff Panel</span>
    <h1>Bookings</h1>
    <p>Confirm appointments, assign technicians, and track active service visits.</p>
  </div>
  <div class="mtx-head-actions">
    <a href="<?= baseUrl('staff/new-booking.php') ?>" class="mtx-btn mtx-btn--primary"><i class="fas fa-plus"></i> New Booking</a>
  </div>
</header>

<!-- Booking status KPI cards -->
<section class="mtx-kpi-grid mtx-kpi-grid--6" aria-label="Booking status">
  <?php foreach ($bookingStatCards as $card): ?>
    <article class="mtx-kpi" style="--kpi-color: <?= $card['color'] ?>;">
      <div class="mtx-kpi-top">
        <span class="mtx-kpi-label"><?= $card['label'] ?></span>
        <span class="mtx-kpi-icon"><i class="fas <?= $card['icon'] ?>"></i></span>
      </div>
      <span class="mtx-kpi-value"><?= $card['count'] ?></span>
      <span class="mtx-kpi-sub">
        <?= $card['desc'] ?><?php if ($card['label'] !== 'Total Bookings'): ?> · <strong><?= $statusPct($card['count']) ?>%</strong><?php endif; ?>
      </span>
    </article>
  <?php endforeach; ?>
</section>

<?php if ($flash): ?><div class="alert success"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if ($flashErr): ?><div class="alert error"><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>

<section class="mtx-card mtx-card--flush">
  <div class="mtx-card-head">
    <div>
      <h2><i class="fas fa-calendar-check"></i> All Bookings</h2>
      <p>Pending first, then by schedule date.</p>
    </div>
    <form method="get" class="mtx-toolbar">
      <div class="mtx-field-search">
        <i class="fas fa-magnifying-glass"></i>
        <input type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Customer or #ID">
      </div>
      <select name="status">
        <option value="">All statuses</option>
        <?php foreach ($validStatuses as $s): ?>
          <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>>
            <?= ucfirst(str_replace('_', ' ', $s)) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="mtx-btn mtx-btn--dark">Filter</button>
      <?php if ($search || $statusFilter): ?>
        <a href="<?= baseUrl('staff/bookings.php') ?>" class="mtx-btn mtx-btn--ghost">Reset</a>
      <?php endif; ?>
    </form>
  </div>

  <?php if ($bookings): ?>
    <div class="mtx-table-wrap">
      <table class="mtx-table">
        <thead>
          <tr>
            <th>Schedule</th>
            <th>Customer</th>
            <th>Motorcycle</th>
            <th>Services / Products</th>
            <th class="num">Total</th>
            <th>Status</th>
            <th>Technician</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($bookings as $b):
            $bid    = (int)$b['id'];
            $color  = $statusColor[$b['status']] ?? '#6b7280';
            $isPending   = $b['status'] === 'pending';
            $isCancellable = in_array($b['status'], ['pending', 'confirmed'], true);
            $highlightRow = ($preloadConfirmId === $bid) ? 'style="background:#eff6ff;"' : '';
            $serviceList = $b['services'] ? array_map('trim', explode(',', $b['services'])) : [];
            $shownServices = array_slice($serviceList, 0, 2);
            $moreServices = count($serviceList) - count($shownServices);
          ?>
            <tr <?= $highlightRow ?>>
              <td>
                <div class="mtx-cell-main">
                  <strong><?= htmlspecialchars(date('M j, Y', strtotime($b['scheduled_date']))) ?></strong>
                  <span class="mtx-cell-sub"><?= $b['scheduled_time'] ? htmlspecialchars(date('g:i A', strtotime($b['scheduled_time']))) : 'No time set' ?> · #<?= $bid ?></span>
                </div>
              </td>
              <td>
                <div class="mtx-cell-main">
                  <strong><?= htmlspecialchars($b['customer_name']) ?></strong>
                  <span class="mtx-cell-sub"><?= htmlspecialchars($b['customer_email']) ?></span>
                  <?php if ($b['customer_phone']): ?>
                    <span class="mtx-cell-sub"><?= htmlspecialchars($b['customer_phone']) ?></span>
                  <?php endif; ?>
                </div>
              </td>
              <td>
                <div class="mtx-cell-main">
                  <strong><?= htmlspecialchars($b['vehicle_name'] ?: 'No vehicle') ?></strong>
                  <span class="mtx-cell-sub">
                    <?= $b['type_name'] ? htmlspecialchars($b['type_name']) . ' · ' . (int)$b['cc'] . 'cc' : '' ?><?= $b['plate_number'] ? ' · ' . htmlspecialchars($b['plate_number']) : '' ?>
                  </span>
                </div>
              </td>
              <td>
                <div class="mtx-cell-main">
                  <?php if ($shownServices): ?>
                    <span style="display:flex;gap:5px;flex-wrap:wrap;">
                      <?php foreach ($shownServices as $svcName): ?>
                        <span class="mtx-pill" style="--pill-color:#2563eb;"><?= htmlspecialchars($svcName) ?></span>
                      <?php endforeach; ?>
                      <?php if ($moreServices > 0): ?>
                        <span class="mtx-pill" style="--pill-color:#6b7280;" title="<?= htmlspecialchars(implode(', ', array_slice($serviceList, 2))) ?>">+<?= $moreServices ?> more</span>
                      <?php endif; ?>
                    </span>
                  <?php else: ?>
                    <span class="mtx-cell-sub">—</span>
                  <?php endif; ?>
                  <?php if ($b['products']): ?>
                    <span class="mtx-cell-sub" title="<?= htmlspecialchars($b['products']) ?>"><i class="fas fa-box" style="color:#d97706;"></i> <?= htmlspecialchars(mb_strlen($b['products']) > 48 ? mb_substr($b['products'], 0, 48) . '…' : $b['products']) ?></span>
                  <?php endif; ?>
                </div>
              </td>
              <td class="num"><span class="mtx-money"><?= formatPrice((float)$b['total_amount']) ?></span></td>
              <td>
                <span class="mtx-pill" style="--pill-color:<?= $color ?>;">
                  <?= ucfirst(str_replace('_', ' ', $b['status'])) ?>
                </span>
              </td>

              <!-- Technician column -->
              <td>
                <?php if ($b['technician_name']): ?>
                  <span class="mtx-pill" style="--pill-color:#15803d;"><i class="fas fa-user-cog"></i> <?= htmlspecialchars($b['technician_name']) ?></span>
                <?php else: ?>
                  <span class="mtx-cell-sub">Unassigned</span>
                <?php endif; ?>
              </td>

              <!-- Actions column -->
              <td>
                <div style="display:grid;gap:6px;justify-items:stretch;min-width:170px;">
                <?php if ($isPending): ?>
                  <!-- CONFIRM = AUTO-ASSIGN the fairest ready + qualified technician -->
                  <form method="post" id="confirm-form-<?= $bid ?>">
                    <?= authContextField() ?>
                    <input type="hidden" name="action" value="confirm_auto">
                    <input type="hidden" name="booking_id" value="<?= $bid ?>">
                    <button type="submit" class="mtx-btn mtx-btn--primary mtx-btn--sm" style="width:100%;">
                      <i class="fas fa-bolt"></i> Confirm (Auto-Assign)
                    </button>
                  </form>

                  <!-- MANUAL ASSIGN — staff override, all technicians selectable -->
                  <form method="post" class="confirm-form">
                    <?= authContextField() ?>
                    <input type="hidden" name="action" value="confirm_booking">
                    <input type="hidden" name="booking_id" value="<?= $bid ?>">
                    <?php $requiredIds = $bookingServiceIds[$bid] ?? []; ?>
                    <select name="technician_id" required class="tech-select"
                            <?= ($preloadConfirmId === $bid) ? 'autofocus' : '' ?>>
                      <option value="">— Manual Assign —</option>
                      <?php foreach ($technicians as $t): ?>
                        <?php
                          $tid = (int)$t['id'];
                          $qualified = true;
                          foreach ($requiredIds as $sid) {
                              if (!isset($techQualifications[$tid][$sid])) { $qualified = false; break; }
                          }
                          $marks = [];
                          if (!$qualified) $marks[] = '⚠ not qualified';
                          if (($t['availability_status'] ?? 'off_duty') !== 'ready') $marks[] = 'off duty';
                        ?>
                        <option value="<?= $tid ?>"><?= htmlspecialchars($t['name'] . ($marks ? ' (' . implode(', ', $marks) . ')' : '')) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button type="submit" class="mtx-btn mtx-btn--ghost mtx-btn--sm" style="width:100%;margin-top:6px;">
                      <i class="fas fa-user-cog"></i> Assign
                    </button>
                  </form>
                <?php endif; ?>

                <?php if ($isCancellable): ?>
                  <form method="post" onsubmit="return confirm('Cancel booking #<?= $bid ?>?');">
                    <?= authContextField() ?>
                    <input type="hidden" name="action" value="cancel_booking">
                    <input type="hidden" name="booking_id" value="<?= $bid ?>">
                    <button type="submit" class="mtx-btn mtx-btn--ghost mtx-btn--sm" style="width:100%;color:#b91c1c;border-color:#f3c1c1;">
                      <i class="fas fa-times"></i> Cancel
                    </button>
                  </form>
                <?php endif; ?>

                <!-- Always show View Details -->
                <a href="<?= baseUrl('staff/booking-detail.php?id=' . $bid) ?>" class="mtx-btn mtx-btn--ghost mtx-btn--sm" style="width:100%;">
                  <i class="fas fa-eye"></i> View Details
                </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="mtx-card-foot">
      <span>Showing <?= count($bookings) ?> booking<?= count($bookings) !== 1 ? 's' : '' ?><?= ($search || $statusFilter) ? ' (filtered)' : '' ?></span>
    </div>
  <?php else: ?>
    <div style="padding:24px;">
      <div class="mtx-empty">
        <i class="fas fa-calendar-xmark"></i>
        <strong>No bookings found.</strong>
        <span>Try a different search or status filter.</span>
      </div>
    </div>
  <?php endif; ?>
</section>

</div><!-- /.mtx-shell -->

<?php if ($preloadConfirmId > 0): ?>
<script>
  // Auto-scroll to the highlighted row
  document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('confirm-form-<?= $preloadConfirmId ?>');
    if (form) {
      form.scrollIntoView({ behavior: 'smooth', block: 'center' });
      var sel = form.parentElement ? form.parentElement.querySelector('select.tech-select') : null;
      if (sel) sel.focus();
    }
  });
</script>
<?php endif; ?>

<?= authContextScriptTag() ?>
</main></div></div></body></html>
