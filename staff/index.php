<?php
$pageTitle = 'Staff Dashboard';
require_once __DIR__ . '/../includes/staff-sidebar.php';
require_once __DIR__ . '/../includes/db.php';

// Mark all notifications as read when visiting dashboard
getDB()->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0")
       ->execute([(int)$currentUser['id']]);

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
?>

<section class="admin-hero">
  <div>
    <span class="eyebrow">Staff Panel</span>
    <h1>Welcome, <?= htmlspecialchars($currentUser['name']) ?>!</h1>
    <p>Manage bookings, products, services, and vehicle options from here.</p>
  </div>
</section>

<section class="metric-grid">
  <article>
    <span>Pending Bookings</span>
    <strong style="color:<?= $pendingCount > 0 ? '#d71920' : 'inherit' ?>"><?= (int)$pendingCount ?></strong>
    <i class="fas fa-clock"></i>
  </article>
  <article>
    <span>Confirmed Today</span>
    <strong><?= (int)$confirmedToday ?></strong>
    <i class="fas fa-calendar-day"></i>
  </article>
  <article>
    <span>In Progress</span>
    <strong style="color:#d97706"><?= (int)$inProgressCount ?></strong>
    <i class="fas fa-wrench"></i>
  </article>
  <article>
    <span>Completed (All)</span>
    <strong style="color:#15803d"><?= (int)$completedTotal ?></strong>
    <i class="fas fa-check-circle"></i>
  </article>
</section>

<section class="admin-card admin-page-stack" style="margin-top:22px;">
  <div class="admin-page-head">
    <div>
      <h2>Pending Bookings — Needs Action</h2>
      <p>These bookings are waiting for your confirmation and technician assignment.</p>
    </div>
    <a href="<?= baseUrl('staff/bookings.php') ?>" class="btn btn-primary">View All Bookings</a>
  </div>

  <?php if ($pendingBookings): ?>
    <div class="admin-table-wrap">
      <table class="admin-data-table">
        <thead>
          <tr>
            <th>Schedule</th>
            <th>Customer</th>
            <th>Motorcycle</th>
            <th>Services</th>
            <th>Total</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pendingBookings as $b): ?>
            <tr>
              <td>
                <strong><?= htmlspecialchars(date('M j, Y', strtotime($b['scheduled_date']))) ?></strong>
                <div class="subtext"><?= $b['scheduled_time'] ? htmlspecialchars(date('g:i A', strtotime($b['scheduled_time']))) : 'No time' ?></div>
                <div class="subtext">#<?= (int)$b['id'] ?></div>
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
              </td>
              <td><?= htmlspecialchars($b['services'] ?: '—') ?></td>
              <td><strong><?= formatPrice((float)$b['total_amount']) ?></strong></td>
              <td>
                <a href="<?= baseUrl('staff/bookings.php?action=confirm&id=' . (int)$b['id']) ?>" class="btn btn-primary" style="font-size:.8rem;padding:6px 14px;">
                  Confirm
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div style="padding:32px;text-align:center;color:var(--muted);">
      <i class="fas fa-check-circle" style="font-size:2.5rem;color:#15803d;margin-bottom:12px;display:block;"></i>
      <strong>All caught up!</strong><br>No pending bookings right now.
    </div>
  <?php endif; ?>
</section>

</main></div></div></body></html>
