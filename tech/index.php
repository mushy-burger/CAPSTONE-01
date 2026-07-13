<?php
$pageTitle = 'Work Queue';
require_once __DIR__ . '/../includes/tech-sidebar.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/TechnicianService.php';

// Mark tech's notifications as read
getDB()->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0")
       ->execute([(int)$currentUser['id']]);

// Quick status update from work queue
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $bookingId = (int)($_POST['booking_id'] ?? 0);

    if ($action === 'start_job' && $bookingId > 0) {
        // Verify this booking is assigned to this technician
        $b = fetchOne(
            "SELECT id FROM bookings WHERE id = ? AND technician_id = ? AND status = 'confirmed'",
            [$bookingId, $currentUser['id']]
        );
        if ($b) {
            getDB()->prepare("UPDATE bookings SET status = 'in_progress' WHERE id = ?")->execute([$bookingId]);
            flashMessage('tech_success', "Job #$bookingId is now In Progress.");
        }
        redirect(baseUrl('tech/index.php'));
    }

    // Ready / Off Duty availability toggle (only 'ready' techs receive auto-assignments)
    if ($action === 'toggle_availability') {
        $newAvailability = isset($_POST['is_ready']) ? 'ready' : 'off_duty';
        getDB()->prepare("UPDATE users SET availability_status = ? WHERE id = ?")
               ->execute([$newAvailability, $currentUser['id']]);
        flashMessage('tech_success', $newAvailability === 'ready'
            ? 'You are now Ready / On Site — you can receive new job assignments.'
            : 'You are now Off Duty — you will not receive automatic assignments.');
        redirect(baseUrl('tech/index.php'));
    }
}

$flash    = getFlash('tech_success');
$flashErr = getFlash('tech_error');

// Fetch assigned jobs (confirmed + in_progress + completed for reference)
$activeJobs = fetchAllRows(
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
        svc.services,
        prod.products
     FROM bookings b
     JOIN users u ON u.id = b.user_id
     LEFT JOIN customer_vehicles cv ON cv.id = b.vehicle_id
     LEFT JOIN motorcycle_brands mb ON mb.id = cv.brand_id
     LEFT JOIN motorcycle_models mm ON mm.id = cv.model_id
     LEFT JOIN motorcycle_types mt ON mt.id = cv.type_id
     LEFT JOIN (
       SELECT booking_id, GROUP_CONCAT(service_name ORDER BY id SEPARATOR ', ') AS services
       FROM booking_services GROUP BY booking_id
     ) svc ON svc.booking_id = b.id
     LEFT JOIN (
       SELECT booking_id, GROUP_CONCAT(product_name ORDER BY id SEPARATOR ', ') AS products
       FROM booking_products GROUP BY booking_id
     ) prod ON prod.booking_id = b.id
     WHERE b.technician_id = ?
       AND b.status IN ('confirmed','in_progress')
     ORDER BY FIELD(b.status,'in_progress','confirmed'), b.scheduled_date ASC, b.scheduled_time ASC",
    [$currentUser['id']]
);

$completedJobs = fetchAllRows(
    "SELECT
        b.*,
        u.name AS customer_name,
        CONCAT(mb.name, ' ', mm.name) AS vehicle_name,
        svc.services
     FROM bookings b
     JOIN users u ON u.id = b.user_id
     LEFT JOIN customer_vehicles cv ON cv.id = b.vehicle_id
     LEFT JOIN motorcycle_brands mb ON mb.id = cv.brand_id
     LEFT JOIN motorcycle_models mm ON mm.id = cv.model_id
     LEFT JOIN (
       SELECT booking_id, GROUP_CONCAT(service_name ORDER BY id SEPARATOR ', ') AS services
       FROM booking_services GROUP BY booking_id
     ) svc ON svc.booking_id = b.id
     WHERE b.technician_id = ? AND b.status = 'completed'
     ORDER BY b.scheduled_date DESC
     LIMIT 10",
    [$currentUser['id']]
);

$statusColor = [
    'confirmed'   => '#2563eb',
    'in_progress' => '#d97706',
    'completed'   => '#15803d',
];

