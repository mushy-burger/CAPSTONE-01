<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/paymongo.php';
requireLogin();

$currentUser = getCurrentUser();
$userId = $currentUser['id'];
$selectedCartIds = array_values(array_unique(array_filter(array_map(
    'intval',
    (array)($_POST['selected_cart_ids'] ?? $_SESSION['checkout_cart_ids'][currentAuthContext()] ?? [])
))));

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $_SESSION['checkout_cart_ids'][currentAuthContext()] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_cart_ids'])) {
    $_SESSION['checkout_cart_ids'][currentAuthContext()] = $selectedCartIds;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $selectedCartIds && isset($_POST['qty'])) {
    foreach ((array)$_POST['qty'] as $cartId => $qty) {
        $cartId = (int)$cartId;
        if (!in_array($cartId, $selectedCartIds, true)) {
            continue;
        }

        $cartRow = fetchOne(
            "SELECT p.id
             FROM cart_items ci
             JOIN products p ON p.id = ci.product_id
             WHERE ci.id = ? AND ci.user_id = ?",
            [$cartId, $userId]
        );
        if (!$cartRow) {
            continue;
        }

        $qty = max(1, min((int)$qty, getAvailableStock((int)$cartRow['id'])));
        getDB()->prepare("UPDATE cart_items SET quantity = ? WHERE id = ? AND user_id = ?")
            ->execute([$qty, $cartId, $userId]);
    }
}

