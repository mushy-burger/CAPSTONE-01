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
// Keep today's breakdown on the same accounting source as total revenue.
$todayBiz = bizTotals(date('Y-m-d'), date('Y-m-d'));
$todayShopProductSales = $todayBiz['shop_product_sales'];
$todayBookingProductSales = $todayBiz['booking_product_sales'];
$todayProductSales = $todayBiz['product_sales'];
$todayLaborSales = $todayBiz['labor_sales'];
$todayDepositRevenue = $todayBiz['deposit_revenue'];
$todaySales = $todayBiz['total_revenue'];

$totalUsers    = (int)(fetchOne("SELECT COUNT(*) AS n FROM users WHERE role='customer'")['n'] ?? 0);
$newUsersToday = (int)(fetchOne("SELECT COUNT(*) AS n FROM users WHERE DATE(created_at)=CURDATE()")['n'] ?? 0);
$lowStockCount = (int)(fetchOne("SELECT COUNT(*) AS n FROM products WHERE stock <= min_stock")['n'] ?? 0);
$ordersWaiting = (int)(fetchOne("SELECT COUNT(*) AS n FROM orders WHERE status='pending'")['n'] ?? 0);

$newUsersYesterday = (int)(fetchOne("SELECT COUNT(*) AS n FROM users WHERE DATE(created_at)=DATE_SUB(CURDATE(), INTERVAL 1 DAY)")['n'] ?? 0);
$newUsersTrend = dashTrend($newUsersToday, $newUsersYesterday);

// --- Greeting (Asia/Manila; kept live client-side by includes/mtx-clock.php) ---
$manilaNow = new DateTime('now', new DateTimeZone('Asia/Manila'));
$hour = (int)$manilaNow->format('G');
$greeting = $hour < 12 ? 'Good Morning' : ($hour < 18 ? 'Good Afternoon' : 'Good Evening');

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

// --- Product Sales modal: daily product revenue (both channels) + best sellers ---
$productSalesByDay = bizRevenueSeries('daily', date('Y-m-d', strtotime('-13 days')), date('Y-m-d'));
$topItems = bizTopProducts(6);
$totalTransactions = (int)(fetchOne("SELECT COUNT(*) AS n FROM orders WHERE payment_status='paid'")['n'] ?? 0);

// --- Today's Shop Activity ---
// Note: "completed today" uses completed_at when present, falling back to today's scheduled completions.
$todaysBookingsCount  = (int)(fetchOne("SELECT COUNT(*) AS n FROM bookings WHERE scheduled_date = CURDATE()")['n'] ?? 0);
$servicesInProgress   = (int)(fetchOne("SELECT COUNT(*) AS n FROM bookings WHERE status='in_progress'")['n'] ?? 0);
$completedToday       = (int)(fetchOne("SELECT COUNT(*) AS n FROM bookings WHERE status='completed' AND DATE(COALESCE(completed_at, scheduled_date)) = CURDATE()")['n'] ?? 0);
$ordersCompletedToday = (int)(fetchOne("SELECT COUNT(*) AS n FROM orders WHERE status='completed' AND DATE(COALESCE(paid_at, created_at)) = CURDATE()")['n'] ?? 0);
$customersServedToday = (int)(fetchOne(
    "SELECT COUNT(DISTINCT user_id) AS n FROM (
        SELECT user_id FROM bookings WHERE DATE(COALESCE(completed_at, scheduled_date)) = CURDATE() AND status='completed' AND user_id IS NOT NULL
        UNION
        SELECT user_id FROM orders WHERE DATE(created_at) = CURDATE() AND payment_status='paid' AND user_id IS NOT NULL
     ) x"
)['n'] ?? 0);

// --- Technician availability: same active/on-duty source used for assignments. ---
$technicianAvailability = fetchAllRows(
    "SELECT id, name, availability_status
     FROM users
     WHERE role = 'technician' AND is_active = 1
     ORDER BY name ASC"
);
$technicianPageSize = 3;
$technicianPageCount = (int)ceil(count($technicianAvailability) / $technicianPageSize);

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

$shopPct = (int)round(SHOP_LABOR_SHARE * 100);
$techPct = (int)round(TECH_LABOR_SHARE * 100);
?>