// --- My availability + my stats/earnings (this technician only) ---
$myAvailability = fetchOne("SELECT availability_status FROM users WHERE id = ?", [$currentUser['id']])['availability_status'] ?? 'off_duty';
$isReady = $myAvailability === 'ready';

$techId = (int)$currentUser['id'];
$customersServicedToday = (int)(fetchOne(
    "SELECT COUNT(DISTINCT user_id) AS n FROM bookings
     WHERE technician_id = ? AND status = 'completed'
       AND completed_at IS NOT NULL AND DATE(completed_at) = CURDATE()",
    [$techId]
)['n'] ?? 0);
$pendingJobsCount   = (int)(fetchOne("SELECT COUNT(*) AS n FROM bookings WHERE technician_id = ? AND status = 'confirmed'", [$techId])['n'] ?? 0);
$completedJobsCount = (int)(fetchOne("SELECT COUNT(*) AS n FROM bookings WHERE technician_id = ? AND status = 'completed'", [$techId])['n'] ?? 0);

$today      = date('Y-m-d');
$weekStart  = date('Y-m-d', strtotime('monday this week'));
$monthStart = date('Y-m-01');
$earningsToday = techEarningsBetween($techId, $today, $today);
$earningsWeek  = techEarningsBetween($techId, $weekStart, $today);
$earningsMonth = techEarningsBetween($techId, $monthStart, $today);
$earningsPerService = techEarningsPerService($techId);
?>

<div class="mtx-shell">

<header class="mtx-page-head">
  <div class="mtx-page-head-copy">
    <span class="eyebrow">Technician Panel</span>
    <h1>Work Queue</h1>
    <p>Jobs assigned to you. Complete your active jobs and log any notes.</p>
  </div>
  <div class="mtx-head-actions" style="align-items:center;">
    <span class="mtx-pill" style="--pill-color:<?= $isReady ? '#15803d' : '#6b7280' ?>;font-size:.82rem;">
      <i class="fas fa-circle"></i> <?= $isReady ? 'Ready / On Site' : 'Off Duty' ?>
    </span>
    <form method="post" class="admin-toggle-form" style="margin:0;">
      <?= authContextField() ?>
      <input type="hidden" name="action" value="toggle_availability">
      <label class="mtx-switch">
        <input type="checkbox" name="is_ready" value="1" <?= $isReady ? 'checked' : '' ?> onchange="this.form.submit()">
        <span class="mtx-switch-track"></span>
        Available for new jobs
      </label>
    </form>
  </div>
</header>

<?php if ($flash): ?><div class="alert success"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if ($flashErr): ?><div class="alert error"><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>

<!-- MY STATS & EARNINGS (visible only to me) -->
<section class="mtx-kpi-grid mtx-kpi-grid--6" aria-label="My stats and earnings">
  <article class="mtx-kpi" style="--kpi-color:#0369a1;">
    <div class="mtx-kpi-top"><span class="mtx-kpi-label">Customers Today</span><span class="mtx-kpi-icon"><i class="fas fa-user-check"></i></span></div>
    <span class="mtx-kpi-value"><?= $customersServicedToday ?></span>
  </article>
  <article class="mtx-kpi" style="--kpi-color:#d97706;">
    <div class="mtx-kpi-top"><span class="mtx-kpi-label">Pending Jobs</span><span class="mtx-kpi-icon"><i class="fas fa-hourglass-half"></i></span></div>
    <span class="mtx-kpi-value"><?= $pendingJobsCount ?></span>
  </article>
  <article class="mtx-kpi" style="--kpi-color:#15803d;">
    <div class="mtx-kpi-top"><span class="mtx-kpi-label">Completed Jobs</span><span class="mtx-kpi-icon"><i class="fas fa-check-double"></i></span></div>
    <span class="mtx-kpi-value"><?= $completedJobsCount ?></span>
  </article>
  <article class="mtx-kpi mtx-kpi--featured">
    <div class="mtx-kpi-top"><span class="mtx-kpi-label">Today's Earnings</span><span class="mtx-kpi-icon"><i class="fas fa-peso-sign"></i></span></div>
    <span class="mtx-kpi-value"><?= formatPrice($earningsToday) ?></span>
  </article>
  <article class="mtx-kpi" style="--kpi-color:#2563eb;">
    <div class="mtx-kpi-top"><span class="mtx-kpi-label">This Week</span><span class="mtx-kpi-icon"><i class="fas fa-calendar-week"></i></span></div>
    <span class="mtx-kpi-value" style="font-size:1.3rem;"><?= formatPrice($earningsWeek) ?></span>
  </article>
  <article class="mtx-kpi" style="--kpi-color:#7c3aed;">
    <div class="mtx-kpi-top"><span class="mtx-kpi-label">This Month</span><span class="mtx-kpi-icon"><i class="fas fa-calendar"></i></span></div>
    <span class="mtx-kpi-value" style="font-size:1.3rem;"><?= formatPrice($earningsMonth) ?></span>
  </article>
