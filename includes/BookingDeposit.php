<?php
/**
 * Reservation deposit for service bookings.
 *
 * A booking cannot be confirmed until a deposit attempt for it reaches 'paid',
 * verified against PayMongo server-side — never from a redirect alone.
 *
 *   Customer books (status = pending)
 *          |
 *   Pay Reservation Deposit  ->  PayMongo Checkout
 *          |
 *   Return / webhook  ->  depositVerify()  ->  PayMongo API says paid
 *          |
 *   Booking may now be confirmed
 *
 * The amount always comes from site_settings, never from the request, so a
 * crafted form cannot lower what the customer owes.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/paymongo.php';

const DEPOSIT_SETTING_KEY = 'reservation_deposit_amount';
const DEPOSIT_DEFAULT     = 200.00;
const DEPOSIT_MIN         = 20.00;    // PayMongo rejects very small amounts
const DEPOSIT_MAX         = 100000.00;

/**
 * The configured deposit, read from the database.
 *
 * Returns 0.0 when the deposit is switched off, which makes the requirement
 * opt-out without a second setting.
 */
function depositAmount(): float {
    $raw = getSiteSetting(DEPOSIT_SETTING_KEY, (string)DEPOSIT_DEFAULT);
    $value = round((float)$raw, 2);
    if ($value <= 0) {
        return 0.0;
    }
    return min(max($value, 0.0), DEPOSIT_MAX);
}

/** True when bookings currently require a deposit at all. */
function depositIsRequired(): bool {
    return depositAmount() > 0;
}

/**
 * Validate an amount typed by staff/admin.
 *
 * @return array{ok:bool,amount:float,error:string}
 */
function depositValidateAmount(string $raw): array {
    $raw = trim(str_replace([',', '₱', 'PHP', ' '], '', $raw));

    if ($raw === '') {
        return ['ok' => false, 'amount' => 0.0, 'error' => 'Enter a reservation deposit amount.'];
    }
    if (!is_numeric($raw)) {
        return ['ok' => false, 'amount' => 0.0, 'error' => 'The reservation deposit must be a number.'];
    }

    $value = round((float)$raw, 2);

    if ($value < 0) {
        return ['ok' => false, 'amount' => 0.0, 'error' => 'The reservation deposit cannot be negative.'];
    }
    // 0 disables the requirement; anything above must clear the provider minimum.
    if ($value > 0 && $value < DEPOSIT_MIN) {
        return ['ok' => false, 'amount' => 0.0, 'error' => 'The reservation deposit must be at least ' . formatPrice(DEPOSIT_MIN) . ' (or 0 to disable it).'];
    }
    if ($value > DEPOSIT_MAX) {
        return ['ok' => false, 'amount' => 0.0, 'error' => 'The reservation deposit cannot exceed ' . formatPrice(DEPOSIT_MAX) . '.'];
    }

    return ['ok' => true, 'amount' => $value, 'error' => ''];
}

/** The paid deposit for a booking, if one exists. */
function depositPaidRow(int $bookingId): ?array {
    return fetchOne(
        "SELECT * FROM booking_deposits
         WHERE booking_id = ? AND status = 'paid'
         ORDER BY id DESC LIMIT 1",
        [$bookingId]
    );
}

/** The most recent attempt for a booking, whatever its state. */
function depositLatestRow(int $bookingId): ?array {
    return fetchOne(
        "SELECT * FROM booking_deposits WHERE booking_id = ? ORDER BY id DESC LIMIT 1",
        [$bookingId]
    );
}

/**
 * Has this booking's deposit been settled?
 *
 * Bookings created before the feature (or while it was disabled) are treated as
 * settled so existing appointments keep working.
 */
function depositIsSettled(int $bookingId): bool {
    if (!depositIsRequired()) {
        return true;
    }
    return depositPaidRow($bookingId) !== null;
}

