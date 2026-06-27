<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
requireLogin();

$currentUser = getCurrentUser();
$orderId     = (int)($_GET['order_id'] ?? 0);
$order       = null;
$orderItems  = [];

if ($orderId > 0) {
    $order = fetchOne(
        "SELECT * FROM orders WHERE id = ? AND user_id = ?",
        [$orderId, $currentUser['id']]
    );
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

      <div style="text-align:center;padding:16px 0 8px;">
        <?php if ($isPaid): ?>
          <div style="width:64px;height:64px;background:#f0fdf4;border-radius:50%;display:grid;place-items:center;margin:0 auto 12px;font-size:1.8rem;">✅</div>
          <h2 style="color:#15803d;margin:0 0 4px;">Payment Successful!</h2>
          <p style="color:#6b7280;margin:0;">Your order is now being processed.</p>
        <?php else: ?>
          <div style="width:64px;height:64px;background:#fffbeb;border-radius:50%;display:grid;place-items:center;margin:0 auto 12px;font-size:1.8rem;">⏳</div>
          <h2 style="color:#b45309;margin:0 0 4px;">Payment Pending</h2>
          <p style="color:#6b7280;margin:0;">Waiting for PayMongo confirmation. Refresh in a moment.</p>
        <?php endif; ?>
      </div>

      <div style="background:#f9fafb;border-radius:10px;padding:18px;margin:18px 0;">
        <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
          <span style="color:#6b7280;">Order</span>
          <strong>#<?= (int)$order['id'] ?></strong>
        </div>
        <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
          <span style="color:#6b7280;">Date</span>
          <strong><?= htmlspecialchars(date('M j, Y g:i A', strtotime($order['created_at']))) ?></strong>
        </div>
        <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
          <span style="color:#6b7280;">Payment</span>
          <strong><?= htmlspecialchars(ucfirst($order['payment_method'] ?? '')) ?></strong>
        </div>
        <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
          <span style="color:#6b7280;">Status</span>
          <strong style="color:<?= $isPaid ? '#15803d' : '#b45309' ?>">
            <?= htmlspecialchars(strtoupper($order['payment_status'] ?? 'pending')) ?>
          </strong>
        </div>
      </div>

      <?php if ($orderItems): ?>
        <h3 style="margin:0 0 10px;font-size:.9rem;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;">Items Ordered</h3>
        <?php foreach ($orderItems as $item): ?>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #f3f4f6;">
            <span><?= htmlspecialchars($item['name']) ?> <span style="color:#9ca3af;">×<?= (int)$item['quantity'] ?></span></span>
            <strong><?= formatPrice((float)$item['price'] * (int)$item['quantity']) ?></strong>
          </div>
        <?php endforeach; ?>
        <div style="display:flex;justify-content:space-between;padding:12px 0;font-size:1.05rem;">
          <strong>Total</strong>
          <strong style="color:#d71920;"><?= formatPrice((float)$order['total']) ?></strong>
        </div>
      <?php endif; ?>

      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:8px;">
        <a class="btn btn-primary" href="<?= baseUrl('shop.php') ?>">Continue Shopping</a>
        <a class="btn btn-outline" href="<?= baseUrl('cart.php?tab=orders') ?>">My Orders</a>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
