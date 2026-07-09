<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/admin-sidebar.php';
require_once __DIR__ . '/../includes/db.php';

// Only returns a trend when a real historical baseline exists (avoids showing misleading 0%/infinite swings)
function dashTrend(float $current, float $previous): ?array {
    if ($previous <= 0) return null;
    $pct = (($current - $previous) / $previous) * 100;
    if (abs($pct) < 0.5) return null;
    return ['up' => $pct > 0, 'pct' => abs(round($pct))];
}

function renderTrend(?array $trend, string $label): string {
    if (!$trend) return '';
    $icon = $trend['up'] ? '&#9650;' : '&#9660;';
    $cls  = $trend['up'] ? 'trend-up' : 'trend-down';
    $sign = $trend['up'] ? '+' : '-';
    return '<span class="metric-trend ' . $cls . '">' . $icon . ' ' . $sign . $trend['pct'] . '% ' . htmlspecialchars($label) . '</span>';
}

// --- Core KPIs ---
$totalRevenue  = (float)(fetchOne("SELECT COALESCE(SUM(total),0) AS n FROM orders WHERE payment_status='paid'")['n'] ?? 0);
$todaySales    = (float)(fetchOne("SELECT COALESCE(SUM(total),0) AS n FROM orders WHERE DATE(created_at)=CURDATE() AND payment_status='paid'")['n'] ?? 0);
$totalUsers    = (int)(fetchOne("SELECT COUNT(*) AS n FROM users WHERE role='customer'")['n'] ?? 0);
$newUsersToday = (int)(fetchOne("SELECT COUNT(*) AS n FROM users WHERE DATE(created_at)=CURDATE()")['n'] ?? 0);
$lowStockCount = (int)(fetchOne("SELECT COUNT(*) AS n FROM products WHERE stock <= min_stock")['n'] ?? 0);
$totalOrders   = (int)(fetchOne("SELECT COUNT(*) AS n FROM orders")['n'] ?? 0);

// --- KPI trend comparisons (only rendered when a real historical baseline exists) ---
$revenueThisWeek = (float)(fetchOne("SELECT COALESCE(SUM(total),0) AS n FROM orders WHERE payment_status='paid' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)")['n'] ?? 0);
$revenueLastWeek = (float)(fetchOne("SELECT COALESCE(SUM(total),0) AS n FROM orders WHERE payment_status='paid' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) AND created_at < DATE_SUB(CURDATE(), INTERVAL 6 DAY)")['n'] ?? 0);
$revenueTrend = dashTrend($revenueThisWeek, $revenueLastWeek);

$salesYesterday = (float)(fetchOne("SELECT COALESCE(SUM(total),0) AS n FROM orders WHERE payment_status='paid' AND DATE(created_at)=DATE_SUB(CURDATE(), INTERVAL 1 DAY)")['n'] ?? 0);
$salesTrend = dashTrend($todaySales, $salesYesterday);

$newUsersYesterday = (int)(fetchOne("SELECT COUNT(*) AS n FROM users WHERE DATE(created_at)=DATE_SUB(CURDATE(), INTERVAL 1 DAY)")['n'] ?? 0);
$newUsersTrend = dashTrend($newUsersToday, $newUsersYesterday);

$ordersThisWeek = (int)(fetchOne("SELECT COUNT(*) AS n FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)")['n'] ?? 0);
$ordersLastWeek = (int)(fetchOne("SELECT COUNT(*) AS n FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) AND created_at < DATE_SUB(CURDATE(), INTERVAL 6 DAY)")['n'] ?? 0);
$ordersTrend = dashTrend((float)$ordersThisWeek, (float)$ordersLastWeek);

// --- Greeting / live snapshot line ---
$hour = (int)date('G');
$greeting = $hour < 12 ? 'Good Morning' : ($hour < 18 ? 'Good Afternoon' : 'Good Evening');
$todayLabel = date('l, F j, Y');
$timeLabel = date('g:i A');

// --- Low stock: compact preview (top 6 most critical) + full list for the modal, driven by per-product min_stock ---
$lowStock = fetchAllRows(
    "SELECT name, stock, min_stock, status FROM products
     WHERE stock <= min_stock
     ORDER BY (CAST(stock AS SIGNED) - CAST(min_stock AS SIGNED)) ASC, stock ASC LIMIT 6"
);
$lowStockFull = fetchAllRows(
    "SELECT p.name, p.stock, p.min_stock, p.status, p.price, c.name AS category_name
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.stock <= p.min_stock
     ORDER BY (CAST(p.stock AS SIGNED) - CAST(p.min_stock AS SIGNED)) ASC, p.stock ASC"
);

