<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
requireLogin();

$userId = getCurrentUser()['id'];
$message = '';
$activeTab = $_GET['tab'] ?? 'cart';
$activeTab = in_array($activeTab, ['cart', 'orders'], true) ? $activeTab : 'cart';

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
                $cartRow = fetchOne(
                    "SELECT p.stock
                     FROM cart_items ci
                     JOIN products p ON p.id = ci.product_id
                     WHERE ci.id = ? AND ci.user_id = ?",
                    [(int)$cartId, $userId]
                );
                if (!$cartRow) {
                    continue;
                }

                $qty = min($qty, (int)$cartRow['stock']);
                if ($qty <= 0) {
                    $stmt = getDB()->prepare("DELETE FROM cart_items WHERE id = ? AND user_id = ?");
                    $stmt->execute([(int)$cartId, $userId]);
                    continue;
                }

                $stmt = getDB()->prepare("UPDATE cart_items SET quantity = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$qty, (int)$cartId, $userId]);
            }
        }
        $message = 'Cart updated.';
    }

    if ($action === 'remove') {
        $cartId = (int)($_POST['cart_id'] ?? 0);
        if ($cartId > 0) {
            getDB()->prepare("DELETE FROM cart_items WHERE id = ? AND user_id = ?")->execute([$cartId, $userId]);
            $message = 'Item removed from cart.';
        }
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

// Detect stale stock: items whose cart qty now exceeds current stock
$staleItems = array_filter($items, fn($i) => (int)$i['quantity'] > (int)$i['stock']);

$orders = fetchAllRows(
    "SELECT o.*
     FROM orders o
     WHERE o.user_id = ?
     ORDER BY o.created_at DESC, o.id DESC",
    [$userId]
);

$orderItems = fetchAllRows(
    "SELECT oi.order_id, oi.quantity, oi.price, p.name, p.image
     FROM order_items oi
     JOIN products p ON p.id = oi.product_id
     JOIN orders o ON o.id = oi.order_id
     WHERE o.user_id = ?
     ORDER BY oi.order_id DESC, oi.id ASC",
    [$userId]
);
$orderItemsByOrder = [];
foreach ($orderItems as $orderItem) {
    $orderItemsByOrder[(int)$orderItem['order_id']][] = $orderItem;
}

$pageTitle = 'Cart - MotoTrack';
require_once __DIR__ . '/includes/header.php';
?>

<section class="section container cart-layout">
  <div>
    <div class="section-heading compact">
      <span class="eyebrow">Shop</span>
      <h2>Cart & Orders</h2>
    </div>
    <div class="page-tabs">
      <a href="<?= baseUrl('cart.php?tab=cart') ?>" class="<?= $activeTab === 'cart' ? 'active' : '' ?>">Add To Cart</a>
      <a href="<?= baseUrl('cart.php?tab=orders') ?>" class="<?= $activeTab === 'orders' ? 'active' : '' ?>">Checked Out Items</a>
    </div>
    <?php if ($message): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($staleItems && $activeTab === 'cart'): ?>
      <div class="alert error" style="margin-bottom:14px;">
        ⚠️ <strong>Stock changed</strong> — the following items now have less stock than your cart quantity:
        <ul style="margin:6px 0 0 18px;font-size:.88rem;">
          <?php foreach ($staleItems as $si): ?>
            <li>
              <strong><?= htmlspecialchars($si['name']) ?></strong>
              — you have <?= (int)$si['quantity'] ?> in cart, only <?= (int)$si['stock'] ?> available.
            </li>
          <?php endforeach; ?>
        </ul>
        Please <strong>update your cart quantities</strong> before checking out.
      </div>
    <?php endif; ?>

    <?php if ($activeTab === 'cart'): ?>
    <?php if ($items): ?>
      <form method="post" action="<?= baseUrl('checkout.php') ?>" id="cartCheckoutForm">
        <?= authContextField() ?>
        <input type="hidden" name="action" value="prepare_checkout">
      </form>
      <div class="cart-table">
        <div class="cart-row cart-head"><span>Select</span><span>Product</span><span>Price</span><span>Quantity</span><span>Subtotal</span><span>Action</span></div>
        <?php foreach ($items as $item): ?>
          <?php $lineSubtotal = (float)$item['price'] * (int)$item['quantity']; ?>
          <div class="cart-row" data-cart-row data-price="<?= htmlspecialchars((string)(float)$item['price']) ?>">
            <span class="cart-select-cell">
              <input type="checkbox" name="selected_cart_ids[]" value="<?= (int)$item['cart_id'] ?>" class="cart-select-checkbox" form="cartCheckoutForm" checked>
            </span>
            <span class="cart-item-cell">
              <?= productImageHtml($item['image'] ?? '', $item['name'], 'cart-item-thumb') ?>
              <span><?= htmlspecialchars($item['name']) ?></span>
            </span>
            <span><?= formatPrice((float)$item['price']) ?></span>
            <span>
              <span class="cart-qty-counter">
                <button type="button" class="cart-qty-btn" data-cart-qty-minus aria-label="Decrease quantity">-</button>
                <input type="number" name="qty[<?= (int)$item['cart_id'] ?>]" min="1" max="<?= (int)$item['stock'] ?>" value="<?= (int)$item['quantity'] ?>" data-cart-qty form="cartCheckoutForm">
                <button type="button" class="cart-qty-btn" data-cart-qty-plus aria-label="Increase quantity">+</button>
              </span>
            </span>
            <strong data-cart-line-subtotal><?= formatPrice($lineSubtotal) ?></strong>
            <span>
              <form method="post" action="<?= baseUrl('cart.php?tab=cart') ?>" class="cart-remove-form">
                <?= authContextField() ?>
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="cart_id" value="<?= (int)$item['cart_id'] ?>">
                <button class="btn btn-outline btn-danger-lite" type="submit">Remove</button>
              </form>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="empty-state">Your cart is empty. <a href="<?= baseUrl('shop.php') ?>">Browse products</a>.</p>
    <?php endif; ?>
    <?php else: ?>
      <div class="history-list">
        <?php if ($orders): ?>
          <?php foreach ($orders as $order): ?>
            <?php $paymentStatus = trim((string)($order['payment_status'] ?? '')); ?>
            <article class="history-card">
              <div class="history-card-head">
                <div>
                  <strong>Order #<?= (int)$order['id'] ?></strong>
                  <span><?= htmlspecialchars(date('M j, Y g:i A', strtotime($order['created_at']))) ?></span>
                </div>
                <div class="history-status-group">
                  <span class="status-pill-lite"><?= htmlspecialchars(strtoupper($order['status'])) ?></span>
                  <span class="status-pill-lite <?= $paymentStatus === 'paid' ? 'is-paid' : '' ?>">
                    <?= htmlspecialchars(strtoupper($paymentStatus !== '' ? $paymentStatus : ($order['payment_method'] === 'paymongo' ? 'awaiting payment' : 'unpaid'))) ?>
                  </span>
                </div>
              </div>
              <div class="history-lines">
                <?php foreach ($orderItemsByOrder[(int)$order['id']] ?? [] as $orderItem): ?>
                  <div>
                    <span class="history-item-label">
                      <?= productImageHtml($orderItem['image'] ?? '', $orderItem['name'], 'history-item-thumb') ?>
                      <span><?= htmlspecialchars($orderItem['name']) ?> x<?= (int)$orderItem['quantity'] ?></span>
                    </span>
                    <strong><?= formatPrice((float)$orderItem['price'] * (int)$orderItem['quantity']) ?></strong>
                  </div>
                <?php endforeach; ?>
              </div>
              <div class="history-total">
                <span>Total</span>
                <strong><?= formatPrice((float)$order['total']) ?></strong>
              </div>
            </article>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="empty-state">No checked out items yet.</p>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <aside class="summary-box">
    <h2>Cart Totals</h2>
    <div><span>Subtotal</span><strong data-cart-selected-subtotal><?= formatPrice($subtotal) ?></strong></div>
    <div><span>Total</span><strong data-cart-selected-total><?= formatPrice($subtotal) ?></strong></div>
    <button class="btn btn-primary" type="submit" form="cartCheckoutForm" data-cart-checkout-btn <?= !$items ? 'disabled' : '' ?>>Proceed to checkout</button>
    <?php if ($items): ?><p class="fine-print" data-cart-selection-message></p><?php endif; ?>
  </aside>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
