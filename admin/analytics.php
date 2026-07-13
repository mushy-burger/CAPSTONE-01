<?php
$pageTitle = 'Analytics';
require_once __DIR__ . '/../includes/admin-sidebar.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/BusinessMetrics.php';

$shopPct = (int)round(SHOP_LABOR_SHARE * 100);
$techPct = (int)round(TECH_LABOR_SHARE * 100);

// --- Date range filter (period presets or custom range) ---
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo   = trim($_GET['date_to']   ?? '');
$period   = trim($_GET['period']    ?? '30');
$validPeriods = ['7'=>'Last 7 Days','30'=>'Last 30 Days','90'=>'Last 90 Days','365'=>'Last Year'];

if ($dateFrom && $dateTo) {
    $rangeFrom   = $dateFrom;
    $rangeTo     = $dateTo;
    $labelPeriod = date('M j', strtotime($dateFrom)) . ' – ' . date('M j, Y', strtotime($dateTo));
} else {
    $days        = array_key_exists($period, $validPeriods) ? (int)$period : 30;
    $rangeFrom   = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
    $rangeTo     = date('Y-m-d');
    $labelPeriod = $validPeriods[(string)$days] ?? "Last $days Days";
}

// --- Executive totals: all-time (matches Dashboard) + selected period ---
$biz       = bizTotals();
$bizPeriod = bizTotals($rangeFrom, $rangeTo);

// --- Trend chart datasets (switchable granularity) ---
$trendSeries = [
    'daily'   => bizRevenueSeries('daily',   date('Y-m-d', strtotime('-29 days')), date('Y-m-d')),
    'weekly'  => bizRevenueSeries('weekly',  date('Y-m-d', strtotime('-83 days')), date('Y-m-d')),
    'monthly' => bizRevenueSeries('monthly', date('Y-m-d', strtotime('-11 months', strtotime(date('Y-m-01')))), date('Y-m-d')),
    'yearly'  => bizRevenueSeries('yearly',  null, null),
];

// --- Period-scoped insights ---
$topProducts = bizTopProducts(8, $rangeFrom, $rangeTo);
$topServices = bizTopServices(8, $rangeFrom, $rangeTo);
$topTechs    = bizTechPerformance(8, $rangeFrom, $rangeTo);

$customersServiced = (int)(fetchOne(
    "SELECT COUNT(DISTINCT user_id) AS n FROM bookings WHERE status = 'completed'"
)['n'] ?? 0);
$completedServicesCount = (int)(fetchOne(
    "SELECT COUNT(*) AS n
     FROM booking_services bs JOIN bookings b ON b.id = bs.booking_id
     WHERE b.status = 'completed'"
)['n'] ?? 0);
$paidOrdersCount = (int)(fetchOne("SELECT COUNT(*) AS n FROM orders WHERE payment_status='paid'")['n'] ?? 0);

// --- Status distributions (all-time snapshot) ---
$ordersByStatus   = fetchAllRows("SELECT status, COUNT(*) AS total, COALESCE(SUM(total),0) AS sales FROM orders GROUP BY status ORDER BY FIELD(status,'pending','processing','completed','cancelled')");
$servicesByStatus = fetchAllRows("SELECT status, COUNT(*) AS total, COALESCE(SUM(total_amount),0) AS value FROM bookings GROUP BY status ORDER BY FIELD(status,'pending','confirmed','in_progress','completed','cancelled')");

// --- Inventory risk ---
$lowStockCount = (int)(fetchOne("SELECT COUNT(*) AS n FROM products WHERE stock <= min_stock")['n'] ?? 0);
$lowStock      = fetchAllRows("SELECT p.name, p.stock, p.min_stock, c.name AS category_name FROM products p JOIN categories c ON c.id=p.category_id WHERE p.stock <= p.min_stock ORDER BY p.stock ASC, p.name LIMIT 10");

$orderStatusColor   = ['pending'=>'#6b7280','processing'=>'#d97706','completed'=>'#15803d','cancelled'=>'#b91c1c'];
$bookingStatusColor = ['pending'=>'#6b7280','confirmed'=>'#2563eb','in_progress'=>'#d97706','completed'=>'#15803d','cancelled'=>'#b91c1c'];
?>