</section>

<?php if ($earningsPerService): ?>
<section class="mtx-card">
  <div class="mtx-card-head">
    <div>
      <h2><i class="fas fa-coins"></i> My Earnings per Service</h2>
      <p>Your <?= (int)(TECH_LABOR_SHARE * 100) ?>% share of the labor fee on completed jobs.</p>
    </div>
  </div>
  <div class="mtx-list">
    <?php foreach ($earningsPerService as $i => $eps): ?>
      <div class="mtx-list-row">
        <span class="mtx-list-rank">#<?= $i + 1 ?></span>
        <span class="mtx-list-main">
          <strong><?= htmlspecialchars($eps['service_name']) ?></strong>
          <span><?= $eps['jobs'] ?> job<?= $eps['jobs'] === 1 ? '' : 's' ?></span>
        </span>
        <span class="mtx-list-end"><strong class="mtx-money--green"><?= formatPrice($eps['earnings']) ?></strong></span>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- ACTIVE & CONFIRMED JOBS -->
<section class="mtx-card mtx-card--flush">
  <div class="mtx-card-head">
    <div>
      <h2><i class="fas fa-wrench"></i> Active Jobs (<?= count($activeJobs) ?>)</h2>
      <p>Confirmed and in-progress assignments, soonest first.</p>
    </div>
  </div>

  <?php if ($activeJobs): ?>
    <div style="display:grid;gap:16px;padding:0 24px 24px;">
      <?php foreach ($activeJobs as $b): ?>
        <?php
          $bid   = (int)$b['id'];
          $color = $statusColor[$b['status']] ?? '#6b7280';
          $isConfirmed   = $b['status'] === 'confirmed';
          $isInProgress  = $b['status'] === 'in_progress';
        ?>
        <div class="job-card">
          <div class="job-card-header">
            <div>
              <span class="status-pill" style="--status-color:<?= $color ?>;">
                <?= ucfirst(str_replace('_', ' ', $b['status'])) ?>
              </span>
              <span class="job-id">#<?= $bid ?></span>
            </div>
            <strong class="job-date"><?= htmlspecialchars(date('M j, Y', strtotime($b['scheduled_date']))) ?>
              <?= $b['scheduled_time'] ? '· ' . htmlspecialchars(date('g:i A', strtotime($b['scheduled_time']))) : '' ?>
            </strong>
          </div>

          <div class="job-card-body">
            <div class="job-info-col">
              <div class="job-label">Customer</div>
              <div><strong><?= htmlspecialchars($b['customer_name']) ?></strong></div>
              <?php if ($b['customer_phone']): ?>
                <div class="subtext"><?= htmlspecialchars($b['customer_phone']) ?></div>
              <?php endif; ?>
            </div>
            <div class="job-info-col">
              <div class="job-label">Motorcycle</div>
              <div><strong><?= htmlspecialchars($b['vehicle_name'] ?: 'No vehicle') ?></strong></div>
              <div class="subtext">
                <?= $b['type_name'] ? htmlspecialchars($b['type_name']) . ' · ' . (int)$b['cc'] . 'cc' : '' ?>
                <?= $b['year'] ? ' · ' . (int)$b['year'] : '' ?>
              </div>
              <?php if ($b['plate_number']): ?>
                <div class="subtext"><strong><?= htmlspecialchars($b['plate_number']) ?></strong></div>
              <?php endif; ?>
            </div>
            <div class="job-info-col">
              <div class="job-label">Services</div>
              <div><?= htmlspecialchars($b['services'] ?: '—') ?></div>
              <?php if ($b['products']): ?>
                <div class="subtext"><?= htmlspecialchars($b['products']) ?></div>
              <?php endif; ?>
            </div>
            <?php if ($b['notes']): ?>
              <div class="job-info-col">
                <div class="job-label">Customer Notes</div>
                <div class="subtext"><?= htmlspecialchars($b['notes']) ?></div>
              </div>
            <?php endif; ?>
          </div>

          <div class="job-card-footer">
            <a href="<?= baseUrl('tech/job.php?id=' . $bid) ?>" class="mtx-btn mtx-btn--ghost mtx-btn--sm">
              <i class="fas fa-eye"></i> View Details / Notes
            </a>
            <?php if ($isConfirmed): ?>
              <form method="post" style="display:inline;">
                <?= authContextField() ?>
                <input type="hidden" name="action" value="start_job">
                <input type="hidden" name="booking_id" value="<?= $bid ?>">
                <button type="submit" class="mtx-btn mtx-btn--primary mtx-btn--sm">
                  <i class="fas fa-play"></i> Start Job
                </button>
              </form>
            <?php elseif ($isInProgress): ?>
              <a href="<?= baseUrl('tech/job.php?id=' . $bid) ?>" class="mtx-btn mtx-btn--primary mtx-btn--sm" style="background:#15803d;box-shadow:0 6px 16px rgba(21,128,61,.24);">
                <i class="fas fa-flag-checkered"></i> Complete Job
              </a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div style="padding:0 24px 24px;">
      <div class="mtx-empty">
        <i class="fas fa-check-double" style="color:#15803d;"></i>
        <strong>No active jobs!</strong>
        <span>You have no confirmed or in-progress assignments right now.</span>
      </div>
    </div>
  <?php endif; ?>
