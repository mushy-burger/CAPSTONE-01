<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/admin-sidebar.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/BusinessMetrics.php';

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
    return '<span class="mtx-kpi-trend ' . $cls . '">' . $icon . ' ' . $sign . $trend['pct'] . '% ' . htmlspecialchars($label) . '</span>';
}

// --- Executive business metrics (shared helper — matches Bookings & Analytics) ---
$biz = bizTotals();

// Week-over-week trend on total revenue
$bizThisWeek = bizTotals(date('Y-m-d', strtotime('-6 days')), date('Y-m-d'));
$bizLastWeek = bizTotals(date('Y-m-d', strtotime('-13 days')), date('Y-m-d', strtotime('-7 days')));
$revenueTrend = dashTrend($bizThisWeek['total_revenue'], $bizLastWeek['total_revenue']);

// --- Operational KPIs ---
$todaySales    = (float)(fetchOne("SELECT COALESCE(SUM(total),0) AS n FROM orders WHERE DATE(created_at)=CURDATE() AND payment_status='paid'")['n'] ?? 0);
$totalUsers    = (int)(fetchOne("SELECT COUNT(*) AS n FROM users WHERE role='customer'")['n'] ?? 0);
$newUsersToday = (int)(fetchOne("SELECT COUNT(*) AS n FROM users WHERE DATE(created_at)=CURDATE()")['n'] ?? 0);
$lowStockCount = (int)(fetchOne("SELECT COUNT(*) AS n FROM products WHERE stock <= min_stock")['n'] ?? 0);
$totalOrders   = (int)(fetchOne("SELECT COUNT(*) AS n FROM orders")['n'] ?? 0);
$ordersWaiting = (int)(fetchOne("SELECT COUNT(*) AS n FROM orders WHERE status='pending'")['n'] ?? 0);

$salesYesterday = (float)(fetchOne("SELECT COALESCE(SUM(total),0) AS n FROM orders WHERE payment_status='paid' AND DATE(created_at)=DATE_SUB(CURDATE(), INTERVAL 1 DAY)")['n'] ?? 0);
$salesTrend = dashTrend($todaySales, $salesYesterday);

$newUsersYesterday = (int)(fetchOne("SELECT COUNT(*) AS n FROM users WHERE DATE(created_at)=DATE_SUB(CURDATE(), INTERVAL 1 DAY)")['n'] ?? 0);
$newUsersTrend = dashTrend($newUsersToday, $newUsersYesterday);

$ordersThisWeek = (int)(fetchOne("SELECT COUNT(*) AS n FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)")['n'] ?? 0);
$ordersLastWeek = (int)(fetchOne("SELECT COUNT(*) AS n FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) AND created_at < DATE_SUB(CURDATE(), INTERVAL 6 DAY)")['n'] ?? 0);
$ordersTrend = dashTrend((float)$ordersThisWeek, (float)$ordersLastWeek);

// --- Greeting (Asia/Manila; kept live client-side by includes/mtx-clock.php) ---
$manilaNow = new DateTime('now', new DateTimeZone('Asia/Manila'));
$hour = (int)$manilaNow->format('G');
$greeting = $hour < 12 ? 'Good Morning' : ($hour < 18 ? 'Good Afternoon' : 'Good Evening');

// --- Revenue chart: last 30 days, product vs labor ---
$revenueSeries30 = bizRevenueSeries('daily', date('Y-m-d', strtotime('-29 days')), date('Y-m-d'));

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

// --- Recent Bookings ---
$recentBookings = fetchAllRows(
    "SELECT b.id, b.scheduled_date, b.scheduled_time, b.status, b.total_amount, b.created_at,
            u.name AS customer_name,
            CONCAT(mb.name, ' ', mm.name) AS vehicle_name,
            svc.services,
            tech.name AS technician_name
     FROM bookings b
     JOIN users u ON u.id = b.user_id
     LEFT JOIN customer_vehicles cv ON cv.id = b.vehicle_id
     LEFT JOIN motorcycle_brands mb ON mb.id = cv.brand_id
     LEFT JOIN motorcycle_models mm ON mm.id = cv.model_id
     LEFT JOIN users tech ON tech.id = b.technician_id
     LEFT JOIN (
       SELECT booking_id, GROUP_CONCAT(service_name ORDER BY id SEPARATOR ', ') AS services
       FROM booking_services GROUP BY booking_id
     ) svc ON svc.booking_id = b.id
     ORDER BY b.created_at DESC LIMIT 6"
);