// --- Recent Orders ---
$recentOrders = fetchAllRows(
    "SELECT o.id, o.total, o.status, o.payment_status, o.created_at, u.name AS customer_name
     FROM orders o
     LEFT JOIN users u ON u.id = o.user_id
     ORDER BY o.created_at DESC LIMIT 6"
);

// --- Revenue Summary modal: daily revenue for the last 14 days + best-selling items (paid orders only) ---
$revenueByDay = fetchAllRows(
    "SELECT DATE(created_at) AS d, COALESCE(SUM(total),0) AS revenue
     FROM orders
     WHERE payment_status='paid' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
     GROUP BY DATE(created_at)
     ORDER BY d ASC"
);
$topItems = fetchAllRows(
    "SELECT COALESCE(p.name, CONCAT('Product #', oi.product_id)) AS product_name,
            SUM(oi.quantity) AS qty_sold,
            SUM(oi.quantity * oi.price) AS revenue
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     LEFT JOIN products p ON p.id = oi.product_id
     WHERE o.payment_status='paid'
     GROUP BY oi.product_id, product_name
     ORDER BY revenue DESC
     LIMIT 6"
);
$totalTransactions = (int)(fetchOne("SELECT COUNT(*) AS n FROM orders WHERE payment_status='paid'")['n'] ?? 0);
$avgOrderValue = $totalTransactions > 0 ? $totalRevenue / $totalTransactions : 0;
$last30Revenue = (float)(fetchOne("SELECT COALESCE(SUM(total),0) AS n FROM orders WHERE payment_status='paid' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)")['n'] ?? 0);
$avgDailyRevenue = $last30Revenue / 30;
$highestSalesDay = fetchOne(
    "SELECT DATE(created_at) AS d, SUM(total) AS revenue
     FROM orders WHERE payment_status='paid'
     GROUP BY DATE(created_at) ORDER BY revenue DESC LIMIT 1"
);

// --- Orders Overview modal: breakdown by status ---
$ordersByStatus = fetchAllRows("SELECT status, COUNT(*) AS n, COALESCE(SUM(total),0) AS revenue FROM orders GROUP BY status");
$ordersByStatusMap = [];
foreach ($ordersByStatus as $row) {
    $ordersByStatusMap[$row['status']] = $row;
}

// --- Line items for the recent orders, keyed by order id, for the Recent Orders detail modal ---
$recentOrderIds = array_map('intval', array_column($recentOrders, 'id'));
$recentOrderDetails = [];
if ($recentOrderIds) {
    $placeholders = implode(',', array_fill(0, count($recentOrderIds), '?'));
    $itemRows = fetchAllRows(
        "SELECT oi.order_id, oi.quantity, oi.price,
                COALESCE(p.name, CONCAT('Product #', oi.product_id)) AS product_name
         FROM order_items oi
         LEFT JOIN products p ON p.id = oi.product_id
         WHERE oi.order_id IN ($placeholders)
         ORDER BY oi.id",
        $recentOrderIds
    );
    $itemsByOrderId = [];
    foreach ($itemRows as $item) {
        $itemsByOrderId[(int)$item['order_id']][] = $item;
    }
    foreach ($recentOrders as $order) {
        $oid = (int)$order['id'];
        $recentOrderDetails[$oid] = [
            'id'            => $oid,
            'customer'      => $order['customer_name'] ?? 'Guest',
            'date'          => date('M j, Y g:i A', strtotime($order['created_at'])),
            'status'        => $order['status'],
            'paymentStatus' => $order['payment_status'] ?? 'unpaid',
            'total'         => formatPrice((float)$order['total']),
            'items'         => array_map(fn($it) => [
                'name'     => $it['product_name'],
                'quantity' => (int)$it['quantity'],
                'price'    => formatPrice((float)$it['price']),
                'subtotal' => formatPrice((float)$it['price'] * (int)$it['quantity']),
            ], $itemsByOrderId[$oid] ?? []),
        ];
    }
}