<div class="mtx-shell">

  <!-- Header -->
  <header class="mtx-page-head">
    <div class="mtx-page-head-copy">
      <span class="eyebrow">Business Intelligence</span>
      <h1>Analytics</h1>
      <p>Revenue, labor, product and technician performance — across the shop and service bookings.</p>
    </div>
    <div class="mtx-page-head-meta">
      <i class="fas fa-filter"></i> Insights scoped to: <strong style="color:var(--ink);"><?= htmlspecialchars($labelPeriod) ?></strong>
    </div>
  </header>

  <!-- Executive KPIs (all-time — matches Dashboard) -->
  <section class="mtx-kpi-grid" aria-label="Business metrics (all time)">
    <article class="mtx-kpi mtx-kpi--featured">
      <div class="mtx-kpi-top">
        <span class="mtx-kpi-label">Total Revenue</span>
        <span class="mtx-kpi-icon"><i class="fas fa-sack-dollar"></i></span>
      </div>
      <span class="mtx-kpi-value"><?= formatPrice($biz['total_revenue']) ?></span>
      <span class="mtx-kpi-sub"><?= htmlspecialchars($labelPeriod) ?>: <strong><?= formatPrice($bizPeriod['total_revenue']) ?></strong></span>
    </article>
    <article class="mtx-kpi" style="--kpi-color:#2563eb;">
      <div class="mtx-kpi-top">
        <span class="mtx-kpi-label">Total Product Sales</span>
        <span class="mtx-kpi-icon"><i class="fas fa-box-open"></i></span>
      </div>
      <span class="mtx-kpi-value"><?= formatPrice($biz['product_sales']) ?></span>
      <span class="mtx-kpi-sub"><?= htmlspecialchars($labelPeriod) ?>: <strong><?= formatPrice($bizPeriod['product_sales']) ?></strong></span>
    </article>
    <a class="mtx-kpi" href="<?= baseUrl('admin/bookings.php') ?>" style="--kpi-color:#d71920;">
      <div class="mtx-kpi-top">
        <span class="mtx-kpi-label">Total Labor Sales</span>
        <span class="mtx-kpi-icon"><i class="fas fa-wrench"></i></span>
      </div>
      <span class="mtx-kpi-value"><?= formatPrice($biz['labor_sales']) ?></span>
      <span class="mtx-kpi-sub"><?= htmlspecialchars($labelPeriod) ?>: <strong><?= formatPrice($bizPeriod['labor_sales']) ?></strong></span>
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

  <!-- Revenue trend chart with granularity switch -->
  <section class="mtx-card">
    <div class="mtx-card-head">
      <div>
        <h2><i class="fas fa-chart-column"></i> Revenue Trends</h2>
        <p>Product sales vs labor sales over time.</p>
      </div>
      <div class="mtx-card-head-actions">
        <div class="mtx-legend" aria-hidden="true">
          <span><i style="--swatch:#2563eb;"></i> Product Sales</span>
          <span><i style="--swatch:#d71920;"></i> Labor Sales</span>
        </div>
        <div class="mtx-seg" role="tablist" aria-label="Chart granularity">
          <button type="button" class="active" data-granularity="daily">Daily</button>
          <button type="button" data-granularity="weekly">Weekly</button>
          <button type="button" data-granularity="monthly">Monthly</button>
          <button type="button" data-granularity="yearly">Yearly</button>
        </div>
      </div>
    </div>
    <div class="mtx-chart"><canvas id="trendChart" aria-label="Revenue trend chart, product versus labor sales"></canvas></div>
    <div class="mtx-empty" id="trendEmpty" style="display:none;">
      <i class="fas fa-chart-line"></i>
      <strong>No revenue in this range yet.</strong>
      <span>Paid orders and completed bookings will appear here.</span>
    </div>
  </section>

  <!-- Period filter -->
  <section class="mtx-card">
    <form method="get" class="mtx-toolbar">
      <div class="mtx-seg" aria-label="Preset periods">
        <?php foreach ($validPeriods as $k => $label): ?>
          <a href="<?= baseUrl('admin/analytics.php?period=' . $k) ?>" class="<?= (!$dateFrom && $period == $k) ? 'active' : '' ?>"><?= $label ?></a>
        <?php endforeach; ?>
      </div>
      <span class="mtx-toolbar-sep">or custom:</span>
      <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
      <span class="mtx-toolbar-sep">to</span>
      <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
      <button type="submit" class="mtx-btn mtx-btn--dark">Apply</button>
      <?php if ($dateFrom || $dateTo): ?>
        <a href="<?= baseUrl('admin/analytics.php') ?>" class="mtx-btn mtx-btn--ghost">Reset</a>
      <?php endif; ?>
    </form>
  </section>

  <!-- Period-scoped revenue summary -->
  <section class="mtx-kpi-grid mtx-kpi-grid--6" aria-label="Selected period summary">
    <article class="mtx-kpi" style="--kpi-color:#111317;">
      <div class="mtx-kpi-top"><span class="mtx-kpi-label"><?= htmlspecialchars($labelPeriod) ?> Revenue</span><span class="mtx-kpi-icon"><i class="fas fa-coins"></i></span></div>
      <span class="mtx-kpi-value"><?= formatPrice($bizPeriod['total_revenue']) ?></span>
    </article>
    <article class="mtx-kpi" style="--kpi-color:#2563eb;">
      <div class="mtx-kpi-top"><span class="mtx-kpi-label">Product Sales</span><span class="mtx-kpi-icon"><i class="fas fa-box"></i></span></div>
      <span class="mtx-kpi-value"><?= formatPrice($bizPeriod['product_sales']) ?></span>
      <span class="mtx-kpi-sub">Shop <strong><?= formatPrice($bizPeriod['shop_product_sales']) ?></strong> · Bookings <strong><?= formatPrice($bizPeriod['booking_product_sales']) ?></strong></span>
    </article>
    <article class="mtx-kpi" style="--kpi-color:#d71920;">
      <div class="mtx-kpi-top"><span class="mtx-kpi-label">Labor Sales</span><span class="mtx-kpi-icon"><i class="fas fa-wrench"></i></span></div>
      <span class="mtx-kpi-value"><?= formatPrice($bizPeriod['labor_sales']) ?></span>
      <span class="mtx-kpi-sub">Shop <strong style="color:#15803d;"><?= formatPrice($bizPeriod['shop_labor']) ?></strong> · Tech <strong style="color:#2563eb;"><?= formatPrice($bizPeriod['tech_labor']) ?></strong></span>
    </article>
    <article class="mtx-kpi" style="--kpi-color:#7c3aed;">
      <div class="mtx-kpi-top"><span class="mtx-kpi-label">Paid Orders</span><span class="mtx-kpi-icon"><i class="fas fa-receipt"></i></span></div>
      <span class="mtx-kpi-value"><?= $paidOrdersCount ?></span>
      <span class="mtx-kpi-sub">All time</span>
    </article>
    <article class="mtx-kpi" style="--kpi-color:#0f766e;">
      <div class="mtx-kpi-top"><span class="mtx-kpi-label">Completed Services</span><span class="mtx-kpi-icon"><i class="fas fa-check-double"></i></span></div>
      <span class="mtx-kpi-value"><?= $completedServicesCount ?></span>
      <span class="mtx-kpi-sub">All time</span>
    </article>
    <article class="mtx-kpi" style="--kpi-color:#0369a1;">
      <div class="mtx-kpi-top"><span class="mtx-kpi-label">Customers Serviced</span><span class="mtx-kpi-icon"><i class="fas fa-user-check"></i></span></div>
      <span class="mtx-kpi-value"><?= $customersServiced ?></span>
      <span class="mtx-kpi-sub">All time</span>
    </article>
  </section>

  <!-- Top performers -->
  <section class="mtx-grid mtx-grid--thirds">
    <div class="mtx-card">
      <div class="mtx-card-head">
        <div>
          <h2><i class="fas fa-box-open"></i> Top-Selling Products</h2>
          <p><?= htmlspecialchars($labelPeriod) ?> · shop + booking sales combined.</p>
        </div>
      </div>
      <?php if ($topProducts): ?>
        <div class="mtx-list">
          <?php foreach ($topProducts as $i => $row): ?>
            <div class="mtx-list-row">
              <span class="mtx-list-rank">#<?= $i + 1 ?></span>
              <span class="mtx-list-main">
                <strong><?= htmlspecialchars($row['product_name']) ?></strong>
                <span><?= $row['units'] ?> unit<?= $row['units'] === 1 ? '' : 's' ?> sold</span>
              </span>
              <span class="mtx-list-end"><strong class="mtx-money--accent"><?= formatPrice($row['revenue']) ?></strong></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="mtx-empty"><i class="fas fa-box"></i><strong>No product sales in this period.</strong></div>
      <?php endif; ?>
    </div>

    <div class="mtx-card">
      <div class="mtx-card-head">
        <div>
          <h2><i class="fas fa-tools"></i> Most Requested Services</h2>
          <p><?= htmlspecialchars($labelPeriod) ?> · completed bookings.</p>
        </div>
      </div>
      <?php if ($topServices): ?>
        <div class="mtx-list">
          <?php foreach ($topServices as $i => $row): ?>
            <div class="mtx-list-row">
              <span class="mtx-list-rank">#<?= $i + 1 ?></span>
              <span class="mtx-list-main">
                <strong><?= htmlspecialchars($row['service_name']) ?></strong>
                <span><?= $row['requests'] ?> request<?= $row['requests'] === 1 ? '' : 's' ?></span>
              </span>
              <span class="mtx-list-end">
                <strong class="mtx-money--accent"><?= formatPrice($row['labor']) ?></strong>
                <span>labor revenue</span>
              </span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="mtx-empty"><i class="fas fa-tools"></i><strong>No completed services in this period.</strong></div>
      <?php endif; ?>
    </div>

    <div class="mtx-card">
      <div class="mtx-card-head">
        <div>
          <h2><i class="fas fa-trophy"></i> Top Technicians</h2>
          <p><?= htmlspecialchars($labelPeriod) ?> · each earns <?= $techPct ?>% of their labor.</p>
        </div>
      </div>
      <?php if ($topTechs): ?>
        <div class="mtx-list">
          <?php foreach ($topTechs as $i => $row): ?>
            <div class="mtx-list-row">
              <span class="mtx-list-rank">#<?= $i + 1 ?></span>
              <span class="mtx-list-main">
                <strong><?= htmlspecialchars($row['tech_name']) ?></strong>
                <span><?= $row['jobs_done'] ?> job<?= $row['jobs_done'] === 1 ? '' : 's' ?> · <?= $row['customers'] ?> customer<?= $row['customers'] === 1 ? '' : 's' ?></span>
              </span>
              <span class="mtx-list-end">
                <strong class="mtx-money--blue"><?= formatPrice($row['tech_share']) ?></strong>
                <span>of <?= formatPrice($row['labor']) ?> labor</span>
              </span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="mtx-empty"><i class="fas fa-user-cog"></i><strong>No completed jobs in this period.</strong></div>
      <?php endif; ?>
    </div>
  </section>

  <!-- Status distributions + inventory -->
  <section class="mtx-grid mtx-grid--thirds">
    <div class="mtx-card">
      <div class="mtx-card-head">
        <div><h2><i class="fas fa-shopping-bag"></i> Orders by Status</h2><p>All time.</p></div>
      </div>
      <?php if ($ordersByStatus): ?>
        <div class="mtx-list">
          <?php foreach ($ordersByStatus as $row): ?>
            <div class="mtx-list-row">
              <span class="mtx-pill" style="--pill-color:<?= $orderStatusColor[$row['status']] ?? '#6b7280' ?>;"><?= ucfirst($row['status']) ?></span>
              <span class="mtx-list-main"></span>
              <span class="mtx-list-end">
                <strong><?= (int)$row['total'] ?> order<?= (int)$row['total'] === 1 ? '' : 's' ?></strong>
                <span><?= formatPrice((float)$row['sales']) ?></span>
              </span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="mtx-empty"><i class="fas fa-shopping-bag"></i><strong>No orders yet.</strong></div>
      <?php endif; ?>
    </div>

    <div class="mtx-card">
      <div class="mtx-card-head">
        <div><h2><i class="fas fa-calendar-check"></i> Bookings by Status</h2><p>All time.</p></div>
      </div>
      <?php if ($servicesByStatus): ?>
        <div class="mtx-list">
          <?php foreach ($servicesByStatus as $row): ?>
            <div class="mtx-list-row">
              <span class="mtx-pill" style="--pill-color:<?= $bookingStatusColor[$row['status']] ?? '#6b7280' ?>;"><?= ucfirst(str_replace('_',' ',$row['status'])) ?></span>
              <span class="mtx-list-main"></span>
              <span class="mtx-list-end">
                <strong><?= (int)$row['total'] ?> booking<?= (int)$row['total'] === 1 ? '' : 's' ?></strong>
                <span><?= formatPrice((float)$row['value']) ?></span>
              </span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="mtx-empty"><i class="fas fa-calendar"></i><strong>No bookings yet.</strong></div>
      <?php endif; ?>
    </div>

    <div class="mtx-card">
      <div class="mtx-card-head">
        <div>
          <h2><i class="fas fa-triangle-exclamation"></i> Low-Stock Risk</h2>
          <p><?= $lowStockCount ?> product<?= $lowStockCount === 1 ? '' : 's' ?> at or below minimum stock.</p>
        </div>
      </div>
      <?php if ($lowStock): ?>
        <div class="mtx-list">
          <?php foreach ($lowStock as $row): ?>
            <div class="mtx-list-row">
              <span class="mtx-list-main">
                <strong><?= htmlspecialchars($row['name']) ?></strong>
                <span><?= htmlspecialchars($row['category_name']) ?> · min <?= (int)$row['min_stock'] ?></span>
              </span>
              <span class="mtx-pill" style="--pill-color:<?= (int)$row['stock'] === 0 ? '#b91c1c' : '#d97706' ?>;">
                <?= (int)$row['stock'] === 0 ? 'OUT' : (int)$row['stock'] . ' left' ?>
              </span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="mtx-empty"><i class="fas fa-box-open"></i><strong>No low-stock products.</strong></div>
      <?php endif; ?>
    </div>
  </section>

