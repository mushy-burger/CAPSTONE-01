<?php
$pageTitle = 'Ratings & Reviews';
require_once __DIR__ . '/../includes/admin-sidebar.php';
require_once __DIR__ . '/../includes/db.php';

// --- Filters ---
$techFilter = (int)($_GET['tech_id'] ?? 0);
$ratingFilter = (int)($_GET['min_rating'] ?? 0);

// --- Summary stats ---
$stats = fetchOne(
    "SELECT
        COUNT(*)                                      AS total_ratings,
        ROUND(AVG(service_rating), 2)                 AS avg_service,
        ROUND(AVG(mechanic_rating), 2)                AS avg_mechanic,
        SUM(service_rating = 5)                       AS five_star,
        SUM(service_rating <= 2)                      AS low_star
     FROM booking_ratings"
);

// --- Per-technician averages ---
$techStats = fetchAllRows(
    "SELECT
        u.id,
        u.name,
        COUNT(r.id)                      AS rating_count,
        ROUND(AVG(r.mechanic_rating), 2) AS avg_mechanic,
        ROUND(AVG(r.service_rating), 2)  AS avg_service,
        SUM(r.mechanic_rating = 5)       AS five_star
     FROM users u
     JOIN booking_ratings r ON r.technician_id = u.id
     WHERE r.mechanic_rating IS NOT NULL
     GROUP BY u.id, u.name
     ORDER BY avg_mechanic DESC, rating_count DESC"
);

// --- All ratings list (paginated) ---
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset  = ($page - 1) * $perPage;

$whereClauses = [];
$params = [];
if ($techFilter > 0) {
    $whereClauses[] = 'r.technician_id = ?';
    $params[] = $techFilter;
}
if ($ratingFilter > 0) {
    $whereClauses[] = 'r.service_rating >= ?';
    $params[] = $ratingFilter;
}
$where = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$totalRows = (int)(fetchOne(
    "SELECT COUNT(*) AS n FROM booking_ratings r $where", $params
)['n'] ?? 0);
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$ratings = fetchAllRows(
    "SELECT r.*,
            u.name   AS customer_name,
            tech.name AS technician_name,
            b.scheduled_date,
            GROUP_CONCAT(bs.service_name ORDER BY bs.id SEPARATOR ', ') AS service_list
     FROM booking_ratings r
     JOIN users u ON u.id = r.user_id
     LEFT JOIN users tech ON tech.id = r.technician_id
     JOIN bookings b ON b.id = r.booking_id
     LEFT JOIN booking_services bs ON bs.booking_id = b.id
     $where
     GROUP BY r.id
     ORDER BY r.created_at DESC
     LIMIT $perPage OFFSET $offset",
    array_merge($params, [])
);

// --- Technician list for filter ---
$technicians = fetchAllRows(
    "SELECT id, name FROM users WHERE role = 'technician' AND is_active = 1 ORDER BY name"
);

function renderStars(int|null|string $rating, string $color = '#f59e0b'): string {
    if ($rating === null || $rating === '') return '<span style="color:#475569;">—</span>';
    $r = (int)$rating;
    $html = '<span style="color:' . $color . ';font-size:.95rem;letter-spacing:1px;">';
    for ($i = 1; $i <= 5; $i++) {
        $html .= $i <= $r ? '★' : '☆';
    }
    $html .= '</span> <span style="font-weight:700;font-size:.82rem;color:#94a3b8;">' . $r . '/5</span>';
    return $html;
}
?>

<div class="mtx-shell">

<header class="mtx-page-head">
  <div class="mtx-page-head-copy">
    <h1><i class="fas fa-star" style="color:#f59e0b;"></i> Ratings &amp; Reviews</h1>
    <p class="subtext">Customer feedback on service quality and technician performance.</p>
  </div>
</header>

