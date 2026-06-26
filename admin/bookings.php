<?php
$pageTitle = 'Bookings Overview';
require_once __DIR__ . '/../includes/admin-sidebar.php';
require_once __DIR__ . '/../includes/db.php';

$validStatuses = ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'];

// --- Summary metrics ---
$totalBookings    = (int)(fetchOne("SELECT COUNT(*) AS n FROM bookings")['n'] ?? 0);
$pendingCount     = (int)(fetchOne("SELECT COUNT(*) AS n FROM bookings WHERE status = 'pending'")['n'] ?? 0);
$inProgressCount  = (int)(fetchOne("SELECT COUNT(*) AS n FROM bookings WHERE status = 'in_progress'")['n'] ?? 0);
$completedCount   = (int)(fetchOne("SELECT COUNT(*) AS n FROM bookings WHERE status = 'completed'")['n'] ?? 0);
$totalRevenue     = (float)(fetchOne("SELECT COALESCE(SUM(total_amount),0) AS n FROM bookings WHERE status = 'completed'")['n'] ?? 0);

// --- Filters ---
$statusFilter = $_GET['status'] ?? '';
$statusFilter = in_array($statusFilter, $validStatuses, true) ? $statusFilter : '';
$search       = trim($_GET['q'] ?? '');
$dateFrom     = trim($_GET['date_from'] ?? '');
$dateTo       = trim($_GET['date_to'] ?? '');

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
if ($dateFrom !== '') {
    $where[]  = 'b.scheduled_date >= ?';
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $where[]  = 'b.scheduled_date <= ?';
    $params[] = $dateTo;
}

$bookings = fetchAllRows(
    "SELECT
        b.*,
        u.name  AS customer_name,
        u.email AS customer_email,
        CONCAT(mb.name, ' ', mm.name) AS vehicle_name,
        mt.name AS type_name,
        svc.services,
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
     " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
     ORDER BY FIELD(b.status,'pending','confirmed','in_progress','completed','cancelled'), b.scheduled_date ASC",
    $params
);

$statusColor = [
    'pending'     => '#6b7280',
    'confirmed'   => '#2563eb',
    'in_progress' => '#d97706',
    'completed'   => '#15803d',
    'cancelled'   => '#b91c1c',
];
?>

<section class="admin-hero">
  <div>
    <span class="eyebrow">Admin — Read Only</span>
    <h1>Bookings Overview</h1>
    <p>High-level visibility of all service appointments. To manage bookings, use the Staff panel.</p>
  </div>
</section>

<!-- Metrics -->
<section class="metric-grid" style="margin-bottom:22px;">
  <article>
    <span>Total Bookings</span>
    <strong><?= $totalBookings ?></strong>
    <i class="fas fa-calendar-alt"></i>
  </article>
  <article>
    <span>Pending</span>
    <strong style="color:<?= $pendingCount > 0 ? '#d71920' : 'inherit' ?>"><?= $pendingCount ?></strong>
    <i class="fas fa-clock"></i>
  </article>
  <article>
    <span>In Progress</span>
    <strong style="color:#d97706"><?= $inProgressCount ?></strong>
    <i class="fas fa-wrench"></i>
  </article>
  <article>
    <span>Completed</span>
    <strong style="color:#15803d"><?= $completedCount ?></strong>
    <i class="fas fa-check-circle"></i>
  </article>
  <article>
    <span>Service Revenue</span>
    <strong><?= formatPrice($totalRevenue) ?></strong>
    <i class="fas fa-peso-sign"></i>
  </article>
</section>

<!-- Table -->
<section class="admin-card admin-page-stack">
  <div class="admin-page-head">
    <div>
      <h2>All Appointments</h2>
      <p>Read-only view. Booking management is done by staff.</p>
    </div>
    <form method="get" class="admin-inline-form" style="flex-wrap:wrap;gap:8px;">
      <input type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Customer name, email or #ID">
      <select name="status">
        <option value="">All statuses</option>
        <?php foreach ($validStatuses as $s): ?>
          <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $s)) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" title="From date">
      <input type="date" name="date_to"   value="<?= htmlspecialchars($dateTo) ?>"   title="To date">
      <button type="submit" class="btn btn-outline">Filter</button>
      <?php if ($search || $statusFilter || $dateFrom || $dateTo): ?>
        <a href="<?= baseUrl('admin/bookings.php') ?>" class="btn btn-outline">Reset</a>
      <?php endif; ?>
    </form>
  </div>

  <?php if ($bookings): ?>
    <div class="admin-table-wrap">
      <table class="admin-data-table">
        <thead>
          <tr>
            <th>#ID / Date</th>
            <th>Customer</th>
            <th>Motorcycle</th>
            <th>Services</th>
            <th>Total</th>
            <th>Status</th>
            <th>Technician</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($bookings as $b): ?>
            <tr>
              <td>
                <strong>#<?= (int)$b['id'] ?></strong>
                <div class="subtext"><?= htmlspecialchars(date('M j, Y', strtotime($b['scheduled_date']))) ?></div>
                <?php if ($b['scheduled_time']): ?>
                  <div class="subtext"><?= htmlspecialchars(date('g:i A', strtotime($b['scheduled_time']))) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <strong><?= htmlspecialchars($b['customer_name']) ?></strong>
                <div class="subtext"><?= htmlspecialchars($b['customer_email']) ?></div>
              </td>
              <td>
                <strong><?= htmlspecialchars($b['vehicle_name'] ?: '—') ?></strong>
                <div class="subtext"><?= htmlspecialchars($b['type_name'] ?? '') ?></div>
              </td>
              <td><?= htmlspecialchars($b['services'] ?: '—') ?></td>
              <td><strong><?= formatPrice((float)$b['total_amount']) ?></strong></td>
              <td>
                <span class="status-pill" style="--status-color:<?= $statusColor[$b['status']] ?? '#6b7280' ?>;">
                  <?= ucfirst(str_replace('_', ' ', $b['status'])) ?>
                </span>
              </td>
              <td>
                <?php if ($b['technician_name']): ?>
                  <span style="font-weight:700;color:#15803d;font-size:.88rem;">
                    <i class="fas fa-user-cog"></i> <?= htmlspecialchars($b['technician_name']) ?>
                  </span>
                <?php else: ?>
                  <span class="subtext">Unassigned</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div style="padding:12px 24px;color:var(--muted);font-size:.85rem;border-top:1px solid var(--line);">
      Showing <?= count($bookings) ?> booking<?= count($bookings) !== 1 ? 's' : '' ?>
    </div>
  <?php else: ?>
    <p class="empty-note">No bookings match your filters.</p>
  <?php endif; ?>
</section>

</main></div></div></body></html>