// --- Today's Shop Activity ---
// Note: bookings has no completed_at column, so "completed today" is approximated as
// a booking scheduled for today that is now marked completed.
$todaysBookingsCount  = (int)(fetchOne("SELECT COUNT(*) AS n FROM bookings WHERE scheduled_date = CURDATE()")['n'] ?? 0);
$servicesInProgress   = (int)(fetchOne("SELECT COUNT(*) AS n FROM bookings WHERE status='in_progress'")['n'] ?? 0);
$completedToday       = (int)(fetchOne("SELECT COUNT(*) AS n FROM bookings WHERE status='completed' AND scheduled_date = CURDATE()")['n'] ?? 0);
$ordersWaiting        = (int)(fetchOne("SELECT COUNT(*) AS n FROM orders WHERE status='pending'")['n'] ?? 0);
$customersServedToday = (int)(fetchOne(
    "SELECT COUNT(DISTINCT user_id) AS n FROM (
        SELECT user_id FROM bookings WHERE scheduled_date = CURDATE() AND status='completed' AND user_id IS NOT NULL
        UNION
        SELECT user_id FROM orders WHERE DATE(created_at) = CURDATE() AND payment_status='paid' AND user_id IS NOT NULL
     ) x"
)['n'] ?? 0);

// --- Technician Snapshot ---
// No availability column exists on users, so "busy" is computed as technicians currently
// holding a confirmed/in-progress booking; everyone else counts as available.
$totalTechnicians = (int)(fetchOne("SELECT COUNT(*) AS n FROM users WHERE role='technician' AND is_active=1")['n'] ?? 0);
$busyTechnicians = (int)(fetchOne("SELECT COUNT(DISTINCT technician_id) AS n FROM bookings WHERE status IN ('confirmed','in_progress') AND technician_id IS NOT NULL")['n'] ?? 0);
$availableTechnicians = max(0, $totalTechnicians - $busyTechnicians);

// --- Total Customers modal: customers served per day (last 14 days), for day-by-day review ---
$customersServedByDay = fetchAllRows(
    "SELECT day, COUNT(DISTINCT user_id) AS n FROM (
        SELECT scheduled_date AS day, user_id FROM bookings
         WHERE status = 'completed' AND user_id IS NOT NULL AND scheduled_date >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
        UNION ALL
        SELECT DATE(created_at) AS day, user_id FROM orders
         WHERE payment_status = 'paid' AND user_id IS NOT NULL AND created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
    ) served
    GROUP BY day
    ORDER BY day ASC"
);
$customersServedTotal14d = array_sum(array_column($customersServedByDay, 'n'));
$avgCustomersServedPerDay = $customersServedTotal14d / 14;
$newCustomersThisWeek = (int)(fetchOne("SELECT COUNT(*) AS n FROM users WHERE role='customer' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)")['n'] ?? 0);
$busiestCustomerDay = null;
foreach ($customersServedByDay as $row) {
    if ($busiestCustomerDay === null || (int)$row['n'] > (int)$busiestCustomerDay['n']) {
        $busiestCustomerDay = $row;
    }
}
?>

<section class="admin-hero admin-hero-live">
  <div>
    <span class="eyebrow">Dashboard Overview</span>
    <h1><?= $greeting ?>, <?= htmlspecialchars($currentUser['name']) ?>!</h1>
    <p class="admin-hero-datetime"><i class="fas fa-calendar"></i> <?= $todayLabel ?> &bull; <i class="fas fa-clock"></i> <?= $timeLabel ?></p>
    <p class="admin-hero-summary">
      Here's a live snapshot of your motorcycle shop today &mdash;
      <strong><?= formatPrice($todaySales) ?></strong> in sales,
      <strong><?= $todaysBookingsCount ?></strong> booking<?= $todaysBookingsCount === 1 ? '' : 's' ?> scheduled,
      and <strong><?= $lowStockCount ?></strong> item<?= $lowStockCount === 1 ? '' : 's' ?> needing restock.
    </p>
  </div>
</section>