</section>

<!-- RECENTLY COMPLETED -->
<?php if ($completedJobs): ?>
<section class="mtx-card mtx-card--flush">
  <div class="mtx-card-head">
    <div>
      <h2><i class="fas fa-flag-checkered"></i> Recently Completed</h2>
      <p>Your last <?= count($completedJobs) ?> finished job<?= count($completedJobs) === 1 ? '' : 's' ?>.</p>
    </div>
    <a href="<?= baseUrl('tech/history.php') ?>" class="mtx-btn mtx-btn--ghost mtx-btn--sm">Full history</a>
  </div>
  <div class="mtx-table-wrap">
    <table class="mtx-table">
      <thead>
        <tr><th>Date</th><th>Customer</th><th>Motorcycle</th><th>Services</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($completedJobs as $b): ?>
          <tr>
            <td>
              <div class="mtx-cell-main">
                <strong><?= htmlspecialchars(date('M j, Y', strtotime($b['scheduled_date']))) ?></strong>
                <span class="mtx-cell-sub">#<?= (int)$b['id'] ?></span>
              </div>
            </td>
            <td><?= htmlspecialchars($b['customer_name']) ?></td>
            <td><?= htmlspecialchars($b['vehicle_name'] ?: '—') ?></td>
            <td><span style="font-size:.84rem;"><?= htmlspecialchars($b['services'] ?: '—') ?></span></td>
            <td><span class="mtx-pill" style="--pill-color:#15803d;">Completed</span></td>
            <td class="num"><a href="<?= baseUrl('tech/job.php?id=' . (int)$b['id']) ?>" class="mtx-btn mtx-btn--ghost mtx-btn--sm"><i class="fas fa-eye"></i> View Details</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>

</div><!-- /.mtx-shell -->

<?= authContextScriptTag() ?>
</main></div></div></body></html>
