<?php
$pageTitle = 'Analytics';
require_once __DIR__ . '/../includes/admin-sidebar.php';
require_once __DIR__ . '/../includes/db.php';

// Date range filter
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo   = trim($_GET['date_to']   ?? '');
$period   = trim($_GET['period']    ?? '7');
$validPeriods = ['7'=>'Last 7 Days','30'=>'Last 30 Days','90'=>'Last 90 Days','365'=>'Last Year'];

// Build date clause
if ($dateFrom && $dateTo) {
    $dateClause  = "DATE(created_at) BETWEEN ? AND ?";
    $dateParams  = [$dateFrom, $dateTo];
    $labelPeriod = date('M j',strtotime($dateFrom)).' – '.date('M j, Y',strtotime($dateTo));
} else {
    $days        = in_array($period, array_keys($validPeriods)) ? (int)$period : 7;
    $dateClause  = "created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
    $dateParams  = [$days];
    $labelPeriod = $validPeriods[(string)$days] ?? "Last $days Days";
}

// Summary metrics
$salesTotal      = (float)(fetchOne("SELECT COALESCE(SUM(total),0) AS n FROM orders WHERE status!='cancelled'")['n'] ?? 0);
$orderCount      = (int)(fetchOne("SELECT COUNT(*) AS n FROM orders")['n'] ?? 0);
$completedOrders = (int)(fetchOne("SELECT COUNT(*) AS n FROM orders WHERE status='completed'")['n'] ?? 0);
$pendingBookings = (int)(fetchOne("SELECT COUNT(*) AS n FROM bookings WHERE status IN ('pending','confirmed','in_progress')")['n'] ?? 0);

// Period-scoped revenue
$periodRevenue = (float)(fetchOne(
    "SELECT COALESCE(SUM(total),0) AS n FROM orders WHERE status!='cancelled' AND $dateClause",
    $dateParams
)['n'] ?? 0);

$periodOrders = (int)(fetchOne(
    "SELECT COUNT(*) AS n FROM orders WHERE $dateClause",
    $dateParams
)['n'] ?? 0);

// By status
$ordersByStatus  = fetchAllRows("SELECT status, COUNT(*) AS total, COALESCE(SUM(total),0) AS sales FROM orders GROUP BY status ORDER BY FIELD(status,'pending','processing','completed','cancelled')");
$servicesByStatus = fetchAllRows("SELECT status, COUNT(*) AS total, COALESCE(SUM(total_amount),0) AS value FROM bookings GROUP BY status ORDER BY FIELD(status,'pending','confirmed','in_progress','completed','cancelled')");
$topProducts     = fetchAllRows("SELECT COALESCE(p.name,CONCAT('Product #',oi.product_id)) AS product_name, SUM(oi.quantity) AS units, SUM(oi.quantity*oi.price) AS sales FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id GROUP BY oi.product_id, p.name ORDER BY units DESC LIMIT 8");
$topServices     = fetchAllRows("SELECT COALESCE(bs.service_name,CONCAT('Service #',bs.service_id)) AS service_name, COUNT(*) AS requests, SUM(bs.labor_fee) AS labor FROM booking_services bs GROUP BY bs.service_id, bs.service_name ORDER BY requests DESC LIMIT 8");

// Daily/Period chart data
$dailySales = fetchAllRows(
    "SELECT DATE(created_at) AS sales_date, COALESCE(SUM(total),0) AS sales, COUNT(*) AS orders
     FROM orders
     WHERE $dateClause AND status!='cancelled'
     GROUP BY DATE(created_at)
     ORDER BY sales_date ASC",
    $dateParams
);

// Technician performance
$techPerformance = fetchAllRows(
    "SELECT u.name AS tech_name, COUNT(b.id) AS jobs_done, COALESCE(SUM(b.total_amount),0) AS value
     FROM bookings b
     JOIN users u ON u.id = b.technician_id
     WHERE b.status = 'completed'
     GROUP BY b.technician_id, u.name
     ORDER BY jobs_done DESC
     LIMIT 8"
);

$lowStockCount = (int)(fetchOne("SELECT COUNT(*) AS n FROM products WHERE stock<=10")['n'] ?? 0);
$lowStock      = fetchAllRows("SELECT p.name, p.stock, p.status, c.name AS category_name FROM products p JOIN categories c ON c.id=p.category_id WHERE p.stock<=10 ORDER BY p.stock ASC, p.name LIMIT 10");
?>

<section class="admin-hero">
  <div>
    <span class="eyebrow">Reporting</span>
    <h1>Sales, service, and inventory overview</h1>
    <p>Use these summaries to spot fulfillment load, best sellers, and inventory risk.</p>
  </div>
</section>

