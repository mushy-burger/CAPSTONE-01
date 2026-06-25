<?php
$pageTitle = 'Services';
require_once __DIR__ . '/../includes/admin-sidebar.php';
require_once __DIR__ . '/../includes/db.php';

$validStatuses = ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $bookingId = (int)($_POST['booking_id'] ?? 0);

    if ($action === 'update_status' && $bookingId > 0) {
        $status = $_POST['status'] ?? '';
        if (in_array($status, $validStatuses, true)) {
            getDB()->prepare("UPDATE bookings SET status = ? WHERE id = ?")->execute([$status, $bookingId]);
            flashMessage('svc_success', 'Service request updated.');
        } else {
            flashMessage('svc_error', 'Invalid service status.');
        }

        redirect(baseUrl('admin/service-requests.php'));
    }
}

$flash = getFlash('svc_success');
$flashErr = getFlash('svc_error');
$statusFilter = $_GET['status'] ?? '';
$statusFilter = in_array($statusFilter, $validStatuses, true) ? $statusFilter : '';

$where = [];
$params = [];
if ($statusFilter !== '') {
    $where[] = 'b.status = ?';
    $params[] = $statusFilter;
}

$bookings = fetchAllRows(
    "SELECT
        b.*,
        u.name AS customer_name,
        u.email AS customer_email,
        CONCAT(mb.name, ' ', mm.name) AS vehicle_name,
        mt.name AS type_name,
        cv.cc,
        cv.plate_number,
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
       FROM booking_services
       GROUP BY booking_id
     ) svc ON svc.booking_id = b.id
     LEFT JOIN (
       SELECT booking_id, GROUP_CONCAT(product_name ORDER BY id SEPARATOR ', ') AS products
       FROM booking_products
       GROUP BY booking_id
     ) prod ON prod.booking_id = b.id
     " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
     ORDER BY b.scheduled_date ASC, b.scheduled_time ASC, b.created_at DESC",
    $params
);
?>

<section class="admin-card admin-page-stack">
  <div class="admin-page-head">
    <div>
      <h1>Service Requests</h1>
      <p>Confirm appointments, track active work, and close completed service visits.</p>
    </div>
    <form method="get" class="admin-inline-form">
      <select name="status">
        <option value="">All statuses</option>
        <?php foreach ($validStatuses as $status): ?>
          <option value="<?= htmlspecialchars($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>>
            <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $status))) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-outline">Filter</button>
      <?php if ($statusFilter): ?><a class="btn btn-outline" href="<?= baseUrl('admin/service-requests.php') ?>">Reset</a><?php endif; ?>
    </form>
  </div>

  <?php if ($flash): ?><div class="alert success"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
  <?php if ($flashErr): ?><div class="alert error"><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>

  <?php if ($bookings): ?>
    <div class="admin-table-wrap">
      <table class="admin-data-table">
        <thead>
          <tr>
            <th>Schedule</th>
            <th>Customer</th>
            <th>Motorcycle</th>
            <th>Services</th>
            <th>Products</th>
            <th>Total</th>
            <th>Status</th>
            <th>Update</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($bookings as $booking): ?>
            <?php
              $bookingId = (int)$booking['id'];
              $statusColor = [
                  'pending' => '#6b7280',
                  'confirmed' => '#2563eb',
                  'in_progress' => '#d97706',
                  'completed' => '#15803d',
                  'cancelled' => '#b91c1c',
              ][$booking['status']] ?? '#6b7280';
            ?>
            <tr>
              <td>
                <strong><?= htmlspecialchars(date('M j, Y', strtotime($booking['scheduled_date']))) ?></strong>
                <div class="subtext"><?= $booking['scheduled_time'] ? htmlspecialchars(date('g:i A', strtotime($booking['scheduled_time']))) : 'No time set' ?></div>
                <div class="subtext">#<?= $bookingId ?></div>
              </td>
              <td>
                <strong><?= htmlspecialchars($booking['customer_name']) ?></strong>
                <div class="subtext"><?= htmlspecialchars($booking['customer_email']) ?></div>
              </td>
              <td>
                <strong><?= htmlspecialchars($booking['vehicle_name'] ?: 'No vehicle') ?></strong>
                <div class="subtext">
                  <?= $booking['type_name'] ? htmlspecialchars($booking['type_name']) . ', ' . (int)$booking['cc'] . 'cc' : 'Vehicle details unavailable' ?>
                </div>
                <?php if ($booking['plate_number']): ?><div class="subtext"><?= htmlspecialchars($booking['plate_number']) ?></div><?php endif; ?>
              </td>
              <td><?= htmlspecialchars($booking['services'] ?: 'No services') ?></td>
              <td><?= htmlspecialchars($booking['products'] ?: 'No products') ?></td>
              <td><strong><?= formatPrice((float)$booking['total_amount']) ?></strong></td>
              <td><span class="status-pill" style="--status-color: <?= $statusColor ?>;"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $booking['status']))) ?></span></td>
              <td>
                <form method="post" class="admin-row-form">
                  <?= authContextField() ?>
                  <input type="hidden" name="action" value="update_status">
                  <input type="hidden" name="booking_id" value="<?= $bookingId ?>">
                  <select name="status">
                    <?php foreach ($validStatuses as $status): ?>
                      <option value="<?= htmlspecialchars($status) ?>" <?= $booking['status'] === $status ? 'selected' : '' ?>>
                        <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $status))) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" class="btn btn-outline">Save</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p class="empty-note">No service requests found.</p>
  <?php endif; ?>
</section>

</main></div></div></body></html>