<!-- Summary Cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:28px;">

  <div class="mtx-card" style="text-align:center;padding:20px 16px;">
    <div style="font-size:2rem;font-weight:900;color:#f59e0b;"><?= number_format((int)($stats['total_ratings'] ?? 0)) ?></div>
    <div style="font-size:.8rem;color:var(--muted);margin-top:4px;font-weight:600;">Total Ratings</div>
  </div>

  <div class="mtx-card" style="text-align:center;padding:20px 16px;">
    <div style="font-size:2rem;font-weight:900;color:#f59e0b;">
      <?= $stats['avg_service'] ? number_format((float)$stats['avg_service'], 1) : '—' ?>
    </div>
    <div style="font-size:.8rem;color:var(--muted);margin-top:4px;font-weight:600;">Avg. Service Rating</div>
    <div style="font-size:.85rem;color:#f59e0b;margin-top:2px;">★★★★★</div>
  </div>

  <div class="mtx-card" style="text-align:center;padding:20px 16px;">
    <div style="font-size:2rem;font-weight:900;color:#2563eb;">
      <?= $stats['avg_mechanic'] ? number_format((float)$stats['avg_mechanic'], 1) : '—' ?>
    </div>
    <div style="font-size:.8rem;color:var(--muted);margin-top:4px;font-weight:600;">Avg. Mechanic Rating</div>
  </div>

  <div class="mtx-card" style="text-align:center;padding:20px 16px;">
    <div style="font-size:2rem;font-weight:900;color:#15803d;"><?= (int)($stats['five_star'] ?? 0) ?></div>
    <div style="font-size:.8rem;color:var(--muted);margin-top:4px;font-weight:600;">5-Star Ratings</div>
  </div>

  <div class="mtx-card" style="text-align:center;padding:20px 16px;">
    <div style="font-size:2rem;font-weight:900;color:#b91c1c;"><?= (int)($stats['low_star'] ?? 0) ?></div>
    <div style="font-size:.8rem;color:var(--muted);margin-top:4px;font-weight:600;">Low Ratings (1–2★)</div>
  </div>

</div>

<!-- Technician Leaderboard -->
<?php if ($techStats): ?>
<section class="mtx-card" style="margin-bottom:28px;">
  <div class="mtx-card-head">
    <div><h2><i class="fas fa-trophy" style="color:#f59e0b;"></i> Technician Leaderboard</h2></div>
  </div>
  <div style="overflow-x:auto;">
    <table class="mtx-table" style="min-width:600px;">
      <thead>
        <tr>
          <th>#</th>
          <th>Technician</th>
          <th>Reviews</th>
          <th>Avg. Mechanic Rating</th>
          <th>Avg. Service Rating</th>
          <th>5-Star Jobs</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($techStats as $rank => $tech): ?>
          <tr>
            <td>
              <?php if ($rank === 0): ?>
                <span style="font-size:1.2rem;">🥇</span>
              <?php elseif ($rank === 1): ?>
                <span style="font-size:1.2rem;">🥈</span>
              <?php elseif ($rank === 2): ?>
                <span style="font-size:1.2rem;">🥉</span>
              <?php else: ?>
                <span style="color:var(--muted);font-weight:700;"><?= $rank + 1 ?></span>
              <?php endif; ?>
            </td>
            <td style="font-weight:700;"><?= htmlspecialchars($tech['name']) ?></td>
            <td><?= (int)$tech['rating_count'] ?></td>
            <td><?= renderStars($tech['avg_mechanic'] ? round($tech['avg_mechanic']) : null) ?></td>
            <td><?= renderStars($tech['avg_service'] ? round($tech['avg_service']) : null, '#2563eb') ?></td>
            <td>
              <span style="background:#f0fdf4;color:#15803d;border-radius:20px;padding:2px 10px;font-weight:700;font-size:.82rem;">
                <?= (int)$tech['five_star'] ?>
              </span>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>