// --- Product Sales modal: daily product revenue (both channels) + best sellers ---
$productSalesByDay = bizRevenueSeries('daily', date('Y-m-d', strtotime('-13 days')), date('Y-m-d'));
$topItems = bizTopProducts(6);
$totalTransactions = (int)(fetchOne("SELECT COUNT(*) AS n FROM orders WHERE payment_status='paid'")['n'] ?? 0);
$shopOrderRevenue = $biz['shop_product_sales'];
$avgOrderValue = $totalTransactions > 0 ? $shopOrderRevenue / $totalTransactions : 0;

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
// Note: "completed today" uses completed_at when present, falling back to today's scheduled completions.
$todaysBookingsCount  = (int)(fetchOne("SELECT COUNT(*) AS n FROM bookings WHERE scheduled_date = CURDATE()")['n'] ?? 0);
$servicesInProgress   = (int)(fetchOne("SELECT COUNT(*) AS n FROM bookings WHERE status='in_progress'")['n'] ?? 0);
$completedToday       = (int)(fetchOne("SELECT COUNT(*) AS n FROM bookings WHERE status='completed' AND DATE(COALESCE(completed_at, scheduled_date)) = CURDATE()")['n'] ?? 0);
$customersServedToday = (int)(fetchOne(
    "SELECT COUNT(DISTINCT user_id) AS n FROM (
        SELECT user_id FROM bookings WHERE DATE(COALESCE(completed_at, scheduled_date)) = CURDATE() AND status='completed' AND user_id IS NOT NULL
        UNION
        SELECT user_id FROM orders WHERE DATE(created_at) = CURDATE() AND payment_status='paid' AND user_id IS NOT NULL
     ) x"
)['n'] ?? 0);

// --- Technician performance & availability ---
$techLeaders = bizTechPerformance(5);
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

$bookingStatusColor = [
    'pending'     => '#6b7280',
    'confirmed'   => '#2563eb',
    'in_progress' => '#d97706',
    'completed'   => '#15803d',
    'cancelled'   => '#b91c1c',
];
$shopPct = (int)round(SHOP_LABOR_SHARE * 100);
$techPct = (int)round(TECH_LABOR_SHARE * 100);
?>