<div class="mtx-shell">

  <!-- Header -->
  <header class="mtx-page-head">
    <div class="mtx-page-head-copy">
      <span class="eyebrow">Executive Overview</span>
      <h1><span data-greeting><?= $greeting ?></span>, <?= htmlspecialchars($currentUser['name']) ?></h1>
    </div>
    <?php require __DIR__ . '/../includes/mtx-clock.php'; ?>
  </header>

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
  <section class="mtx-kpi-grid mtx-kpi-grid--3" aria-label="Operations">
    <article class="mtx-kpi metric-tile-clickable" data-open-modal="todaySalesModal" role="button" tabindex="0" aria-haspopup="dialog" style="--kpi-color:#0f766e;">
      <div class="mtx-kpi-top">
        <span class="mtx-kpi-label">Today's Sales</span>
        <span class="mtx-kpi-icon"><i class="fas fa-chart-line"></i></span>
      </div>
      <span class="mtx-kpi-value"><?= formatPrice($todaySales) ?></span>
      <span class="mtx-kpi-sub">Products + completed service labor</span>
      <span class="mtx-kpi-link">View breakdown <i class="fas fa-arrow-right"></i></span>
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
  </section>

  <!-- Today's Activity / Technician Availability / Inventory Alerts -->
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
        <div class="activity-row"><span><i class="fas fa-shopping-bag"></i> Orders Completed</span><strong><?= $ordersCompletedToday ?></strong></div>
        <div class="activity-row"><span><i class="fas fa-user-check"></i> Customers Served</span><strong><?= $customersServedToday ?></strong></div>
      </div>
    </div>

    <div class="mtx-card tech-availability-card<?= $technicianPageCount > 1 ? ' tech-availability-card--paginated' : '' ?>">
      <div class="mtx-card-head">
        <div>
          <h2><i class="fas fa-user-clock"></i> Technician Availability</h2>
          <p>Current assignment availability for active technicians.</p>
        </div>
      </div>
      <?php if ($technicianAvailability): ?>
        <div class="mtx-list" id="technicianAvailabilityList">
          <?php foreach (array_slice($technicianAvailability, 0, $technicianPageSize) as $technician): ?>
            <?php $isReady = ($technician['availability_status'] ?? 'off_duty') === 'ready'; ?>
            <div class="mtx-list-row">
              <span class="mtx-list-main"><strong><?= htmlspecialchars($technician['name']) ?></strong></span>
              <span class="mtx-pill" style="--pill-color:<?= $isReady ? '#15803d' : '#b91c1c' ?>;"><i class="fas <?= $isReady ? 'fa-circle-check' : 'fa-circle-minus' ?>"></i> <?= $isReady ? 'Available' : 'Unavailable' ?></span>
            </div>
          <?php endforeach; ?>
        </div>
        <?php if ($technicianPageCount > 1): ?>
          <nav class="tech-availability-pagination" aria-label="Technician availability pages">
            <button type="button" class="tech-availability-pagination__button" data-technician-page-prev aria-label="Previous technicians"><i class="fas fa-chevron-left"></i></button>
            <span class="tech-availability-pagination__pages" data-technician-pages></span>
            <button type="button" class="tech-availability-pagination__button" data-technician-page-next aria-label="Next technicians"><i class="fas fa-chevron-right"></i></button>
          </nav>
        <?php endif; ?>
      <?php else: ?>
        <div class="mtx-empty"><i class="fas fa-user-cog"></i><strong>No technicians available.</strong></div>
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

<!-- Today's Sales Breakdown modal -->
<div class="dash-modal dash-modal--today-sales" id="todaySalesModal" aria-hidden="true">
  <div class="dash-modal__backdrop" data-close-modal></div>
  <div class="dash-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="todaySalesModalTitle">
    <div class="dash-modal__header">
      <button type="button" class="dash-modal__close" data-close-modal aria-label="Close">&times;</button>
      <h2 id="todaySalesModalTitle"><i class="fas fa-chart-line"></i> Today's Sales Breakdown</h2>
      <p class="dash-modal__subtitle">Revenue generated today.</p>
    </div>
    <div class="dash-modal__body">
      <div class="dash-stat-row">
        <div class="dash-stat dash-stat--accent"><span>Today's Sales</span><strong><?= formatPrice($todaySales) ?></strong></div>
        <div class="dash-stat"><span>Product Sales</span><strong><?= formatPrice($todayProductSales) ?></strong></div>
        <div class="dash-stat"><span>Labor Sales</span><strong><?= formatPrice($todayLaborSales) ?></strong></div>
      </div>

      <section class="dash-sales-breakdown" aria-label="Today's revenue breakdown">
        <div class="dash-sales-breakdown__section">
          <h3 class="dash-section-title">Product Sales</h3>
          <div class="dash-sales-breakdown__rows">
            <div><span>Shop Orders</span><strong><?= formatPrice($todayShopProductSales) ?></strong></div>
            <div><span>Booking Products</span><strong><?= formatPrice($todayBookingProductSales) ?></strong></div>
            <div class="dash-sales-breakdown__total"><span>Product Sales Total</span><strong><?= formatPrice($todayProductSales) ?></strong></div>
          </div>
        </div>
        <div class="dash-sales-breakdown__section">
          <h3 class="dash-section-title">Labor Sales</h3>
          <div class="dash-sales-breakdown__rows">
            <div><span>Completed Service Labor</span><strong><?= formatPrice($todayLaborSales) ?></strong></div>
            <div class="dash-sales-breakdown__total"><span>Labor Sales Total</span><strong><?= formatPrice($todayLaborSales) ?></strong></div>
          </div>
        </div>
        <div class="dash-sales-breakdown__section">
          <h3 class="dash-section-title">Reservation Deposits</h3>
          <div class="dash-sales-breakdown__rows">
            <div><span>Paid deposits less credits applied on completed bookings</span><strong><?= formatPrice($todayDepositRevenue) ?></strong></div>
          </div>
        </div>
        <div class="dash-sales-final-total">
          <span>Today's Total Revenue</span>
          <strong><?= formatPrice($todaySales) ?></strong>
        </div>
      </section>

    </div>
  </div>
