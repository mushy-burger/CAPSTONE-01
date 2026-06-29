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

<section class="admin-hero">
  <div>
    <span class="eyebrow">Technician</span>
    <h1>Job History</h1>
    <p>Search and review all your past and current job assignments.</p>
  </div>
</section>

<!-- Filters -->
<section class="admin-card" style="margin-bottom:18px;padding:16px 24px;">
  <form method="get" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
    <input type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Customer name, phone or #ID" style="min-width:180px;flex:1;">
    <select name="status">
      <option value="">All statuses</option>
      <?php foreach ($validStatuses as $s): ?>
        <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" title="From date">
    <input type="date" name="date_to"   value="<?= htmlspecialchars($dateTo) ?>"   title="To date">
    <button type="submit" class="btn btn-outline">Search</button>
    <?php if ($search||$status||$dateFrom||$dateTo): ?>
      <a href="<?= baseUrl('tech/history.php') ?>" class="btn btn-outline">Reset</a>
    <?php endif; ?>
  </form>
</section>

<!-- Stats bar -->
<section class="metric-grid" style="margin-bottom:18px;">
  <article><span>Results</span><strong><?= $totalCount ?></strong><i class="fas fa-list"></i></article>
  <article><span>This Page</span><strong><?= count($jobs) ?></strong><i class="fas fa-file"></i></article>
  <article><span>Page</span><strong><?= $page ?> / <?= $totalPages ?></strong><i class="fas fa-book-open"></i></article>
</section>

<!-- Results -->
<section class="admin-card admin-page-stack">
  <?php if ($jobs): ?>
    <div class="admin-table-wrap">
      <table class="admin-data-table">
        <thead>
          <tr>
            <th>#</th><th>Customer</th><th>Vehicle</th><th>Services</th><th>Date</th><th>Total</th><th>Status</th><th>Notes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($jobs as $job): ?>
            <?php $sc = $statusColors[$job['status']] ?? $statusColors['pending']; ?>
            <tr>
              <td><strong>#<?= (int)$job['id'] ?></strong></td>
              <td>
                <strong><?= htmlspecialchars($job['customer_name']) ?></strong>
                <?php if ($job['customer_phone']): ?>
                  <div class="subtext"><?= htmlspecialchars($job['customer_phone']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <?= htmlspecialchars($job['bike_label'] ?? '—') ?>
                <?php if ($job['engine_cc']): ?><div class="subtext"><?= (int)$job['engine_cc'] ?>cc</div><?php endif; ?>
              </td>
              <td style="max-width:180px;font-size:.83rem;"><?= htmlspecialchars($job['services'] ?? '—') ?></td>
              <td>
                <?= htmlspecialchars($job['scheduled_date'] ? date('M j, Y', strtotime($job['scheduled_date'])) : '—') ?>
                <?php if ($job['scheduled_time']): ?><div class="subtext"><?= htmlspecialchars(date('g:i A', strtotime($job['scheduled_time']))) ?></div><?php endif; ?>
              </td>
              <td><strong><?= formatPrice((float)$job['total_amount']) ?></strong></td>
              <td>
                <span style="padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:900;background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;">
                  <?= strtoupper(str_replace('_',' ',$job['status'])) ?>
                </span>
              </td>
              <td style="max-width:140px;font-size:.82rem;color:#6b7280;">
                <?= $job['tech_notes'] ? htmlspecialchars(mb_substr($job['tech_notes'],0,60)).(mb_strlen($job['tech_notes'])>60?'…':'') : '—' ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
      <div style="display:flex;gap:8px;align-items:center;padding:14px 24px;border-top:1px solid var(--line);flex-wrap:wrap;">
        <?php
          $q = http_build_query(array_filter(['q'=>$search,'status'=>$status,'date_from'=>$dateFrom,'date_to'=>$dateTo]));
          $base = baseUrl('tech/history.php') . ($q?"?$q&":'?');
        ?>
        <a href="<?= $base ?>page=1" class="btn btn-outline" style="font-size:.82rem;" <?= $page===1?'aria-disabled="true"':'' ?>>«</a>
        <a href="<?= $base ?>page=<?= max(1,$page-1) ?>" class="btn btn-outline" style="font-size:.82rem;">‹ Prev</a>
        <span style="font-size:.85rem;color:var(--muted);">Page <?= $page ?> of <?= $totalPages ?></span>
        <a href="<?= $base ?>page=<?= min($totalPages,$page+1) ?>" class="btn btn-outline" style="font-size:.82rem;">Next ›</a>
        <a href="<?= $base ?>page=<?= $totalPages ?>" class="btn btn-outline" style="font-size:.82rem;" <?= $page===$totalPages?'aria-disabled="true"':'' ?>>»</a>
      </div>
    <?php endif; ?>
  <?php else: ?>
    <p class="empty-note">No jobs match your search.</p>
  <?php endif; ?>
</section>

<?= authContextScriptTag() ?>
</main></div></div></body></html>
