<?php
/**
 * Customer-facing service rating page.
 * URL: rate-booking.php?token=<64-char hex>
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$appointmentBookingId = (int)($_GET['booking_id'] ?? $_POST['booking_id'] ?? 0);
$isAppointmentRating = $appointmentBookingId > 0;
$token = trim((string)($_GET['token'] ?? ''));
$currentUser = null;
if ($isAppointmentRating) {
    requireLogin();
    $currentUser = getCurrentUser();
}
$booking = null;
$error = '';
$success = false;
$serviceRating = (int)($_POST['service_rating'] ?? 0);
$mechanicRating = isset($_POST['mechanic_rating']) && $_POST['mechanic_rating'] !== ''
    ? (int)$_POST['mechanic_rating']
    : null;
$comment = trim((string)($_POST['comment'] ?? ''));

if ($isAppointmentRating) {
    $booking = fetchOne(
        "SELECT b.id, b.user_id, b.technician_id, b.status,
                b.rating_token_used, b.scheduled_date,
                EXISTS(SELECT 1 FROM booking_ratings r WHERE r.booking_id = b.id) AS has_rating,
                u.name AS customer_name,
                tech.name AS technician_name,
                (SELECT GROUP_CONCAT(bs.service_name ORDER BY bs.id SEPARATOR ', ')
                 FROM booking_services bs
                 WHERE bs.booking_id = b.id) AS service_list
         FROM bookings b
         JOIN users u ON u.id = b.user_id
         LEFT JOIN users tech ON tech.id = b.technician_id
         WHERE b.id = ? AND b.user_id = ? AND b.status = 'completed'",
        [$appointmentBookingId, (int)$currentUser['id']]
    );

    if (!$booking) {
        $error = 'This completed appointment is unavailable for rating.';
    } elseif ((int)$booking['rating_token_used'] === 1 || (int)$booking['has_rating'] === 1) {
        $error = 'A rating was already submitted for this appointment. Thank you.';
    }
} elseif ($token === '' || strlen($token) !== 64 || !ctype_xdigit($token)) {
    $error = 'This rating link is invalid.';
} else {
    $booking = fetchOne(
        "SELECT b.id, b.user_id, b.technician_id, b.status,
                b.rating_token_used, b.scheduled_date,
                EXISTS(SELECT 1 FROM booking_ratings r WHERE r.booking_id = b.id) AS has_rating,
                u.name AS customer_name,
                tech.name AS technician_name,
                (SELECT GROUP_CONCAT(bs.service_name ORDER BY bs.id SEPARATOR ', ')
                 FROM booking_services bs
                 WHERE bs.booking_id = b.id) AS service_list
         FROM bookings b
         JOIN users u ON u.id = b.user_id
         LEFT JOIN users tech ON tech.id = b.technician_id
         WHERE b.rating_token = ? AND b.status = 'completed'",
        [$token]
    );

    if (!$booking) {
        $error = 'This rating link is invalid, or the booking is not complete.';
    } elseif ((int)$booking['rating_token_used'] === 1 || (int)$booking['has_rating'] === 1) {
        $error = 'A rating was already submitted for this booking. Thank you.';
    }
}

if (!$error && $booking && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($serviceRating < 1 || $serviceRating > 5) {
        $error = 'Select an overall service rating from 1 to 5 stars.';
    } elseif (!empty($booking['technician_id']) && !empty($booking['technician_name']) && $mechanicRating === null) {
        $error = 'Select a mechanic rating from 1 to 5 stars.';
    } elseif ($mechanicRating !== null && ($mechanicRating < 1 || $mechanicRating > 5)) {
        $error = 'Select a mechanic rating from 1 to 5 stars.';
    } elseif (mb_strlen($comment) > 800) {
        $error = 'Keep feedback within 800 characters.';
    } else {
        $db = getDB();
        $db->beginTransaction();
        try {
            if ($isAppointmentRating) {
                $claim = $db->prepare(
                    "UPDATE bookings
                     SET rating_token_used = 1
                     WHERE id = ? AND user_id = ? AND status = 'completed' AND rating_token_used = 0"
                );
                $claim->execute([$booking['id'], (int)$currentUser['id']]);
            } else {
                $claim = $db->prepare(
                    "UPDATE bookings
                     SET rating_token_used = 1
                     WHERE id = ? AND rating_token = ? AND rating_token_used = 0"
                );
                $claim->execute([$booking['id'], $token]);
            }
            if ($claim->rowCount() !== 1) {
                throw new RuntimeException('rating_already_used');
            }

            $db->prepare(
                "INSERT INTO booking_ratings
                    (booking_id, user_id, technician_id, service_rating, mechanic_rating, comment)
                 VALUES (?, ?, ?, ?, ?, ?)"
            )->execute([
                $booking['id'],
                $booking['user_id'],
                $booking['technician_id'] ?: null,
                $serviceRating,
                $mechanicRating,
                $comment !== '' ? $comment : null,
            ]);

            $db->commit();
            $success = true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = $e->getMessage() === 'rating_already_used'
                ? 'A rating was already submitted for this booking. Thank you.'
                : 'Your rating could not be saved. Please try again.';
        }
    }
}

$pageTitle = 'Rate Your Service - MotoTrack';
require_once __DIR__ . '/includes/header.php';
?>

<style>
.page-rate-booking .rating-page {
  min-height: calc(100vh - 76px);
  padding: 84px 0 96px;
  color: #f7f8fa;
  background: #0b0e12;
}
.page-rate-booking .rating-shell { width: min(680px, calc(100% - 32px)); margin: 0 auto; }
.page-rate-booking .rating-panel {
  padding: clamp(24px, 5vw, 42px);
  border: 1px solid rgba(255,255,255,.1);
  border-radius: 16px;
  background: #141920;
  box-shadow: 0 24px 64px rgba(0,0,0,.34);
}
.page-rate-booking .rating-heading {
  margin: 0;
  color: #fff;
  font-size: clamp(2rem, 6vw, 3.2rem);
  line-height: 1;
  letter-spacing: -.03em;
}
.page-rate-booking .rating-intro {
  max-width: 60ch;
  margin: 14px 0 28px;
  color: rgba(255,255,255,.66);
}
.page-rate-booking .rating-meta {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 1px;
  margin-bottom: 30px;
  overflow: hidden;
  border: 1px solid rgba(255,255,255,.1);
  border-radius: 12px;
  background: rgba(255,255,255,.1);
}
.page-rate-booking .rating-meta-item { min-width: 0; padding: 14px; background: #10151b; }
.page-rate-booking .rating-meta-item span,
.page-rate-booking .rating-meta-item strong { display: block; }
.page-rate-booking .rating-meta-item span {
  margin-bottom: 4px;
  color: rgba(255,255,255,.5);
  font-size: .72rem;
  font-weight: 800;
  text-transform: uppercase;
}
.page-rate-booking .rating-meta-item strong {
  overflow-wrap: anywhere;
  color: #fff;
  font-size: .9rem;
}
.page-rate-booking .rating-group { margin-bottom: 26px; }
.page-rate-booking .rating-label {
  display: flex;
  align-items: center;
  gap: 9px;
  margin-bottom: 11px;
  color: #fff;
  font-weight: 800;
}
.page-rate-booking .rating-label i { color: #ff6269; }
.page-rate-booking .rating-required,
.page-rate-booking .rating-optional {
  color: rgba(255,255,255,.52);
  font-size: .72rem;
  font-weight: 700;
}
.page-rate-booking .rating-optional {
  padding: 3px 8px;
  border: 1px solid rgba(255,255,255,.14);
  border-radius: 999px;
}
.page-rate-booking .rating-stars {
  display: flex;
  flex-direction: row-reverse;
  justify-content: flex-end;
  gap: 8px;
}
.page-rate-booking .rating-stars input {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  opacity: 0;
}
.page-rate-booking .rating-stars label {
  color: rgba(255,255,255,.22);
  font-size: 2.15rem;
  line-height: 1;
  cursor: pointer;
  transition: color 160ms ease, transform 160ms ease;
}
.page-rate-booking .rating-stars label:hover,
.page-rate-booking .rating-stars label:hover ~ label,
.page-rate-booking .rating-stars input:checked ~ label {
  color: var(--gold);
  transform: translateY(-2px);
}
.page-rate-booking .rating-stars input:focus-visible + label {
  outline: 3px solid #ff6269;
  outline-offset: 4px;
  border-radius: 4px;
}
.page-rate-booking .rating-feedback {
  min-height: 20px;
  margin-top: 8px;
  color: rgba(255,255,255,.58);
  font-size: .82rem;
}
.page-rate-booking .rating-feedback[data-tone="good"] { color: #7ee2ad; }
.page-rate-booking .rating-feedback[data-tone="warn"] { color: #ffd073; }
.page-rate-booking .rating-feedback[data-tone="bad"] { color: #ff8c92; }
.page-rate-booking .rating-comment {
  width: 100%;
  min-height: 112px;
  padding: 13px 14px;
  resize: vertical;
  border: 1px solid rgba(255,255,255,.16);
  border-radius: 12px;
  outline: none;
  color: #fff;
  background: #0f141a;
  font: inherit;
}
.page-rate-booking .rating-comment::placeholder { color: rgba(255,255,255,.44); }
.page-rate-booking .rating-comment:focus {
  border-color: #ff6269;
  box-shadow: 0 0 0 3px rgba(215,25,32,.18);
}
.page-rate-booking .rating-count {
  margin-top: 6px;
  color: rgba(255,255,255,.5);
  font-size: .75rem;
  text-align: right;
}
.page-rate-booking .rating-submit { width: 100%; }
.page-rate-booking .rating-submit:disabled { cursor: wait; opacity: .68; }
.page-rate-booking .rating-alert {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  margin: 0 0 22px;
  padding: 13px 14px;
  border: 1px solid rgba(255,98,105,.34);
  border-radius: 12px;
  color: #ffd5d7;
  background: rgba(215,25,32,.14);
}
.page-rate-booking .rating-state { text-align: center; }
.page-rate-booking .rating-state-icon {
  display: grid;
  width: 72px;
  height: 72px;
  margin: 0 auto 22px;
  place-items: center;
  border-radius: 50%;
  color: #fff;
  background: var(--accent);
  box-shadow: 0 14px 32px rgba(215,25,32,.28);
  font-size: 1.8rem;
}
.page-rate-booking .rating-state h1 {
  margin: 0 0 12px;
  color: #fff;
  font-size: clamp(1.8rem, 5vw, 2.6rem);
  letter-spacing: -.025em;
}
.page-rate-booking .rating-state p {
  max-width: 54ch;
  margin: 0 auto 24px;
  color: rgba(255,255,255,.64);
}
.page-rate-booking .rating-result-stars {
  margin-bottom: 18px;
  color: var(--gold);
  font-size: 1.5rem;
}
@media (max-width: 640px) {
  .page-rate-booking .rating-page { padding: 48px 0 64px; }
  .page-rate-booking .rating-meta { grid-template-columns: 1fr; }
  .page-rate-booking .rating-stars label { font-size: 1.95rem; }
}
@media (prefers-reduced-motion: reduce) {
  .page-rate-booking .rating-stars label { transition: none; }
}
</style>

<section class="rating-page">
  <div class="rating-shell">
    <div class="rating-panel">
      <?php if ($success): ?>
        <div class="rating-state" role="status">
          <div class="rating-state-icon"><i class="fas fa-star" aria-hidden="true"></i></div>
          <h1>Thank you for your feedback</h1>
          <div class="rating-result-stars" aria-label="<?= $serviceRating ?> out of 5 stars">
            <?php for ($i = 1; $i <= 5; $i++): ?>
              <i class="<?= $i <= $serviceRating ? 'fas' : 'far' ?> fa-star" aria-hidden="true"></i>
            <?php endfor; ?>
          </div>
          <p>Your rating helps MotoTrack improve service and recognize excellent work.</p>
          <a class="btn btn-primary" href="<?= $isAppointmentRating ? baseUrl('book-service.php?tab=appointments&ctx=' . urlencode(currentAuthContext())) : baseUrl('index.php') ?>"><?= $isAppointmentRating ? 'Back to My Appointments' : 'Return home' ?></a>
        </div>
      <?php elseif ($error && (!$booking || (int)($booking['rating_token_used'] ?? 0) === 1)): ?>
        <div class="rating-state">
          <div class="rating-state-icon"><i class="fas fa-link-slash" aria-hidden="true"></i></div>
          <h1>Rating link unavailable</h1>
          <div class="rating-alert" role="alert">
            <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
            <span><?= htmlspecialchars($error) ?></span>
          </div>
          <p>Contact MotoTrack if you received this link for a completed service.</p>
          <a class="btn btn-primary" href="<?= baseUrl('contact.php') ?>">Contact MotoTrack</a>
        </div>
      <?php else: ?>
        <h1 class="rating-heading">Rate your service</h1>
        <p class="rating-intro">Tell us how your MotoTrack visit went. Your feedback helps improve future service.</p>

        <div class="rating-meta">
          <div class="rating-meta-item">
            <span>Service</span>
            <strong><?= htmlspecialchars($booking['service_list'] ?: 'Motorcycle Service') ?></strong>
          </div>
          <div class="rating-meta-item">
            <span>Date</span>
            <strong><?= htmlspecialchars(date('F j, Y', strtotime($booking['scheduled_date']))) ?></strong>
          </div>
          <div class="rating-meta-item">
            <span>Technician</span>
            <strong><?= htmlspecialchars($booking['technician_name'] ?: 'MotoTrack team') ?></strong>
          </div>
        </div>

        <?php if ($error): ?>
          <div class="rating-alert" role="alert">
            <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
            <span><?= htmlspecialchars($error) ?></span>
          </div>
        <?php endif; ?>

        <form method="post" id="ratingForm">
          <?php if ($isAppointmentRating): ?>
            <?= authContextField() ?>
            <input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>">
          <?php endif; ?>
          <div class="rating-group">
            <div class="rating-label">
              <i class="fas fa-star" aria-hidden="true"></i>
              <span>Overall service</span>
              <span class="rating-required">Required</span>
            </div>
            <div class="rating-stars" id="serviceStars">
              <?php for ($i = 5; $i >= 1; $i--): ?>
                <input type="radio" name="service_rating" id="sr<?= $i ?>" value="<?= $i ?>" required <?= $serviceRating === $i ? 'checked' : '' ?>>
                <label for="sr<?= $i ?>" aria-label="<?= $i ?> star<?= $i === 1 ? '' : 's' ?>"><i class="fas fa-star" aria-hidden="true"></i></label>
              <?php endfor; ?>
            </div>
            <div class="rating-feedback" id="serviceRatingText" aria-live="polite"></div>
          </div>

          <?php if ($booking['technician_name']): ?>
            <div class="rating-group">
              <div class="rating-label">
                <i class="fas fa-user-cog" aria-hidden="true"></i>
                <span>Your Mechanic: <?= htmlspecialchars($booking['technician_name']) ?></span>
                <span class="rating-required">Required</span>
              </div>
              <div class="rating-stars" id="mechanicStars">
                <?php for ($i = 5; $i >= 1; $i--): ?>
                  <input type="radio" name="mechanic_rating" id="mr<?= $i ?>" value="<?= $i ?>" required <?= $mechanicRating === $i ? 'checked' : '' ?>>
                  <label for="mr<?= $i ?>" aria-label="<?= $i ?> star<?= $i === 1 ? '' : 's' ?>"><i class="fas fa-star" aria-hidden="true"></i></label>
                <?php endfor; ?>
              </div>
              <div class="rating-feedback" id="mechanicRatingText" aria-live="polite"></div>
            </div>
          <?php endif; ?>

          <div class="rating-group">
            <label class="rating-label" for="comment">
              <i class="fas fa-comment-dots" aria-hidden="true"></i>
              <span>Written feedback</span>
              <span class="rating-optional">Optional</span>
            </label>
            <textarea class="rating-comment" name="comment" id="comment" placeholder="What went well? What could we improve?" maxlength="800"><?= htmlspecialchars($comment) ?></textarea>
            <div class="rating-count"><span id="charCount"><?= mb_strlen($comment) ?></span>/800</div>
          </div>

          <button type="submit" class="btn btn-primary rating-submit" id="submitBtn">
            <i class="fas fa-paper-plane" aria-hidden="true"></i> Submit rating
          </button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</section>

<script>
(function () {
  var labels = {
    1: 'Poor - did not meet expectations',
    2: 'Below average - needs improvement',
    3: 'Average - acceptable with room to improve',
    4: 'Good - satisfied with the service',
    5: 'Excellent - exceeded expectations'
  };

  function watchStars(groupId, textId) {
    var group = document.getElementById(groupId);
    var output = document.getElementById(textId);
    if (!group || !output) return;

    function showRating(input) {
      if (!input || !input.checked) return;
      output.textContent = labels[input.value] || '';
      output.dataset.tone = input.value >= 4 ? 'good' : (input.value <= 2 ? 'bad' : 'warn');
    }

    group.querySelectorAll('input[type="radio"]').forEach(function (input) {
      input.addEventListener('change', function () { showRating(input); });
      showRating(input);
    });
  }

  watchStars('serviceStars', 'serviceRatingText');
  watchStars('mechanicStars', 'mechanicRatingText');

  var comment = document.getElementById('comment');
  var count = document.getElementById('charCount');
  if (comment && count) {
    comment.addEventListener('input', function () { count.textContent = comment.value.length; });
  }

  var form = document.getElementById('ratingForm');
  var button = document.getElementById('submitBtn');
  if (form && button) {
    form.addEventListener('submit', function () {
      button.disabled = true;
      button.innerHTML = '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Submitting';
    });
  }
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
