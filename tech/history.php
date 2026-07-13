<?php
$pageTitle = 'Job History';
require_once __DIR__ . '/../includes/tech-sidebar.php';
require_once __DIR__ . '/../includes/db.php';

$techId   = (int)$currentUser['id'];
$search   = trim($_GET['q'] ?? '');
$status   = trim($_GET['status'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo   = trim($_GET['date_to']   ?? '');

$validStatuses = ['completed','cancelled','in_progress','confirmed'];
$status = in_array($status, $validStatuses, true) ? $status : '';

// Pagination
$perPage    = 15;
$page       = max(1, (int)($_GET['page'] ?? 1));
$offset     = ($page - 1) * $perPage;

$where  = ['b.technician_id = ?'];
$params = [$techId];

if ($search !== '') {
    $where[]  = '(u.name LIKE ? OR u.phone LIKE ? OR b.id LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($status !== '') {
    $where[]  = 'b.status = ?';
    $params[] = $status;
}
if ($dateFrom !== '') {
    $where[]  = 'b.scheduled_date >= ?';
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $where[]  = 'b.scheduled_date <= ?';
    $params[] = $dateTo;
}

$whereStr = implode(' AND ', $where);

$totalCount = (int)(fetchOne(
    "SELECT COUNT(*) AS n FROM bookings b
     JOIN users u ON u.id = b.user_id
     WHERE $whereStr",
    $params
)['n'] ?? 0);

$totalPages = max(1, (int)ceil($totalCount / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$paginatedParams = array_merge($params, [$perPage, $offset]);

$jobs = fetchAllRows(
    "SELECT b.id, b.status, b.scheduled_date, b.scheduled_time, b.total_amount, b.tech_notes,
            u.name AS customer_name, u.phone AS customer_phone,
            v.cc AS engine_cc,
            CONCAT(mb.name,' ',mm.name) AS bike_label,
            svc.services
     FROM bookings b
     JOIN users u ON u.id = b.user_id
     LEFT JOIN customer_vehicles v ON v.id = b.vehicle_id
     LEFT JOIN motorcycle_models mm ON mm.id = v.model_id
     LEFT JOIN motorcycle_brands mb ON mb.id = mm.brand_id
     LEFT JOIN (
       SELECT booking_id, GROUP_CONCAT(service_name ORDER BY id SEPARATOR ', ') AS services
       FROM booking_services GROUP BY booking_id
     ) svc ON svc.booking_id = b.id
     WHERE $whereStr
     ORDER BY b.scheduled_date DESC, b.id DESC
     LIMIT ? OFFSET ?",
    $paginatedParams
);

$statusColors = [
    'confirmed'  => ['bg'=>'#eff6ff','color'=>'#1d4ed8'],
    'in_progress'=> ['bg'=>'#fffbeb','color'=>'#b45309'],
    'completed'  => ['bg'=>'#f0fdf4','color'=>'#15803d'],
    'cancelled'  => ['bg'=>'#fef2f2','color'=>'#b91c1c'],
    'pending'    => ['bg'=>'#f3f4f6','color'=>'#6b7280'],
];
?>

<div class="mtx-shell">

<header class="mtx-page-head">
  <div class="mtx-page-head-copy">
    <span class="eyebrow">Technician Panel</span>
    <h1>Job History</h1>
    <p>Search and review all your past and current job assignments.</p>
  </div>
  <div class="mtx-page-head-meta">
    <i class="fas fa-list"></i> <?= $totalCount ?> result<?= $totalCount === 1 ? '' : 's' ?> · Page <?= $page ?> of <?= $totalPages ?>
  </div>
</header>

<!-- Results -->
<section class="mtx-card mtx-card--flush">
  <div class="mtx-card-head">
    <div>
      <h2><i class="fas fa-history"></i> All Assignments</h2>
      <p>Newest first. Click a row action to open the full job detail.</p>
    </div>
    <form method="get" class="mtx-toolbar">
      <div class="mtx-field-search">
        <i class="fas fa-magnifying-glass"></i>
        <input type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Customer, phone or #ID">
      </div>
      <select name="status">
        <option value="">All statuses</option>
        <?php foreach ($validStatuses as $s): ?>
          <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" title="From date">
      <input type="date" name="date_to"   value="<?= htmlspecialchars($dateTo) ?>"   title="To date">
      <button type="submit" class="mtx-btn mtx-btn--dark">Search</button>
      <?php if ($search||$status||$dateFrom||$dateTo): ?>
        <a href="<?= baseUrl('tech/history.php') ?>" class="mtx-btn mtx-btn--ghost">Reset</a>
      <?php endif; ?>
    </form>
  </div>

  <?php if ($jobs): ?>
    <div class="mtx-table-wrap">
      <table class="mtx-table">
        <thead>
          <tr>
            <th>Job</th><th>Customer</th><th>Vehicle</th><th>Services</th><th class="num">Total</th><th>Status</th><th>Notes</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($jobs as $job): ?>
            <?php $sc = $statusColors[$job['status']] ?? $statusColors['pending']; ?>
            <tr>
              <td>
                <div class="mtx-cell-main">
                  <strong>#<?= (int)$job['id'] ?></strong>
                  <span class="mtx-cell-sub">
                    <?= htmlspecialchars($job['scheduled_date'] ? date('M j, Y', strtotime($job['scheduled_date'])) : '—') ?><?= $job['scheduled_time'] ? ' · ' . htmlspecialchars(date('g:i A', strtotime($job['scheduled_time']))) : '' ?>
                  </span>
                </div>
              </td>
              <td>
                <div class="mtx-cell-main">
                  <strong><?= htmlspecialchars($job['customer_name']) ?></strong>
                  <?php if ($job['customer_phone']): ?>
                    <span class="mtx-cell-sub"><?= htmlspecialchars($job['customer_phone']) ?></span>
                  <?php endif; ?>
                </div>
              </td>
              <td>
                <div class="mtx-cell-main">
                  <strong style="font-weight:600;"><?= htmlspecialchars($job['bike_label'] ?? '—') ?></strong>
                  <?php if ($job['engine_cc']): ?><span class="mtx-cell-sub"><?= (int)$job['engine_cc'] ?>cc</span><?php endif; ?>
                </div>
              </td>
              <td style="max-width:200px;"><span style="font-size:.83rem;"><?= htmlspecialchars($job['services'] ?? '—') ?></span></td>
              <td class="num"><span class="mtx-money"><?= formatPrice((float)$job['total_amount']) ?></span></td>
              <td>
                <span class="mtx-pill" style="--pill-color:<?= $sc['color'] ?>;">
                  <?= ucfirst(str_replace('_',' ',$job['status'])) ?>
                </span>
              </td>
              <td style="max-width:150px;">
                <span class="mtx-cell-sub" <?= $job['tech_notes'] ? 'title="' . htmlspecialchars($job['tech_notes'], ENT_QUOTES) . '"' : '' ?>>
                  <?= $job['tech_notes'] ? htmlspecialchars(mb_substr($job['tech_notes'],0,60)).(mb_strlen($job['tech_notes'])>60?'…':'') : '—' ?>
                </span>
              </td>
              <td class="num">
                <a href="<?= baseUrl('tech/job.php?id=' . (int)$job['id']) ?>" class="mtx-btn mtx-btn--ghost mtx-btn--sm"><i class="fas fa-eye"></i> View Details</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="mtx-card-foot">
      <span>Showing <?= count($jobs) ?> of <?= $totalCount ?> job<?= $totalCount === 1 ? '' : 's' ?></span>
      <?php if ($totalPages > 1): ?>
        <span style="display:flex;gap:6px;align-items:center;">
          <?php
            $q = http_build_query(array_filter(['q'=>$search,'status'=>$status,'date_from'=>$dateFrom,'date_to'=>$dateTo]));
            $base = baseUrl('tech/history.php') . ($q?"?$q&":'?');
          ?>
          <a href="<?= $base ?>page=1" class="mtx-btn mtx-btn--ghost mtx-btn--sm" <?= $page===1?'aria-disabled="true"':'' ?>>«</a>
          <a href="<?= $base ?>page=<?= max(1,$page-1) ?>" class="mtx-btn mtx-btn--ghost mtx-btn--sm">‹ Prev</a>
          <span style="font-size:.82rem;color:var(--muted);padding:0 6px;">Page <?= $page ?> of <?= $totalPages ?></span>
          <a href="<?= $base ?>page=<?= min($totalPages,$page+1) ?>" class="mtx-btn mtx-btn--ghost mtx-btn--sm">Next ›</a>
          <a href="<?= $base ?>page=<?= $totalPages ?>" class="mtx-btn mtx-btn--ghost mtx-btn--sm" <?= $page===$totalPages?'aria-disabled="true"':'' ?>>»</a>
        </span>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div style="padding:24px;">
      <div class="mtx-empty">
        <i class="fas fa-clipboard-list"></i>
        <strong>No jobs match your search.</strong>
        <span>Try widening the date range or clearing filters.</span>
      </div>
    </div>
  <?php endif; ?>
</section>

</div><!-- /.mtx-shell -->

<?= authContextScriptTag() ?>
</main></div></div></body></html>