<div class="mtx-shell">

  <!-- Header -->
  <header class="mtx-page-head">
    <div class="mtx-page-head-copy">
      <span class="eyebrow">Executive Overview</span>
      <h1><span data-greeting><?= $greeting ?></span>, <?= htmlspecialchars($currentUser['name']) ?></h1>
      <p>
        <strong style="color:var(--ink);"><?= formatPrice($todaySales) ?></strong> in shop sales today,
        <strong style="color:var(--ink);"><?= $todaysBookingsCount ?></strong> booking<?= $todaysBookingsCount === 1 ? '' : 's' ?> scheduled,
        <strong style="color:var(--ink);"><?= $lowStockCount ?></strong> item<?= $lowStockCount === 1 ? '' : 's' ?> need restocking.
      </p>
    </div>
    <?php require __DIR__ . '/../includes/mtx-clock.php'; ?>
  </header>

  <!-- Quick Actions -->
  <nav class="mtx-qa-strip" aria-label="Quick actions">
    <a href="<?= baseUrl('admin/settings.php?tab=services') ?>" class="mtx-qa" style="--qa-color:#0f766e;">
      <i class="fas fa-tools"></i><span>Add Service</span>
    </a>
    <a href="<?= baseUrl('admin/bookings.php') ?>" class="mtx-qa" style="--qa-color:#d71920;">
      <i class="fas fa-list-check"></i><span>View Work Queue</span>
    </a>
    <a href="<?= baseUrl('admin/orders.php') ?>" class="mtx-qa" style="--qa-color:#7c3aed;">
      <i class="fas fa-receipt"></i><span>Manage Orders</span>
    </a>
    <a href="<?= baseUrl('admin/analytics.php') ?>" class="mtx-qa" style="--qa-color:#d97706;">
      <i class="fas fa-chart-bar"></i><span>View Analytics</span>
    </a>
  </nav>

  <!-- Business KPI cards -->
  <section class="mtx-kpi-grid" aria-label="Business metrics">
    <article class="mtx-kpi mtx-kpi--featured">
      <div class="mtx-kpi-top">
        <span class="mtx-kpi-label">Total Revenue</span>
        <span class="mtx-kpi-icon"><i class="fas fa-sack-dollar"></i></span>
      </div>
      <span class="mtx-kpi-value"><?= formatPrice($biz['total_revenue']) ?></span>
      <span class="mtx-kpi-sub">
        <?= renderTrend($revenueTrend, 'vs last week') ?>
        Products + Labor (100%)
      </span>
    </article>

    <article class="mtx-kpi metric-tile-clickable" data-open-modal="revenueModal" role="button" tabindex="0" aria-haspopup="dialog" style="--kpi-color:#2563eb;">
      <div class="mtx-kpi-top">
        <span class="mtx-kpi-label">Total Product Sales</span>
        <span class="mtx-kpi-icon"><i class="fas fa-box-open"></i></span>
      </div>
      <span class="mtx-kpi-value"><?= formatPrice($biz['product_sales']) ?></span>
      <span class="mtx-kpi-sub">Shop <strong><?= formatPrice($biz['shop_product_sales']) ?></strong> &middot; Bookings <strong><?= formatPrice($biz['booking_product_sales']) ?></strong></span>
      <span class="mtx-kpi-link">View summary <i class="fas fa-arrow-right"></i></span>
    </article>

    <a class="mtx-kpi" href="<?= baseUrl('admin/bookings.php') ?>" style="--kpi-color:#d71920;">
      <div class="mtx-kpi-top">
        <span class="mtx-kpi-label">Total Labor Sales</span>
        <span class="mtx-kpi-icon"><i class="fas fa-wrench"></i></span>
      </div>
      <span class="mtx-kpi-value"><?= formatPrice($biz['labor_sales']) ?></span>
      <span class="mtx-kpi-sub">100% of completed booking labor</span>
      <span class="mtx-kpi-link">View breakdown <i class="fas fa-arrow-right"></i></span>
    </a>

    <article class="mtx-kpi" style="--kpi-color:#15803d;">
      <div class="mtx-kpi-top">
        <span class="mtx-kpi-label">Shop Labor Earnings</span>
        <span class="mtx-kpi-icon"><i class="fas fa-store"></i></span>
      </div>
      <span class="mtx-kpi-value" style="color:#15803d;"><?= formatPrice($biz['shop_labor']) ?></span>
      <span class="mtx-kpi-sub"><?= $shopPct ?>% of Total Labor Sales</span>
    </article>

    <article class="mtx-kpi" style="--kpi-color:#2563eb;">
      <div class="mtx-kpi-top">
        <span class="mtx-kpi-label">Technician Labor Earnings</span>
        <span class="mtx-kpi-icon"><i class="fas fa-user-cog"></i></span>
      </div>
      <span class="mtx-kpi-value" style="color:#2563eb;"><?= formatPrice($biz['tech_labor']) ?></span>
      <span class="mtx-kpi-sub"><?= $techPct ?>% of Total Labor Sales</span>
    </article>
  </section>

  <!-- Operational KPI cards -->
  <section class="mtx-kpi-grid mtx-kpi-grid--6" aria-label="Operations">
    <article class="mtx-kpi" style="--kpi-color:#0f766e;">
      <div class="mtx-kpi-top">
        <span class="mtx-kpi-label">Today's Sales</span>
        <span class="mtx-kpi-icon"><i class="fas fa-chart-line"></i></span>
      </div>
      <span class="mtx-kpi-value"><?= formatPrice($todaySales) ?></span>
      <span class="mtx-kpi-sub"><?= renderTrend($salesTrend, 'vs yesterday') ?></span>
    </article>
    <article class="mtx-kpi metric-tile-clickable" data-open-modal="ordersModal" role="button" tabindex="0" aria-haspopup="dialog" style="--kpi-color:#7c3aed;">
      <div class="mtx-kpi-top">
        <span class="mtx-kpi-label">Total Orders</span>
        <span class="mtx-kpi-icon"><i class="fas fa-shopping-bag"></i></span>
      </div>
      <span class="mtx-kpi-value"><?= $totalOrders ?></span>
      <span class="mtx-kpi-sub"><?= renderTrend($ordersTrend, 'vs last week') ?><span><?= $ordersWaiting ?> waiting</span></span>
    </article>
    <article class="mtx-kpi metric-tile-clickable" data-open-modal="customersModal" role="button" tabindex="0" aria-haspopup="dialog" style="--kpi-color:#0369a1;">
      <div class="mtx-kpi-top">
        <span class="mtx-kpi-label">Customers</span>
        <span class="mtx-kpi-icon"><i class="fas fa-users"></i></span>
      </div>
      <span class="mtx-kpi-value"><?= $totalUsers ?></span>
      <span class="mtx-kpi-sub">View trends</span>
    </article>
    <article class="mtx-kpi" style="--kpi-color:#15803d;">
      <div class="mtx-kpi-top">
        <span class="mtx-kpi-label">New Today</span>
        <span class="mtx-kpi-icon"><i class="fas fa-user-plus"></i></span>
      </div>
      <span class="mtx-kpi-value" style="<?= $newUsersToday > 0 ? 'color:#15803d;' : '' ?>"><?= $newUsersToday ?></span>
      <span class="mtx-kpi-sub"><?= renderTrend($newUsersTrend, 'vs yesterday') ?></span>
    </article>
    <article class="mtx-kpi metric-tile-clickable" data-open-modal="lowStockModal" role="button" tabindex="0" aria-haspopup="dialog" style="--kpi-color:#d97706;">
      <div class="mtx-kpi-top">
        <span class="mtx-kpi-label">Low Stock</span>
        <span class="mtx-kpi-icon"><i class="fas fa-exclamation-triangle"></i></span>
      </div>
      <span class="mtx-kpi-value" style="<?= $lowStockCount > 0 ? 'color:#d71920;' : '' ?>"><?= $lowStockCount ?></span>
      <span class="mtx-kpi-sub">View items</span>
    </article>
    <article class="mtx-kpi" style="--kpi-color:#d97706;">
      <div class="mtx-kpi-top">
        <span class="mtx-kpi-label">In Progress</span>
        <span class="mtx-kpi-icon"><i class="fas fa-spinner"></i></span>
      </div>
      <span class="mtx-kpi-value"><?= $servicesInProgress ?></span>
      <span class="mtx-kpi-sub">Active service jobs</span>
    </article>
  </section>

  <!-- Revenue chart -->
  <section class="mtx-card">
    <div class="mtx-card-head">
      <div>
        <h2><i class="fas fa-chart-column"></i> Revenue — Last 30 Days</h2>
        <p>Product sales (shop + bookings) and labor sales per day.</p>
      </div>
      <div class="mtx-legend" aria-hidden="true">
        <span><i style="--swatch:#2563eb;"></i> Product Sales</span>
        <span><i style="--swatch:#d71920;"></i> Labor Sales</span>
      </div>
    </div>
    <?php if ($revenueSeries30): ?>
      <div class="mtx-chart"><canvas id="mainRevenueChart" aria-label="Daily revenue, last 30 days, split into product and labor sales"></canvas></div>
    <?php else: ?>
      <div class="mtx-empty">
        <i class="fas fa-chart-line"></i>
        <strong>No revenue recorded in the last 30 days.</strong>
        <span>Paid orders and completed bookings will appear here.</span>
      </div>
    <?php endif; ?>
  </section>

  <!-- Recent Orders / Recent Bookings -->
  <section class="mtx-grid mtx-grid--half">
    <div class="mtx-card">
      <div class="mtx-card-head">
        <div>
          <h2><i class="fas fa-receipt"></i> Recent Orders</h2>
          <p>Click an order to see what was purchased.</p>
        </div>
        <a href="<?= baseUrl('admin/orders.php') ?>" class="mtx-btn mtx-btn--ghost mtx-btn--sm">View all</a>
      </div>
      <?php if ($recentOrders): ?>
        <div class="mtx-feed">
          <?php foreach ($recentOrders as $order): ?>
            <?php
              $sc = ['pending'=>'#6b7280','processing'=>'#d97706','completed'=>'#15803d','cancelled'=>'#b91c1c'][$order['status']] ?? '#6b7280';
              $psColor = ($order['payment_status'] ?? '') === 'paid' ? '#15803d' : '#d97706';
            ?>
            <div class="mtx-feed-row" data-order-row="<?= (int)$order['id'] ?>" role="button" tabindex="0" aria-haspopup="dialog">
              <span class="mtx-avatar"><?= htmlspecialchars(strtoupper(substr($order['customer_name'] ?? 'G', 0, 1))) ?></span>
              <span class="mtx-feed-main">
                <strong>#<?= (int)$order['id'] ?> &middot; <?= htmlspecialchars($order['customer_name'] ?? 'Guest') ?></strong>
                <span><?= htmlspecialchars(date('M j, Y g:i A', strtotime($order['created_at']))) ?></span>
              </span>
              <span class="mtx-feed-end">
                <span class="mtx-money"><?= formatPrice((float)$order['total']) ?></span>
                <span class="mtx-feed-badges">
                  <span class="mtx-pill" style="--pill-color:<?= $sc ?>;"><?= ucfirst($order['status']) ?></span>
                  <span class="mtx-pill" style="--pill-color:<?= $psColor ?>;"><?= strtoupper($order['payment_status'] ?? 'UNPAID') ?></span>
                </span>
              </span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="mtx-empty"><i class="fas fa-receipt"></i><strong>No orders yet.</strong></div>
      <?php endif; ?>
    </div>

    <div class="mtx-card">
      <div class="mtx-card-head">
        <div>
          <h2><i class="fas fa-calendar-check"></i> Recent Bookings</h2>
          <p>Latest service appointments across all statuses.</p>
        </div>
        <a href="<?= baseUrl('admin/bookings.php') ?>" class="mtx-btn mtx-btn--ghost mtx-btn--sm">View all</a>
      </div>
      <?php if ($recentBookings): ?>
        <div class="mtx-feed">
          <?php foreach ($recentBookings as $b): ?>
            <a class="mtx-feed-row" href="<?= baseUrl('admin/bookings.php?q=' . (int)$b['id']) ?>">
              <span class="mtx-avatar"><?= htmlspecialchars(strtoupper(substr($b['customer_name'], 0, 1))) ?></span>
              <span class="mtx-feed-main">
                <strong>#<?= (int)$b['id'] ?> &middot; <?= htmlspecialchars($b['customer_name']) ?></strong>
                <span>
                  <?= htmlspecialchars($b['services'] ?: 'No services recorded') ?>
                  &middot; <?= htmlspecialchars(date('M j', strtotime($b['scheduled_date']))) ?><?= $b['scheduled_time'] ? ', ' . htmlspecialchars(date('g:i A', strtotime($b['scheduled_time']))) : '' ?>
                </span>
              </span>
              <span class="mtx-feed-end">
                <span class="mtx-money"><?= formatPrice((float)$b['total_amount']) ?></span>
                <span class="mtx-feed-badges">
                  <span class="mtx-pill" style="--pill-color:<?= $bookingStatusColor[$b['status']] ?? '#6b7280' ?>;"><?= ucfirst(str_replace('_',' ',$b['status'])) ?></span>
                </span>
              </span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="mtx-empty"><i class="fas fa-calendar"></i><strong>No bookings yet.</strong></div>
      <?php endif; ?>
    </div>
  </section>

  <!-- Today's Activity / Technician Performance / Inventory Alerts -->
  <section class="mtx-grid mtx-grid--thirds">
    <div class="mtx-card">
      <div class="mtx-card-head">
        <div><h2><i class="fas fa-clipboard-list"></i> Today's Shop Activity</h2></div>
      </div>
      <div class="activity-list">
        <div class="activity-row"><span><i class="fas fa-calendar-day"></i> Today's Bookings</span><strong><?= $todaysBookingsCount ?></strong></div>
        <div class="activity-row"><span><i class="fas fa-spinner"></i> Services In Progress</span><strong><?= $servicesInProgress ?></strong></div>
        <div class="activity-row"><span><i class="fas fa-check-circle"></i> Completed Services</span><strong><?= $completedToday ?></strong></div>
        <div class="activity-row"><span><i class="fas fa-hourglass-half"></i> Orders Waiting</span><strong><?= $ordersWaiting ?></strong></div>
        <div class="activity-row"><span><i class="fas fa-user-check"></i> Customers Served</span><strong><?= $customersServedToday ?></strong></div>
      </div>
    </div>

    <div class="mtx-card">
      <div class="mtx-card-head">
        <div>
          <h2><i class="fas fa-trophy"></i> Technician Performance</h2>
          <p>Completed jobs — each technician earns <?= $techPct ?>% of their labor.</p>
        </div>
        <span class="mtx-pill" style="--pill-color:#15803d;"><i class="fas fa-user-check"></i> <?= $availableTechnicians ?> available</span>
      </div>
      <?php if ($techLeaders): ?>
        <div class="mtx-list">
          <?php foreach ($techLeaders as $i => $t): ?>
            <div class="mtx-list-row">
              <span class="mtx-list-rank">#<?= $i + 1 ?></span>
              <span class="mtx-list-main">
                <strong><?= htmlspecialchars($t['tech_name']) ?></strong>
                <span><?= $t['jobs_done'] ?> job<?= $t['jobs_done'] === 1 ? '' : 's' ?> &middot; <?= $t['customers'] ?> customer<?= $t['customers'] === 1 ? '' : 's' ?></span>
              </span>
              <span class="mtx-list-end">
                <strong class="mtx-money--blue"><?= formatPrice($t['tech_share']) ?></strong>
                <span>of <?= formatPrice($t['labor']) ?> labor</span>
              </span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="mtx-empty"><i class="fas fa-user-cog"></i><strong>No completed jobs yet.</strong></div>
      <?php endif; ?>
    </div>

    <div class="mtx-card">
      <div class="mtx-card-head">
        <div>
          <h2><i class="fas fa-triangle-exclamation"></i> Inventory Alerts</h2>
          <p>Products at or below their minimum stock level.</p>
        </div>
        <?php if ($lowStock): ?>
          <button type="button" class="mtx-btn mtx-btn--ghost mtx-btn--sm" data-open-modal="lowStockModal">View all (<?= $lowStockCount ?>)</button>
        <?php endif; ?>
      </div>
      <?php if ($lowStock): ?>
        <div class="mtx-list">
          <?php foreach ($lowStock as $item): ?>
            <?php
              $isCritical = (int)$item['stock'] === 0;
              $badgeColor = $isCritical ? '#b91c1c' : '#d97706';
              $badgeLabel = $isCritical ? 'Critical' : 'Low';
            ?>
            <div class="mtx-list-row">
              <span class="mtx-list-main">
                <strong><?= htmlspecialchars($item['name']) ?></strong>
                <span><?= (int)$item['stock'] ?> in stock &middot; min <?= (int)$item['min_stock'] ?></span>
              </span>
              <span class="mtx-pill" style="--pill-color:<?= $badgeColor ?>;"><i class="fas fa-circle"></i> <?= $badgeLabel ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="mtx-empty"><i class="fas fa-box-open"></i><strong>All products are well-stocked.</strong></div>
      <?php endif; ?>
    </div>
  </section>

