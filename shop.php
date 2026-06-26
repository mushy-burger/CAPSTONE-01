<?php
$pageTitle = 'Shop - MotoTrack';
require_once __DIR__ . '/includes/header.php';

$categoryId = isset($_GET['category']) ? (int)$_GET['category'] : null;
$q = trim($_GET['q'] ?? '');
$categories = getCategories();

$where = ["p.status != 'out_of_stock'"];
$params = [];
if ($categoryId) {
    $where[] = 'p.category_id = ?';
    $params[] = $categoryId;
}
if ($q !== '') {
    $where[] = '(p.name LIKE ? OR p.brand LIKE ? OR p.description LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}

$products = fetchAllRows(
    "SELECT p.*, c.name AS category_name
     FROM products p
     JOIN categories c ON c.id = p.category_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY p.featured DESC, p.created_at DESC, p.id DESC",
    $params
);
?>

<section class="section container">
  <form class="filter-bar" method="get">
    <?= authContextField() ?>
    <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search product or brand">
    <select name="category">
      <option value="">All categories</option>
      <?php foreach ($categories as $category): ?>
        <option value="<?= (int)$category['id'] ?>" <?= $categoryId === (int)$category['id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($category['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-dark" type="submit">Filter</button>
  </form>

  <div class="category-pills">
    <a href="<?= baseUrl('shop.php') ?>" class="<?= !$categoryId ? 'active' : '' ?>">All</a>
    <?php foreach ($categories as $category): ?>
      <a href="<?= baseUrl('shop.php?category=' . (int)$category['id']) ?>" class="<?= $categoryId === (int)$category['id'] ? 'active' : '' ?>">
        <?= htmlspecialchars($category['name']) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="product-grid">
    <?php foreach ($products as $product): ?>
      <?= productCard($product) ?>
    <?php endforeach; ?>
  </div>

  <?php if (!$products): ?>
    <p class="empty-state">No products matched your search.</p>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
