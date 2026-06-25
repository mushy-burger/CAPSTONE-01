<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/admin-sidebar.php';

$todaySales = fetchOne("SELECT COALESCE(SUM(total),0) AS total FROM orders WHERE DATE(created_at) = CURDATE()")['total'] ?? 0;
$totalOrders = fetchOne("SELECT COUNT(*) AS total FROM orders")['total'] ?? 0;
$pendingServices = fetchOne("SELECT COUNT(*) AS total FROM bookings WHERE status = 'pending'")['total'] ?? 0;
$products = fetchOne("SELECT COUNT(*) AS total FROM products")['total'] ?? 0;
$lowStock = fetchAllRows("SELECT name, stock FROM products WHERE stock <= 10 ORDER BY stock ASC LIMIT 6");
$recentBookings = fetchAllRows(
    "SELECT
        b.*,
        u.name AS customer,
        GROUP_CONCAT(bs.service_name ORDER BY bs.id SEPARATOR ', ') AS services
     FROM bookings b
     JOIN users u ON u.id = b.user_id
     LEFT JOIN booking_services bs ON bs.booking_id = b.id
     GROUP BY b.id, u.name
     ORDER BY b.created_at DESC
     LIMIT 6"
);
?>

<section class="admin-hero">
  <div>
    <span class="eyebrow">Dashboard Overview</span>
    <h1>Welcome, <?= htmlspecialchars($currentUser['name']) ?>!</h1>
    <p>Here is what is happening with your business today.</p>
  </div>
</section>

<section class="metric-grid">
  <article><span>Today's Sales</span><strong><?= formatPrice((float)$todaySales) ?></strong><i class="fas fa-peso-sign"></i></article>
  <article><span>Total Orders</span><strong><?= (int)$totalOrders ?></strong><i class="fas fa-shopping-bag"></i></article>
  <article><span>Pending Services</span><strong><?= (int)$pendingServices ?></strong><i class="fas fa-clock"></i></article>
  <article><span>Products</span><strong><?= (int)$products ?></strong><i class="fas fa-box"></i></article>
</section>

<section class="admin-grid">
  <div class="admin-card">
    <h2>Low-stock alerts</h2>
    <?php if ($lowStock): ?>
      <?php foreach ($lowStock as $item): ?>
        <div class="list-row"><span><?= htmlspecialchars($item['name']) ?></span><strong><?= (int)$item['stock'] ?> left</strong></div>
      <?php endforeach; ?>
    <?php else: ?>
      <p>No low-stock products.</p>
    <?php endif; ?>
  </div>
  <div class="admin-card">
    <h2>Recent service requests</h2>
    <?php foreach ($recentBookings as $booking): ?>
      <div class="list-row">
        <span><?= htmlspecialchars($booking['customer']) ?> - <?= htmlspecialchars($booking['services'] ?: 'No services') ?></span>
        <strong><?= htmlspecialchars($booking['status']) ?></strong>
      </div>
    <?php endforeach; ?>
    <?php if (!$recentBookings): ?><p>No service requests yet.</p><?php endif; ?>
  </div>
</section>

</main>
</div>
</div>
</body>
</html>
