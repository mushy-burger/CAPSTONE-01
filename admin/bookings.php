<?php
$pageTitle = 'Bookings & Labor';
require_once __DIR__ . '/../includes/admin-sidebar.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/BusinessMetrics.php';

$validStatuses = ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'];
$shopPct = (int)round(SHOP_LABOR_SHARE * 100);
$techPct = (int)round(TECH_LABOR_SHARE * 100);

// --- Labor financials (shared helper — figures match the Dashboard exactly) ---
$biz = bizTotals();

// --- Summary counts ---
$totalBookings   = (int)(fetchOne("SELECT COUNT(*) AS n FROM bookings")['n'] ?? 0);
$pendingCount    = (int)(fetchOne("SELECT COUNT(*) AS n FROM bookings WHERE status = 'pending'")['n'] ?? 0);
$inProgressCount = (int)(fetchOne("SELECT COUNT(*) AS n FROM bookings WHERE status = 'in_progress'")['n'] ?? 0);
$completedCount  = (int)(fetchOne("SELECT COUNT(*) AS n FROM bookings WHERE status = 'completed'")['n'] ?? 0);

// --- Filters ---
$statusFilter = $_GET['status'] ?? '';
$statusFilter = in_array($statusFilter, $validStatuses, true) ? $statusFilter : '';
$search       = trim($_GET['q'] ?? '');
$dateFrom     = trim($_GET['date_from'] ?? '');
$dateTo       = trim($_GET['date_to'] ?? '');
$techFilter   = (int)($_GET['tech'] ?? 0);

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
if ($techFilter > 0) {
    $where[]  = 'b.technician_id = ?';
    $params[] = $techFilter;
}

$bookings = fetchAllRows(
    "SELECT
        b.*,
        u.name  AS customer_name,
        u.email AS customer_email,
        u.phone AS customer_phone,
        CONCAT(mb.name, ' ', mm.name) AS vehicle_name,
        mt.name AS type_name,
        cv.cc, cv.plate_number, cv.year,
        COALESCE(svc.labor, 0) AS svc_labor,
        tech.name AS technician_name,
        tech.email AS technician_email
     FROM bookings b
     JOIN users u ON u.id = b.user_id
     LEFT JOIN customer_vehicles cv ON cv.id = b.vehicle_id
     LEFT JOIN motorcycle_brands mb ON mb.id = cv.brand_id
     LEFT JOIN motorcycle_models mm ON mm.id = cv.model_id
     LEFT JOIN motorcycle_types mt ON mt.id = cv.type_id
     LEFT JOIN users tech ON tech.id = b.technician_id
     LEFT JOIN (
       SELECT booking_id, SUM(labor_fee) AS labor
       FROM booking_services GROUP BY booking_id
     ) svc ON svc.booking_id = b.id
     " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
     ORDER BY FIELD(b.status,'pending','confirmed','in_progress','completed','cancelled'), b.scheduled_date ASC, b.id ASC",
    $params
);

$technicians = fetchAllRows("SELECT id, name FROM users WHERE role = 'technician' AND is_active = 1 ORDER BY name");

// --- Line items (services + products) for the filtered bookings, for rows + detail modal ---
$bookingIds = array_map('intval', array_column($bookings, 'id'));
$servicesByBooking = [];
$productsByBooking = [];
if ($bookingIds) {
    $ph = implode(',', array_fill(0, count($bookingIds), '?'));
    foreach (fetchAllRows("SELECT * FROM booking_services WHERE booking_id IN ($ph) ORDER BY id", $bookingIds) as $svc) {
        $servicesByBooking[(int)$svc['booking_id']][] = $svc;
    }
    foreach (fetchAllRows("SELECT * FROM booking_products WHERE booking_id IN ($ph) ORDER BY id", $bookingIds) as $prod) {
        $productsByBooking[(int)$prod['booking_id']][] = $prod;
    }
}

// --- Filtered completed totals (table footer) ---
$filteredLabor = 0.0;
foreach ($bookings as $b) {
    if ($b['status'] === 'completed') {
        $filteredLabor += (float)$b['svc_labor'];
    }
}