</div>

<!-- Product Sales Summary modal -->
<div class="dash-modal dash-modal--revenue" id="revenueModal" aria-hidden="true">
  <div class="dash-modal__backdrop" data-close-modal></div>
  <div class="dash-modal__dialog">
    <div class="dash-modal__header">
      <button type="button" class="dash-modal__close" data-close-modal aria-label="Close">&times;</button>
      <h2><i class="fas fa-box-open"></i> Product Sales Summary</h2>
      <p class="dash-modal__subtitle">Product revenue from the shop and from service bookings.</p>
    </div>
    <div class="dash-modal__body">
      <div class="dash-stat-row">
        <div class="dash-stat"><span>Shop Orders</span><strong><?= formatPrice($biz['shop_product_sales']) ?></strong></div>
        <div class="dash-stat"><span>Booking Products</span><strong><?= formatPrice($biz['booking_product_sales']) ?></strong></div>
        <div class="dash-stat"><span>Paid Transactions</span><strong><?= $totalTransactions ?></strong></div>
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

<script type="application/json" id="productSalesByDayData"><?= json_encode($productSalesByDay, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?></script>
<script type="application/json" id="customersByDayData"><?= json_encode($customersServedByDay, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?></script>
<script type="application/json" id="technicianAvailabilityData"><?= json_encode($technicianAvailability, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?></script>

</main>
</div>
</div>
<?= authContextScriptTag() ?>
<script>
(function () {
  function openModal(id) {
    var el = document.getElementById(id);
    if (!el) return;
    el.classList.remove('is-closing');
    el.classList.add('is-opening');
    requestAnimationFrame(function () {
      el.classList.remove('is-opening');
      el.classList.add('is-open');
    });
  }
  function closeModal(el) {
    el.classList.remove('is-opening', 'is-open');
    el.classList.add('is-closing');
    window.setTimeout(function () {
      el.classList.remove('is-closing');
    }, 220);
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

  // Technician Availability: pages over the current server-provided list.
  var technicians = readJson('technicianAvailabilityData', []);
  var technicianList = document.getElementById('technicianAvailabilityList');
  var technicianPages = document.querySelector('[data-technician-pages]');
  var technicianPrev = document.querySelector('[data-technician-page-prev]');
  var technicianNext = document.querySelector('[data-technician-page-next]');
  var techniciansPerPage = 3;
  var currentTechnicianPage = 1;
  var totalTechnicianPages = Math.ceil(technicians.length / techniciansPerPage);

  function technicianStatus(technician) {
    return (technician.availability_status || 'off_duty') === 'ready';
  }

  function renderTechnicianAvailability() {
    if (!technicianList || totalTechnicianPages < 1) return;
    technicianList.innerHTML = '';
    technicians.slice((currentTechnicianPage - 1) * techniciansPerPage, currentTechnicianPage * techniciansPerPage).forEach(function (technician) {
      var row = document.createElement('div');
      row.className = 'mtx-list-row';
      var main = document.createElement('span');
      main.className = 'mtx-list-main';
      var name = document.createElement('strong');
      name.textContent = technician.name;
      main.appendChild(name);

      var isReady = technicianStatus(technician);
      var badge = document.createElement('span');
      badge.className = 'mtx-pill';
      badge.style.setProperty('--pill-color', isReady ? '#15803d' : '#b91c1c');
      var icon = document.createElement('i');
      icon.className = 'fas ' + (isReady ? 'fa-circle-check' : 'fa-circle-minus');
      badge.appendChild(icon);
      badge.appendChild(document.createTextNode(' ' + (isReady ? 'Available' : 'Unavailable')));

      row.appendChild(main);
      row.appendChild(badge);
      technicianList.appendChild(row);
    });

    if (technicianPages) {
      technicianPages.innerHTML = '';
      for (var page = 1; page <= totalTechnicianPages; page++) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'tech-availability-pagination__button' + (page === currentTechnicianPage ? ' is-active' : '');
        button.textContent = page;
        button.setAttribute('aria-label', 'Technician page ' + page);
        if (page === currentTechnicianPage) button.setAttribute('aria-current', 'page');
        button.addEventListener('click', (function (requestedPage) {
          return function () { currentTechnicianPage = requestedPage; renderTechnicianAvailability(); };
        })(page));
        technicianPages.appendChild(button);
      }
    }
    if (technicianPrev) technicianPrev.disabled = currentTechnicianPage === 1;
    if (technicianNext) technicianNext.disabled = currentTechnicianPage === totalTechnicianPages;
  }

  if (technicianPrev && technicianNext && technicianList) {
    technicianPrev.addEventListener('click', function () { if (currentTechnicianPage > 1) { currentTechnicianPage--; renderTechnicianAvailability(); } });
    technicianNext.addEventListener('click', function () { if (currentTechnicianPage < totalTechnicianPages) { currentTechnicianPage++; renderTechnicianAvailability(); } });
    renderTechnicianAvailability();
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
