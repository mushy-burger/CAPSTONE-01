<?php
$pageTitle = 'Staff Dashboard';
require_once __DIR__ . '/../includes/staff-sidebar.php';
require_once __DIR__ . '/../includes/db.php';

// Notifications stay unread until explicitly marked via the bell's
// "Mark all read" — keeps the unread-count badge meaningful.

$pendingCount    = fetchOne("SELECT COUNT(*) AS n FROM bookings WHERE status = 'pending'")['n'] ?? 0;
$confirmedToday  = fetchOne("SELECT COUNT(*) AS n FROM bookings WHERE status = 'confirmed' AND scheduled_date = CURDATE()")['n'] ?? 0;
$inProgressCount = fetchOne("SELECT COUNT(*) AS n FROM bookings WHERE status = 'in_progress'")['n'] ?? 0;
$completedTotal  = fetchOne("SELECT COUNT(*) AS n FROM bookings WHERE status = 'completed'")['n'] ?? 0;

$pendingBookings = fetchAllRows(
    "SELECT
        b.*,
        u.name AS customer_name,
        u.email AS customer_email,
        u.phone AS customer_phone,
        CONCAT(mb.name, ' ', mm.name) AS vehicle_name,
        mt.name AS type_name,
        cv.cc,
        svc.services
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
     WHERE b.status = 'pending'
     ORDER BY b.created_at ASC
     LIMIT 10"
);

$manilaNow = new DateTime('now', new DateTimeZone('Asia/Manila'));
$hour = (int)$manilaNow->format('G');
$greeting = $hour < 12 ? 'Good Morning' : ($hour < 18 ? 'Good Afternoon' : 'Good Evening');
?>