/** Deposits for many bookings at once, keyed by booking id (avoids N+1). */
function depositRowsFor(array $bookingIds): array {
    $ids = array_values(array_unique(array_map('intval', $bookingIds)));
    if (!$ids) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    // One row per booking: the paid attempt if there is one, else the newest.
    $rows = fetchAllRows(
        "SELECT * FROM booking_deposits
         WHERE booking_id IN ($placeholders)
         ORDER BY booking_id, (status = 'paid') DESC, id DESC",
        $ids
    );

    $byBooking = [];
    foreach ($rows as $row) {
        $bookingId = (int)$row['booking_id'];
        if (!isset($byBooking[$bookingId])) {
            $byBooking[$bookingId] = $row;
        }
    }
    return $byBooking;
}

/**
 * Start (or resume) a deposit payment for a booking.
 *
 * Idempotency: an existing unpaid attempt with a live checkout URL is reused
 * rather than creating a second PayMongo session, so refreshing or clicking Pay
 * repeatedly does not spawn duplicate payment records.
 *
 * @return array{ok:bool,url:string,error:string,deposit:?array}
 */
function depositStartPayment(int $bookingId, int $userId): array {
    $fail = static fn(string $message): array => ['ok' => false, 'url' => '', 'error' => $message, 'deposit' => null];

    // Ownership is part of the query: another user's booking simply is not found.
    $booking = fetchOne(
        "SELECT b.*, u.name AS customer_name, u.email AS customer_email, u.phone AS customer_phone
         FROM bookings b JOIN users u ON u.id = b.user_id
         WHERE b.id = ? AND b.user_id = ?",
        [$bookingId, $userId]
    );
    if (!$booking) {
        return $fail('That booking could not be found.');
    }
    if (in_array($booking['status'], ['cancelled', 'completed'], true)) {
        return $fail('This booking is no longer open for payment.');
    }
    if (!depositIsRequired()) {
        return $fail('No reservation deposit is required right now.');
    }
    if (depositPaidRow($bookingId)) {
        return ['ok' => true, 'url' => '', 'error' => '', 'deposit' => depositPaidRow($bookingId)];
    }
    if (!paymongoIsConfigured()) {
        return $fail('Online payment is not available right now. Please contact the shop.');
    }

    // Reuse a still-usable attempt instead of creating another session.
    $existing = depositLatestRow($bookingId);
    if ($existing
        && $existing['status'] === 'pending'
        && !empty($existing['checkout_url'])
        && !empty($existing['checkout_session_id'])
    ) {
        // Confirm with PayMongo that it has not since been paid or expired.
        $verified = depositVerify((int)$existing['id']);
        if ($verified['status'] === 'paid') {
            return ['ok' => true, 'url' => '', 'error' => '', 'deposit' => depositLatestRow($bookingId)];
        }
        if ($verified['status'] === 'pending') {
            return ['ok' => true, 'url' => (string)$existing['checkout_url'], 'error' => '', 'deposit' => $existing];
        }
    }

    // The amount is taken from settings here, never from the request.
    $amount = depositAmount();

    $db = getDB();
    $db->beginTransaction();
    try {
        $db->prepare(
            "INSERT INTO booking_deposits (booking_id, user_id, amount, status) VALUES (?, ?, ?, 'pending')"
        )->execute([$bookingId, $userId, $amount]);
        $depositId = (int)$db->lastInsertId();

        $session = paymongoCreateBookingDepositSession(
            $bookingId,
            $depositId,
            $userId,
            $amount,
            [
                'name'  => (string)$booking['customer_name'],
                'email' => (string)$booking['customer_email'],
                'phone' => (string)($booking['customer_phone'] ?? ''),
            ]
        );

        $sessionId  = (string)($session['data']['id'] ?? '');
        $checkoutUrl = (string)($session['data']['attributes']['checkout_url'] ?? '');
        if ($sessionId === '' || $checkoutUrl === '') {
            throw new RuntimeException('PayMongo did not return a checkout session.');
        }

        $db->prepare(
            "UPDATE booking_deposits SET checkout_session_id = ?, checkout_url = ?, payment_reference = ? WHERE id = ?"
        )->execute([$sessionId, $checkoutUrl, 'MT-BK-' . $bookingId . '-' . $depositId, $depositId]);

        $db->commit();

        return ['ok' => true, 'url' => $checkoutUrl, 'error' => '', 'deposit' => depositLatestRow($bookingId)];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return $fail('Could not start the payment: ' . $e->getMessage());
    }
}