<section class="metric-grid">
  <article class="metric-tile-clickable" data-open-modal="revenueModal" role="button" tabindex="0" aria-haspopup="dialog">
    <span>Total Revenue</span>
    <strong><?= formatPrice($totalRevenue) ?></strong>
    <?= renderTrend($revenueTrend, 'from last week') ?>
    <i class="fas fa-peso-sign"></i>
    <em class="metric-hint">View summary</em>
  </article>
  <article>
    <span>Today's Sales</span>
    <strong><?= formatPrice($todaySales) ?></strong>
    <?= renderTrend($salesTrend, 'from yesterday') ?>
    <i class="fas fa-chart-line"></i>
  </article>
  <article class="metric-tile-clickable" data-open-modal="customersModal" role="button" tabindex="0" aria-haspopup="dialog">
    <span>Total Customers</span>
    <strong><?= $totalUsers ?></strong>
    <i class="fas fa-users"></i>
    <em class="metric-hint">View trends</em>
  </article>
  <article>
    <span>New Today</span>
    <strong style="<?= $newUsersToday > 0 ? 'color:#15803d' : '' ?>"><?= $newUsersToday ?></strong>
    <?= renderTrend($newUsersTrend, 'from yesterday') ?>
    <i class="fas fa-user-plus"></i>
  </article>
  <article class="metric-tile-clickable" data-open-modal="lowStockModal" role="button" tabindex="0" aria-haspopup="dialog">
    <span>Low Stock Items</span>
    <strong style="<?= $lowStockCount > 0 ? 'color:#d71920' : '' ?>"><?= $lowStockCount ?></strong>
    <i class="fas fa-exclamation-triangle"></i>
    <em class="metric-hint">View items</em>
  </article>
  <article class="metric-tile-clickable" data-open-modal="ordersModal" role="button" tabindex="0" aria-haspopup="dialog">
    <span>Total Orders</span>
    <strong><?= $totalOrders ?></strong>
    <?= renderTrend($ordersTrend, 'from last week') ?>
    <i class="fas fa-shopping-bag"></i>
    <em class="metric-hint">View details</em>
  </article>
</section>

<section class="dashboard-grid">
  <div class="admin-card dashboard-span-2">
    <div class="admin-card-head">
      <h2>Recent Orders</h2>
      <p class="card-subnote">Click an order to see what was purchased.</p>
    </div>
    <?php foreach ($recentOrders as $order): ?>
      <?php
        $sc = ['pending'=>'#6b7280','processing'=>'#d97706','completed'=>'#15803d','cancelled'=>'#b91c1c'][$order['status']] ?? '#6b7280';
        $psColor = ($order['payment_status'] ?? '') === 'paid' ? '#15803d' : '#d97706';
      ?>
      <div class="list-row list-row-clickable order-row" data-order-row="<?= (int)$order['id'] ?>" role="button" tabindex="0" aria-haspopup="dialog">
        <span class="order-row-main">
          <strong>#<?= (int)$order['id'] ?></strong>
          <span class="subtext"><?= htmlspecialchars($order['customer_name'] ?? 'Guest') ?></span>
          <span class="subtext"><?= htmlspecialchars(date('M j, Y g:i A', strtotime($order['created_at']))) ?></span>
        </span>
        <span class="order-row-end">
          <strong><?= formatPrice((float)$order['total']) ?></strong>
          <span class="order-row-badges">
            <span class="status-pill" style="--status-color:<?= $sc ?>;"><?= ucfirst($order['status']) ?></span>
            <span class="status-pill" style="--status-color:<?= $psColor ?>;"><?= strtoupper($order['payment_status'] ?? 'UNPAID') ?></span>
          </span>
        </span>
      </div>
    <?php endforeach; ?>
    <?php if (!$recentOrders): ?><p class="empty-note">No orders yet.</p><?php endif; ?>
    <div style="margin-top:10px;">
      <a href="<?= baseUrl('admin/orders.php') ?>" class="btn btn-outline" style="font-size:.82rem;">View All Orders</a>
    </div>
  </div>

  <div class="admin-card quick-actions-card">
    <h2>Quick Actions</h2>
    <div class="quick-actions-list">
      <a href="<?= baseUrl('staff/products.php') ?>#tab-manage" class="quick-action">
        <i class="fas fa-box"></i><span>Add Product</span>
      </a>
      <a href="<?= baseUrl('admin/settings.php?tab=services') ?>" class="quick-action">
        <i class="fas fa-tools"></i><span>Add Service</span>
      </a>
      <a href="<?= baseUrl('admin/bookings.php') ?>" class="quick-action">
        <i class="fas fa-list-check"></i><span>View Work Queue</span>
      </a>
      <a href="<?= baseUrl('admin/analytics.php') ?>" class="quick-action">
        <i class="fas fa-chart-bar"></i><span>View Analytics</span>
      </a>
    </div>
  </div>

  <div class="admin-card">
    <h2>Low-Stock Alerts</h2>
    <?php if ($lowStock): ?>
      <div class="stock-alert-list">
        <?php foreach ($lowStock as $item): ?>
          <?php
            $isCritical = (int)$item['stock'] === 0;
            $badgeColor = $isCritical ? '#b91c1c' : '#d97706';
            $badgeIcon  = $isCritical ? '&#128308;' : '&#128992;';
            $badgeLabel = $isCritical ? 'Critical' : 'Low';
          ?>
          <div class="stock-alert-row">
            <div class="stock-alert-name">
              <?= htmlspecialchars($item['name']) ?>
              <span class="subtext"><?= (int)$item['stock'] ?> in stock &middot; min <?= (int)$item['min_stock'] ?></span>
            </div>
            <span class="stock-badge" style="--badge-color:<?= $badgeColor ?>;"><?= $badgeIcon ?> <?= $badgeLabel ?></span>
          </div>
        <?php endforeach; ?>
      </div>
      <div style="margin-top:10px;">
        <button type="button" class="btn btn-outline" style="font-size:.82rem;" data-open-modal="lowStockModal">View All (<?= $lowStockCount ?>)</button>
      </div>
    <?php else: ?>
      <p class="empty-note">&#9989; All products are well-stocked.</p>
    <?php endif; ?>
  </div>

  <div class="admin-card">
    <h2>Today's Shop Activity</h2>
    <div class="activity-list">
      <div class="activity-row"><span><i class="fas fa-calendar-day"></i> Today's Bookings</span><strong><?= $todaysBookingsCount ?></strong></div>
      <div class="activity-row"><span><i class="fas fa-spinner"></i> Services In Progress</span><strong><?= $servicesInProgress ?></strong></div>
      <div class="activity-row"><span><i class="fas fa-check-circle"></i> Completed Services</span><strong><?= $completedToday ?></strong></div>
      <div class="activity-row"><span><i class="fas fa-hourglass-half"></i> Orders Waiting</span><strong><?= $ordersWaiting ?></strong></div>
      <div class="activity-row"><span><i class="fas fa-user-check"></i> Customers Served</span><strong><?= $customersServedToday ?></strong></div>
    </div>
  </div>

  <div class="admin-card">
    <h2>Technician Snapshot</h2>
    <?php if ($totalTechnicians > 0): ?>
      <div class="activity-list">
        <div class="activity-row"><span><i class="fas fa-user-check" style="color:#15803d;"></i> Available</span><strong><?= $availableTechnicians ?></strong></div>
        <div class="activity-row"><span><i class="fas fa-user-clock" style="color:#d97706;"></i> Busy</span><strong><?= $busyTechnicians ?></strong></div>
        <div class="activity-row"><span><i class="fas fa-check-double"></i> Completed Jobs Today</span><strong><?= $completedToday ?></strong></div>
        <div class="activity-row"><span><i class="fas fa-tools"></i> Active Jobs</span><strong><?= $servicesInProgress ?></strong></div>
      </div>
    <?php else: ?>
      <p class="empty-note">No technicians on staff yet.</p>
    <?php endif; ?>
  </div>
