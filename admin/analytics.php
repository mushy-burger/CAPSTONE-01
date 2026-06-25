<?php
$pageTitle = 'Analytics';
require_once __DIR__ . '/../includes/admin-sidebar.php';
require_once __DIR__ . '/../includes/db.php';

$salesTotal = (float)(fetchOne("SELECT COALESCE(SUM(total), 0) AS total FROM orders WHERE status != 'cancelled'")['total'] ?? 0);
$orderCount = (int)(fetchOne("SELECT COUNT(*) AS total FROM orders")['total'] ?? 0);
$completedOrders = (int)(fetchOne("SELECT COUNT(*) AS total FROM orders WHERE status = 'completed'")['total'] ?? 0);
$pendingBookings = (int)(fetchOne("SELECT COUNT(*) AS total FROM bookings WHERE status IN ('pending', 'confirmed', 'in_progress')")['total'] ?? 0);
$lowStockCount = (int)(fetchOne("SELECT COUNT(*) AS total FROM products WHERE stock <= 10")['total'] ?? 0);

$ordersByStatus = fetchAllRows(
    "SELECT status, COUNT(*) AS total, COALESCE(SUM(total), 0) AS sales
     FROM orders
     GROUP BY status
     ORDER BY FIELD(status, 'pending', 'processing', 'completed', 'cancelled')"
);

$servicesByStatus = fetchAllRows(
    "SELECT status, COUNT(*) AS total, COALESCE(SUM(total_amount), 0) AS value
     FROM bookings
     GROUP BY status
     ORDER BY FIELD(status, 'pending', 'confirmed', 'in_progress', 'completed', 'cancelled')"
);

$topProducts = fetchAllRows(
    "SELECT
        COALESCE(p.name, CONCAT('Product #', oi.product_id)) AS product_name,
        SUM(oi.quantity) AS units,
        SUM(oi.quantity * oi.price) AS sales
     FROM order_items oi
     LEFT JOIN products p ON p.id = oi.product_id
     GROUP BY oi.product_id, p.name
     ORDER BY units DESC, sales DESC
     LIMIT 8"
);

$topServices = fetchAllRows(
    "SELECT
        COALESCE(bs.service_name, CONCAT('Service #', bs.service_id)) AS service_name,
        COUNT(*) AS requests,
        SUM(bs.labor_fee) AS labor
     FROM booking_services bs
     GROUP BY bs.service_id, bs.service_name
     ORDER BY requests DESC, labor DESC
     LIMIT 8"
);

$dailySales = fetchAllRows(
    "SELECT DATE(created_at) AS sales_date, COALESCE(SUM(total), 0) AS sales, COUNT(*) AS orders
     FROM orders
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
       AND status != 'cancelled'
     GROUP BY DATE(created_at)
     ORDER BY sales_date DESC"
);

$lowStock = fetchAllRows(
    "SELECT p.name, p.stock, p.status, c.name AS category_name
     FROM products p
     JOIN categories c ON c.id = p.category_id
     WHERE p.stock <= 10
     ORDER BY p.stock ASC, p.name
     LIMIT 10"
);
?>

<section class="admin-hero">
  <div>
    <span class="eyebrow">Reporting</span>
    <h1>Sales, service, and inventory overview</h1>
    <p>Use these summaries to spot fulfillment load, best sellers, and inventory risk.</p>
  </div>
</section>

<section class="metric-grid">
  <article><span>Total Sales</span><strong><?= formatPrice($salesTotal) ?></strong><i class="fas fa-peso-sign"></i></article>
  <article><span>Total Orders</span><strong><?= $orderCount ?></strong><i class="fas fa-shopping-bag"></i></article>
  <article><span>Completed Orders</span><strong><?= $completedOrders ?></strong><i class="fas fa-check-circle"></i></article>
  <article><span>Active Services</span><strong><?= $pendingBookings ?></strong><i class="fas fa-tools"></i></article>
</section>

<section class="admin-grid analytics-grid">
  <div class="admin-card">
    <h2>Orders by Status</h2>
    <?php foreach ($ordersByStatus as $row): ?>
      <div class="list-row">
        <span><?= htmlspecialchars(ucfirst($row['status'])) ?></span>
        <strong><?= (int)$row['total'] ?> / <?= formatPrice((float)$row['sales']) ?></strong>
      </div>
    <?php endforeach; ?>
    <?php if (!$ordersByStatus): ?><p>No orders yet.</p><?php endif; ?>
  </div>

  <div class="admin-card">
    <h2>Services by Status</h2>
    <?php foreach ($servicesByStatus as $row): ?>
      <div class="list-row">
        <span><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $row['status']))) ?></span>
        <strong><?= (int)$row['total'] ?> / <?= formatPrice((float)$row['value']) ?></strong>
      </div>
    <?php endforeach; ?>
    <?php if (!$servicesByStatus): ?><p>No service bookings yet.</p><?php endif; ?>
  </div>

  <div class="admin-card">
    <h2>Top Products</h2>
    <?php foreach ($topProducts as $row): ?>
      <div class="list-row">
        <span><?= htmlspecialchars($row['product_name']) ?></span>
        <strong><?= (int)$row['units'] ?> sold</strong>
      </div>
    <?php endforeach; ?>
    <?php if (!$topProducts): ?><p>No product sales yet.</p><?php endif; ?>
  </div>

  <div class="admin-card">
    <h2>Top Services</h2>
    <?php foreach ($topServices as $row): ?>
      <div class="list-row">
        <span><?= htmlspecialchars($row['service_name']) ?></span>
        <strong><?= (int)$row['requests'] ?> request<?= (int)$row['requests'] === 1 ? '' : 's' ?></strong>
      </div>
    <?php endforeach; ?>
    <?php if (!$topServices): ?><p>No service requests yet.</p><?php endif; ?>
  </div>
</section>

<section class="admin-grid analytics-grid">
  <div class="admin-card">
    <h2>Last 7 Days</h2>
    <?php foreach ($dailySales as $row): ?>
      <div class="list-row">
        <span><?= htmlspecialchars(date('M j, Y', strtotime($row['sales_date']))) ?> · <?= (int)$row['orders'] ?> order<?= (int)$row['orders'] === 1 ? '' : 's' ?></span>
        <strong><?= formatPrice((float)$row['sales']) ?></strong>
      </div>
    <?php endforeach; ?>
    <?php if (!$dailySales): ?><p>No recent sales.</p><?php endif; ?>
  </div>

  <div class="admin-card">
    <h2>Low-stock Risk</h2>
    <p><?= $lowStockCount ?> product<?= $lowStockCount === 1 ? '' : 's' ?> at or below 10 units.</p>
    <?php foreach ($lowStock as $row): ?>
      <div class="list-row">
        <span><?= htmlspecialchars($row['name']) ?><span class="subtext"><?= htmlspecialchars($row['category_name']) ?></span></span>
        <strong><?= (int)$row['stock'] ?> left</strong>
      </div>
    <?php endforeach; ?>
    <?php if (!$lowStock): ?><p>No low-stock products.</p><?php endif; ?>
  </div>
</section>

</main></div></div></body></html>
