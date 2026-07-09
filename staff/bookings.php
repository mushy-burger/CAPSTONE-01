<?php
$pageTitle = 'Bookings';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/TechnicianService.php';
requireStaff();
$currentUser = getCurrentUser();

$validStatuses = ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'];

// Mark notifications as read when visiting bookings
getDB()->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0")
       ->execute([(int)$currentUser['id']]);

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

        flashMessage('bk_success', "Booking #$bookingId confirmed and manually assigned to {$tech['name']}.");
        redirect(baseUrl('staff/bookings.php'));
    }

    // CONFIRM (AUTO-ASSIGN) — system picks the fairest ready + qualified technician
    if ($action === 'confirm_auto' && $bookingId > 0) {
        $booking = fetchOne("SELECT b.*, u.name AS customer_name FROM bookings b JOIN users u ON u.id = b.user_id WHERE b.id = ?", [$bookingId]);

        if (!$booking || $booking['status'] !== 'pending') {
            flashMessage('bk_error', 'This booking cannot be confirmed (it may have already been processed).');
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

        flashMessage('bk_success', "Booking #$bookingId confirmed — auto-assigned to {$tech['name']}.");
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

<!-- Booking status KPI cards -->
<section class="bk-stats-grid">
  <?php foreach ($bookingStatCards as $card): ?>
    <article class="bk-stat-card" style="--stat-color: <?= $card['color'] ?>;">
      <span class="bk-stat-icon"><i class="fas <?= $card['icon'] ?>"></i></span>
      <span class="bk-stat-label"><?= $card['label'] ?></span>
      <span class="bk-stat-value"><?= $card['count'] ?></span>
      <span class="bk-stat-desc">
        <?= $card['desc'] ?>
        <?php if ($card['label'] !== 'Total Bookings'): ?>
          &middot; <strong><?= $statusPct($card['count']) ?>%</strong> of all
        <?php endif; ?>
      </span>
    </article>
  <?php endforeach; ?>
</section>

<section class="admin-card admin-page-stack">
  <div class="admin-page-head">
    <div>
      <h1>Bookings</h1>
      <p>Confirm appointments, assign technicians, and track active service visits.</p>
    </div>
    <form method="get" class="admin-inline-form">
      <input type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search customer or #ID">
      <select name="status">
        <option value="">All statuses</option>
        <?php foreach ($validStatuses as $s): ?>
          <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>>
            <?= ucfirst(str_replace('_', ' ', $s)) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-outline">Filter</button>
      <?php if ($search || $statusFilter): ?>
        <a href="<?= baseUrl('staff/bookings.php') ?>" class="btn btn-outline">Reset</a>
      <?php endif; ?>
    </form>
  </div>

  <?php if ($flash): ?><div class="alert success"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
  <?php if ($flashErr): ?><div class="alert error"><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>

  <?php if ($bookings): ?>
    <div class="admin-table-wrap">
      <table class="admin-data-table">
        <thead>
          <tr>
            <th>Schedule / #ID</th>
            <th>Customer</th>
            <th>Motorcycle</th>
            <th>Services / Products</th>
            <th>Total</th>
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
          ?>
            <tr <?= $highlightRow ?>>
              <td>
                <strong><?= htmlspecialchars(date('M j, Y', strtotime($b['scheduled_date']))) ?></strong>
                <div class="subtext"><?= $b['scheduled_time'] ? htmlspecialchars(date('g:i A', strtotime($b['scheduled_time']))) : 'No time set' ?></div>
                <div class="subtext">#<?= $bid ?></div>
              </td>
              <td>
                <strong><?= htmlspecialchars($b['customer_name']) ?></strong>
                <div class="subtext"><?= htmlspecialchars($b['customer_email']) ?></div>
                <?php if ($b['customer_phone']): ?>
                  <div class="subtext"><?= htmlspecialchars($b['customer_phone']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <strong><?= htmlspecialchars($b['vehicle_name'] ?: 'No vehicle') ?></strong>
                <div class="subtext"><?= $b['type_name'] ? htmlspecialchars($b['type_name']) . ' · ' . (int)$b['cc'] . 'cc' : '' ?></div>
                <?php if ($b['plate_number']): ?>
                  <div class="subtext"><?= htmlspecialchars($b['plate_number']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <?= htmlspecialchars($b['services'] ?: '—') ?>
                <?php if ($b['products']): ?>
                  <div class="subtext"><?= htmlspecialchars($b['products']) ?></div>
                <?php endif; ?>
              </td>
              <td><strong><?= formatPrice((float)$b['total_amount']) ?></strong></td>
              <td>
                <span class="status-pill" style="--status-color:<?= $color ?>;">
                  <?= ucfirst(str_replace('_', ' ', $b['status'])) ?>
                </span>
              </td>

              <!-- Technician column -->
              <td>
                <?php if ($b['technician_name']): ?>
                  <span class="subtext" style="font-weight:700;color:#15803d;">
                    <i class="fas fa-user-cog"></i> <?= htmlspecialchars($b['technician_name']) ?>
                  </span>
                <?php else: ?>
                  <span class="subtext">Unassigned</span>
                <?php endif; ?>
              </td>

              <!-- Actions column -->
              <td>
                <?php if ($isPending): ?>
                  <!-- CONFIRM = AUTO-ASSIGN the fairest ready + qualified technician -->
                  <form method="post" id="confirm-form-<?= $bid ?>">
                    <?= authContextField() ?>
                    <input type="hidden" name="action" value="confirm_auto">
                    <input type="hidden" name="booking_id" value="<?= $bid ?>">
                    <button type="submit" class="btn btn-primary" style="font-size:.8rem;padding:6px 14px;">
                      <i class="fas fa-bolt"></i> Confirm (Auto-Assign)
                    </button>
                  </form>

                  <!-- MANUAL ASSIGN — staff override, all technicians selectable -->
                  <form method="post" class="confirm-form" style="margin-top:6px;">
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
                    <button type="submit" class="btn btn-outline" style="font-size:.8rem;padding:6px 14px;margin-top:6px;">
                      <i class="fas fa-user-cog"></i> Assign
                    </button>
                  </form>
                <?php endif; ?>

                <?php if ($isCancellable): ?>
                  <form method="post" style="margin-top:6px;" onsubmit="return confirm('Cancel booking #<?= $bid ?>?');">
                    <?= authContextField() ?>
                    <input type="hidden" name="action" value="cancel_booking">
                    <input type="hidden" name="booking_id" value="<?= $bid ?>">
                    <button type="submit" class="btn btn-outline" style="font-size:.8rem;padding:6px 14px;color:#b91c1c;border-color:#b91c1c;">
                      <i class="fas fa-times"></i> Cancel
                    </button>
                  </form>
                <?php endif; ?>

                <?php if (!$isPending && !$isCancellable && $b['status'] !== 'cancelled'): ?>
                  <span class="subtext">No actions available</span>
                <?php endif; ?>
                <!-- Always show View Details -->
                <a href="<?= baseUrl('staff/booking-detail.php?id=' . $bid) ?>"
                   class="btn btn-outline" style="font-size:.8rem;padding:6px 14px;margin-top:6px;display:inline-block;">
                  <i class="fas fa-eye"></i> View
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p class="empty-note">No bookings found.</p>
  <?php endif; ?>
</section>

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