</section>

<!-- Revenue Summary modal -->
<div class="dash-modal" id="revenueModal" aria-hidden="true">
  <div class="dash-modal__backdrop" data-close-modal></div>
  <div class="dash-modal__dialog">
    <button type="button" class="dash-modal__close" data-close-modal aria-label="Close">&times;</button>
    <h2><i class="fas fa-peso-sign"></i> Revenue Summary</h2>
    <p class="dash-modal__subtitle">Where your paid revenue is coming from &mdash; last 14 days.</p>
    <div class="dash-modal__body">
      <div class="dash-stat-row">
        <div class="dash-stat"><span>Average Order Value</span><strong><?= formatPrice($avgOrderValue) ?></strong></div>
        <div class="dash-stat"><span>Avg. Daily Revenue (30d)</span><strong><?= formatPrice($avgDailyRevenue) ?></strong></div>
        <div class="dash-stat"><span>Total Transactions</span><strong><?= $totalTransactions ?></strong></div>
        <div class="dash-stat">
          <span>Highest Sales Day</span>
          <?php if ($highestSalesDay): ?>
            <strong><?= formatPrice((float)$highestSalesDay['revenue']) ?></strong>
            <em class="dash-stat-sub"><?= htmlspecialchars(date('M j, Y', strtotime($highestSalesDay['d']))) ?></em>
          <?php else: ?>
            <strong>&mdash;</strong>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($revenueByDay): ?>
        <div class="dash-chart-wrap"><canvas id="revenueByDayChart" aria-label="Revenue by day, last 14 days"></canvas></div>
      <?php else: ?>
        <div class="dash-empty-state">
          <i class="fas fa-chart-line"></i>
          <p>No paid orders in the last 14 days yet.</p>
          <span>Revenue trends will appear here once orders come in.</span>
        </div>
      <?php endif; ?>
      <h3 class="dash-section-title">Top Selling Items</h3>
      <?php if ($topItems): ?>
        <div class="dash-rank-list">
          <?php foreach ($topItems as $i => $item): ?>
            <div class="dash-rank-row">
              <span class="dash-rank-badge">#<?= $i + 1 ?></span>
              <span class="dash-rank-name"><?= htmlspecialchars($item['product_name']) ?></span>
              <span class="dash-rank-qty"><?= (int)$item['qty_sold'] ?> sold</span>
              <strong class="dash-rank-revenue"><?= formatPrice((float)$item['revenue']) ?></strong>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="dash-empty-state">
          <i class="fas fa-box-open"></i>
          <p>No items sold yet.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Low Stock Items modal -->