$statusColor = [
    'pending'     => '#6b7280',
    'confirmed'   => '#2563eb',
    'in_progress' => '#d97706',
    'completed'   => '#15803d',
    'cancelled'   => '#b91c1c',
];

// --- Detail modal payload ---
$bookingDetails = [];
foreach ($bookings as $b) {
    $bid = (int)$b['id'];
    $labor = (float)$b['svc_labor'];
    $pay = bizBookingPaymentState($b['status']);
    $bookingDetails[$bid] = [
        'id'         => $bid,
        'customer'   => $b['customer_name'],
        'email'      => $b['customer_email'],
        'phone'      => $b['customer_phone'] ?: '—',
        'vehicle'    => $b['vehicle_name'] ?: 'No vehicle on record',
        'vehicleSub' => trim(($b['type_name'] ?? '') . ($b['cc'] ? ' · ' . (int)$b['cc'] . 'cc' : '') . ($b['year'] ? ' · ' . (int)$b['year'] : '') . ($b['plate_number'] ? ' · Plate ' . $b['plate_number'] : ''), ' ·'),
        'technician' => $b['technician_name'] ?: 'Unassigned',
        'date'       => date('l, F j, Y', strtotime($b['scheduled_date'])),
        'time'       => $b['scheduled_time'] ? date('g:i A', strtotime($b['scheduled_time'])) : 'Not specified',
        'status'     => ucfirst(str_replace('_', ' ', $b['status'])),
        'statusColor'=> $statusColor[$b['status']] ?? '#6b7280',
        'payment'    => $pay['label'],
        'payColor'   => $pay['color'],
        'notes'      => trim((string)$b['notes']),
        'techNotes'  => trim((string)$b['tech_notes']),
        'services'   => array_map(fn($s) => [
            'name'  => $s['service_name'],
            'labor' => formatPrice((float)$s['labor_fee']),
            'shop'  => formatPrice((float)$s['labor_fee'] * SHOP_LABOR_SHARE),
            'tech'  => formatPrice((float)$s['labor_fee'] * TECH_LABOR_SHARE),
        ], $servicesByBooking[$bid] ?? []),
        'products'   => array_map(fn($p) => [
            'name'  => $p['product_name'],
            'price' => formatPrice((float)$p['product_price']),
        ], $productsByBooking[$bid] ?? []),
        'laborTotal'    => formatPrice($labor),
        'shopShare'     => formatPrice($labor * SHOP_LABOR_SHARE),
        'techShare'     => formatPrice($labor * TECH_LABOR_SHARE),
        'productsTotal' => formatPrice((float)$b['products_total']),
        'grandTotal'    => formatPrice((float)$b['total_amount']),
    ];
}
?>

