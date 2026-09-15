<?php
$pageTitle = 'Product Details - MotoTrack';
require_once __DIR__ . '/includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$product = fetchOne(
    "SELECT p.*, c.name AS category_name,
            " . availableStockSql('p') . " AS available_stock
     FROM products p
     JOIN categories c ON c.id = p.category_id
     WHERE p.id = ?",
    [$id]
);

if (!$product) {
    http_response_code(404);
    ?>
    <section class="section container">
      <div class="empty-state customer-empty-state">
        <div class="empty-state-icon"><i class="fas fa-box-open" aria-hidden="true"></i></div>
        <h1>Product not found</h1>
        <p>This product may have been removed, or the link is out of date.</p>
        <div class="empty-state-actions">
          <a class="btn btn-primary" href="<?= baseUrl('shop.php') ?>">Browse products</a>
          <a class="btn btn-outline" href="<?= baseUrl('index.php') ?>">Back to home</a>
        </div>
      </div>
    </section>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$related = fetchAllRows(
    "SELECT p.*, c.name AS category_name
     FROM products p JOIN categories c ON c.id = p.category_id
     WHERE p.category_id = ? AND p.id != ? AND p.status != 'archived'
     ORDER BY p.featured DESC, p.id DESC LIMIT 4",
    [$product['category_id'], $product['id']]
);
$product['available_stock'] = getAvailableStock((int)$product['id']);
$canAddToCart = $product['available_stock'] > 0;
?>

<section class="section container product-detail">
  <div class="product-detail-media">
    <?= productImageHtml($product['image'] ?? '', $product['name'], '') ?>
  </div>
  <div class="product-detail-copy">
    <span class="eyebrow"><?= htmlspecialchars($product['category_name']) ?></span>
    <h1><?= htmlspecialchars($product['name']) ?></h1>
    <div class="price-line large">
      <?php if ($product['original_price']): ?><span class="old-price"><?= formatPrice((float)$product['original_price']) ?></span><?php endif; ?>
      <strong><?= formatPrice((float)$product['price']) ?></strong>
    </div>
    <p><?= htmlspecialchars($product['description'] ?? 'No description available.') ?></p>
    <dl class="meta-list">
      <div><dt>Brand</dt><dd><?= htmlspecialchars($product['brand'] ?? 'Generic') ?></dd></div>
      <div><dt>Availability</dt><dd><?= $canAddToCart ? 'Available' : 'Unavailable' ?></dd></div>
    </dl>
    <?php if ($canAddToCart): ?>
      <form class="cart-add-row" method="post" action="<?= baseUrl('cart.php') ?>">
        <?= authContextField() ?>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
        <input type="number" name="quantity" min="1" value="1">
        <button class="btn btn-primary" type="submit">Add to cart</button>
      </form>
    <?php else: ?>
      <div class="alert error">This product is currently out of stock.</div>
    <?php endif; ?>
  </div>
</section>

<?php if ($related): ?>
<section class="section container">
  <div class="section-heading">
    <span class="eyebrow">Product</span>
    <h2>Related Products</h2>
  </div>
  <div class="product-grid">
    <?php foreach ($related as $item): ?>
      <?= productCard($item) ?>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
