<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/admin-sidebar.php';
require_once __DIR__ . '/../includes/db.php';

// orders table now has: id, user_id, subtotal, total, payment_method, payment_status, status, created_at
$totalRevenue  = (float)(fetchOne("SELECT COALESCE(SUM(total),0) AS n FROM orders WHERE payment_status='paid'")['n'] ?? 0);
$todaySales    = (float)(fetchOne("SELECT COALESCE(SUM(total),0) AS n FROM orders WHERE DATE(created_at)=CURDATE() AND payment_status='paid'")['n'] ?? 0);
$totalUsers    = (int)(fetchOne("SELECT COUNT(*) AS n FROM users WHERE role='customer'")['n'] ?? 0);
$newUsersToday = (int)(fetchOne("SELECT COUNT(*) AS n FROM users WHERE DATE(created_at)=CURDATE()")['n'] ?? 0);
$lowStockCount = (int)(fetchOne("SELECT COUNT(*) AS n FROM products WHERE stock<=10")['n'] ?? 0);
$totalOrders   = (int)(fetchOne("SELECT COUNT(*) AS n FROM orders")['n'] ?? 0);

$lowStock     = fetchAllRows("SELECT name, stock, status FROM products WHERE stock<=10 ORDER BY stock ASC LIMIT 8");
$recentOrders = fetchAllRows(
    "SELECT o.id, o.total, o.status, o.created_at, u.name AS customer_name
     FROM orders o
     LEFT JOIN users u ON u.id = o.user_id
     ORDER BY o.created_at DESC LIMIT 6"
);
$recentUsers  = fetchAllRows(
    "SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 6"
);
?>

<section class="admin-hero">
  <div>
    <span class="eyebrow">Dashboard Overview</span>
    <h1>Welcome, <?= htmlspecialchars($currentUser['name']) ?>!</h1>
    <p>Here is a live snapshot of your business today.</p>
  </div>
</section>

<section class="metric-grid">
  <article><span>Total Revenue</span><strong><?= formatPrice($totalRevenue) ?></strong><i class="fas fa-peso-sign"></i></article>
  <article><span>Today's Sales</span><strong><?= formatPrice($todaySales) ?></strong><i class="fas fa-chart-line"></i></article>
  <article><span>Total Customers</span><strong><?= $totalUsers ?></strong><i class="fas fa-users"></i></article>
  <article><span>New Today</span><strong style="<?= $newUsersToday > 0 ? 'color:#15803d' : '' ?>"><?= $newUsersToday ?></strong><i class="fas fa-user-plus"></i></article>
  <article><span>Low Stock Items</span><strong style="<?= $lowStockCount > 0 ? 'color:#d71920' : '' ?>"><?= $lowStockCount ?></strong><i class="fas fa-exclamation-triangle"></i></article>
  <article><span>Total Orders</span><strong><?= $totalOrders ?></strong><i class="fas fa-shopping-bag"></i></article>
</section>

<section class="admin-grid">
  <div class="admin-card">
    <h2>Low-Stock Alerts</h2>
    <?php if ($lowStock): ?>
      <?php foreach ($lowStock as $item): ?>
        <div class="list-row">
          <span><?= htmlspecialchars($item['name']) ?></span>
          <strong style="color:<?= (int)$item['stock'] === 0 ? '#b91c1c' : '#d97706' ?>">
            <?= (int)$item['stock'] === 0 ? 'OUT OF STOCK' : (int)$item['stock'] . ' left' ?>
          </strong>
        </div>
      <?php endforeach; ?>
      <div style="margin-top:10px;">
        <a href="<?= baseUrl('staff/products.php') ?>" class="btn btn-outline" style="font-size:.82rem;">Manage Inventory</a>
      </div>
    <?php else: ?>
      <p>✅ All products are well-stocked.</p>
    <?php endif; ?>
  </div>

  <div class="admin-card">
    <h2>Recent Orders</h2>
    <?php foreach ($recentOrders as $order): ?>
      <?php
        $sc = ['pending'=>'#6b7280','processing'=>'#d97706','completed'=>'#15803d','cancelled'=>'#b91c1c'][$order['status']] ?? '#6b7280';
      ?>
      <div class="list-row">
        <span>
          <strong>#<?= (int)$order['id'] ?></strong>
          <span class="subtext"><?= htmlspecialchars($order['customer_name'] ?? 'Guest') ?></span>
        </span>
        <span style="display:flex;flex-direction:column;align-items:flex-end;gap:2px;">
          <strong><?= formatPrice((float)$order['total']) ?></strong>
          <span style="font-size:.72rem;color:<?= $sc ?>;font-weight:900;"><?= strtoupper($order['status']) ?></span>
        </span>
      </div>
    <?php endforeach; ?>
    <?php if (!$recentOrders): ?><p>No orders yet.</p><?php endif; ?>
    <div style="margin-top:10px;">
      <a href="<?= baseUrl('admin/orders.php') ?>" class="btn btn-outline" style="font-size:.82rem;">View All Orders</a>
    </div>
  </div>

  <div class="admin-card">
    <h2>Recent Registrations</h2>
    <?php foreach ($recentUsers as $u): ?>
      <div class="list-row">
        <span>
          <strong><?= htmlspecialchars($u['name']) ?></strong>
          <span class="subtext"><?= htmlspecialchars($u['email']) ?></span>
        </span>
        <span style="font-size:.72rem;color:#6b7280;font-weight:700;"><?= htmlspecialchars(date('M j', strtotime($u['created_at']))) ?></span>
      </div>
    <?php endforeach; ?>
    <?php if (!$recentUsers): ?><p>No users yet.</p><?php endif; ?>
    <div style="margin-top:10px;">
      <a href="<?= baseUrl('admin/users.php') ?>" class="btn btn-outline" style="font-size:.82rem;">Manage Users</a>
    </div>
  </div>
</section>

</main></div></div></body></html>