<div class="mtx-shell">

  <!-- Header -->
  <header class="mtx-page-head">
    <div class="mtx-page-head-copy">
      <span class="eyebrow">Labor Financials — Read Only</span>
      <h1>Bookings &amp; Labor Breakdown</h1>
      <p>Every service appointment with its labor revenue split — <?= $shopPct ?>% to the shop, <?= $techPct ?>% to the technician. Booking management stays in the Staff panel.</p>
    </div>
    <div class="mtx-head-actions">
      <a href="<?= baseUrl('staff/bookings.php') ?>" class="mtx-btn mtx-btn--ghost"><i class="fas fa-list-check"></i> Manage in Staff Panel</a>
    </div>
  </header>

  <!-- Labor financial cards (match Dashboard) -->
  <section class="mtx-kpi-grid mtx-kpi-grid--4" aria-label="Labor financials">
    <article class="mtx-kpi mtx-kpi--featured">
      <div class="mtx-kpi-top">
        <span class="mtx-kpi-label">Total Labor Sales</span>
        <span class="mtx-kpi-icon"><i class="fas fa-wrench"></i></span>
      </div>
      <span class="mtx-kpi-value"><?= formatPrice($biz['labor_sales']) ?></span>
      <span class="mtx-kpi-sub">100% of labor on <strong><?= $completedCount ?></strong> completed booking<?= $completedCount === 1 ? '' : 's' ?></span>
    </article>
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
    <article class="mtx-kpi">
      <div class="mtx-kpi-top">
        <span class="mtx-kpi-label">Labor Split</span>
        <span class="mtx-kpi-icon"><i class="fas fa-scale-balanced"></i></span>
      </div>
      <div class="mtx-split-bar" style="margin:10px 0 8px;" aria-hidden="true">
        <span style="width:<?= $shopPct ?>%;background:#15803d;"></span>
        <span style="width:<?= $techPct ?>%;background:#2563eb;"></span>
      </div>
      <span class="mtx-kpi-sub">
        <span style="color:#15803d;font-weight:800;">Shop <?= $shopPct ?>%</span>
        <span style="color:#2563eb;font-weight:800;">Technicians <?= $techPct ?>%</span>
      </span>
    </article>
  </section>

  <!-- Status counters -->
  <section class="mtx-kpi-grid mtx-kpi-grid--4" aria-label="Booking counts">
    <article class="mtx-kpi" style="--kpi-color:#111317;">
      <div class="mtx-kpi-top"><span class="mtx-kpi-label">Total Bookings</span><span class="mtx-kpi-icon"><i class="fas fa-calendar-alt"></i></span></div>
      <span class="mtx-kpi-value"><?= $totalBookings ?></span>
    </article>
    <article class="mtx-kpi" style="--kpi-color:#6b7280;">
      <div class="mtx-kpi-top"><span class="mtx-kpi-label">Pending</span><span class="mtx-kpi-icon"><i class="fas fa-clock"></i></span></div>
      <span class="mtx-kpi-value" style="<?= $pendingCount > 0 ? 'color:#d71920;' : '' ?>"><?= $pendingCount ?></span>
    </article>
    <article class="mtx-kpi" style="--kpi-color:#d97706;">
      <div class="mtx-kpi-top"><span class="mtx-kpi-label">In Progress</span><span class="mtx-kpi-icon"><i class="fas fa-wrench"></i></span></div>
      <span class="mtx-kpi-value" style="color:#d97706;"><?= $inProgressCount ?></span>
    </article>
    <article class="mtx-kpi" style="--kpi-color:#15803d;">
      <div class="mtx-kpi-top"><span class="mtx-kpi-label">Completed</span><span class="mtx-kpi-icon"><i class="fas fa-check-circle"></i></span></div>
      <span class="mtx-kpi-value" style="color:#15803d;"><?= $completedCount ?></span>
    </article>
  </section>

  <!-- Breakdown table -->
  <section class="mtx-card mtx-card--flush">
    <div class="mtx-card-head">
      <div>
        <h2><i class="fas fa-table-list"></i> Labor Breakdown by Booking</h2>
        <p>Labor shares are earned when a booking is completed. Click a row for the full booking detail.</p>
      </div>
      <form method="get" class="mtx-toolbar">
        <div class="mtx-field-search">
          <i class="fas fa-magnifying-glass"></i>
          <input type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Customer, email or #ID">
        </div>
        <select name="status">
          <option value="">All statuses</option>
          <?php foreach ($validStatuses as $s): ?>
            <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $s)) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="tech">
          <option value="">All technicians</option>
          <?php foreach ($technicians as $t): ?>
            <option value="<?= (int)$t['id'] ?>" <?= $techFilter === (int)$t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" title="From date">
        <input type="date" name="date_to"   value="<?= htmlspecialchars($dateTo) ?>"   title="To date">
        <button type="submit" class="mtx-btn mtx-btn--dark">Filter</button>
        <?php if ($search || $statusFilter || $dateFrom || $dateTo || $techFilter): ?>
          <a href="<?= baseUrl('admin/bookings.php') ?>" class="mtx-btn mtx-btn--ghost">Reset</a>
        <?php endif; ?>
      </form>
    </div>

    <?php if ($bookings): ?>
      <div class="mtx-table-wrap">
        <table class="mtx-table">
          <thead>
            <tr>
              <th>Booking</th>
              <th>Customer</th>
              <th>Motorcycle</th>
              <th>Technician</th>
              <th>Services</th>
              <th class="num">Labor Cost</th>
              <th class="num">Shop (<?= $shopPct ?>%)</th>
              <th class="num">Technician (<?= $techPct ?>%)</th>
              <th>Status</th>
              <th>Payment</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($bookings as $b): ?>
              <?php
                $bid = (int)$b['id'];
                $labor = (float)$b['svc_labor'];
                $svcNames = array_column($servicesByBooking[$bid] ?? [], 'service_name');
                $pay = bizBookingPaymentState($b['status']);
                $isCompleted = $b['status'] === 'completed';
              ?>
              <tr data-booking-row="<?= $bid ?>" role="button" tabindex="0" aria-haspopup="dialog">
                <td>
                  <div class="mtx-cell-main">
                    <strong>#<?= $bid ?></strong>
                    <span class="mtx-cell-sub">
                      <?= htmlspecialchars(date('M j, Y', strtotime($b['scheduled_date']))) ?><?= $b['scheduled_time'] ? ' · ' . htmlspecialchars(date('g:i A', strtotime($b['scheduled_time']))) : '' ?>
                    </span>
                  </div>
                </td>
                <td>
                  <div class="mtx-cell-main">
                    <strong><?= htmlspecialchars($b['customer_name']) ?></strong>
                    <span class="mtx-cell-sub"><?= htmlspecialchars($b['customer_email']) ?></span>
                  </div>
                </td>
                <td>
                  <div class="mtx-cell-main">
                    <strong><?= htmlspecialchars($b['vehicle_name'] ?: '—') ?></strong>
                    <span class="mtx-cell-sub"><?= htmlspecialchars($b['type_name'] ?? '') ?><?= $b['plate_number'] ? ' · ' . htmlspecialchars($b['plate_number']) : '' ?></span>
                  </div>
                </td>
                <td>
                  <?php if ($b['technician_name']): ?>
                    <span class="mtx-pill" style="--pill-color:#15803d;"><i class="fas fa-user-cog"></i> <?= htmlspecialchars($b['technician_name']) ?></span>
                  <?php else: ?>
                    <span class="mtx-cell-sub">Unassigned</span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="mtx-cell-main">
                    <?php if ($svcNames): ?>
                      <strong style="font-weight:600;"><?= htmlspecialchars(implode(', ', $svcNames)) ?></strong>
                      <span class="mtx-cell-sub"><?= count($svcNames) ?> service<?= count($svcNames) === 1 ? '' : 's' ?></span>
                    <?php else: ?>
                      <span class="mtx-cell-sub">—</span>
                    <?php endif; ?>
                  </div>
                </td>
                <td class="num"><span class="mtx-money"><?= formatPrice($labor) ?></span></td>
                <td class="num"><span class="mtx-money <?= $isCompleted ? 'mtx-money--green' : '' ?>"><?= formatPrice($labor * SHOP_LABOR_SHARE) ?></span></td>
                <td class="num"><span class="mtx-money <?= $isCompleted ? 'mtx-money--blue' : '' ?>"><?= formatPrice($labor * TECH_LABOR_SHARE) ?></span></td>
                <td>
                  <span class="mtx-pill" style="--pill-color:<?= $statusColor[$b['status']] ?? '#6b7280' ?>;">
                    <?= ucfirst(str_replace('_', ' ', $b['status'])) ?>
                  </span>
                </td>
                <td><span class="mtx-pill" style="--pill-color:<?= $pay['color'] ?>;"><?= $pay['label'] ?></span></td>
                <td class="num"><button type="button" class="mtx-btn mtx-btn--ghost mtx-btn--sm" data-booking-view="<?= $bid ?>">View</button></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="5">Completed bookings in this view</td>
              <td class="num"><?= formatPrice($filteredLabor) ?></td>
              <td class="num" style="color:#15803d;"><?= formatPrice($filteredLabor * SHOP_LABOR_SHARE) ?></td>
              <td class="num" style="color:#2563eb;"><?= formatPrice($filteredLabor * TECH_LABOR_SHARE) ?></td>
              <td colspan="3"></td>
            </tr>
          </tfoot>
        </table>
      </div>
      <div class="mtx-card-foot">
        <span>Showing <?= count($bookings) ?> booking<?= count($bookings) !== 1 ? 's' : '' ?><?= ($search || $statusFilter || $dateFrom || $dateTo || $techFilter) ? ' (filtered)' : '' ?></span>
        <span>Only completed bookings count toward labor earnings.</span>
      </div>
    <?php else: ?>
      <div style="padding:24px;">
        <div class="mtx-empty">
          <i class="fas fa-calendar-xmark"></i>
          <strong>No bookings match your filters.</strong>
          <span>Try widening the date range or clearing the search.</span>
        </div>
      </div>
    <?php endif; ?>
  </section>

