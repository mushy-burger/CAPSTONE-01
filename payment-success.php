<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
requireLogin();

$currentUser = getCurrentUser();
$orderId = (int)($_GET['order_id'] ?? 0);
$order = null;

if ($orderId > 0) {
    $order = fetchOne(
        "SELECT * FROM orders WHERE id = ? AND user_id = ?",
        [$orderId, $currentUser['id']]
    );
}

$pageTitle = 'Payment Status - MotoTrack';
require_once __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
  <div class="container">
    <span class="eyebrow">Payment</span>
    <h1>Payment Status</h1>
  </div>
</section>

<section class="section container">
  <div class="auth-card">
    <?php if (!$order): ?>
      <div class="alert error">We could not find that order.</div>
    <?php else: ?>
      <h2>Order #<?= (int)$order['id'] ?></h2>
      <?php if (($order['payment_status'] ?? '') === 'paid'): ?>
        <div class="alert success">Payment received successfully. Your order is now being processed.</div>
      <?php else: ?>
        <div class="alert">Your payment page was completed, but MotoTrack is still waiting for PayMongo confirmation. Refresh this page after a moment.</div>
      <?php endif; ?>
      <p><strong>Total:</strong> <?= formatPrice((float)$order['total']) ?></p>
      <p><strong>Payment status:</strong> <?= htmlspecialchars((string)($order['payment_status'] ?? 'pending')) ?></p>
      <p><strong>Order status:</strong> <?= htmlspecialchars((string)$order['status']) ?></p>
      <a class="btn btn-primary" href="<?= baseUrl('shop.php') ?>">Continue Shopping</a>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