/**
 * Verify one attempt against PayMongo and record the outcome.
 *
 * This is the only place a deposit becomes 'paid'. It asks PayMongo directly,
 * so returning from the checkout page proves nothing on its own.
 *
 * @return array{status:string,error:string}
 */
function depositVerify(int $depositId): array {
    $deposit = fetchOne("SELECT * FROM booking_deposits WHERE id = ?", [$depositId]);
    if (!$deposit) {
        return ['status' => 'unknown', 'error' => 'Payment record not found.'];
    }
    if ($deposit['status'] === 'paid') {
        return ['status' => 'paid', 'error' => ''];
    }
    if (empty($deposit['checkout_session_id'])) {
        return ['status' => (string)$deposit['status'], 'error' => 'No checkout session to verify.'];
    }

    try {
        $session = paymongoRetrieveCheckoutSession((string)$deposit['checkout_session_id']);
    } catch (Throwable $e) {
        return ['status' => (string)$deposit['status'], 'error' => $e->getMessage()];
    }

    // The session must carry this deposit's own metadata, so a session paid for
    // one booking can never settle a different one.
    $attributes = $session['data']['attributes'] ?? [];
    $metaBooking = (int)($attributes['metadata']['booking_id'] ?? 0);
    $metaDeposit = (int)($attributes['metadata']['deposit_id'] ?? 0);
    if ($metaBooking !== (int)$deposit['booking_id'] || $metaDeposit !== (int)$deposit['id']) {
        return ['status' => (string)$deposit['status'], 'error' => 'This payment does not belong to this booking.'];
    }

    if (paymongoCheckoutSessionIsPaid($session)) {
        $paymentId = '';
        foreach (($attributes['payments'] ?? []) as $payment) {
            if (strtolower((string)($payment['attributes']['status'] ?? '')) === 'paid') {
                $paymentId = (string)($payment['id'] ?? '');
                break;
            }
        }

        // Conditional update: only a still-unpaid row is settled, so two
        // concurrent verifications (page + webhook) cannot both "first-pay" it.
        $stmt = getDB()->prepare(
            "UPDATE booking_deposits
                SET status = 'paid', payment_id = ?, paid_at = COALESCE(paid_at, NOW())
              WHERE id = ? AND status <> 'paid'"
        );
        $stmt->execute([$paymentId !== '' ? $paymentId : null, $depositId]);

        return ['status' => 'paid', 'error' => ''];
    }

    // Not paid: reflect an expired session so the customer is offered a retry.
    $sessionStatus = strtolower((string)($attributes['status'] ?? ''));
    if (in_array($sessionStatus, ['expired', 'cancelled'], true)) {
        getDB()->prepare("UPDATE booking_deposits SET status = 'expired' WHERE id = ? AND status = 'pending'")
            ->execute([$depositId]);
        return ['status' => 'expired', 'error' => ''];
    }

    return ['status' => 'pending', 'error' => ''];
}

/** Verify the latest attempt for a booking. Safe to call on every page load. */
function depositVerifyLatest(int $bookingId): array {
    $latest = depositLatestRow($bookingId);
    if (!$latest) {
        return ['status' => 'none', 'error' => ''];
    }
    if ($latest['status'] === 'paid') {
        return ['status' => 'paid', 'error' => ''];
    }
    return depositVerify((int)$latest['id']);
}

/** Mark the current attempt cancelled when the customer backs out of checkout. */
function depositMarkCancelled(int $bookingId, int $userId): void {
    getDB()->prepare(
        "UPDATE booking_deposits SET status = 'cancelled'
          WHERE booking_id = ? AND user_id = ? AND status = 'pending'"
    )->execute([$bookingId, $userId]);
}

/** Human-readable label for a deposit state. */
function depositStatusLabel(?array $deposit): string {
    return match ($deposit['status'] ?? 'none') {
        'paid'      => 'PAID',
        'pending'   => 'PENDING',
        'failed'    => 'FAILED',
        'cancelled' => 'CANCELLED',
        'expired'   => 'EXPIRED',
        default     => 'UNPAID',
    };
}