<!-- Filters + Rating List -->
<section class="mtx-card">
  <div class="mtx-card-head">
    <div><h2><i class="fas fa-list"></i> All Reviews</h2></div>
  </div>

  <!-- Filter bar -->
  <form method="get" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px;align-items:flex-end;">
    <label class="mtx-field" style="flex:1;min-width:160px;">
      <span>Technician</span>
      <select name="tech_id">
        <option value="">All Technicians</option>
        <?php foreach ($technicians as $t): ?>
          <option value="<?= (int)$t['id'] ?>" <?= $techFilter === (int)$t['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($t['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="mtx-field" style="flex:1;min-width:130px;">
      <span>Min. Rating</span>
      <select name="min_rating">
        <option value="">Any</option>
        <?php for ($s = 5; $s >= 1; $s--): ?>
          <option value="<?= $s ?>" <?= $ratingFilter === $s ? 'selected' : '' ?>><?= $s ?>★ &amp; up</option>
        <?php endfor; ?>
      </select>
    </label>
    <button type="submit" class="mtx-btn mtx-btn--primary mtx-btn--sm">
      <i class="fas fa-filter"></i> Filter
    </button>
    <?php if ($techFilter || $ratingFilter): ?>
      <a href="<?= baseUrl('admin/ratings.php') ?>" class="mtx-btn mtx-btn--ghost mtx-btn--sm">
        <i class="fas fa-times"></i> Clear
      </a>
    <?php endif; ?>
  </form>

  <?php if ($ratings): ?>
    <div style="overflow-x:auto;">
      <table class="mtx-table">
        <thead>
          <tr>
            <th>Booking</th>
            <th>Customer</th>
            <th>Technician</th>
            <th>Service Rating</th>
            <th>Mechanic Rating</th>
            <th>Comment</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($ratings as $r): ?>
            <tr>
              <td>
                <a href="<?= baseUrl('staff/booking-detail.php?id=' . (int)$r['booking_id']) ?>"
                   style="font-weight:700;color:var(--accent);">#<?= (int)$r['booking_id'] ?></a>
                <?php if ($r['service_list']): ?>
                  <div style="font-size:.75rem;color:var(--muted);margin-top:2px;">
                    <?= htmlspecialchars(mb_strimwidth($r['service_list'], 0, 40, '…')) ?>
                  </div>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars($r['customer_name']) ?></td>
              <td>
                <?= $r['technician_name']
                    ? htmlspecialchars($r['technician_name'])
                    : '<span style="color:var(--muted);">—</span>' ?>
              </td>
              <td><?= renderStars($r['service_rating']) ?></td>
              <td><?= renderStars($r['mechanic_rating']) ?></td>
              <td style="max-width:220px;white-space:normal;">
                <?php if ($r['comment']): ?>
                  <span style="font-size:.83rem;color:var(--text);line-height:1.5;">
                    "<?= htmlspecialchars(mb_strimwidth($r['comment'], 0, 100, '…')) ?>"
                  </span>
                <?php else: ?>
                  <span style="color:var(--muted);font-size:.8rem;">No comment</span>
                <?php endif; ?>
              </td>
              <td style="white-space:nowrap;font-size:.82rem;color:var(--muted);">
                <?= date('M j, Y', strtotime($r['created_at'])) ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
      <div style="display:flex;gap:8px;justify-content:center;margin-top:20px;flex-wrap:wrap;">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
          <a href="?page=<?= $p ?>&tech_id=<?= $techFilter ?>&min_rating=<?= $ratingFilter ?>"
             class="mtx-btn <?= $p === $page ? 'mtx-btn--primary' : 'mtx-btn--ghost' ?> mtx-btn--sm">
            <?= $p ?>
          </a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>

  <?php else: ?>
    <div class="mtx-empty" style="padding:40px 0;text-align:center;">
      <i class="fas fa-star" style="font-size:2rem;color:var(--muted);display:block;margin-bottom:12px;"></i>
      <p style="color:var(--muted);">No ratings yet. They'll appear here once customers submit feedback after completed jobs.</p>
    </div>
  <?php endif; ?>

</section>

</div><!-- /.mtx-shell -->

<?= authContextScriptTag() ?>
</main></div></div></body></html>