</div><!-- /.mtx-shell -->

<!-- Product Sales Summary modal -->
<div class="dash-modal" id="revenueModal" aria-hidden="true">
  <div class="dash-modal__backdrop" data-close-modal></div>
  <div class="dash-modal__dialog">
    <button type="button" class="dash-modal__close" data-close-modal aria-label="Close">&times;</button>
    <h2><i class="fas fa-box-open"></i> Product Sales Summary</h2>
    <p class="dash-modal__subtitle">Product revenue from the shop and from service bookings.</p>
    <div class="dash-modal__body">
      <div class="dash-stat-row">
        <div class="dash-stat"><span>Shop Orders</span><strong><?= formatPrice($biz['shop_product_sales']) ?></strong></div>
        <div class="dash-stat"><span>Booking Products</span><strong><?= formatPrice($biz['booking_product_sales']) ?></strong></div>
        <div class="dash-stat"><span>Paid Transactions</span><strong><?= $totalTransactions ?></strong></div>
        <div class="dash-stat"><span>Avg. Order Value</span><strong><?= formatPrice($avgOrderValue) ?></strong></div>
      </div>
      <?php if ($productSalesByDay): ?>
        <div class="dash-chart-wrap"><canvas id="productSalesByDayChart" aria-label="Product sales by day, last 14 days"></canvas></div>
      <?php else: ?>
        <div class="dash-empty-state">
          <i class="fas fa-chart-line"></i>
          <p>No product sales in the last 14 days yet.</p>
          <span>Trends will appear here once sales come in.</span>
        </div>
      <?php endif; ?>
      <h3 class="dash-section-title">Top Selling Products (all channels)</h3>
      <?php if ($topItems): ?>
        <div class="dash-rank-list">
          <?php foreach ($topItems as $i => $item): ?>
            <div class="dash-rank-row">
              <span class="dash-rank-badge">#<?= $i + 1 ?></span>
              <span class="dash-rank-name"><?= htmlspecialchars($item['product_name']) ?></span>
              <span class="dash-rank-qty"><?= (int)$item['units'] ?> sold</span>
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
        <a href="<?= baseUrl('staff/products.php') ?>" class="mtx-btn mtx-btn--ghost mtx-btn--sm">Manage Inventory</a>
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
        <a href="<?= baseUrl('admin/orders.php') ?>" class="mtx-btn mtx-btn--ghost mtx-btn--sm">View Full Order Details</a>
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
<script type="application/json" id="revenueSeries30Data"><?= json_encode($revenueSeries30, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?></script>
<script type="application/json" id="productSalesByDayData"><?= json_encode($productSalesByDay, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?></script>
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

  function readJson(id, fallback) {
    try {
      return JSON.parse(document.getElementById(id).textContent || '');
    } catch (e) { return fallback; }
  }
  function php(n) { return 'PHP ' + Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  function shortDate(iso) {
    var d = new Date(iso + 'T00:00:00');
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
  }

  // Recent Orders -> Order Detail modal
  var orderDetails = readJson('recentOrderDetailsData', {});

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

  // Main revenue chart: stacked product + labor per day
  var series30 = readJson('revenueSeries30Data', []);
  var mainCanvas = document.getElementById('mainRevenueChart');
  if (mainCanvas && series30.length && window.Chart) {
    new Chart(mainCanvas, {
      type: 'bar',
      data: {
        labels: series30.map(function (r) { return shortDate(r.bucket); }),
        datasets: [
          {
            label: 'Product Sales',
            data: series30.map(function (r) { return parseFloat(r.product_sales); }),
            backgroundColor: '#2563eb',
            borderColor: '#ffffff',
            borderWidth: 1,
            borderRadius: 4,
            maxBarThickness: 22,
            stack: 'revenue'
          },
          {
            label: 'Labor Sales',
            data: series30.map(function (r) { return parseFloat(r.labor_sales); }),
            backgroundColor: '#d71920',
            borderColor: '#ffffff',
            borderWidth: 1,
            borderRadius: 4,
            maxBarThickness: 22,
            stack: 'revenue'
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function (ctx) { return ctx.dataset.label + ': ' + php(ctx.parsed.y); },
              footer: function (items) {
                var total = items.reduce(function (sum, it) { return sum + it.parsed.y; }, 0);
                return 'Total: ' + php(total);
              }
            }
          }
        },
        scales: {
          x: { stacked: true, grid: { display: false } },
          y: {
            stacked: true,
            beginAtZero: true,
            grid: { color: '#eef0f3' },
            ticks: { callback: function (v) { return v.toLocaleString(); } }
          }
        }
      }
    });
  }

  // Product sales by day chart (modal)
  var productByDay = readJson('productSalesByDayData', []);
  var productCanvas = document.getElementById('productSalesByDayChart');
  if (productCanvas && productByDay.length && window.Chart) {
    new Chart(productCanvas, {
      type: 'bar',
      data: {
        labels: productByDay.map(function (r) { return shortDate(r.bucket); }),
        datasets: [{
          label: 'Product Sales (PHP)',
          data: productByDay.map(function (r) { return parseFloat(r.product_sales); }),
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
          tooltip: { callbacks: { label: function (ctx) { return php(ctx.parsed.y); } } }
        },
        scales: {
          x: { grid: { display: false } },
          y: { beginAtZero: true, ticks: { callback: function (v) { return v.toLocaleString(); } } }
        }
      }
    });
  }

  // Customers served by day chart (modal)
  var customersByDay = readJson('customersByDayData', []);
  var customersChartCanvas = document.getElementById('customersByDayChart');
  if (customersChartCanvas && customersByDay.length && window.Chart) {
    new Chart(customersChartCanvas, {
      type: 'bar',
      data: {
        labels: customersByDay.map(function (r) { return shortDate(r.day); }),
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
          x: { grid: { display: false } },
          y: { beginAtZero: true, ticks: { precision: 0 } }
        }
      }
    });
  }
})();
</script>
</body>
</html>
