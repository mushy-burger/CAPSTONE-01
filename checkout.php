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
            "SELECT p.stock
             FROM cart_items ci
             JOIN products p ON p.id = ci.product_id
             WHERE ci.id = ? AND ci.user_id = ?",
            [$cartId, $userId]
        );
        if (!$cartRow) {
            continue;
        }

        $qty = max(1, min((int)$qty, (int)$cartRow['stock']));
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
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create_checkout' && $items) {
    $payment = sanitize($_POST['payment_method'] ?? 'paymongo');
    $paymongoMethod = paymongoNormalizePaymentMethod($payment);

    if (!in_array($paymongoMethod, ['paymongo', 'gcash', 'paymaya'], true)) {
        $error = 'Please use the PayMongo payment option.';
    } elseif (!$paymongoReady) {
        $error = 'PayMongo is not configured yet. Add your PayMongo keys in the local .env file first.';
    } else {
        try {
            getDB()->beginTransaction();
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

<section class="page-hero">
  <div class="container">
    <span class="eyebrow">Shop</span>
    <h1>Checkout</h1>
  </div>
</section>

<section class="section container checkout-layout">
  <form class="checkout-panel" method="post">
    <?= authContextField() ?>
    <h2>Choose Payment Method</h2>
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
    <label><input type="radio" name="payment_method" value="paymongo" checked> PayMongo Checkout</label>
    <p class="fine-print">
      Pay securely using the PayMongo hosted checkout page. Available methods depend on your PayMongo account setup and may include Card, GCash, Maya, and QRPh.
    </p>
    <?php if (!$paymongoReady): ?>
      <div class="alert error">PayMongo keys are missing. Add them in your local <code>.env</code> file first.</div>
    <?php endif; ?>
    <button class="btn btn-primary" type="submit" <?= (!$items || !$paymongoReady) ? 'disabled' : '' ?>>Pay with PayMongo</button>
    <?php endif; ?>
  </form>

  <aside class="summary-box">
    <h2>Your Order</h2>
    <?php foreach ($items as $item): ?>
      <div><span><?= htmlspecialchars($item['name']) ?> x<?= (int)$item['quantity'] ?></span><strong><?= formatPrice((float)$item['price'] * (int)$item['quantity']) ?></strong></div>
    <?php endforeach; ?>
    <div><span>Subtotal</span><strong><?= formatPrice($subtotal) ?></strong></div>
    <div><span>Total</span><strong><?= formatPrice($subtotal) ?></strong></div>
  </aside>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
