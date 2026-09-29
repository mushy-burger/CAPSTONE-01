<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/OrderDeliveryService.php';
requireLogin();

$userId = getCurrentUser()['id'];
$message = '';
$error = '';
$activeTab = $_GET['tab'] ?? 'cart';
$activeTab = in_array($activeTab, ['cart', 'orders'], true) ? $activeTab : 'cart';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $productId = (int)($_POST['product_id'] ?? 0);
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));
        $db = getDB();
        $db->beginTransaction();
        try {
            $product = fetchOne("SELECT id, name, status FROM products WHERE id = ? AND status != 'archived' FOR UPDATE", [$productId]);
            if (!$product) throw new RuntimeException('Product is no longer available.');
            $available = getAvailableStock($productId);
            $existing = fetchOne("SELECT id, quantity FROM cart_items WHERE user_id = ? AND product_id = ? FOR UPDATE", [$userId, $productId]);
            $newQuantity = (int)($existing['quantity'] ?? 0) + $quantity;
            if ($available <= 0) throw new RuntimeException('This product is currently unavailable.');
            if ($newQuantity > $available) throw new RuntimeException('Only the currently available quantity can be added.');
            if ($existing) {
                $db->prepare("UPDATE cart_items SET quantity = ? WHERE id = ? AND user_id = ?")->execute([$newQuantity, $existing['id'], $userId]);
            } else {
                $db->prepare("INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)")->execute([$userId, $productId, $quantity]);
            }
            $db->commit();
            $message = 'Product added to cart.';
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            $error = $e->getMessage();
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
                    "SELECT p.id
                     FROM cart_items ci
                     JOIN products p ON p.id = ci.product_id
                     WHERE ci.id = ? AND ci.user_id = ?",
                    [(int)$cartId, $userId]
                );
                if (!$cartRow) {
                    continue;
                }

                $qty = min($qty, getAvailableStock((int)$cartRow['id']));
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

foreach ($items as &$item) {
    $item['available_stock'] = getAvailableStock((int)$item['id']);
}
unset($item);
$staleItems = array_filter($items, fn($i) => (int)$i['quantity'] > (int)$i['available_stock']);

// Presentation-only product rail: it reuses the normal cart add action below
// and never changes the selected-cart or checkout data path.
$recommendedProducts = fetchAllRows(
    "SELECT p.*, c.name AS category_name,
            " . availableStockSql('p') . " AS available_stock
     FROM products p
     JOIN categories c ON c.id = p.category_id
     WHERE p.status != 'archived'
     HAVING available_stock > 0
     ORDER BY p.featured DESC, p.created_at DESC, p.id DESC
     LIMIT 4"
);

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