<div class="dash-modal" id="lowStockModal" aria-hidden="true">
  <div class="dash-modal__backdrop" data-close-modal></div>
  <div class="dash-modal__dialog">
    <button type="button" class="dash-modal__close" data-close-modal aria-label="Close">&times;</button>
    <h2><i class="fas fa-exclamation-triangle"></i> Low Stock Items</h2>
    <p class="dash-modal__subtitle">Products at or below their minimum stock level, most critical first.</p>
    <div class="dash-modal__body">
      <?php if ($lowStockFull): ?>
        <div class="dash-rank-list">
          <?php foreach ($lowStockFull as $item): ?>
            <?php
              $isCritical = (int)$item['stock'] === 0;
              $badgeColor = $isCritical ? '#b91c1c' : '#d97706';
              $badgeIcon  = $isCritical ? '&#128308;' : '&#128992;';
              $badgeLabel = $isCritical ? 'Critical' : 'Low';
            ?>
            <div class="dash-rank-row">
              <span class="dash-rank-name">
                <?= htmlspecialchars($item['name']) ?>
                <span class="subtext"><?= htmlspecialchars($item['category_name'] ?? 'Uncategorized') ?> &middot; <?= formatPrice((float)$item['price']) ?></span>
              </span>
              <span class="dash-rank-qty"><?= (int)$item['stock'] ?> / min <?= (int)$item['min_stock'] ?></span>
              <span class="stock-badge" style="--badge-color:<?= $badgeColor ?>;"><?= $badgeIcon ?> <?= $badgeLabel ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="empty-note">&#9989; All products are well-stocked.</p>
      <?php endif; ?>
      <div style="margin-top:14px;">
        <a href="<?= baseUrl('staff/products.php') ?>" class="btn btn-outline" style="font-size:.82rem;">Manage Inventory</a>
      </div>
    </div>
  </div>
</div>

<!-- Total Orders modal -->
<div class="dash-modal" id="ordersModal" aria-hidden="true">
  <div class="dash-modal__backdrop" data-close-modal></div>
  <div class="dash-modal__dialog">
    <button type="button" class="dash-modal__close" data-close-modal aria-label="Close">&times;</button>
    <h2><i class="fas fa-shopping-bag"></i> Orders Overview</h2>
    <p class="dash-modal__subtitle">All <?= $totalOrders ?> orders, broken down by status.</p>
    <div class="dash-modal__body">
      <div class="dash-status-grid">
        <?php
          $statusMeta = [
            'pending'    => ['label' => 'Pending',    'color' => '#6b7280'],
            'processing' => ['label' => 'Processing', 'color' => '#d97706'],
            'completed'  => ['label' => 'Completed',  'color' => '#15803d'],
            'cancelled'  => ['label' => 'Cancelled',  'color' => '#b91c1c'],
          ];
        ?>
        <?php foreach ($statusMeta as $key => $meta): ?>
          <?php $s = $ordersByStatusMap[$key] ?? ['n' => 0, 'revenue' => 0]; ?>
          <div class="dash-status-chip" style="--chip-color:<?= $meta['color'] ?>">
            <span><?= $meta['label'] ?></span>
            <strong><?= (int)$s['n'] ?></strong>
            <em><?= formatPrice((float)$s['revenue']) ?></em>
          </div>
        <?php endforeach; ?>
      </div>
      <div style="margin-top:14px;">
        <a href="<?= baseUrl('admin/orders.php') ?>" class="btn btn-outline" style="font-size:.82rem;">View Full Order Details</a>
      </div>
    </div>
  </div>
</div>

