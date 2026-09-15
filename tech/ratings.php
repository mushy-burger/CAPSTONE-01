<?php
$pageTitle = 'My Ratings';
require_once __DIR__ . '/../includes/tech-sidebar.php';
require_once __DIR__ . '/../includes/db.php';

$techId = (int)$currentUser['id'];
$summary = fetchOne(
    "SELECT COUNT(*) AS total_reviews,
            ROUND(AVG(mechanic_rating), 2) AS average_rating,
            SUM(mechanic_rating = 5) AS five_star_reviews
     FROM booking_ratings
     WHERE technician_id = ? AND mechanic_rating IS NOT NULL",
    [$techId]
) ?? ['total_reviews' => 0, 'average_rating' => null, 'five_star_reviews' => 0];

$reviews = fetchAllRows(
    "SELECT r.booking_id, r.service_rating, r.mechanic_rating, r.comment, r.created_at,
            b.scheduled_date,
            (SELECT GROUP_CONCAT(bs.service_name ORDER BY bs.id SEPARATOR ', ')
             FROM booking_services bs WHERE bs.booking_id = b.id) AS service_list
     FROM booking_ratings r
     JOIN bookings b ON b.id = r.booking_id
     WHERE r.technician_id = ? AND r.mechanic_rating IS NOT NULL
     ORDER BY r.created_at DESC, r.id DESC",
    [$techId]
);
?>

<style>
  .tech-ratings { display:grid; gap:24px; }
  .tech-ratings__intro { max-width:62ch; margin:0; color:#667085; }
  .tech-ratings__stats { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:16px; }
  .tech-ratings__stat { min-height:132px; padding:22px; border:1px solid #e7eaf0; border-radius:14px; background:#fff; box-shadow:0 12px 28px rgba(15,23,42,.06); }
  .tech-ratings__stat span { display:block; margin-bottom:13px; color:#667085; font-size:.75rem; font-weight:800; letter-spacing:.07em; text-transform:uppercase; }
  .tech-ratings__stat strong { color:#172033; font-size:clamp(1.75rem,3vw,2.35rem); line-height:1; font-variant-numeric:tabular-nums; }
  .tech-ratings__stat strong i { color:#f4b740; font-size:.86em; }
  .tech-ratings__review-list { display:grid; gap:12px; padding:4px 0; }
  .tech-ratings__review { padding:20px; border:1px solid #e7eaf0; border-radius:14px; background:#fff; }
  .tech-ratings__review-head { display:flex; align-items:flex-start; justify-content:space-between; gap:18px; }
  .tech-ratings__review-title { display:grid; gap:5px; }
  .tech-ratings__review-title strong { color:#172033; }
  .tech-ratings__review-title span { color:#667085; font-size:.82rem; }
  .tech-ratings__stars { flex:0 0 auto; color:#f4b740; letter-spacing:.06em; }
  .tech-ratings__comment { margin:16px 0 0; padding-top:16px; border-top:1px solid #eef0f4; color:#344054; line-height:1.65; }
  .tech-ratings__comment--empty { color:#98a2b3; font-style:italic; }
  @media (max-width:760px) { .tech-ratings__stats { grid-template-columns:1fr; } }
  @media (max-width:520px) { .tech-ratings__review { padding:16px; } .tech-ratings__review-head { display:grid; gap:10px; } }
</style>

<div class="mtx-shell tech-ratings">
  <header class="mtx-page-head">
    <div class="mtx-page-head-copy">
      <h1>My Ratings</h1>
      <p class="tech-ratings__intro">Customer feedback about your completed service work.</p>
    </div>
  </header>

  <section class="tech-ratings__stats" aria-label="My rating summary">
    <article class="tech-ratings__stat"><span>Average Rating</span><strong><i class="fas fa-star" aria-hidden="true"></i> <?= $summary['average_rating'] !== null ? number_format((float)$summary['average_rating'], 1) : '—' ?></strong></article>
    <article class="tech-ratings__stat"><span>Total Reviews</span><strong><?= (int)$summary['total_reviews'] ?></strong></article>
    <article class="tech-ratings__stat"><span>5-Star Reviews</span><strong><?= (int)$summary['five_star_reviews'] ?></strong></article>
  </section>

  <section class="mtx-card">
    <div class="mtx-card-head">
      <div><h2><i class="fas fa-star"></i> Customer Reviews</h2><p>Only ratings submitted for your assigned completed jobs appear here.</p></div>
    </div>
    <?php if ($reviews): ?>
      <div class="tech-ratings__review-list">
        <?php foreach ($reviews as $review): ?>
          <article class="tech-ratings__review">
            <div class="tech-ratings__review-head">
              <div class="tech-ratings__review-title">
                <strong>Appointment #<?= (int)$review['booking_id'] ?></strong>
                <span><?= htmlspecialchars($review['service_list'] ?: 'Motorcycle Service') ?> · <?= htmlspecialchars(date('M j, Y', strtotime($review['scheduled_date']))) ?></span>
              </div>
              <div class="tech-ratings__stars" aria-label="<?= (int)$review['mechanic_rating'] ?> out of 5 stars">
                <?php for ($i = 1; $i <= 5; $i++): ?><i class="<?= $i <= (int)$review['mechanic_rating'] ? 'fas' : 'far' ?> fa-star" aria-hidden="true"></i><?php endfor; ?>
              </div>
            </div>
            <?php if (!empty($review['comment'])): ?>
              <p class="tech-ratings__comment">“<?= nl2br(htmlspecialchars($review['comment'])) ?>”</p>
            <?php else: ?>
              <p class="tech-ratings__comment tech-ratings__comment--empty">No written comment left.</p>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="empty-state">No mechanic ratings yet.</p>
    <?php endif; ?>
  </section>
</div>

<?= authContextScriptTag() ?>
</main></div></div></body></html>
