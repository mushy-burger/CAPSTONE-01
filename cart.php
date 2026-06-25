<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
requireLogin();

$userId = getCurrentUser()['id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $productId = (int)($_POST['product_id'] ?? 0);
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));
        $product = fetchOne("SELECT id, stock FROM products WHERE id = ? AND status != 'out_of_stock'", [$productId]);
        if ($product) {
            $quantity = min($quantity, (int)$product['stock']);
            $stmt = getDB()->prepare(
                "INSERT INTO cart_items (user_id, product_id, quantity)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE quantity = LEAST(quantity + VALUES(quantity), ?)"
            );
            $stmt->execute([$userId, $productId, $quantity, (int)$product['stock']]);
            $message = 'Product added to cart.';
        }
    }

    if ($action === 'update') {
        foreach ($_POST['qty'] ?? [] as $cartId => $qty) {
            $qty = max(0, (int)$qty);
            if ($qty === 0) {
                $stmt = getDB()->prepare("DELETE FROM cart_items WHERE id = ? AND user_id = ?");
                $stmt->execute([(int)$cartId, $userId]);
            } else {
                $stmt = getDB()->prepare("UPDATE cart_items SET quantity = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$qty, (int)$cartId, $userId]);
            }
        }
        $message = 'Cart updated.';
    }
}

$items = fetchAllRows(
    "SELECT ci.id AS cart_id, ci.quantity, p.*, c.name AS category_name
     FROM cart_items ci
     JOIN products p ON p.id = ci.product_id
     JOIN categories c ON c.id = p.category_id
     WHERE ci.user_id = ?
     ORDER BY ci.id DESC",
    [$userId]
);
$subtotal = array_reduce($items, fn($sum, $item) => $sum + ((float)$item['price'] * (int)$item['quantity']), 0.0);

$pageTitle = 'Cart - MotoTrack';
require_once __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
  <div class="container">
    <span class="eyebrow">Shop</span>
    <h1>Cart</h1>
  </div>
</section>

<section class="section container cart-layout">
  <div>
    <?php if ($message): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($items): ?>
      <form method="post">
        <?= authContextField() ?>
        <input type="hidden" name="action" value="update">
        <div class="cart-table">
          <div class="cart-row cart-head"><span>Product</span><span>Price</span><span>Quantity</span><span>Subtotal</span></div>
          <?php foreach ($items as $item): ?>
            <div class="cart-row">
              <span><?= htmlspecialchars($item['name']) ?></span>
              <span><?= formatPrice((float)$item['price']) ?></span>
              <span><input type="number" name="qty[<?= (int)$item['cart_id'] ?>]" min="0" max="<?= (int)$item['stock'] ?>" value="<?= (int)$item['quantity'] ?>"></span>
              <strong><?= formatPrice((float)$item['price'] * (int)$item['quantity']) ?></strong>
            </div>
          <?php endforeach; ?>
        </div>
        <button class="btn btn-outline" type="submit">Update cart</button>
      </form>
    <?php else: ?>
      <p class="empty-state">Your cart is empty. <a href="<?= baseUrl('shop.php') ?>">Browse products</a>.</p>
    <?php endif; ?>
  </div>

  <aside class="summary-box">
    <h2>Cart Totals</h2>
    <div><span>Subtotal</span><strong><?= formatPrice($subtotal) ?></strong></div>
    <div><span>Total</span><strong><?= formatPrice($subtotal) ?></strong></div>
    <a class="btn btn-primary <?= !$items ? 'disabled' : '' ?>" href="<?= baseUrl('checkout.php') ?>">Proceed to checkout</a>
  </aside>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
