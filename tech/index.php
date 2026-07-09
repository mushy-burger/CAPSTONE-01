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

<section class="admin-hero" style="display:flex;justify-content:space-between;align-items:flex-start;gap:20px;flex-wrap:wrap;">
  <div>
    <span class="eyebrow">Technician Panel</span>
    <h1>Work Queue</h1>
    <p>Jobs assigned to you. Complete your active jobs and log any notes.</p>
  </div>
  <div style="text-align:right;">
    <span class="status-pill" style="--status-color:<?= $isReady ? '#2563eb' : '#6b7280' ?>;font-size:.9rem;">
      <?= $isReady ? '🟢 Ready / On Site' : '⚪ Off Duty' ?>
    </span>
    <form method="post" class="admin-toggle-form">
      <?= authContextField() ?>
      <input type="hidden" name="action" value="toggle_availability">
      <label>
        <input type="checkbox" name="is_ready" value="1" <?= $isReady ? 'checked' : '' ?> onchange="this.form.submit()">
        Available for new jobs
      </label>
    </form>
  </div>
</section>

<?php if ($flash): ?><div class="alert success"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if ($flashErr): ?><div class="alert error"><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>

<!-- MY STATS & EARNINGS (visible only to me) -->
<section class="metric-grid" style="grid-template-columns:repeat(6, minmax(0,1fr));">
  <article><span>Customers Today</span><strong><?= $customersServicedToday ?></strong><i class="fas fa-user-check"></i></article>
  <article><span>Pending Jobs</span><strong><?= $pendingJobsCount ?></strong><i class="fas fa-hourglass-half"></i></article>
  <article><span>Completed Jobs</span><strong><?= $completedJobsCount ?></strong><i class="fas fa-check-double"></i></article>
  <article><span>Today's Earnings</span><strong style="font-size:1.35rem;"><?= formatPrice($earningsToday) ?></strong><i class="fas fa-peso-sign"></i></article>
  <article><span>This Week</span><strong style="font-size:1.35rem;"><?= formatPrice($earningsWeek) ?></strong><i class="fas fa-calendar-week"></i></article>
  <article><span>This Month</span><strong style="font-size:1.35rem;"><?= formatPrice($earningsMonth) ?></strong><i class="fas fa-calendar"></i></article>
</section>

<?php if ($earningsPerService): ?>
<section class="admin-card" style="margin-bottom:28px;padding:24px;">
  <h2 style="margin-top:0;">My Earnings per Service</h2>
  <p class="subtext" style="margin:0 0 10px;">Your <?= (int)(TECH_LABOR_SHARE * 100) ?>% share of the labor fee on completed jobs.</p>
  <?php foreach ($earningsPerService as $eps): ?>
    <div class="list-row">
      <span>
        <?= htmlspecialchars($eps['service_name']) ?>
        <span class="subtext"><?= $eps['jobs'] ?> job<?= $eps['jobs'] === 1 ? '' : 's' ?></span>
      </span>
      <strong><?= formatPrice($eps['earnings']) ?></strong>
    </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<!-- ACTIVE & CONFIRMED JOBS -->
<section class="admin-card admin-page-stack" style="margin-bottom:28px;">
  <div class="admin-page-head" style="padding:22px 24px 0;">
    <h2 style="margin:0;">Active Jobs (<?= count($activeJobs) ?>)</h2>
  </div>

  <?php if ($activeJobs): ?>
    <div style="display:grid;gap:18px;padding:20px 24px;">
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
            <a href="<?= baseUrl('tech/job.php?id=' . $bid) ?>" class="btn btn-outline">
              <i class="fas fa-eye"></i> View / Add Notes
            </a>
            <?php if ($isConfirmed): ?>
              <form method="post" style="display:inline;">
                <?= authContextField() ?>
                <input type="hidden" name="action" value="start_job">
                <input type="hidden" name="booking_id" value="<?= $bid ?>">
                <button type="submit" class="btn btn-primary">
                  <i class="fas fa-play"></i> Start Job
                </button>
              </form>
            <?php elseif ($isInProgress): ?>
              <a href="<?= baseUrl('tech/job.php?id=' . $bid) ?>" class="btn btn-primary">
                <i class="fas fa-flag-checkered"></i> Complete Job
              </a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div style="padding:48px;text-align:center;color:var(--muted);">
      <i class="fas fa-check-double" style="font-size:3rem;color:#15803d;margin-bottom:14px;display:block;"></i>
      <strong>No active jobs!</strong><br>You have no confirmed or in-progress assignments right now.
    </div>
  <?php endif; ?>
</section>

<!-- RECENTLY COMPLETED -->
<?php if ($completedJobs): ?>
<section class="admin-card admin-page-stack">
  <div class="admin-page-head" style="padding:18px 24px 0;">
    <h2 style="margin:0;">Recently Completed</h2>
  </div>
  <div class="admin-table-wrap">
    <table class="admin-data-table">
      <thead>
        <tr><th>Date</th><th>Customer</th><th>Motorcycle</th><th>Services</th><th>Status</th></tr>
      </thead>
      <tbody>
        <?php foreach ($completedJobs as $b): ?>
          <tr>
            <td>
              <strong><?= htmlspecialchars(date('M j, Y', strtotime($b['scheduled_date']))) ?></strong>
              <div class="subtext">#<?= (int)$b['id'] ?></div>
            </td>
            <td><?= htmlspecialchars($b['customer_name']) ?></td>
            <td><?= htmlspecialchars($b['vehicle_name'] ?: '—') ?></td>
            <td><?= htmlspecialchars($b['services'] ?: '—') ?></td>
            <td><span class="status-pill" style="--status-color:#15803d;">Completed</span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>

<?= authContextScriptTag() ?>
</main></div></div></body></html>