$items = fetchAllRows(
    "SELECT ci.id AS cart_id, ci.quantity, p.*, c.name AS category_name
     FROM cart_items ci
     JOIN products p ON p.id = ci.product_id
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE ci.user_id = ?
       " . ($selectedCartIds ? "AND ci.id IN (" . implode(',', array_fill(0, count($selectedCartIds), '?')) . ")" : "AND 1 = 0") . "
     ORDER BY ci.id DESC",
    array_merge([$userId], $selectedCartIds)
);
$subtotal = array_reduce($items, fn($sum, $item) => $sum + ((float)$item['price'] * (int)$item['quantity']), 0.0);
$message = '';
$error = '';
$paymongoReady = paymongoIsConfigured();
$paymongoTestMode = $paymongoReady && paymongoIsTestMode();
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create_checkout' && $items) {
    $payment = sanitize($_POST['payment_method'] ?? 'paymongo');
    $paymongoMethod = paymongoNormalizePaymentMethod($payment);

    if ($payment === 'test_sandbox' && $paymongoTestMode) {
        // Sandbox payment (TEST keys only): completes the order through the
        // exact fulfillment path the PayMongo webhook uses — stock deduction,
        // paid status, cart cleanup — without contacting PayMongo's hosted
        // checkout. Unavailable on live keys.
        try {
            getDB()->beginTransaction();
            assertAvailableStockForItems($items, getDB());
            $stmt = getDB()->prepare(
                "INSERT INTO orders (user_id, subtotal, total, payment_method, payment_reference, checkout_session_id, payment_status, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')"
            );
            $stmt->execute([$userId, $subtotal, $subtotal, 'paymongo', '', 'SANDBOX-TEST', 'awaiting_payment']);
            $orderId = (int)getDB()->lastInsertId();
            getDB()->prepare("UPDATE orders SET payment_reference = ? WHERE id = ?")
                ->execute(['MT-' . $orderId . '-TEST', $orderId]);

            $itemStmt = getDB()->prepare("INSERT INTO order_items (order_id, cart_item_id, product_id, quantity, price) VALUES (?, ?, ?, ?, ?)");
            foreach ($items as $item) {
                $itemStmt->execute([$orderId, $item['cart_id'], $item['id'], $item['quantity'], $item['price']]);
            }
            getDB()->commit();

            fulfillPaidOrder($orderId, 'SANDBOX-TEST');
            $_SESSION['checkout_cart_ids'][currentAuthContext()] = [];
            redirect(baseUrl('payment-success.php?order_id=' . $orderId));
        } catch (Throwable $e) {
            if (getDB()->inTransaction()) {
                getDB()->rollBack();
            }
            $error = $e->getMessage();
        }
    } elseif (!in_array($paymongoMethod, ['paymongo', 'gcash', 'paymaya'], true)) {
        $error = 'Please use the PayMongo payment option.';
    } elseif (!$paymongoReady) {
        $error = 'Online payment is temporarily unavailable. Please try again later or contact the shop.';
    } else {
        try {
            getDB()->beginTransaction();
            assertAvailableStockForItems($items, getDB());
            $stmt = getDB()->prepare(
                "INSERT INTO orders (user_id, subtotal, total, payment_method, payment_status, status)
                 VALUES (?, ?, ?, ?, ?, 'pending')"
            );
            $stmt->execute([$userId, $subtotal, $subtotal, 'paymongo', 'awaiting_payment']);
            $orderId = (int)getDB()->lastInsertId();

            $itemStmt = getDB()->prepare("INSERT INTO order_items (order_id, cart_item_id, product_id, quantity, price) VALUES (?, ?, ?, ?, ?)");
            foreach ($items as $item) {
                $itemStmt->execute([$orderId, $item['cart_id'], $item['id'], $item['quantity'], $item['price']]);
            }

            $customer = fetchOne("SELECT name, email, phone FROM users WHERE id = ?", [$userId]) ?? [
                'name' => $currentUser['name'] ?? '',
                'email' => $currentUser['email'] ?? '',
                'phone' => '',
            ];

            $checkout = paymongoCreateCheckoutSession(
                ['id' => $orderId, 'user_id' => $userId],
                $items,
                $customer,
                $paymongoMethod
            );

            $sessionData = $checkout['data'] ?? [];
            $checkoutSessionId = $sessionData['id'] ?? '';
            $checkoutUrl = $sessionData['attributes']['checkout_url'] ?? '';
            if ($checkoutSessionId === '' || $checkoutUrl === '') {
                throw new RuntimeException('PayMongo did not return a checkout URL.');
            }

            getDB()->prepare(
                "UPDATE orders
                 SET checkout_session_id = ?, payment_reference = ?, payment_status = ?
                 WHERE id = ?"
            )->execute([$checkoutSessionId, 'MT-' . $orderId, 'checkout_created', $orderId]);

            getDB()->commit();
            $_SESSION['checkout_cart_ids'][currentAuthContext()] = [];
            redirect($checkoutUrl);
        } catch (Throwable $e) {
            if (getDB()->inTransaction()) {
                getDB()->rollBack();
            }
            $error = $e->getMessage();
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create_checkout' && !$items) {
    $error = 'Select at least one cart item before checkout.';
}

$pageTitle = 'Checkout - MotoTrack';
require_once __DIR__ . '/includes/header.php';
?>

<style>
  /* Checkout-only layout; payment names, values, and submit flow are unchanged. */
  .page-checkout .checkout-hero { padding: 126px 0 18px; background: transparent; border: 0; }
  .page-checkout .checkout-hero .container { padding: 0; border: 0; border-radius: 0; background: none; box-shadow: none; overflow: visible; }
  .page-checkout .checkout-hero .container::after { display: none; }
  .page-checkout .checkout-hero h1 { margin: 0; color: #fff; font-size: clamp(2.4rem, 5vw, 4rem); letter-spacing: -.035em; line-height: 1; }
  .page-checkout .checkout-hero p { margin: 12px 0 0; color: rgba(255,255,255,.68); font-size: 1.03rem; }
  .page-checkout .checkout-layout--refined { grid-template-columns: minmax(0, 1.55fr) minmax(320px, .9fr); gap: 28px; padding-top: 14px; padding-bottom: 76px; }
  .page-checkout .checkout-panel,
  .page-checkout .checkout-summary { padding: 30px; border: 1px solid rgba(255,255,255,.11); border-radius: 16px; background: linear-gradient(180deg, rgba(20,21,28,.98), rgba(12,13,18,.96)); box-shadow: 0 24px 52px rgba(0,0,0,.26); }
  .page-checkout .checkout-panel { gap: 18px; }
  .page-checkout .checkout-panel__head { display: grid; gap: 7px; margin-bottom: 2px; }
  .page-checkout .checkout-panel__head h2,
  .page-checkout .checkout-summary h2 { margin: 0; color: #fff; font-size: 1.42rem; }
  .page-checkout .checkout-panel__head p { margin: 0; color: rgba(255,255,255,.62); }
  .page-checkout .payment-choice { position: relative; display: grid; grid-template-columns: 22px minmax(0,1fr) auto; align-items: center; column-gap: 14px; min-height: 96px; padding: 18px; border: 1px solid rgba(255,255,255,.12); border-radius: 14px; background: rgba(255,255,255,.025); color: #fff; cursor: pointer; transition: border-color 160ms ease, background 160ms ease, transform 160ms ease, box-shadow 160ms ease; }
  .page-checkout .payment-choice:hover { border-color: rgba(255,92,101,.52); background: rgba(255,255,255,.05); transform: translateY(-1px); }
  .page-checkout .payment-choice:has(input:checked) { border-color: #ef3a42; background: linear-gradient(135deg, rgba(215,25,32,.18), rgba(255,255,255,.045)); box-shadow: 0 14px 30px rgba(134,12,18,.2); }
  .page-checkout .payment-choice input { width: 22px; min-height: 22px; margin: 0; padding: 0; accent-color: #d71920; cursor: pointer; }
  .page-checkout .payment-choice input:focus-visible { outline: 3px solid rgba(255,98,105,.42); outline-offset: 3px; }
  .page-checkout .payment-choice__title { color: #fff; font-size: 1rem; font-weight: 800; }
  .page-checkout .payment-choice--paymongo::after { content: 'Secure checkout powered by PayMongo. Card, GCash, Maya, and QRPh may be available.'; grid-column: 2 / -1; color: rgba(255,255,255,.66); font-size: .86rem; line-height: 1.45; }
  .page-checkout .payment-choice__tag { padding: 4px 8px; border: 1px solid rgba(244,183,64,.35); border-radius: 999px; color: #f7c452; font-size: .68rem; font-weight: 800; letter-spacing: .08em; }
  .page-checkout .payment-choice--sandbox::after { content: 'Development and demonstration only. No real charge will be made.'; grid-column: 2 / -1; color: rgba(255,255,255,.66); font-size: .86rem; line-height: 1.45; }
  .page-checkout .payment-choice__sr-detail { position: absolute; width: 1px; height: 1px; margin: -1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
  .page-checkout .checkout-submit { width: 100%; min-height: 54px; margin-top: 4px; }
  .page-checkout .checkout-panel .btn:disabled { cursor: not-allowed; opacity: .52; transform: none; }
  .page-checkout .checkout-summary { position: sticky; top: 104px; }
  .page-checkout .checkout-summary h2 { margin-bottom: 18px; }
  .page-checkout .checkout-summary .summary-box__item,
  .page-checkout .checkout-summary .summary-box__line { display: flex; justify-content: space-between; gap: 18px; padding: 14px 0; border-bottom: 1px solid rgba(255,255,255,.09); }
  .page-checkout .checkout-summary .summary-box__item span { color: rgba(255,255,255,.72); line-height: 1.45; }
  .page-checkout .checkout-summary strong { color: #fff; font-variant-numeric: tabular-nums; }
  .page-checkout .checkout-summary .summary-box__line { color: rgba(255,255,255,.62); }
  .page-checkout .checkout-summary .summary-box__total { display: flex; justify-content: space-between; align-items: baseline; gap: 18px; padding: 20px 0 0; border: 0; color: #fff; font-weight: 800; }
  .page-checkout .checkout-summary .summary-box__total strong { color: #ff6269; font-size: 1.5rem; }
  @media (max-width: 860px) { .page-checkout .checkout-layout--refined { grid-template-columns: 1fr; } .page-checkout .checkout-summary { position: static; } }
  @media (max-width: 620px) { .page-checkout .checkout-hero { padding-top: 102px; } .page-checkout .checkout-hero h1 { font-size: clamp(2.25rem, 11vw, 3.15rem); } .page-checkout .checkout-layout--refined { padding-top: 6px; padding-bottom: 48px; } .page-checkout .checkout-panel, .page-checkout .checkout-summary { padding: 22px 18px; border-radius: 14px; } .page-checkout .payment-choice { min-height: 92px; padding: 16px; } }
</style>

<section class="page-hero checkout-hero">
  <div class="container">
    <h1>Checkout</h1>
    <p>Secure payment for your selected items.</p>
  </div>
</section>

<section class="section container checkout-layout checkout-layout--refined">
  <form class="checkout-panel" method="post">
    <?= authContextField() ?>
    <div class="checkout-panel__head">
      <h2>Payment Method</h2>
      <p>Choose how you want to complete this order.</p>
    </div>
    <?php if ($message): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if (!$items): ?>
      <div class="alert error">Select at least one cart item before checkout.</div>
      <a class="btn btn-primary" href="<?= baseUrl('cart.php?tab=cart') ?>">Back to Cart</a>
    <?php else: ?>
    <input type="hidden" name="action" value="create_checkout">
    <?php foreach ($items as $item): ?>
      <input type="hidden" name="selected_cart_ids[]" value="<?= (int)$item['cart_id'] ?>">
    <?php endforeach; ?>
    <label class="payment-choice payment-choice--paymongo"><input type="radio" name="payment_method" value="paymongo" aria-describedby="paymongo-payment-detail" checked> <span class="payment-choice__title">PayMongo Checkout</span></label>
    <p class="fine-print payment-choice__sr-detail" id="paymongo-payment-detail">
      Pay securely using the PayMongo hosted checkout page. Available methods depend on your PayMongo account setup and may include Card, GCash, Maya, and QRPh.
    </p>
    <?php if ($paymongoTestMode): ?>
      <label class="payment-choice payment-choice--sandbox"><input type="radio" name="payment_method" value="test_sandbox" aria-describedby="sandbox-payment-detail"> <span class="payment-choice__title">Sandbox Test Payment</span><span class="payment-choice__tag">TEST MODE</span></label>
      <p class="fine-print payment-choice__sr-detail" id="sandbox-payment-detail">
        Instantly marks this order as paid without contacting PayMongo — for development and demos while running on test keys. No real charge is made. This option disappears automatically on live keys.
      </p>
    <?php endif; ?>
    <?php if (!$paymongoReady): ?>
      <div class="alert error">Online payment is temporarily unavailable. Please try again later or contact the shop.</div>
    <?php endif; ?>
    <button class="btn btn-primary checkout-submit" type="submit" <?= (!$items || !$paymongoReady) ? 'disabled' : '' ?>>Pay with PayMongo</button>
    <?php endif; ?>
  </form>

  <aside class="summary-box checkout-summary" aria-label="Order summary">
    <h2>Your Order</h2>
    <?php foreach ($items as $item): ?>
      <div class="summary-box__item"><span><?= htmlspecialchars($item['name']) ?> × <?= (int)$item['quantity'] ?></span><strong><?= formatPrice((float)$item['price'] * (int)$item['quantity']) ?></strong></div>
    <?php endforeach; ?>
    <div class="summary-box__line"><span>Subtotal</span><strong><?= formatPrice($subtotal) ?></strong></div>
    <div class="summary-grand-total summary-box__total"><span>Total</span><strong><?= formatPrice($subtotal) ?></strong></div>
  </aside>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