<!-- Total Customers modal -->
<div class="dash-modal" id="customersModal" aria-hidden="true">
  <div class="dash-modal__backdrop" data-close-modal></div>
  <div class="dash-modal__dialog">
    <button type="button" class="dash-modal__close" data-close-modal aria-label="Close">&times;</button>
    <h2><i class="fas fa-users"></i> Customer Trends</h2>
    <p class="dash-modal__subtitle">How many customers you served each day &mdash; last 14 days.</p>
    <div class="dash-modal__body">
      <div class="dash-stat-row">
        <div class="dash-stat"><span>Total Registered Customers</span><strong><?= $totalUsers ?></strong></div>
        <div class="dash-stat"><span>New This Week</span><strong><?= $newCustomersThisWeek ?></strong></div>
        <div class="dash-stat"><span>Avg. Served / Day (14d)</span><strong><?= number_format($avgCustomersServedPerDay, 1) ?></strong></div>
        <div class="dash-stat">
          <span>Busiest Day</span>
          <?php if ($busiestCustomerDay): ?>
            <strong><?= (int)$busiestCustomerDay['n'] ?> customer<?= (int)$busiestCustomerDay['n'] === 1 ? '' : 's' ?></strong>
            <em class="dash-stat-sub"><?= htmlspecialchars(date('M j, Y', strtotime($busiestCustomerDay['day']))) ?></em>
          <?php else: ?>
            <strong>&mdash;</strong>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($customersServedByDay): ?>
        <div class="dash-chart-wrap"><canvas id="customersByDayChart" aria-label="Customers served by day, last 14 days"></canvas></div>
      <?php else: ?>
        <div class="dash-empty-state">
          <i class="fas fa-users"></i>
          <p>No customers served in the last 14 days yet.</p>
          <span>This fills in as bookings and orders are completed.</span>
        </div>
      <?php endif; ?>
      <h3 class="dash-section-title">Day-by-Day Breakdown</h3>
      <?php if ($customersServedByDay): ?>
        <div class="dash-rank-list">
          <?php foreach (array_reverse($customersServedByDay) as $row): ?>
            <div class="dash-rank-row">
              <span class="dash-rank-name"><?= htmlspecialchars(date('l, M j, Y', strtotime($row['day']))) ?></span>
              <strong class="dash-rank-revenue"><?= (int)$row['n'] ?> customer<?= (int)$row['n'] === 1 ? '' : 's' ?></strong>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="dash-empty-state">
          <i class="fas fa-list"></i>
          <p>Nothing to show yet.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Order Detail modal (populated from Recent Orders) -->
<div class="dash-modal" id="orderDetailModal" aria-hidden="true">
  <div class="dash-modal__backdrop" data-close-modal></div>
  <div class="dash-modal__dialog">
    <button type="button" class="dash-modal__close" data-close-modal aria-label="Close">&times;</button>
    <h2 id="orderDetailTitle"><i class="fas fa-receipt"></i> Order</h2>
    <p class="dash-modal__subtitle" id="orderDetailMeta"></p>
    <div class="dash-modal__body">
      <div class="dash-rank-list" id="orderDetailItems"></div>
      <div class="dash-stat-row" style="margin-top:12px;">
        <div class="dash-stat"><span>Order Total</span><strong id="orderDetailTotal"></strong></div>
      </div>
    </div>
  </div>
</div>

