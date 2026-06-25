<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
requireLogin();

$userId = getCurrentUser()['id'];
$items = fetchAllRows(
    "SELECT ci.quantity, p.*
     FROM cart_items ci
     JOIN products p ON p.id = ci.product_id
     WHERE ci.user_id = ?",
    [$userId]
);
$subtotal = array_reduce($items, fn($sum, $item) => $sum + ((float)$item['price'] * (int)$item['quantity']), 0.0);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $items) {
    $payment = sanitize($_POST['payment_method'] ?? 'cash');
    try {
        getDB()->beginTransaction();
        foreach ($items as $item) {
            if ((int)$item['stock'] < (int)$item['quantity']) {
                throw new RuntimeException($item['name'] . ' does not have enough stock.');
            }
        }
        $stmt = getDB()->prepare("INSERT INTO orders (user_id, subtotal, total, payment_method, status) VALUES (?, ?, ?, ?, 'processing')");
        $stmt->execute([$userId, $subtotal, $subtotal, $payment]);
        $orderId = (int)getDB()->lastInsertId();

        $itemStmt = getDB()->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
        $stockStmt = getDB()->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
        foreach ($items as $item) {
            $itemStmt->execute([$orderId, $item['id'], $item['quantity'], $item['price']]);
            $stockStmt->execute([$item['quantity'], $item['id']]);
        }
        getDB()->prepare("DELETE FROM cart_items WHERE user_id = ?")->execute([$userId]);
        getDB()->commit();
        $message = 'Order placed successfully. Reference #' . $orderId;
        $items = [];
        $subtotal = 0.0;
    } catch (Throwable $e) {
        if (getDB()->inTransaction()) {
            getDB()->rollBack();
        }
        $error = $e->getMessage();
    }
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
    <label><input type="radio" name="payment_method" value="cash" checked> Cash</label>
    <label><input type="radio" name="payment_method" value="bank"> Bank transfer</label>
    <label><input type="radio" name="payment_method" value="gcash"> GCash</label>
    <label><input type="radio" name="payment_method" value="paymaya"> PayMaya</label>
    <p class="fine-print">For alternate arrangements, contact the shop before placing your order.</p>
    <button class="btn btn-primary" type="submit" <?= !$items ? 'disabled' : '' ?>>Place order</button>
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
