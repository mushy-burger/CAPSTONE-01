<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
requireLogin();

$currentUser = getCurrentUser();
$orderId = (int)($_GET['order_id'] ?? 0);

if ($orderId > 0) {
    getDB()->prepare(
        "UPDATE orders
         SET payment_status = CASE
             WHEN payment_status IS NULL OR payment_status = '' THEN 'cancelled'
             ELSE payment_status
         END
         WHERE id = ? AND user_id = ?"
    )->execute([$orderId, $currentUser['id']]);
}

$pageTitle = 'Payment Cancelled - MotoTrack';
require_once __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
  <div class="container">
    <span class="eyebrow">Payment</span>
    <h1>Payment Cancelled</h1>
  </div>
</section>

<section class="section container">
  <div class="auth-card">
    <div class="alert error">The PayMongo checkout was cancelled. Your cart is still here, so you can try again anytime.</div>
    <a class="btn btn-primary" href="<?= baseUrl('checkout.php') ?>">Back to Checkout</a>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