<script type="application/json" id="recentOrderDetailsData"><?= json_encode($recentOrderDetails, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?></script>
<script type="application/json" id="revenueByDayData"><?= json_encode($revenueByDay, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?></script>
<script type="application/json" id="customersByDayData"><?= json_encode($customersServedByDay, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?></script>

</main>
</div>
</div>
<?= authContextScriptTag() ?>
<script>
(function () {
  function openModal(id) {
    var el = document.getElementById(id);
    if (el) el.classList.add('is-open');
  }
  function closeModal(el) {
    el.classList.remove('is-open');
  }

  document.querySelectorAll('[data-open-modal]').forEach(function (trigger) {
    trigger.addEventListener('click', function () { openModal(trigger.getAttribute('data-open-modal')); });
    trigger.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openModal(trigger.getAttribute('data-open-modal')); }
    });
  });

  document.querySelectorAll('.dash-modal [data-close-modal]').forEach(function (el) {
    el.addEventListener('click', function () { closeModal(el.closest('.dash-modal')); });
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      document.querySelectorAll('.dash-modal.is-open').forEach(closeModal);
    }
  });

  // Recent Orders -> Order Detail modal
  var orderDetails = {};
  try {
    orderDetails = JSON.parse(document.getElementById('recentOrderDetailsData').textContent || '{}');
  } catch (e) { orderDetails = {}; }

  function showOrderDetail(orderId) {
    var data = orderDetails[orderId];
    if (!data) return;
    document.getElementById('orderDetailTitle').innerHTML = '<i class="fas fa-receipt"></i> Order #' + data.id;
    document.getElementById('orderDetailMeta').textContent = data.customer + ' · ' + data.date + ' · ' + data.status.toUpperCase() + ' · ' + data.paymentStatus.toUpperCase();
    document.getElementById('orderDetailTotal').textContent = data.total;
    var itemsWrap = document.getElementById('orderDetailItems');
    itemsWrap.innerHTML = '';
    if (!data.items.length) {
      itemsWrap.innerHTML = '<p class="empty-note">No items recorded for this order.</p>';
    } else {
      data.items.forEach(function (item) {
        var row = document.createElement('div');
        row.className = 'dash-rank-row';

        var nameSpan = document.createElement('span');
        nameSpan.className = 'dash-rank-name';
        nameSpan.textContent = item.name;

        var qtySpan = document.createElement('span');
        qtySpan.className = 'dash-rank-qty';
        qtySpan.textContent = 'x' + item.quantity + ' @ ' + item.price;

        var revStrong = document.createElement('strong');
        revStrong.className = 'dash-rank-revenue';
        revStrong.textContent = item.subtotal;

        row.appendChild(nameSpan);
        row.appendChild(qtySpan);
        row.appendChild(revStrong);
        itemsWrap.appendChild(row);
      });
    }
    openModal('orderDetailModal');
  }

  document.querySelectorAll('[data-order-row]').forEach(function (row) {
    row.addEventListener('click', function () { showOrderDetail(row.getAttribute('data-order-row')); });
    row.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); showOrderDetail(row.getAttribute('data-order-row')); }
    });
  });

  // Revenue by day chart
  var revenueByDay = [];
  try {
    revenueByDay = JSON.parse(document.getElementById('revenueByDayData').textContent || '[]');
  } catch (e) { revenueByDay = []; }

  var chartCanvas = document.getElementById('revenueByDayChart');
  if (chartCanvas && revenueByDay.length && window.Chart) {
    new Chart(chartCanvas, {
      type: 'bar',
      data: {
        labels: revenueByDay.map(function (r) {
          var d = new Date(r.d + 'T00:00:00');
          return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        }),
        datasets: [{
          label: 'Revenue (PHP)',
          data: revenueByDay.map(function (r) { return parseFloat(r.revenue); }),
          backgroundColor: '#d71920',
          borderRadius: 6,
          maxBarThickness: 26
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function (ctx) { return 'PHP ' + ctx.parsed.y.toLocaleString(); }
            }
          }
        },
        scales: {
          x: { grid: { display: false }, title: { display: true, text: 'Date', font: { size: 11, weight: 'bold' } } },
          y: { beginAtZero: true, title: { display: true, text: 'Revenue (PHP)', font: { size: 11, weight: 'bold' } }, ticks: { callback: function (v) { return v.toLocaleString(); } } }
        }
      }
    });
  }

  // Customers served by day chart
  var customersByDay = [];
  try {
    customersByDay = JSON.parse(document.getElementById('customersByDayData').textContent || '[]');
  } catch (e) { customersByDay = []; }

  var customersChartCanvas = document.getElementById('customersByDayChart');
  if (customersChartCanvas && customersByDay.length && window.Chart) {
    new Chart(customersChartCanvas, {
      type: 'bar',
      data: {
        labels: customersByDay.map(function (r) {
          var d = new Date(r.day + 'T00:00:00');
          return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        }),
        datasets: [{
          label: 'Customers Served',
          data: customersByDay.map(function (r) { return parseInt(r.n, 10); }),
          backgroundColor: '#2563eb',
          borderRadius: 6,
          maxBarThickness: 26
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function (ctx) { return ctx.parsed.y + ' customer' + (ctx.parsed.y === 1 ? '' : 's'); }
            }
          }
        },
        scales: {
          x: { grid: { display: false }, title: { display: true, text: 'Date', font: { size: 11, weight: 'bold' } } },
          y: { beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: 'Customers Served', font: { size: 11, weight: 'bold' } } }
        }
      }
    });
  }
})();
</script>
</body>
</html>