</div><!-- /.mtx-shell -->

<!-- Booking detail modal -->
<div class="mtx-modal" id="bookingDetailModal" aria-hidden="true">
  <div class="mtx-modal__backdrop" data-close-modal></div>
  <div class="mtx-modal__dialog">
    <button type="button" class="mtx-modal__close" data-close-modal aria-label="Close">&times;</button>
    <h2 class="mtx-modal__title" id="bkTitle">Booking</h2>
    <p class="mtx-modal__meta" id="bkMeta"></p>

    <div class="mtx-modal-section">
      <h3>Customer &amp; Schedule</h3>
      <div class="mtx-kv-grid">
        <div class="mtx-kv"><span>Customer</span><strong id="bkCustomer"></strong></div>
        <div class="mtx-kv"><span>Email</span><strong id="bkEmail"></strong></div>
        <div class="mtx-kv"><span>Phone</span><strong id="bkPhone"></strong></div>
        <div class="mtx-kv"><span>Technician</span><strong id="bkTech"></strong></div>
        <div class="mtx-kv"><span>Date</span><strong id="bkDate"></strong></div>
        <div class="mtx-kv"><span>Time</span><strong id="bkTime"></strong></div>
      </div>
    </div>

    <div class="mtx-modal-section">
      <h3>Motorcycle</h3>
      <div class="mtx-kv-grid">
        <div class="mtx-kv"><span>Model</span><strong id="bkVehicle"></strong></div>
        <div class="mtx-kv"><span>Details</span><strong id="bkVehicleSub"></strong></div>
      </div>
    </div>

    <div class="mtx-modal-section">
      <h3>Services &amp; Labor Split</h3>
      <div class="mtx-line-rows" id="bkServices"></div>
    </div>

    <div class="mtx-modal-section" id="bkProductsSection">
      <h3>Products / Materials</h3>
      <div class="mtx-line-rows" id="bkProducts"></div>
    </div>

    <div class="mtx-modal-section">
      <h3>Financial Summary</h3>
      <div class="mtx-kv-grid">
        <div class="mtx-kv"><span>Labor Total (100%)</span><strong id="bkLaborTotal"></strong></div>
        <div class="mtx-kv"><span>Products Total</span><strong id="bkProductsTotal"></strong></div>
        <div class="mtx-kv"><span>Shop Share (<?= $shopPct ?>%)</span><strong id="bkShopShare" style="color:#15803d;"></strong></div>
        <div class="mtx-kv"><span>Technician Share (<?= $techPct ?>%)</span><strong id="bkTechShare" style="color:#2563eb;"></strong></div>
      </div>
      <div class="mtx-total-row"><span>Booking Total</span><span id="bkGrandTotal"></span></div>
    </div>

    <div class="mtx-modal-section" id="bkNotesSection">
      <h3>Notes</h3>
      <div class="mtx-line-rows" id="bkNotes"></div>
    </div>
  </div>
