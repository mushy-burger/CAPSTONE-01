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
        // Server-side gate: this is what a crafted POST hits.
        if (!depositIsSettled($bookingId)) {
            flashMessage('deposit_error', 'Please complete the reservation deposit payment before confirming your booking.');
            redirect(baseUrl('booking-deposit.php?booking_id=' . $bookingId));
        }

        // Conditional update — a repeat submit changes 0 rows instead of
        // re-confirming, so refreshing cannot duplicate the confirmation.
        $stmt = getDB()->prepare(
            "UPDATE bookings SET status = 'confirmed' WHERE id = ? AND user_id = ? AND status = 'pending'"
        );
        $stmt->execute([$bookingId, $user['id']]);

        if ($stmt->rowCount() === 1) {
            notifyAllStaff(
                "Booking #{$bookingId} from {$user['name']} is confirmed — reservation deposit paid.",
                'booking',
                $bookingId
            );
            flashMessage('booking_success', 'Booking #' . $bookingId . ' confirmed. We have received your reservation deposit.');
        } else {
            flashMessage('booking_success', 'Booking #' . $bookingId . ' is already confirmed.');
        }
        redirect(baseUrl('book-service.php?tab=appointments'));
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
    if ($verified['status'] === 'paid') {
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
$isConfirmed = $booking['status'] !== 'pending';

$pageTitle = 'Reservation Deposit - MotoTrack';
require_once __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
  <div class="container">
    <span class="eyebrow">Booking #<?= (int)$booking['id'] ?></span>
    <h1>Reservation Deposit</h1>
  </div>
</section>

<section class="section container">
  <div class="auth-card" style="max-width:620px;">

    <?php if ($notice): ?><div class="alert success"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- Booking summary -->
    <div style="background:#f9fafb;border-radius:10px;padding:18px;margin-bottom:18px;">
      <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
        <span style="color:#6b7280;">Date</span>
        <strong><?= htmlspecialchars(date('F j, Y', strtotime($booking['scheduled_date']))) ?></strong>
      </div>
      <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
        <span style="color:#6b7280;">Time</span>
        <strong><?= $booking['scheduled_time'] ? htmlspecialchars(date('g:i A', strtotime($booking['scheduled_time']))) : 'To be confirmed' ?></strong>
      </div>
      <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
        <span style="color:#6b7280;">Motorcycle</span>
        <strong><?= htmlspecialchars($booking['vehicle_name'] ?: 'Not specified') ?><?= $booking['plate_number'] ? ' (' . htmlspecialchars($booking['plate_number']) . ')' : '' ?></strong>
      </div>
      <?php if ($services): ?>
        <div style="display:flex;justify-content:space-between;margin-bottom:6px;gap:16px;">
          <span style="color:#6b7280;">Service<?= count($services) > 1 ? 's' : '' ?></span>
          <strong style="text-align:right;"><?= htmlspecialchars(implode(', ', array_column($services, 'service_name'))) ?></strong>
        </div>
      <?php endif; ?>
      <?php if ($booking['technician_name']): ?>
        <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
          <span style="color:#6b7280;">Technician</span>
          <strong><?= htmlspecialchars($booking['technician_name']) ?></strong>
        </div>
      <?php endif; ?>
      <div style="display:flex;justify-content:space-between;padding-top:10px;border-top:1px solid #e5e7eb;">
        <span style="color:#6b7280;">Estimated total</span>
        <strong><?= formatPrice((float)$booking['total_amount']) ?></strong>
      </div>
    </div>

    <?php if (!$required): ?>
      <div class="alert success">No reservation deposit is required for this booking.</div>
      <?php if (!$isConfirmed): ?>
        <form method="post">
          <?= authContextField() ?>
          <input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>">
          <input type="hidden" name="action" value="confirm">
          <button type="submit" class="btn btn-primary" style="width:100%;">Confirm Booking</button>
        </form>
      <?php endif; ?>

    <?php else: ?>
      <!-- Deposit panel -->
      <div style="border:1px solid <?= $isPaid ? '#bbf7d0' : '#fde68a' ?>;background:<?= $isPaid ? '#f0fdf4' : '#fffbeb' ?>;border-radius:10px;padding:18px;margin-bottom:18px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
          <span style="color:#6b7280;font-weight:700;">Reservation Deposit</span>
          <strong style="font-size:1.3rem;color:<?= $isPaid ? '#15803d' : '#b45309' ?>;">
            <?= formatPrice($isPaid ? (float)$paidRow['amount'] : $amountDue) ?>
          </strong>
        </div>
        <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
          <span style="color:#6b7280;">Payment Status</span>
          <strong style="color:<?= $isPaid ? '#15803d' : '#b45309' ?>;"><?= htmlspecialchars(depositStatusLabel($deposit)) ?></strong>
        </div>
        <?php if ($isPaid): ?>
          <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
            <span style="color:#6b7280;">Payment Method</span><strong>PayMongo</strong>
          </div>
          <?php if (!empty($paidRow['payment_reference'])): ?>
            <div style="display:flex;justify-content:space-between;margin-bottom:6px;gap:12px;">
              <span style="color:#6b7280;">Reference</span>
              <strong style="font-family:monospace;font-size:.85rem;word-break:break-all;"><?= htmlspecialchars($paidRow['payment_reference']) ?></strong>
            </div>
          <?php endif; ?>
          <?php if (!empty($paidRow['paid_at'])): ?>
            <div style="display:flex;justify-content:space-between;">
              <span style="color:#6b7280;">Paid</span>
              <strong><?= htmlspecialchars(date('M j, Y g:i A', strtotime($paidRow['paid_at']))) ?></strong>
            </div>
          <?php endif; ?>
        <?php else: ?>
          <p style="margin:10px 0 0;color:#6b7280;font-size:.9rem;">
            A reservation deposit of <?= formatPrice($amountDue) ?> is required to confirm your booking.
          </p>
        <?php endif; ?>
      </div>

      <?php if ($isConfirmed): ?>
        <div class="alert success">This booking is already confirmed.</div>
        <a class="btn btn-outline" href="<?= baseUrl('book-service.php?tab=appointments') ?>">Back to appointments</a>

      <?php elseif ($isPaid): ?>
        <form method="post">
          <?= authContextField() ?>
          <input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>">
          <input type="hidden" name="action" value="confirm">
          <button type="submit" class="btn btn-primary" style="width:100%;">Confirm Booking</button>
        </form>

      <?php else: ?>
        <form method="post">
          <?= authContextField() ?>
          <input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>">
          <input type="hidden" name="action" value="pay">
          <button type="submit" class="btn btn-primary" style="width:100%;">
            <?= ($deposit && in_array($deposit['status'], ['cancelled','failed','expired'], true)) ? 'Retry Payment' : 'Pay Reservation Deposit' ?>
          </button>
        </form>
        <p class="fine-print" style="margin-top:10px;text-align:center;">
          You will be redirected to PayMongo to complete the payment securely.
        </p>
      <?php endif; ?>
    <?php endif; ?>

    <div style="margin-top:16px;text-align:center;">
      <a href="<?= baseUrl('book-service.php?tab=appointments') ?>" style="color:#6b7280;font-size:.9rem;">Back to my appointments</a>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