<!-- Date range filter -->
<section class="admin-card" style="margin-bottom:18px;padding:18px 24px;">
  <form method="get" style="display:flex;align-items:center;flex-wrap:wrap;gap:10px;">
    <strong style="font-size:.88rem;">Period:</strong>
    <?php foreach ($validPeriods as $k=>$label): ?>
      <a href="<?= baseUrl('admin/analytics.php?period='.$k) ?>"
         class="btn btn-outline" style="font-size:.82rem;<?= (!$dateFrom && $period==$k?'background:var(--accent);color:#fff;':'') ?>">
        <?= $label ?>
      </a>
    <?php endforeach; ?>
    <span style="color:var(--muted);font-size:.82rem;">or custom:</span>
    <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" style="font-size:.85rem;">
    <span style="color:var(--muted);">to</span>
    <input type="date" name="date_to"   value="<?= htmlspecialchars($dateTo) ?>"   style="font-size:.85rem;">
    <button type="submit" class="btn btn-primary" style="font-size:.85rem;">Apply</button>
    <?php if ($dateFrom||$dateTo): ?>
      <a href="<?= baseUrl('admin/analytics.php') ?>" class="btn btn-outline" style="font-size:.82rem;">Reset</a>
    <?php endif; ?>
  </form>
</section>

<section class="metric-grid">
  <article><span>Total Sales (All Time)</span><strong><?= formatPrice($salesTotal) ?></strong><i class="fas fa-peso-sign"></i></article>
  <article><span><?= htmlspecialchars($labelPeriod) ?> Revenue</span><strong><?= formatPrice($periodRevenue) ?></strong><i class="fas fa-chart-line"></i></article>
  <article><span><?= htmlspecialchars($labelPeriod) ?> Orders</span><strong><?= $periodOrders ?></strong><i class="fas fa-shopping-bag"></i></article>
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
        <span><?= htmlspecialchars(ucfirst(str_replace('_',' ',$row['status']))) ?></span>
        <strong><?= (int)$row['total'] ?> / <?= formatPrice((float)$row['value']) ?></strong>
      </div>
    <?php endforeach; ?>
    <?php if (!$servicesByStatus): ?><p>No bookings yet.</p><?php endif; ?>
  </div>

  <div class="admin-card">
    <h2>Top Products Sold</h2>
    <?php foreach ($topProducts as $row): ?>
      <div class="list-row">
        <span><?= htmlspecialchars($row['product_name']) ?></span>
        <strong><?= (int)$row['units'] ?> sold &mdash; <?= formatPrice((float)$row['sales']) ?></strong>
      </div>
    <?php endforeach; ?>
    <?php if (!$topProducts): ?><p>No product sales yet.</p><?php endif; ?>
  </div>

  <div class="admin-card">
    <h2>Top Services Requested</h2>
    <?php foreach ($topServices as $row): ?>
      <div class="list-row">
        <span><?= htmlspecialchars($row['service_name']) ?></span>
        <strong><?= (int)$row['requests'] ?> request<?= (int)$row['requests']===1?'':'s' ?></strong>
      </div>
    <?php endforeach; ?>
    <?php if (!$topServices): ?><p>No service requests yet.</p><?php endif; ?>
  </div>
</section>

<section class="admin-grid analytics-grid">
  <div class="admin-card">
    <h2><?= htmlspecialchars($labelPeriod) ?> — Daily Sales</h2>
    <?php if ($dailySales): ?>
      <?php foreach ($dailySales as $row): ?>
        <div class="list-row">
          <span><?= htmlspecialchars(date('M j, Y', strtotime($row['sales_date']))) ?> &middot; <?= (int)$row['orders'] ?> order<?= (int)$row['orders']===1?'':'s' ?></span>
          <strong><?= formatPrice((float)$row['sales']) ?></strong>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p>No sales in this period.</p>
    <?php endif; ?>
  </div>

  <div class="admin-card">
    <h2>Technician Performance</h2>
    <?php foreach ($techPerformance as $row): ?>
      <div class="list-row">
        <span><?= htmlspecialchars($row['tech_name']) ?></span>
        <strong><?= (int)$row['jobs_done'] ?> job<?= (int)$row['jobs_done']===1?'':'s' ?> &mdash; <?= formatPrice((float)$row['value']) ?></strong>
      </div>
    <?php endforeach; ?>
    <?php if (!$techPerformance): ?><p>No completed jobs yet.</p><?php endif; ?>
  </div>

  <div class="admin-card">
    <h2>Low-Stock Risk</h2>
    <p><?= $lowStockCount ?> product<?= $lowStockCount===1?'':'s' ?> at or below 10 units.</p>
    <?php foreach ($lowStock as $row): ?>
      <div class="list-row">
        <span><?= htmlspecialchars($row['name']) ?><span class="subtext"><?= htmlspecialchars($row['category_name']) ?></span></span>
        <strong style="color:<?= (int)$row['stock']===0?'#b91c1c':'#d97706' ?>"><?= (int)$row['stock']===0?'OUT':((int)$row['stock'].' left') ?></strong>
      </div>
    <?php endforeach; ?>
    <?php if (!$lowStock): ?><p>No low-stock products.</p><?php endif; ?>
  </div>
</section>

<?= authContextScriptTag() ?>
</main></div></div></body></html>
