<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/paymongo.php';
requireLogin();

$currentUser = getCurrentUser();
$orderId = (int)($_GET['order_id'] ?? 0);
$order = null;
$orderItems = [];
$verificationError = '';

if ($orderId > 0) {
    $order = fetchOne(
        "SELECT * FROM orders WHERE id = ? AND user_id = ?",
        [$orderId, $currentUser['id']]
    );

    if ($order && ($order['payment_status'] ?? '') !== 'paid' && !empty($order['checkout_session_id'])) {
        try {
            $session = paymongoRetrieveCheckoutSession((string)$order['checkout_session_id']);
            $sessionOrderId = paymongoCheckoutSessionOrderId($session);
            if ($sessionOrderId > 0 && $sessionOrderId !== (int)$order['id']) {
                throw new RuntimeException('PayMongo checkout session does not match this order.');
            }

            if (paymongoCheckoutSessionIsPaid($session)) {
                fulfillPaidOrder((int)$order['id'], (string)$order['checkout_session_id']);
                $order = fetchOne(
                    "SELECT * FROM orders WHERE id = ? AND user_id = ?",
                    [$orderId, $currentUser['id']]
                );
            }
        } catch (Throwable $e) {
            $verificationError = $e->getMessage();
        }
    }

    if ($order) {
        $orderItems = fetchAllRows(
            "SELECT oi.quantity, oi.price, p.name, p.image
             FROM order_items oi
             LEFT JOIN products p ON p.id = oi.product_id
             WHERE oi.order_id = ?
             ORDER BY oi.id",
            [$orderId]
        );
    }
}

$pageTitle = 'Order Confirmation - MotoTrack';
require_once __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
  <div class="container">
    <span class="eyebrow">Payment</span>
    <h1>Order Confirmation</h1>
  </div>
</section>

<section class="section container">
  <div class="auth-card" style="max-width:600px;">
    <?php if (!$order): ?>
      <div class="alert error">We could not find that order. Please check your order history.</div>
      <a class="btn btn-outline" href="<?= baseUrl('cart.php?tab=orders') ?>">My Orders</a>
    <?php else: ?>
      <?php $isPaid = ($order['payment_status'] ?? '') === 'paid'; ?>

      <div class="payment-status">
        <?php if ($isPaid): ?>
          <div class="payment-status-icon is-success"><i class="fas fa-check-circle" aria-hidden="true"></i></div>
          <h2 class="is-success">Payment Successful!</h2>
          <p>Your order is completed.</p>
        <?php else: ?>
          <div class="payment-status-icon is-pending"><i class="fas fa-clock" aria-hidden="true"></i></div>
          <h2 class="is-pending">Payment Pending</h2>
          <p>Waiting for PayMongo confirmation. Refresh in a moment.</p>
          <?php if ($verificationError): ?>
            <p class="fine-print"><?= htmlspecialchars($verificationError) ?></p>
          <?php endif; ?>
        <?php endif; ?>
      </div>

      <div class="payment-receipt-card">
        <div class="payment-receipt-row"><span>Order</span><strong>#<?= (int)$order['id'] ?></strong></div>
        <div class="payment-receipt-row"><span>Date</span><strong><?= htmlspecialchars(date('M j, Y g:i A', strtotime($order['created_at']))) ?></strong></div>
        <div class="payment-receipt-row"><span>Payment</span><strong><?= htmlspecialchars(ucfirst($order['payment_method'] ?? '')) ?></strong></div>
        <div class="payment-receipt-row"><span>Status</span><strong class="<?= $isPaid ? 'is-success' : 'is-pending' ?>"><?= htmlspecialchars(strtoupper($order['payment_status'] ?? 'pending')) ?></strong></div>
      </div>

      <?php if ($orderItems): ?>
        <h2 class="payment-items-title">Items Ordered</h2>
        <?php foreach ($orderItems as $item): ?>
          <div class="payment-receipt-item">
            <span><?= htmlspecialchars($item['name']) ?> <span class="qty">x<?= (int)$item['quantity'] ?></span></span>
            <strong><?= formatPrice((float)$item['price'] * (int)$item['quantity']) ?></strong>
          </div>
        <?php endforeach; ?>
        <div class="payment-receipt-total">
          <strong>Total</strong>
          <strong><?= formatPrice((float)$order['total']) ?></strong>
        </div>
      <?php endif; ?>

      <div class="payment-actions">
        <a class="btn btn-primary" href="<?= baseUrl('shop.php') ?>">Continue Shopping</a>
        <a class="btn btn-outline" href="<?= baseUrl('cart.php?tab=orders') ?>">My Orders</a>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