</div><!-- /.mtx-shell -->

<script type="application/json" id="trendSeriesData"><?= json_encode($trendSeries, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?></script>

<?= authContextScriptTag() ?>
<script>
(function () {
  var series = {};
  try {
    series = JSON.parse(document.getElementById('trendSeriesData').textContent || '{}');
  } catch (e) { series = {}; }

  var canvas = document.getElementById('trendChart');
  var emptyEl = document.getElementById('trendEmpty');
  if (!canvas || !window.Chart) return;

  var MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

  function php(n) { return 'PHP ' + Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

  function label(bucket, granularity) {
    if (granularity === 'daily') {
      var d = new Date(bucket + 'T00:00:00');
      return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }
    if (granularity === 'weekly') {
      var parts = bucket.split('-W');
      return 'W' + parts[1] + ' ’' + parts[0].slice(2);
    }
    if (granularity === 'monthly') {
      var p = bucket.split('-');
      return MONTHS[parseInt(p[1], 10) - 1] + ' ' + p[0];
    }
    return bucket;
  }

  var chart = null;

  function render(granularity) {
    var rows = series[granularity] || [];
    var hasData = rows.length > 0;
    canvas.parentElement.style.display = hasData ? '' : 'none';
    emptyEl.style.display = hasData ? 'none' : '';
    if (!hasData) { if (chart) { chart.destroy(); chart = null; } return; }

    var labels = rows.map(function (r) { return label(r.bucket, granularity); });
    var product = rows.map(function (r) { return parseFloat(r.product_sales); });
    var labor = rows.map(function (r) { return parseFloat(r.labor_sales); });

    if (chart) chart.destroy();
    chart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [
          {
            label: 'Product Sales',
            data: product,
            backgroundColor: '#2563eb',
            borderColor: '#ffffff',
            borderWidth: 1,
            borderRadius: 4,
            maxBarThickness: 34,
            stack: 'revenue'
          },
          {
            label: 'Labor Sales',
            data: labor,
            backgroundColor: '#d71920',
            borderColor: '#ffffff',
            borderWidth: 1,
            borderRadius: 4,
            maxBarThickness: 34,
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

  document.querySelectorAll('[data-granularity]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('[data-granularity]').forEach(function (b) { b.classList.remove('active'); });
      btn.classList.add('active');
      render(btn.getAttribute('data-granularity'));
    });
  });

  render('daily');
})();
</script>
</main></div></div></body></html>