<div class="mtx-shell">

  <header class="mtx-page-head">
    <div class="mtx-page-head-copy">
      <span class="eyebrow">Staff Panel</span>
      <h1><span data-greeting><?= $greeting ?></span>, <?= htmlspecialchars($currentUser['name']) ?></h1>
      <p>Manage bookings, products, services, and vehicle options from here.</p>
    </div>
    <div class="mtx-head-actions" style="align-items:center;">
      <?php require __DIR__ . '/../includes/mtx-clock.php'; ?>
      <a href="<?= baseUrl('staff/new-booking.php') ?>" class="mtx-btn mtx-btn--primary"><i class="fas fa-plus"></i> New Booking</a>
      <a href="<?= baseUrl('staff/bookings.php') ?>" class="mtx-btn mtx-btn--ghost"><i class="fas fa-list-check"></i> All Bookings</a>
    </div>
  </header>

  <section class="mtx-kpi-grid mtx-kpi-grid--4" aria-label="Booking status">
    <article class="mtx-kpi" style="--kpi-color:#d71920;">
      <div class="mtx-kpi-top"><span class="mtx-kpi-label">Pending Bookings</span><span class="mtx-kpi-icon"><i class="fas fa-hourglass-half"></i></span></div>
      <span class="mtx-kpi-value" style="<?= $pendingCount > 0 ? 'color:#d71920;' : '' ?>"><?= (int)$pendingCount ?></span>
      <span class="mtx-kpi-sub">Awaiting confirmation</span>
    </article>
    <article class="mtx-kpi" style="--kpi-color:#2563eb;">
      <div class="mtx-kpi-top"><span class="mtx-kpi-label">Confirmed Today</span><span class="mtx-kpi-icon"><i class="fas fa-calendar-day"></i></span></div>
      <span class="mtx-kpi-value"><?= (int)$confirmedToday ?></span>
      <span class="mtx-kpi-sub">Scheduled for today</span>
    </article>
    <article class="mtx-kpi" style="--kpi-color:#d97706;">
      <div class="mtx-kpi-top"><span class="mtx-kpi-label">In Progress</span><span class="mtx-kpi-icon"><i class="fas fa-wrench"></i></span></div>
      <span class="mtx-kpi-value" style="color:#d97706;"><?= (int)$inProgressCount ?></span>
      <span class="mtx-kpi-sub">Being serviced now</span>
    </article>
    <article class="mtx-kpi" style="--kpi-color:#15803d;">
      <div class="mtx-kpi-top"><span class="mtx-kpi-label">Completed</span><span class="mtx-kpi-icon"><i class="fas fa-check-circle"></i></span></div>
      <span class="mtx-kpi-value" style="color:#15803d;"><?= (int)$completedTotal ?></span>
      <span class="mtx-kpi-sub">All time</span>
    </article>
  </section>

  <section class="mtx-grid mtx-grid--main">
    <div class="mtx-card mtx-card--flush">
      <div class="mtx-card-head">
        <div>
          <h2><i class="fas fa-bell"></i> Pending Bookings — Needs Action</h2>
          <p>Waiting for your confirmation and technician assignment.</p>
        </div>
        <a href="<?= baseUrl('staff/bookings.php') ?>" class="mtx-btn mtx-btn--ghost mtx-btn--sm">View all</a>
      </div>

      <?php if ($pendingBookings): ?>
        <div class="mtx-table-wrap">
          <table class="mtx-table">
            <thead>
              <tr>
                <th>Schedule</th>
                <th>Customer</th>
                <th>Motorcycle</th>
                <th>Services</th>
                <th class="num">Total</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pendingBookings as $b): ?>
                <tr>
                  <td>
                    <div class="mtx-cell-main">
                      <strong><?= htmlspecialchars(date('M j, Y', strtotime($b['scheduled_date']))) ?></strong>
                      <span class="mtx-cell-sub"><?= $b['scheduled_time'] ? htmlspecialchars(date('g:i A', strtotime($b['scheduled_time']))) : 'No time' ?> · #<?= (int)$b['id'] ?></span>
                    </div>
                  </td>
                  <td>
                    <div class="mtx-cell-main">
                      <strong><?= htmlspecialchars($b['customer_name']) ?></strong>
                      <span class="mtx-cell-sub"><?= htmlspecialchars($b['customer_phone'] ?: $b['customer_email']) ?></span>
                    </div>
                  </td>
                  <td>
                    <div class="mtx-cell-main">
                      <strong><?= htmlspecialchars($b['vehicle_name'] ?: 'No vehicle') ?></strong>
                      <span class="mtx-cell-sub"><?= $b['type_name'] ? htmlspecialchars($b['type_name']) . ' · ' . (int)$b['cc'] . 'cc' : '' ?></span>
                    </div>
                  </td>
                  <td><span style="font-size:.84rem;"><?= htmlspecialchars($b['services'] ?: '—') ?></span></td>
                  <td class="num"><span class="mtx-money"><?= formatPrice((float)$b['total_amount']) ?></span></td>
                  <td class="num">
                    <a href="<?= baseUrl('staff/bookings.php?action=confirm&id=' . (int)$b['id']) ?>" class="mtx-btn mtx-btn--primary mtx-btn--sm">Confirm</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div style="padding:0 24px 24px;">
          <div class="mtx-empty">
            <i class="fas fa-check-circle" style="color:#15803d;"></i>
            <strong>All caught up!</strong>
            <span>No pending bookings right now.</span>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <div class="mtx-card">
      <div class="mtx-card-head">
        <div><h2><i class="fas fa-bolt"></i> Quick Actions</h2></div>
      </div>
      <div class="quick-actions-list">
        <a href="<?= baseUrl('staff/new-booking.php') ?>" class="quick-action">
          <i class="fas fa-plus-circle"></i><span>Create Walk-in Booking</span>
        </a>
        <a href="<?= baseUrl('staff/bookings.php?status=pending') ?>" class="quick-action">
          <i class="fas fa-hourglass-half"></i><span>Review Pending (<?= (int)$pendingCount ?>)</span>
        </a>
        <a href="<?= baseUrl('staff/products.php') ?>" class="quick-action">
          <i class="fas fa-box"></i><span>Manage Products</span>
        </a>
        <a href="<?= baseUrl('staff/services.php') ?>" class="quick-action">
          <i class="fas fa-tools"></i><span>Manage Services</span>
        </a>
        <a href="<?= baseUrl('staff/vehicles.php') ?>" class="quick-action">
          <i class="fas fa-motorcycle"></i><span>Vehicle Options</span>
        </a>
      </div>
    </div>
  </section>

</div><!-- /.mtx-shell -->

<?= authContextScriptTag() ?>
</main></div></div></body></html>