<section class="section container cart-page">
  <header class="cart-page-heading">
    <h1>Cart &amp; Orders</h1>
    <p>Review the items in your cart and proceed to checkout.</p>
  </header>

  <nav class="page-tabs cart-page-tabs" aria-label="Cart views">
    <a href="<?= baseUrl('cart.php?tab=cart') ?>" class="<?= $activeTab === 'cart' ? 'active' : '' ?>"<?= $activeTab === 'cart' ? ' aria-current="page"' : '' ?>><i class="fas fa-shopping-cart" aria-hidden="true"></i><span>Cart (<?= count($items) ?>)</span></a>
    <a href="<?= baseUrl('cart.php?tab=orders') ?>" class="<?= $activeTab === 'orders' ? 'active' : '' ?>"<?= $activeTab === 'orders' ? ' aria-current="page"' : '' ?>><i class="fas fa-box" aria-hidden="true"></i><span>Checked Out Items</span></a>
  </nav>

  <?php if ($message): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($staleItems && $activeTab === 'cart'): ?>
      <div class="alert error" style="margin-bottom:14px;">
        ⚠️ <strong>Stock changed</strong> — the following items now have less stock than your cart quantity:
        <ul style="margin:6px 0 0 18px;font-size:.88rem;">
          <?php foreach ($staleItems as $si): ?>
            <li>
              <strong><?= htmlspecialchars($si['name']) ?></strong>
              — you have <?= (int)$si['quantity'] ?> in cart, but the item is no longer fully available.
            </li>
          <?php endforeach; ?>
        </ul>
        Please <strong>update your cart quantities</strong> before checking out.
      </div>
    <?php endif; ?>

  <div class="cart-layout <?= $activeTab === 'orders' ? 'is-orders' : '' ?>">
    <main class="cart-main">
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
              <input type="checkbox" name="selected_cart_ids[]" value="<?= (int)$item['cart_id'] ?>" class="cart-select-checkbox" form="cartCheckoutForm" aria-label="Select <?= htmlspecialchars($item['name']) ?> for checkout" checked>
            </span>
            <span class="cart-item-cell">
              <?= productImageHtml($item['image'] ?? '', $item['name'], 'cart-item-thumb') ?>
              <span class="cart-item-copy"><strong><?= htmlspecialchars($item['name']) ?></strong><small><?= htmlspecialchars($item['category_name'] ?? 'Product') ?></small></span>
            </span>
            <span class="cart-price-cell" data-label="Price"><?= formatPrice((float)$item['price']) ?></span>
            <span class="cart-quantity-cell" data-label="Quantity">
              <span class="cart-qty-counter">
                <button type="button" class="cart-qty-btn" data-cart-qty-minus aria-label="Decrease quantity">-</button>
                <input type="number" name="qty[<?= (int)$item['cart_id'] ?>]" min="1" max="<?= max(1, (int)$item['available_stock']) ?>" value="<?= (int)$item['quantity'] ?>" data-cart-qty form="cartCheckoutForm">
                <button type="button" class="cart-qty-btn" data-cart-qty-plus aria-label="Increase quantity">+</button>
              </span>
            </span>
            <strong class="cart-subtotal-cell" data-label="Subtotal" data-cart-line-subtotal><?= formatPrice($lineSubtotal) ?></strong>
            <span class="cart-action-cell">
              <form method="post" action="<?= baseUrl('cart.php?tab=cart') ?>" class="cart-remove-form">
                <?= authContextField() ?>
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="cart_id" value="<?= (int)$item['cart_id'] ?>">
                <button class="btn btn-outline btn-danger-lite" type="submit"><i class="fas fa-trash-alt" aria-hidden="true"></i><span>Remove</span></button>
              </form>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty-state customer-empty-state cart-empty-state">
        <span class="cart-empty-icon" aria-hidden="true"><i class="fas fa-shopping-cart"></i></span>
        <h2>Your cart is empty</h2>
        <p>Browse the catalog and add the parts or accessories you want to compare or checkout later.</p>
        <a class="btn btn-primary" href="<?= baseUrl('shop.php') ?>"><i class="fas fa-shopping-cart" aria-hidden="true"></i><span>Browse Products</span></a>
      </div>
    <?php endif; ?>

    <?php if ($recommendedProducts): ?>
      <section class="cart-recommendations" aria-labelledby="cartRecommendationsTitle">
        <header class="cart-recommendations-head">
          <h2 id="cartRecommendationsTitle">Recommended for You</h2>
          <a href="<?= baseUrl('shop.php') ?>">View Shop <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
        </header>
        <div class="cart-recommendation-grid">
          <?php foreach ($recommendedProducts as $product): ?>
            <article class="cart-recommendation-card">
              <a href="<?= baseUrl('product.php?id=' . (int)$product['id']) ?>" class="cart-recommendation-media">
                <?= productImageHtml($product['image'] ?? '', $product['name'], 'cart-recommendation-image') ?>
              </a>
              <div class="cart-recommendation-copy">
                <h3><a href="<?= baseUrl('product.php?id=' . (int)$product['id']) ?>"><?= htmlspecialchars($product['name']) ?></a></h3>
                <strong><?= formatPrice((float)$product['price']) ?></strong>
                <form method="post" action="<?= baseUrl('cart.php?tab=cart') ?>">
                  <?= authContextField() ?>
                  <input type="hidden" name="action" value="add">
                  <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                  <input type="hidden" name="quantity" value="1">
                  <button type="submit" class="btn btn-dark"><i class="fas fa-shopping-cart" aria-hidden="true"></i><span>Add to Cart</span></button>
                </form>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>
    <?php else: ?>
      <div class="history-list">
        <?php if ($orders): ?>
          <?php foreach ($orders as $order): ?>
            <?php
              $paymentStatus = trim((string)($order['payment_status'] ?? ''));
              $deliveryStatus = orderDeliveryStatus($order);
              $trackingUrl = trim((string)($order['tracking_url'] ?? ''));
            ?>
            <article class="history-card" id="order-<?= (int)$order['id'] ?>">
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
              <?php if (isOnlineShopOrder($order)): ?>
                <section class="history-delivery" aria-label="Delivery tracking">
                  <div class="history-delivery-head">
                    <span><i class="fas fa-truck" aria-hidden="true"></i> Delivery Tracking</span>
                    <strong class="history-delivery-status is-<?= htmlspecialchars($deliveryStatus) ?>"><?= htmlspecialchars(ucfirst($deliveryStatus)) ?></strong>
                  </div>
                  <?php if ($deliveryStatus === 'pending'): ?>
                    <p>Your order is being prepared. Tracking information will appear once your order is dispatched.</p>
                  <?php elseif ($deliveryStatus === 'delivering'): ?>
                    <p>Your order is on the way.</p>
                  <?php else: ?>
                    <p>Your order has been delivered.<?php if (!empty($order['delivered_at'])): ?> <?= htmlspecialchars(date('M j, Y g:i A', strtotime((string)$order['delivered_at']))) ?>.<?php endif; ?></p>
                  <?php endif; ?>
                  <?php if ($trackingUrl !== ''): ?>
                    <a class="btn btn-outline history-delivery-link" href="<?= htmlspecialchars($trackingUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer"><i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i> Track Delivery</a>
                  <?php endif; ?>
                </section>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="empty-state customer-empty-state">
            <h2>No checked out items yet</h2>
            <p>Completed product orders will appear here.</p>
            <a class="btn btn-outline" href="<?= baseUrl('shop.php') ?>">Browse Products</a>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    </main>

  <?php if ($activeTab === 'cart'): ?>
  <aside class="summary-box">
    <h2>Cart Summary</h2>
    <div><span>Items</span><strong data-cart-selected-count><?= count($items) ?></strong></div>
    <div><span>Subtotal</span><strong data-cart-selected-subtotal><?= formatPrice($subtotal) ?></strong></div>
    <div class="summary-grand-total"><span>Total</span><strong data-cart-selected-total><?= formatPrice($subtotal) ?></strong></div>
    <button class="btn btn-primary" type="submit" form="cartCheckoutForm" data-cart-checkout-btn <?= !$items ? 'disabled' : '' ?>><i class="fas fa-credit-card" aria-hidden="true"></i><span>Proceed to Checkout</span></button>
    <?php if ($items): ?><p class="fine-print" data-cart-selection-message></p><?php endif; ?>
    <p class="cart-summary-note"><i class="fas fa-info-circle" aria-hidden="true"></i><span>Prices and availability may change depending on current stock.</span></p>
  </aside>
  <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
