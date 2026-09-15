<?php
/**
 * Reservation deposit payment for a booking.
 *
 * GET  ?booking_id=N                 show the deposit state
 * GET  ?booking_id=N&result=success  returned from PayMongo — verify server-side
 * GET  ?booking_id=N&result=cancelled returned from PayMongo — mark cancelled
 * POST action=pay                    start / resume checkout, then redirect
 * POST action=confirm                confirm the booking (deposit must be paid)
 *
 * Returning from PayMongo never marks anything paid on its own: the status is
 * always re-read from the PayMongo API by depositVerify().
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/BookingDeposit.php';
requireLogin();

$user = getCurrentUser();
$bookingId = (int)($_GET['booking_id'] ?? $_POST['booking_id'] ?? 0);

// Ownership is enforced in SQL — another customer's booking is simply not found.
$booking = $bookingId ? fetchOne(
    "SELECT b.*, t.name AS technician_name,
            CONCAT(mb.name, ' ', mm.name) AS vehicle_name, cv.plate_number
     FROM bookings b
     LEFT JOIN users t ON t.id = b.technician_id
     LEFT JOIN customer_vehicles cv ON cv.id = b.vehicle_id
     LEFT JOIN motorcycle_brands mb ON mb.id = cv.brand_id
     LEFT JOIN motorcycle_models mm ON mm.id = cv.model_id
     WHERE b.id = ? AND b.user_id = ?",
    [$bookingId, $user['id']]
) : null;

if (!$booking) {
    flashMessage('booking_error', 'That booking could not be found.');
    redirect(baseUrl('book-service.php?tab=appointments'));
}

$services = fetchAllRows("SELECT service_name, labor_fee FROM booking_services WHERE booking_id = ? ORDER BY id", [$bookingId]);
$notice = '';
$error  = '';

// ---------------------------------------------------------------- POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'pay') {
        $start = depositStartPayment($bookingId, (int)$user['id']);
        if (!$start['ok']) {
            flashMessage('deposit_error', $start['error']);
        } elseif ($start['url'] !== '') {
            // Off to PayMongo's hosted checkout.
            header('Location: ' . $start['url']);
            exit;
        } else {
            flashMessage('deposit_notice', 'Your reservation deposit is already paid.');
        }
        redirect(baseUrl('booking-deposit.php?booking_id=' . $bookingId));
    }

    if ($action === 'confirm') {
        // Staff confirms while assigning a technician. Reject crafted customer
        // confirmation requests as well as the removed UI action.
        flashMessage('deposit_error', 'Staff will confirm your booking and assign a mechanic after reviewing your reservation.');
        redirect(baseUrl('booking-deposit.php?booking_id=' . $bookingId));

    }
}

// ------------------------------------------------- returning from PayMongo
$result = $_GET['result'] ?? '';

if ($result === 'cancelled') {
    depositMarkCancelled($bookingId, (int)$user['id']);
    $error = 'Reservation deposit payment was not completed. You can try again below.';
} elseif ($result === 'success') {
    // Verify with PayMongo rather than trusting the redirect.
    $verified = depositVerifyLatest($bookingId);
    if ($verified['status'] === 'paid' && $verified['error'] === '') {
        $notice = 'Payment successful! Your reservation deposit has been verified.';
    } elseif ($verified['error'] !== '') {
        $error = 'We could not verify the payment yet: ' . $verified['error'];
    } else {
        $error = 'Your payment is still being confirmed by PayMongo. Refresh this page in a moment.';
    }
} else {
    // Any visit re-checks a pending attempt, so a webhook-confirmed payment or
    // an abandoned session is reflected without user action.
    depositVerifyLatest($bookingId);
}

$flashNotice = getFlash('deposit_notice');
$flashError  = getFlash('deposit_error');
if ($flashNotice !== '') { $notice = $flashNotice; }
if ($flashError !== '')  { $error = $flashError; }

$deposit    = depositLatestRow($bookingId);
$paidRow    = depositPaidRow($bookingId);
$isPaid     = $paidRow !== null;
$required   = depositIsRequired();
$amountDue  = $required ? depositAmount() : 0.0;
$isPending = $booking['status'] === 'pending';
$isCancelled = $booking['status'] === 'cancelled';
$isStaffReviewPending = $isPaid && $isPending;

$pageTitle = 'Reservation Deposit - MotoTrack';
require_once __DIR__ . '/includes/header.php';
?>

<section class="deposit-page">
  <div class="container deposit-page__container">
    <header class="deposit-page__heading">
      <span class="deposit-booking-id">Booking #<?= (int)$booking['id'] ?></span>
      <h1>Reservation Deposit</h1>
      <p>Review your booking and complete the payment to secure your service slot.</p>
    </header>

    <?php if ($notice): ?><div class="alert success deposit-alert"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert error deposit-alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="deposit-layout">
      <article class="deposit-summary-panel">
        <div class="deposit-panel-heading">
          <h2>Booking summary</h2>
          <span>Reservation details</span>
        </div>

        <dl class="deposit-summary-list">
          <div class="deposit-summary-row">
            <dt>Date</dt>
            <dd><?= htmlspecialchars(date('F j, Y', strtotime($booking['scheduled_date']))) ?></dd>
          </div>
          <div class="deposit-summary-row">
            <dt>Time</dt>
            <dd><?= $booking['scheduled_time'] ? htmlspecialchars(date('g:i A', strtotime($booking['scheduled_time']))) : 'To be confirmed' ?></dd>
          </div>
          <div class="deposit-summary-row">
            <dt>Motorcycle</dt>
            <dd><?= htmlspecialchars($booking['vehicle_name'] ?: 'Not specified') ?><?= $booking['plate_number'] ? ' (' . htmlspecialchars($booking['plate_number']) . ')' : '' ?></dd>
          </div>
          <?php if ($services): ?>
            <div class="deposit-summary-row">
              <dt>Service<?= count($services) > 1 ? 's' : '' ?></dt>
              <dd><?= htmlspecialchars(implode(', ', array_column($services, 'service_name'))) ?></dd>
            </div>
          <?php endif; ?>
          <?php if ($booking['technician_name']): ?>
            <div class="deposit-summary-row">
              <dt>Technician</dt>
              <dd><?= htmlspecialchars($booking['technician_name']) ?></dd>
            </div>
          <?php endif; ?>
          <div class="deposit-summary-row deposit-summary-row--total">
            <dt>Estimated total</dt>
            <dd><?= formatPrice((float)$booking['total_amount']) ?></dd>
          </div>
        </dl>
      </article>

      <aside class="deposit-payment-panel">
        <?php if (!$required): ?>
          <div class="deposit-payment-copy">
            <span class="deposit-payment-label">Booking confirmation</span>
            <h2>No deposit required</h2>
            <p>Your booking can be confirmed without a reservation deposit.</p>
          </div>
          <div class="deposit-status deposit-status--paid"><span>Payment status</span><strong>Not required</strong></div>
          <p class="deposit-staff-note">Your booking is awaiting staff confirmation and mechanic assignment.</p>

        <?php else: ?>
          <div class="deposit-payment-copy">
            <span class="deposit-payment-label">Reservation deposit</span>
            <h2><?= formatPrice($isPaid ? (float)$paidRow['amount'] : $amountDue) ?></h2>
            <p><?= $isPaid ? 'Your reservation deposit has been verified.' : 'A reservation deposit of ' . formatPrice($amountDue) . ' is required to confirm your booking.' ?></p>
          </div>

          <div class="deposit-status <?= $isPaid ? 'deposit-status--paid' : 'deposit-status--unpaid' ?>">
            <span>Payment status</span>
            <strong><?= htmlspecialchars(depositStatusLabel($deposit)) ?></strong>
          </div>

          <?php if ($isPaid): ?>
            <dl class="deposit-payment-details">
              <div><dt>Payment method</dt><dd>PayMongo</dd></div>
              <?php if (!empty($paidRow['payment_reference'])): ?>
                <div><dt>Reference</dt><dd class="deposit-reference"><?= htmlspecialchars($paidRow['payment_reference']) ?></dd></div>
              <?php endif; ?>
              <?php if (!empty($paidRow['paid_at'])): ?>
                <div><dt>Paid</dt><dd><?= htmlspecialchars(date('M j, Y g:i A', strtotime($paidRow['paid_at']))) ?></dd></div>
              <?php endif; ?>
            </dl>
          <?php endif; ?>

          <?php if ($isCancelled): ?>
            <div class="alert error deposit-inline-alert">This booking has been cancelled. Please contact the shop if you need help with your deposit.</div>

          <?php elseif ($isStaffReviewPending): ?>
            <div class="alert success deposit-inline-alert">Reservation deposit paid.</div>
            <p class="deposit-staff-note">Your booking is awaiting staff confirmation and mechanic assignment.</p>

          <?php elseif (!$isPending): ?>
            <div class="alert success deposit-inline-alert">Your booking has been accepted and is being handled by the shop.</div>

          <?php else: ?>
            <form method="post" class="deposit-action-form">
              <?= authContextField() ?>
              <input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>">
              <input type="hidden" name="action" value="pay">
              <button type="submit" class="btn btn-primary deposit-primary-action">
                <?= ($deposit && in_array($deposit['status'], ['cancelled','failed','expired'], true)) ? 'Retry Payment' : 'Pay Reservation Deposit' ?>
              </button>
            </form>
            <p class="deposit-secure-note"><i class="fas fa-lock" aria-hidden="true"></i> You will be redirected to PayMongo to complete payment securely.</p>
          <?php endif; ?>
        <?php endif; ?>
      </aside>
    </div>

    <div class="deposit-back-link">
      <a href="<?= baseUrl('book-service.php?tab=appointments') ?>"><i class="fas fa-arrow-left" aria-hidden="true"></i> Back to my appointments</a>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