</div>

<script type="application/json" id="bookingDetailsData"><?= json_encode($bookingDetails, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?></script>

<?= authContextScriptTag() ?>
<script>
(function () {
  var details = {};
  try {
    details = JSON.parse(document.getElementById('bookingDetailsData').textContent || '{}');
  } catch (e) { details = {}; }

  var modal = document.getElementById('bookingDetailModal');

  function setText(id, value) {
    document.getElementById(id).textContent = value;
  }

  function lineRow(html) {
    var row = document.createElement('div');
    row.className = 'mtx-line-row';
    row.innerHTML = html;
    return row;
  }
  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function openBooking(id) {
    var b = details[id];
    if (!b) return;

    setText('bkTitle', 'Booking #' + b.id);
    var meta = document.getElementById('bkMeta');
    meta.innerHTML = '<span class="mtx-pill" style="--pill-color:' + b.statusColor + ';">' + esc(b.status) + '</span> ' +
                     '<span class="mtx-pill" style="--pill-color:' + b.payColor + ';">Payment: ' + esc(b.payment) + '</span>';

    setText('bkCustomer', b.customer);
    setText('bkEmail', b.email);
    setText('bkPhone', b.phone);
    setText('bkTech', b.technician);
    setText('bkDate', b.date);
    setText('bkTime', b.time);
    setText('bkVehicle', b.vehicle);
    setText('bkVehicleSub', b.vehicleSub || '—');

    var svcWrap = document.getElementById('bkServices');
    svcWrap.innerHTML = '';
    if (!b.services.length) {
      svcWrap.appendChild(lineRow('<span class="mtx-cell-sub">No services recorded.</span>'));
    } else {
      b.services.forEach(function (s) {
        svcWrap.appendChild(lineRow(
          '<span><i class="fas fa-tools" style="color:#2563eb;"></i>' + esc(s.name) + '</span>' +
          '<span class="mtx-line-shares">' +
            '<span>Shop ' + esc(s.shop) + '</span><span>Tech ' + esc(s.tech) + '</span>' +
            '<strong class="mtx-money">' + esc(s.labor) + '</strong>' +
          '</span>'
        ));
      });
    }

    var prodSection = document.getElementById('bkProductsSection');
    var prodWrap = document.getElementById('bkProducts');
    prodWrap.innerHTML = '';
    if (!b.products.length) {
      prodSection.style.display = 'none';
    } else {
      prodSection.style.display = '';
      b.products.forEach(function (p) {
        prodWrap.appendChild(lineRow(
          '<span><i class="fas fa-box" style="color:#d97706;"></i>' + esc(p.name) + '</span>' +
          '<strong class="mtx-money">' + esc(p.price) + '</strong>'
        ));
      });
    }

    setText('bkLaborTotal', b.laborTotal);
    setText('bkProductsTotal', b.productsTotal);
    setText('bkShopShare', b.shopShare);
    setText('bkTechShare', b.techShare);
    setText('bkGrandTotal', b.grandTotal);

    var notesSection = document.getElementById('bkNotesSection');
    var notesWrap = document.getElementById('bkNotes');
    notesWrap.innerHTML = '';
    if (!b.notes && !b.techNotes) {
      notesSection.style.display = 'none';
    } else {
      notesSection.style.display = '';
      if (b.notes) {
        notesWrap.appendChild(lineRow('<span><i class="fas fa-comment" style="color:#6b7280;"></i><strong style="font-weight:800;">Customer:</strong> ' + esc(b.notes) + '</span>'));
      }
      if (b.techNotes) {
        notesWrap.appendChild(lineRow('<span><i class="fas fa-sticky-note" style="color:#15803d;"></i><strong style="font-weight:800;">Technician:</strong> ' + esc(b.techNotes) + '</span>'));
      }
    }

    modal.classList.add('is-open');
  }

  document.querySelectorAll('[data-booking-view]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      openBooking(btn.getAttribute('data-booking-view'));
    });
  });
  document.querySelectorAll('[data-booking-row]').forEach(function (row) {
    row.addEventListener('click', function () { openBooking(row.getAttribute('data-booking-row')); });
    row.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openBooking(row.getAttribute('data-booking-row')); }
    });
  });

  modal.querySelectorAll('[data-close-modal]').forEach(function (el) {
    el.addEventListener('click', function () { modal.classList.remove('is-open'); });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') modal.classList.remove('is-open');
  });
})();
</script>
</main></div></div></body></html>
