<?php
/**
 * Customer-facing service rating page.
 *
 * URL: rate-booking.php?token=<64-char hex>
 *
 * The token is SHA-256(booking_id . secret_key . user_id), generated when the
 * job completes and sent via SMS/email. It is single-use: rating_token_used is
 * set to 1 on submission so the link cannot be replayed.
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$token = trim($_GET['token'] ?? '');

$booking = null;
$error   = '';
$success = false;

if ($token === '' || strlen($token) !== 64 || !ctype_xdigit($token)) {
    $error = 'This rating link is invalid or has expired.';
} else {
    $booking = fetchOne(
        "SELECT b.id, b.user_id, b.technician_id, b.status,
                b.rating_token_used, b.scheduled_date,
                u.name AS customer_name,
                tech.name AS technician_name,
                GROUP_CONCAT(bs.service_name ORDER BY bs.id SEPARATOR ', ') AS service_list
         FROM bookings b
         JOIN users u ON u.id = b.user_id
         LEFT JOIN users tech ON tech.id = b.technician_id
         LEFT JOIN booking_services bs ON bs.booking_id = b.id
         WHERE b.rating_token = ? AND b.status = 'completed'
         GROUP BY b.id",
        [$token]
    );

    if (!$booking) {
        $error = 'This rating link is invalid, has already been used, or the booking is not yet complete.';
    } elseif ((int)$booking['rating_token_used'] === 1) {
        $error = 'You have already submitted a rating for this booking. Thank you!';
    }
}

// POST — submit the rating
if (!$error && $booking && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $serviceRating  = (int)($_POST['service_rating']  ?? 0);
    $mechanicRating = isset($_POST['mechanic_rating']) && $_POST['mechanic_rating'] !== ''
                        ? (int)$_POST['mechanic_rating'] : null;
    $comment        = trim($_POST['comment'] ?? '');

    if ($serviceRating < 1 || $serviceRating > 5) {
        $error = 'Please select a service rating (1–5 stars).';
    } elseif ($mechanicRating !== null && ($mechanicRating < 1 || $mechanicRating > 5)) {
        $error = 'Mechanic rating must be between 1 and 5 stars.';
    } else {
        $db = getDB();
        $db->beginTransaction();
        try {
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

            // Invalidate the token
            $db->prepare("UPDATE bookings SET rating_token_used = 1 WHERE id = ?")
               ->execute([$booking['id']]);

            $db->commit();
            $success = true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = 'Something went wrong. Please try again.';
        }
    }
}

$pageTitle = 'Rate Your Service — MotoTrack';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <meta name="description" content="Rate your MotoTrack service experience and help us improve.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --bg: #0f172a;
      --surface: #1e293b;
      --border: #334155;
      --accent: #f59e0b;
      --accent2: #2563eb;
      --text: #f1f5f9;
      --muted: #94a3b8;
      --success: #22c55e;
      --danger: #ef4444;
      --radius: 16px;
    }
    body {
      font-family: 'Inter', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px 16px;
      background-image: radial-gradient(ellipse at 20% 50%, rgba(37,99,235,.15) 0%, transparent 60%),
                        radial-gradient(ellipse at 80% 20%, rgba(245,158,11,.1) 0%, transparent 50%);
    }
    .card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 40px 36px;
      width: 100%;
      max-width: 520px;
      box-shadow: 0 24px 64px rgba(0,0,0,.4);
    }
    .brand {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 28px;
    }
    .brand-icon {
      width: 42px;
      height: 42px;
      background: linear-gradient(135deg, #d71920, #f59e0b);
      border-radius: 10px;
      display: grid;
      place-items: center;
      font-size: 1.2rem;
      color: #fff;
    }
    .brand-name { font-size: 1.3rem; font-weight: 800; color: var(--text); }
    h1 { font-size: 1.5rem; font-weight: 800; margin-bottom: 6px; }
    .subtitle { color: var(--muted); font-size: .9rem; margin-bottom: 28px; line-height: 1.5; }
    .booking-meta {
      background: rgba(255,255,255,.04);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 14px 18px;
      margin-bottom: 28px;
      font-size: .85rem;
      color: var(--muted);
      line-height: 1.7;
    }
    .booking-meta strong { color: var(--text); }

    /* Star rating widget */
    .rating-group { margin-bottom: 24px; }
    .rating-label {
      font-weight: 700;
      font-size: .88rem;
      margin-bottom: 10px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .rating-label i { color: var(--accent); }
    .stars-wrap {
      display: flex;
      gap: 6px;
      flex-direction: row-reverse;
      justify-content: flex-end;
    }
    .stars-wrap input[type="radio"] { display: none; }
    .stars-wrap label {
      font-size: 2rem;
      color: var(--border);
      cursor: pointer;
      transition: color .15s, transform .15s;
      line-height: 1;
    }
    .stars-wrap label:hover,
    .stars-wrap label:hover ~ label,
    .stars-wrap input:checked ~ label { color: var(--accent); transform: scale(1.1); }
    .stars-wrap input:checked + label { color: var(--accent); }

    .optional-badge {
      font-size: .72rem;
      background: rgba(148,163,184,.15);
      color: var(--muted);
      border-radius: 20px;
      padding: 2px 8px;
      font-weight: 600;
    }
    textarea {
      width: 100%;
      background: rgba(255,255,255,.04);
      border: 1px solid var(--border);
      border-radius: 10px;
      color: var(--text);
      font-family: inherit;
      font-size: .88rem;
      padding: 12px 14px;
      resize: vertical;
      min-height: 90px;
      transition: border-color .2s;
      margin-top: 10px;
    }
    textarea:focus { outline: none; border-color: var(--accent2); }
    textarea::placeholder { color: var(--muted); }

    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      width: 100%;
      padding: 14px 24px;
      background: linear-gradient(135deg, #d71920, #f59e0b);
      color: #fff;
      font-family: inherit;
      font-size: 1rem;
      font-weight: 800;
      border: none;
      border-radius: 12px;
      cursor: pointer;
      transition: opacity .2s, transform .15s;
      margin-top: 8px;
    }
    .btn:hover { opacity: .92; transform: translateY(-1px); }
    .btn:active { transform: translateY(0); }

    .alert {
      padding: 14px 16px;
      border-radius: 10px;
      font-size: .88rem;
      font-weight: 600;
      margin-bottom: 20px;
      display: flex;
      align-items: flex-start;
      gap: 10px;
    }
    .alert-error { background: rgba(239,68,68,.12); border: 1px solid rgba(239,68,68,.3); color: #fca5a5; }
    .alert-info  { background: rgba(37,99,235,.12);  border: 1px solid rgba(37,99,235,.3);  color: #93c5fd; }

    /* Success state */
    .success-state { text-align: center; padding: 16px 0; }
    .success-icon {
      width: 80px;
      height: 80px;
      background: rgba(34,197,94,.12);
      border-radius: 50%;
      display: grid;
      place-items: center;
      margin: 0 auto 20px;
      font-size: 2.2rem;
      color: var(--success);
      border: 2px solid rgba(34,197,94,.3);
    }
    .success-state h2 { font-size: 1.4rem; font-weight: 800; margin-bottom: 10px; }
    .success-state p { color: var(--muted); font-size: .9rem; line-height: 1.6; }
    .submitted-stars { font-size: 1.5rem; margin: 12px 0; color: var(--accent); }

    hr.divider { border: none; border-top: 1px solid var(--border); margin: 20px 0; }
  </style>
</head>
<body>
<div class="card">

  <div class="brand">
    <div class="brand-icon"><i class="fas fa-motorcycle"></i></div>
    <span class="brand-name">MotoTrack</span>
  </div>

  <?php if ($success): ?>
    <!-- SUCCESS STATE -->
    <div class="success-state">
      <div class="success-icon"><i class="fas fa-star"></i></div>
      <h2>Thank You for Your Feedback!</h2>
      <div class="submitted-stars">
        <?php for ($i = 1; $i <= 5; $i++): ?>
          <i class="fas fa-star<?= $i <= $serviceRating ? '' : '-o' ?>"></i>
        <?php endfor; ?>
      </div>
      <p>Your rating has been submitted. It helps us improve our service and recognize our team members who go the extra mile.</p>
      <hr class="divider">
      <p style="font-size:.82rem;color:var(--muted);">This link is now expired. Thank you for choosing MotoTrack!</p>
    </div>

  <?php elseif ($error): ?>
    <!-- ERROR STATE -->
    <h1>Rate Your Service</h1>
    <div class="alert alert-error" role="alert">
      <i class="fas fa-circle-exclamation" style="margin-top:2px;flex-shrink:0;"></i>
      <span><?= htmlspecialchars($error) ?></span>
    </div>
    <p class="subtitle">If you believe this is a mistake, please contact us directly.</p>

  <?php else: ?>
    <!-- RATING FORM -->
    <h1>Rate Your Service</h1>
    <p class="subtitle">
      How was your experience at MotoTrack? Your honest feedback helps us serve you better.
    </p>

    <div class="booking-meta">
      <div><strong>Service:</strong> <?= htmlspecialchars($booking['service_list'] ?: 'Motorcycle Service') ?></div>
      <div><strong>Date:</strong> <?= htmlspecialchars(date('F j, Y', strtotime($booking['scheduled_date']))) ?></div>
      <?php if ($booking['technician_name']): ?>
        <div><strong>Technician:</strong> <?= htmlspecialchars($booking['technician_name']) ?></div>
      <?php endif; ?>
    </div>

    <form method="post" id="ratingForm">

      <!-- Overall Service Rating -->
      <div class="rating-group">
        <div class="rating-label">
          <i class="fas fa-star"></i>
          Overall Service Experience <span style="color:#ef4444;margin-left:2px;">*</span>
        </div>
        <div class="stars-wrap" id="serviceStars">
          <?php for ($i = 5; $i >= 1; $i--): ?>
            <input type="radio" name="service_rating" id="sr<?= $i ?>" value="<?= $i ?>" required>
            <label for="sr<?= $i ?>" title="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>">&#9733;</label>
          <?php endfor; ?>
        </div>
        <div id="serviceRatingText" style="font-size:.8rem;color:var(--muted);margin-top:8px;min-height:18px;"></div>
      </div>

      <?php if ($booking['technician_name']): ?>
      <!-- Mechanic Rating -->
      <div class="rating-group">
        <div class="rating-label">
          <i class="fas fa-user-cog"></i>
          Mechanic: <?= htmlspecialchars($booking['technician_name']) ?>
          <span class="optional-badge">optional</span>
        </div>
        <div class="stars-wrap" id="mechanicStars">
          <?php for ($i = 5; $i >= 1; $i--): ?>
            <input type="radio" name="mechanic_rating" id="mr<?= $i ?>" value="<?= $i ?>">
            <label for="mr<?= $i ?>" title="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>">&#9733;</label>
          <?php endfor; ?>
        </div>
        <div id="mechanicRatingText" style="font-size:.8rem;color:var(--muted);margin-top:8px;min-height:18px;"></div>
      </div>
      <?php endif; ?>

      <!-- Comment -->
      <div class="rating-group">
        <div class="rating-label">
          <i class="fas fa-comment-dots"></i>
          Tell us more <span class="optional-badge">optional</span>
        </div>
        <textarea name="comment" id="comment" placeholder="Any specific feedback? What did we do well or where can we improve?" maxlength="800"></textarea>
        <div style="font-size:.75rem;color:var(--muted);margin-top:6px;text-align:right;">
          <span id="charCount">0</span>/800
        </div>
      </div>

      <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <button type="submit" class="btn" id="submitBtn">
        <i class="fas fa-paper-plane"></i> Submit Rating
      </button>
    </form>
  <?php endif; ?>

</div>

<script>
(function(){
  var labels = {
    1: 'Poor — did not meet expectations',
    2: 'Below average — needs improvement',
    3: 'Average — acceptable but room to grow',
    4: 'Good — satisfied with the service',
    5: 'Excellent — exceeded expectations!'
  };

  function watchStars(groupId, textId) {
    var group = document.getElementById(groupId);
    var text  = document.getElementById(textId);
    if (!group || !text) return;
    group.querySelectorAll('input[type="radio"]').forEach(function(input){
      input.addEventListener('change', function(){
        text.textContent = labels[this.value] || '';
        text.style.color = this.value >= 4 ? '#4ade80' : (this.value <= 2 ? '#f87171' : '#fbbf24');
      });
    });
  }
  watchStars('serviceStars', 'serviceRatingText');
  watchStars('mechanicStars', 'mechanicRatingText');

  // Character counter
  var ta = document.getElementById('comment');
  var cc = document.getElementById('charCount');
  if (ta && cc) {
    ta.addEventListener('input', function(){ cc.textContent = this.value.length; });
  }

  // Prevent double-submit
  var form = document.getElementById('ratingForm');
  var btn  = document.getElementById('submitBtn');
  if (form && btn) {
    form.addEventListener('submit', function(){
      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting…';
    });
  }
})();
</script>
</body>
</html>
