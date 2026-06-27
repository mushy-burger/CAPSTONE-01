<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$pageTitle = 'Shop - MotoTrack';
require_once __DIR__ . '/includes/header.php';

$categoryId = isset($_GET['category']) ? (int)$_GET['category'] : null;
$q          = trim($_GET['q'] ?? '');
$sort       = trim($_GET['sort'] ?? 'featured');
$validSorts = ['featured','price_asc','price_desc','newest'];
$sort       = in_array($sort, $validSorts, true) ? $sort : 'featured';
$categories = getCategories();

$where  = ["p.status != 'out_of_stock'"];
$params = [];
if ($categoryId) {
    $where[]  = 'p.category_id = ?';
    $params[] = $categoryId;
}
if ($q !== '') {
    $where[]  = '(p.name LIKE ? OR p.brand LIKE ? OR p.description LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}

$orderBy = match($sort) {
    'price_asc'  => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'newest'     => 'p.created_at DESC, p.id DESC',
    default      => 'p.featured DESC, p.created_at DESC, p.id DESC',
};

$products = fetchAllRows(
    "SELECT p.*, c.name AS category_name
     FROM products p
     JOIN categories c ON c.id = p.category_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY $orderBy",
    $params
);
?>

<section class="section container">
  <!-- Filter / Sort Bar -->
  <form class="filter-bar" method="get" style="flex-wrap:wrap;gap:10px;">
    <?= authContextField() ?>
    <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search product or brand" style="flex:1;min-width:180px;">
    <select name="category">
      <option value="">All categories</option>
      <?php foreach ($categories as $category): ?>
        <option value="<?= (int)$category['id'] ?>" <?= $categoryId === (int)$category['id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($category['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <select name="sort">
      <option value="featured"   <?= $sort==='featured'   ? 'selected':'' ?>>Featured</option>
      <option value="newest"     <?= $sort==='newest'     ? 'selected':'' ?>>Newest</option>
      <option value="price_asc"  <?= $sort==='price_asc'  ? 'selected':'' ?>>Price: Low to High</option>
      <option value="price_desc" <?= $sort==='price_desc' ? 'selected':'' ?>>Price: High to Low</option>
    </select>
    <button class="btn btn-dark" type="submit">Filter</button>
    <?php if ($q || $categoryId || $sort !== 'featured'): ?>
      <a href="<?= baseUrl('shop.php') ?>" class="btn btn-outline">Reset</a>
    <?php endif; ?>
  </form>

  <!-- Category Pills -->
  <div class="category-pills">
    <a href="<?= baseUrl('shop.php') ?>" class="<?= !$categoryId ? 'active' : '' ?>">All</a>
    <?php foreach ($categories as $category): ?>
      <a href="<?= baseUrl('shop.php?category=' . (int)$category['id']) ?>"
         class="<?= $categoryId === (int)$category['id'] ? 'active' : '' ?>">
        <?= htmlspecialchars($category['name']) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- Results count -->
  <?php if ($q || $categoryId): ?>
    <p style="color:var(--muted);font-size:.88rem;margin-bottom:12px;">
      <?= count($products) ?> result<?= count($products)!==1?'s':'' ?>
      <?= $q ? ' for "<strong>' . htmlspecialchars($q) . '</strong>"' : '' ?>
    </p>
  <?php endif; ?>

  <!-- Product Grid -->
  <div class="product-grid">
    <?php foreach ($products as $product): ?>
      <?php
        // Add low-stock badge dynamically
        $stockBadge = '';
        $stock = (int)$product['stock'];
        if ($stock <= 5 && $stock > 0) {
            $stockBadge = '<span style="position:absolute;top:10px;left:10px;background:#d97706;color:#fff;font-size:.68rem;font-weight:900;padding:3px 8px;border-radius:20px;z-index:2;">Only ' . $stock . ' left</span>';
        }
        if ($product['featured']) {
            $stockBadge .= '<span style="position:absolute;top:10px;right:10px;background:#d71920;color:#fff;font-size:.68rem;font-weight:900;padding:3px 8px;border-radius:20px;z-index:2;">Featured</span>';
        }
      ?>
      <div style="position:relative;">
        <?= $stockBadge ?>
        <?= productCard($product) ?>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if (!$products): ?>
    <p class="empty-state">No products matched your search. <a href="<?= baseUrl('shop.php') ?>">Clear filters</a></p>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
