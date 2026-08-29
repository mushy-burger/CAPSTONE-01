<?php
$pageTitle = 'Products';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/ProductCodes.php';
requireStaff();
$currentUser = getCurrentUser();

function buildUniqueCategorySlug(string $name, ?int $ignoreId = null): string {
    $base = slug($name);
    if ($base === '') {
        $base = 'category';
    }

    $candidate = $base;
    $suffix = 2;

    while (true) {
        $params = [$candidate];
        $sql = "SELECT id FROM categories WHERE slug = ?";
        if ($ignoreId) {
            $sql .= " AND id != ?";
            $params[] = $ignoreId;
        }

        if (!fetchOne($sql, $params)) {
            return $candidate;
        }

        $candidate = $base . '-' . $suffix;
        $suffix++;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_category') {
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $categoryName = trim($_POST['category_name'] ?? '');

        if ($categoryName === '') {
            flashMessage('prod_error', 'Category name is required.');
            redirect(baseUrl('staff/products.php') . '#tab-categories');
        }

        $slugValue = buildUniqueCategorySlug($categoryName, $categoryId ?: null);

        if ($categoryId) {
            getDB()->prepare("UPDATE categories SET name = ?, slug = ? WHERE id = ?")
                ->execute([$categoryName, $slugValue, $categoryId]);
            flashMessage('prod_success', 'Category updated.');
        } else {
            getDB()->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)")
                ->execute([$categoryName, $slugValue]);
            flashMessage('prod_success', 'Category added.');
        }

        redirect(baseUrl('staff/products.php') . '#tab-categories');
    }

    if ($action === 'delete_category') {
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $linked = fetchOne("SELECT COUNT(*) AS total FROM products WHERE category_id = ?", [$categoryId]);
        if ((int)($linked['total'] ?? 0) > 0) {
            flashMessage('prod_error', 'This category cannot be deleted while products use it.');
        } elseif ($categoryId > 0) {
            getDB()->prepare("DELETE FROM categories WHERE id = ?")->execute([$categoryId]);
            flashMessage('prod_success', 'Category deleted.');
        }

        redirect(baseUrl('staff/products.php') . '#tab-categories');
    }

    if ($action === 'delete_product') {
        $pid = (int)($_POST['product_id'] ?? 0);
        $linked = fetchOne(
            "SELECT
                (SELECT COUNT(*) FROM order_items WHERE product_id = ?) AS order_refs,
                (SELECT COUNT(*) FROM booking_products WHERE product_id = ?) AS booking_refs",
            [$pid, $pid]
        );

        if ((int)($linked['order_refs'] ?? 0) > 0 || (int)($linked['booking_refs'] ?? 0) > 0) {
            flashMessage('prod_error', 'This product cannot be deleted because it is used in orders or service bookings. Set it to Out of Stock instead.');
            redirect(baseUrl('staff/products.php') . '#tab-list');
        }

        $row = fetchOne("SELECT image FROM products WHERE id = ?", [$pid]);
        if ($row && $row['image'] && file_exists(__DIR__ . '/../uploads/' . $row['image'])) {
            unlink(__DIR__ . '/../uploads/' . $row['image']);
        }
        getDB()->prepare("DELETE FROM products WHERE id = ?")->execute([$pid]);
        flashMessage('prod_success', 'Product deleted.');
        redirect(baseUrl('staff/products.php') . '#tab-list');
    }

    if ($action === 'save_product') {
        $pid         = (int)($_POST['product_id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $categoryId  = (int)($_POST['category_id'] ?? 0);
        $brand       = trim($_POST['brand'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price       = (float)($_POST['price'] ?? 0);
        $origPrice   = ($_POST['original_price'] ?? '') !== '' ? (float)$_POST['original_price'] : null;
        $stock       = (int)($_POST['stock'] ?? 0);
        $minStock    = max(0, (int)($_POST['min_stock'] ?? 10));
        $status      = in_array($_POST['status'] ?? '', ['available', 'low_stock', 'out_of_stock'], true) ? $_POST['status'] : 'available';
        $featured    = isset($_POST['featured']) ? 1 : 0;

        if (!$name || !$categoryId || $price <= 0) {
            flashMessage('prod_error', 'Name, category, and a valid price are required.');
            redirect(baseUrl('staff/products.php' . ($pid ? '?edit=' . $pid : '')) . '#product-form');
        }

        // --- Product identification (barcode / QR) ---
        // Validated before the product is written so a duplicate code cannot
        // leave a half-saved product behind.
        $codeMode      = $_POST['code_mode'] ?? 'none';
        $codeMode      = in_array($codeMode, ['none', 'existing', 'generate'], true) ? $codeMode : 'none';
        $existingCode  = mtxNormalizeCode($_POST['product_code'] ?? '');
        $codeSymbology = in_array($_POST['code_symbology'] ?? '', ['qr', 'barcode', 'both'], true)
            ? $_POST['code_symbology']
            : 'both';

        if ($codeMode === 'existing' && $existingCode !== '') {
            $codeError = mtxValidateCode($existingCode, $pid);
            if ($codeError !== '') {
                flashMessage('prod_error', $codeError);
                redirect(baseUrl('staff/products.php' . ($pid ? '?edit=' . $pid : '')) . '#product-form');
            }
        }

        $imageName = $_POST['existing_image'] ?? null;
        if (!empty($_FILES['image']['name'])) {
            $file = $_FILES['image'];
            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            if (!in_array($file['type'], $allowed, true)) {
                flashMessage('prod_error', 'Invalid image type.');
                redirect(baseUrl('staff/products.php' . ($pid ? '?edit=' . $pid : '')) . '#product-form');
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $dest = __DIR__ . '/../uploads/products/';
            if (!is_dir($dest)) {
                mkdir($dest, 0755, true);
            }
            $filename = 'products/' . uniqid('prod_') . '.' . $ext;
            move_uploaded_file($file['tmp_name'], __DIR__ . '/../uploads/' . $filename);

            if ($pid && $imageName && file_exists(__DIR__ . '/../uploads/' . $imageName)) {
                unlink(__DIR__ . '/../uploads/' . $imageName);
            }
            $imageName = $filename;
        }

        if ($pid) {
            getDB()->prepare(
                "UPDATE products SET name=?,category_id=?,brand=?,description=?,price=?,original_price=?,stock=?,min_stock=?,status=?,featured=?,image=? WHERE id=?"
            )->execute([$name, $categoryId, $brand, $description, $price, $origPrice, $stock, $minStock, $status, $featured, $imageName, $pid]);
            flashMessage('prod_success', 'Product updated.');
        } else {
            getDB()->prepare(
                "INSERT INTO products (name,category_id,brand,description,price,original_price,stock,min_stock,status,featured,image) VALUES (?,?,?,?,?,?,?,?,?,?,?)"
            )->execute([$name, $categoryId, $brand, $description, $price, $origPrice, $stock, $minStock, $status, $featured, $imageName]);
            // A new product has no id until now, and the MotoTrack code is
            // derived from it, so the code is attached after the insert.
            $pid = (int)getDB()->lastInsertId();
            flashMessage('prod_success', 'Product added.');
        }

        $savedCode = '';
        if ($codeMode === 'generate') {
            $savedCode = mtxAssignGeneratedCode($pid, $codeSymbology);
        } elseif ($codeMode === 'existing' && $existingCode !== '') {
            mtxSaveCode($pid, $existingCode, 'manufacturer', $codeSymbology);
            $savedCode = $existingCode;
        }

        if ($savedCode !== '') {
            flashMessage('prod_success', getFlash('prod_success') . ' Code ' . $savedCode . ' assigned.');
        }

        // Print the label straight after saving when asked.
        if ($savedCode !== '' && ($_POST['print_label'] ?? '') === '1') {
            redirect(baseUrl('staff/product-label.php?id=' . $pid . '&code=' . rawurlencode($savedCode) . '&autoprint=1'));
        }

        redirect(baseUrl('staff/products.php') . '#tab-list');
    }
}

$flash = getFlash('prod_success');
$flashErr = getFlash('prod_error');
$categories = fetchAllRows(
    "SELECT c.*, COALESCE(pc.product_count, 0) AS product_count
     FROM categories c
     LEFT JOIN (
       SELECT category_id, COUNT(*) AS product_count
       FROM products
       GROUP BY category_id
     ) pc ON pc.category_id = c.id
     ORDER BY c.name"
);

$search = trim($_GET['q'] ?? '');
$catFilter = (int)($_GET['cat'] ?? 0);

$where = ['1=1'];
$params = [];
if ($search !== '') {
    $where[] = '(p.name LIKE ? OR p.brand LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($catFilter) {
    $where[] = 'p.category_id = ?';
    $params[] = $catFilter;
}

$products = fetchAllRows(
    "SELECT p.*, c.name AS category_name
     FROM products p JOIN categories c ON c.id = p.category_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY p.created_at DESC, p.id DESC",
    $params
);

// Overall inventory stats for the KPI cards (independent of list filters)
$prodStats = fetchOne(
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(stock = 0), 0) AS out_stock,
            COALESCE(SUM(stock > 0 AND stock <= min_stock), 0) AS low_stock
     FROM products"
);
$statTotal    = (int)($prodStats['total'] ?? 0);
$statOut      = (int)($prodStats['out_stock'] ?? 0);
$statLow      = (int)($prodStats['low_stock'] ?? 0);
$statCategories = count($categories);

// Identification codes for the visible rows, fetched in one query.
$codesByProduct = mtxGetCodesForProducts(array_column($products, 'id'));

$editId = (int)($_GET['edit'] ?? 0);
$editProd = $editId ? fetchOne("SELECT * FROM products WHERE id = ?", [$editId]) : null;
$editCodes = $editProd ? mtxGetProductCodes((int)$editProd['id']) : [];
$editGeneratedCode = null;
$editManufacturerCode = null;
foreach ($editCodes as $codeRow) {
    if ($codeRow['code_type'] === 'mototrack' && $editGeneratedCode === null) {
        $editGeneratedCode = $codeRow;
    } elseif ($codeRow['code_type'] === 'manufacturer' && $editManufacturerCode === null) {
        $editManufacturerCode = $codeRow;
    }
}
$editCategoryId = (int)($_GET['edit_category'] ?? 0);
$editCategory = $editCategoryId ? fetchOne("SELECT * FROM categories WHERE id = ?", [$editCategoryId]) : null;
$formTitle = $editProd ? 'Edit Product' : 'Add New Product';
$categoryFormTitle = $editCategory ? 'Edit Category' : 'Add New Category';

$activeTab = $_GET['tab'] ?? 'list';
$activeTab = $activeTab === 'manage' ? 'categories' : $activeTab; // legacy links
$activeTab = in_array($activeTab, ['list', 'categories'], true) ? $activeTab : 'list';
if ($editProd) {
    $activeTab = 'list';
}
if ($editCategory) {
    $activeTab = 'categories';
}

require_once __DIR__ . '/../includes/staff-sidebar.php';
?>

<div class="prodx-page">

  <!-- Toasts (from flash messages) -->
  <?php if ($flash || $flashErr): ?>
    <div class="prodx-toast-stack" id="prodxToasts">
      <?php if ($flash): ?>
        <div class="prodx-toast prodx-toast--success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($flash) ?></div>
      <?php endif; ?>
      <?php if ($flashErr): ?>
        <div class="prodx-toast prodx-toast--error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($flashErr) ?></div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <!-- Page header -->
  <header class="prodx-header">
    <div>
      <h1>Products</h1>
      <p>Manage inventory, pricing, categories, and stock from one place.</p>
    </div>
    <button type="button" class="prodx-btn prodx-btn--primary prodx-btn--lg" id="prodxAddProductBtn">
      <i class="fas fa-plus"></i> Add Product
    </button>
  </header>

  <!-- Inventory stats -->
  <section class="bk-stats-grid prodx-stats">
    <article class="bk-stat-card" style="--stat-color:#d71920;">
      <span class="bk-stat-icon"><i class="fas fa-boxes-stacked"></i></span>
      <span class="bk-stat-label">Total Products</span>
      <span class="bk-stat-value"><?= $statTotal ?></span>
      <span class="bk-stat-desc">Items in your catalog</span>
    </article>
    <article class="bk-stat-card" style="--stat-color:#4f8df9;">
      <span class="bk-stat-icon"><i class="fas fa-tags"></i></span>
      <span class="bk-stat-label">Categories</span>
      <span class="bk-stat-value"><?= $statCategories ?></span>
      <span class="bk-stat-desc">Product groupings</span>
    </article>
    <article class="bk-stat-card" style="--stat-color:#f0883e;">
      <span class="bk-stat-icon"><i class="fas fa-triangle-exclamation"></i></span>
      <span class="bk-stat-label">Low Stock</span>
      <span class="bk-stat-value"><?= $statLow ?></span>
      <span class="bk-stat-desc">At or below minimum level</span>
    </article>
    <article class="bk-stat-card" style="--stat-color:#f16a6a;">
      <span class="bk-stat-icon"><i class="fas fa-circle-xmark"></i></span>
      <span class="bk-stat-label">Out of Stock</span>
      <span class="bk-stat-value"><?= $statOut ?></span>
      <span class="bk-stat-desc">Needs restocking now</span>
    </article>
  </section>

  <!-- Tabs -->
  <nav class="prodx-tabs" aria-label="Products sections">
    <button type="button" class="prodx-tab <?= $activeTab === 'list' ? 'active' : '' ?>" data-tab="list">
      <i class="fas fa-box"></i> Products
    </button>
    <button type="button" class="prodx-tab <?= $activeTab === 'categories' ? 'active' : '' ?>" data-tab="categories">
      <i class="fas fa-tags"></i> Categories
    </button>
  </nav>

  <!-- ============ PRODUCTS TAB ============ -->
  <div id="tab-list" class="prodx-panel <?= $activeTab === 'list' ? 'active' : '' ?>">

    <!-- Toolbar -->
    <div class="prodx-card prodx-toolbar">
      <form method="get" class="prodx-toolbar-form">
        <?= authContextField() ?>
        <input type="hidden" name="tab" value="list">
        <div class="prodx-field prodx-field--search">
          <i class="fas fa-magnifying-glass"></i>
          <input type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search products or brands…">
        </div>
        <select name="cat" class="prodx-select">
          <option value="">All categories</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= (int)$cat['id'] ?>" <?= $catFilter === (int)$cat['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($cat['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <select id="prodxStatusFilter" class="prodx-select">
          <option value="">All statuses</option>
          <option value="available">Available</option>
          <option value="low_stock">Low Stock</option>
          <option value="out_of_stock">Out of Stock</option>
        </select>
        <select id="prodxSort" class="prodx-select">
          <option value="newest">Newest</option>
          <option value="name">Name A–Z</option>
          <option value="price_asc">Price: Low to High</option>
          <option value="price_desc">Price: High to Low</option>
          <option value="stock_asc">Stock: Low to High</option>
        </select>
        <button type="submit" class="prodx-btn prodx-btn--dark">Filter</button>
        <?php if ($search || $catFilter): ?>
          <a href="<?= baseUrl('staff/products.php?tab=list') ?>" class="prodx-btn prodx-btn--ghost">Clear</a>
        <?php endif; ?>
      </form>
    </div>

    <!-- Product rows -->
    <?php if ($products): ?>
      <div class="prodx-list" id="prodxList">
        <?php foreach ($products as $index => $p): ?>
          <?php
            $imgSrc = ($p['image'] && file_exists(__DIR__ . '/../uploads/' . $p['image']))
                ? uploadUrl($p['image'])
                : null;
            $statusMeta = [
                'available'    => ['label' => 'Available',    'class' => 'ok'],
                'low_stock'    => ['label' => 'Low Stock',    'class' => 'warn'],
                'out_of_stock' => ['label' => 'Out of Stock', 'class' => 'bad'],
            ][$p['status']] ?? ['label' => ucfirst($p['status']), 'class' => 'ok'];
            $stockIsLow = (int)$p['stock'] > 0 && (int)$p['stock'] <= (int)$p['min_stock'];
            $stockIsOut = (int)$p['stock'] === 0;
          ?>
          <article class="prodx-row"
                   data-name="<?= htmlspecialchars(mb_strtolower($p['name'])) ?>"
                   data-price="<?= (float)$p['price'] ?>"
                   data-stock="<?= (int)$p['stock'] ?>"
                   data-status="<?= htmlspecialchars($p['status']) ?>"
                   data-index="<?= $index ?>">
            <button type="button" class="prodx-thumb" data-preview-src="<?= $imgSrc ? htmlspecialchars($imgSrc) : '' ?>">
              <?php if ($imgSrc): ?>
                <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy">
              <?php else: ?>
                <span class="prodx-thumb-fallback"><?= strtoupper(mb_substr($p['name'], 0, 1)) ?></span>
              <?php endif; ?>
            </button>

            <div class="prodx-row-main">
              <strong class="prodx-row-name">
                <?= htmlspecialchars($p['name']) ?>
                <?php if ($p['featured']): ?><i class="fas fa-star prodx-featured" title="Featured"></i><?php endif; ?>
              </strong>
              <div class="prodx-row-meta">
                <span class="prodx-badge"><?= htmlspecialchars($p['category_name']) ?></span>
                <?php if ($p['brand']): ?><span class="prodx-brand"><?= htmlspecialchars($p['brand']) ?></span><?php endif; ?>
                <?php foreach (($codesByProduct[(int)$p['id']] ?? []) as $codeRow): ?>
                  <span class="prodx-codechip <?= $codeRow['code_type'] === 'mototrack' ? 'is-mtx' : '' ?>"
                        title="<?= $codeRow['code_type'] === 'mototrack' ? 'MotoTrack generated code' : 'Manufacturer code' ?>">
                    <i class="fas fa-<?= $codeRow['symbology'] === 'qr' ? 'qrcode' : 'barcode' ?>"></i>
                    <?= htmlspecialchars($codeRow['code']) ?>
                  </span>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="prodx-row-price">
              <?php if ($p['original_price']): ?>
                <span class="prodx-strike">₱<?= number_format((float)$p['original_price'], 2) ?></span>
              <?php endif; ?>
              <strong>₱<?= number_format((float)$p['price'], 2) ?></strong>
            </div>

            <div class="prodx-row-stock <?= $stockIsOut ? 'is-out' : ($stockIsLow ? 'is-low' : '') ?>">
              <span class="prodx-stock-num"><?= (int)$p['stock'] ?></span>
              <span class="prodx-stock-cap">in stock<?= $stockIsLow ? ' · low' : '' ?></span>
            </div>

            <span class="prodx-pill prodx-pill--<?= $statusMeta['class'] ?>"><?= $statusMeta['label'] ?></span>

            <div class="prodx-menu-wrap">
              <button type="button" class="prodx-menu-btn" aria-haspopup="true" aria-expanded="false" aria-label="Actions for <?= htmlspecialchars($p['name']) ?>">
                <i class="fas fa-ellipsis-vertical"></i>
              </button>
              <div class="prodx-menu" hidden>
                <a href="<?= baseUrl('product.php?id=' . (int)$p['id']) ?>" target="_blank"><i class="fas fa-eye"></i> Preview</a>
                <a href="<?= baseUrl('staff/products.php?tab=list&edit=' . (int)$p['id']) ?>#product-form"><i class="fas fa-pen"></i> Edit</a>
                <?php if (!empty($codesByProduct[(int)$p['id']])): ?>
                  <a href="<?= baseUrl('staff/product-label.php?id=' . (int)$p['id']) ?>" target="_blank"><i class="fas fa-print"></i> Print label</a>
                <?php endif; ?>
                <form method="post" onsubmit="return confirm('Delete \'<?= htmlspecialchars(addslashes($p['name'])) ?>\'?')">
                  <?= authContextField() ?>
                  <input type="hidden" name="action" value="delete_product">
                  <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                  <button type="submit" class="prodx-menu-danger"><i class="fas fa-trash"></i> Delete</button>
                </form>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
      <p class="prodx-empty prodx-empty--inline" id="prodxNoMatches" hidden>
        <i class="fas fa-filter-circle-xmark"></i> No products match the selected status filter.
      </p>
    <?php else: ?>
      <div class="prodx-card prodx-empty">
        <i class="fas fa-box-open"></i>
        <h3>No products <?= ($search || $catFilter) ? 'matched your search' : 'yet' ?>.</h3>
        <?php if ($search || $catFilter): ?>
          <a href="<?= baseUrl('staff/products.php?tab=list') ?>" class="prodx-btn prodx-btn--ghost">Clear filters</a>
        <?php else: ?>
          <p>Add your first product to start building the catalog.</p>
          <button type="button" class="prodx-btn prodx-btn--primary" data-open-wizard>Add First Product</button>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <!-- ============ PRODUCT WIZARD (create / edit) ============ -->
    <div id="product-form" class="prodx-card prodx-wizard" <?= $editProd ? '' : 'hidden' ?>>
      <div class="prodx-wizard-head">
        <h2><?= $formTitle ?></h2>
        <button type="button" class="prodx-wizard-close" id="prodxWizardClose" aria-label="Close form">&times;</button>
      </div>

      <ol class="prodx-steps-nav">
        <li class="active" data-step-dot="1"><span>1</span> Basic Info</li>
        <li data-step-dot="2"><span>2</span> Pricing</li>
        <li data-step-dot="3"><span>3</span> Inventory</li>
        <li data-step-dot="4"><span>4</span> Details</li>
        <li data-step-dot="5"><span>5</span> Identification</li>
      </ol>

      <form method="post" enctype="multipart/form-data" id="prodxWizardForm">
        <?= authContextField() ?>
        <input type="hidden" name="action" value="save_product">
        <?php if ($editProd): ?>
          <input type="hidden" name="product_id" value="<?= (int)$editProd['id'] ?>">
          <input type="hidden" name="existing_image" value="<?= htmlspecialchars($editProd['image'] ?? '') ?>">
        <?php endif; ?>

        <fieldset class="prodx-step active" data-step="1">
          <div class="prodx-form-grid">
            <label class="prodx-label span-2">
              <span>Product name <em>*</em></span>
              <input type="text" name="name" required value="<?= htmlspecialchars($editProd['name'] ?? '') ?>" placeholder="e.g. Motul 7100 10W-40">
            </label>
            <label class="prodx-label">
              <span>Category <em>*</em></span>
              <select name="category_id" required>
                <option value="">Select category</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?= (int)$cat['id'] ?>" <?= $editProd && (int)$editProd['category_id'] === (int)$cat['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="prodx-label">
              <span>Brand</span>
              <input type="text" name="brand" value="<?= htmlspecialchars($editProd['brand'] ?? '') ?>" placeholder="e.g. Motul">
            </label>
          </div>
        </fieldset>

        <fieldset class="prodx-step" data-step="2">
          <div class="prodx-form-grid">
            <label class="prodx-label">
              <span>Selling price (PHP) <em>*</em></span>
              <input type="number" name="price" min="0" step="0.01" required value="<?= isset($editProd) ? (float)$editProd['price'] : '' ?>" placeholder="0.00">
            </label>
            <label class="prodx-label">
              <span>Original price</span>
              <input type="number" name="original_price" min="0" step="0.01" value="<?= isset($editProd) && $editProd['original_price'] ? (float)$editProd['original_price'] : '' ?>" placeholder="Optional — shows a strikethrough">
            </label>
          </div>
        </fieldset>

        <fieldset class="prodx-step" data-step="3">
          <div class="prodx-form-grid">
            <label class="prodx-label">
              <span>Stock</span>
              <input type="number" name="stock" min="0" value="<?= isset($editProd) ? (int)$editProd['stock'] : '0' ?>">
            </label>
            <label class="prodx-label">
              <span>Minimum stock level</span>
              <input type="number" name="min_stock" min="0" value="<?= isset($editProd) ? (int)$editProd['min_stock'] : '10' ?>">
            </label>
            <label class="prodx-label">
              <span>Status</span>
              <select name="status">
                <?php foreach (['available' => 'Available', 'low_stock' => 'Low Stock', 'out_of_stock' => 'Out of Stock'] as $val => $label): ?>
                  <option value="<?= $val ?>" <?= $editProd && $editProd['status'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
        </fieldset>

        <fieldset class="prodx-step" data-step="4">
          <div class="prodx-form-grid">
            <label class="prodx-label span-2">
              <span>Description</span>
              <textarea name="description" rows="4" placeholder="Key specs, compatibility, or selling points"><?= htmlspecialchars($editProd['description'] ?? '') ?></textarea>
            </label>

            <div class="prodx-label span-2">
              <span>Product image</span>
              <?php
                $existingImage = $editProd['image'] ?? null;
                $existingImageOk = $existingImage && file_exists(__DIR__ . '/../uploads/' . $existingImage);
              ?>
              <div class="prodx-dropzone" id="prodxDropzone">
                <input type="file" name="image" id="productImageInput" accept="image/*" hidden>
                <div class="prodx-dropzone-inner" id="prodxDropzoneInner" <?= $existingImageOk ? 'hidden' : '' ?>>
                  <i class="fas fa-cloud-arrow-up"></i>
                  <strong>Drag &amp; drop an image here</strong>
                  <span>PNG, JPG, WebP or GIF</span>
                  <button type="button" class="prodx-btn prodx-btn--ghost" id="prodxBrowseBtn">Browse files</button>
                </div>
                <div class="prodx-dropzone-preview" id="prodxDropzonePreview" <?= $existingImageOk ? '' : 'hidden' ?>>
                  <img src="<?= $existingImageOk ? uploadUrl($existingImage) : '' ?>" alt="Product image preview" id="prodxPreviewImg">
                  <button type="button" class="prodx-btn prodx-btn--ghost" id="prodxChangeImageBtn">Change image</button>
                </div>
              </div>
            </div>

            <label class="prodx-toggle span-2">
              <input type="checkbox" name="featured" value="1" <?= $editProd && $editProd['featured'] ? 'checked' : '' ?>>
              <span class="prodx-toggle-track"><span class="prodx-toggle-thumb"></span></span>
              <span class="prodx-toggle-text"><strong>Featured product</strong> — highlight on the storefront</span>
            </label>
          </div>
        </fieldset>

        <fieldset class="prodx-step" data-step="5">
          <?php
            // Pre-select the mode that matches what this product already has.
            $currentMode = 'none';
            if ($editGeneratedCode) {
                $currentMode = 'generate';
            } elseif ($editManufacturerCode) {
                $currentMode = 'existing';
            }
            $currentSymbology = $editGeneratedCode['symbology'] ?? $editManufacturerCode['symbology'] ?? 'both';
            // For a new product the code is only known after saving, so preview
            // the next id the table will hand out.
            $previewCode = $editProd
                ? mtxProductCode((int)$editProd['id'])
                : mtxProductCode((int)(fetchOne("SELECT COALESCE(MAX(id), 0) + 1 AS next FROM products")['next'] ?? 1));
          ?>
          <p class="prodx-muted prodx-idmsg">
            Give this product a scannable code so it can be found instantly at the counter.
            The product's database ID never changes — a code is only an extra way to look it up.
          </p>

          <div class="prodx-idmodes">
            <label class="prodx-idmode">
              <input type="radio" name="code_mode" value="none" <?= $currentMode === 'none' ? 'checked' : '' ?>>
              <span class="prodx-idmode-body">
                <strong><i class="fas fa-ban"></i> No code</strong>
                <small>Skip for now — you can add one later.</small>
              </span>
            </label>

            <label class="prodx-idmode">
              <input type="radio" name="code_mode" value="existing" <?= $currentMode === 'existing' ? 'checked' : '' ?>>
              <span class="prodx-idmode-body">
                <strong><i class="fas fa-barcode"></i> Use existing code</strong>
                <small>The item already has a manufacturer barcode or QR.</small>
              </span>
            </label>

            <label class="prodx-idmode">
              <input type="radio" name="code_mode" value="generate" <?= $currentMode === 'generate' ? 'checked' : '' ?>>
              <span class="prodx-idmode-body">
                <strong><i class="fas fa-wand-magic-sparkles"></i> Generate MotoTrack code</strong>
                <small>For items with no usable barcode of their own.</small>
              </span>
            </label>
          </div>

          <!-- Existing / scanned code -->
          <div class="prodx-idpanel" data-idpanel="existing" hidden>
            <label class="prodx-label">
              <span>Scan or enter the code <em>*</em></span>
              <div class="prodx-scanfield">
                <i class="fas fa-barcode"></i>
                <input type="text" name="product_code" id="prodxCodeInput" maxlength="64" autocomplete="off"
                       placeholder="Click here, then scan the product"
                       value="<?= htmlspecialchars($editManufacturerCode['code'] ?? '') ?>">
              </div>
            </label>
            <p class="prodx-codestatus" id="prodxCodeStatus" hidden></p>
            <p class="prodx-muted">
              A barcode scanner types the code and presses Enter, so put the cursor in the field and scan.
            </p>
          </div>

          <!-- Generated MotoTrack code -->
          <div class="prodx-idpanel" data-idpanel="generate" hidden>
            <div class="prodx-genrow">
              <div>
                <span class="prodx-genlabel">MotoTrack code</span>
                <strong class="prodx-gencode" id="prodxGenCode"><?= htmlspecialchars($previewCode) ?></strong>
                <?php if (!$editProd): ?>
                  <small class="prodx-muted">Confirmed once the product is saved.</small>
                <?php endif; ?>
              </div>
              <?php if ($editGeneratedCode): ?>
                <a href="<?= baseUrl('staff/product-label.php?id=' . (int)$editProd['id'] . '&code=' . rawurlencode($editGeneratedCode['code'])) ?>"
                   target="_blank" class="prodx-btn prodx-btn--ghost prodx-btn--sm">
                  <i class="fas fa-print"></i> Print label
                </a>
              <?php endif; ?>
            </div>
          </div>

          <!-- Symbology + print, shared by both code modes -->
          <div class="prodx-idpanel" data-idpanel="symbology" hidden>
            <span class="prodx-genlabel">Generate</span>
            <div class="prodx-symbology">
              <?php foreach (['qr' => 'QR only', 'barcode' => 'Barcode only', 'both' => 'Both'] as $value => $label): ?>
                <label class="prodx-symopt">
                  <input type="radio" name="code_symbology" value="<?= $value ?>" <?= $currentSymbology === $value ? 'checked' : '' ?>>
                  <span><?= $label ?></span>
                </label>
              <?php endforeach; ?>
            </div>

            <div class="prodx-preview" id="prodxPreview" hidden>
              <span class="prodx-genlabel">Preview</span>
              <div class="prodx-preview-symbols" id="prodxPreviewSymbols"></div>
            </div>

            <label class="prodx-toggle">
              <input type="checkbox" name="print_label" value="1">
              <span class="prodx-toggle-track"><span class="prodx-toggle-thumb"></span></span>
              <span class="prodx-toggle-text"><strong>Print label after saving</strong> — opens the print dialog</span>
            </label>
          </div>
        </fieldset>

        <div class="prodx-wizard-actions">
          <button type="button" class="prodx-btn prodx-btn--ghost" id="prodxPrevBtn" disabled><i class="fas fa-arrow-left"></i> Previous</button>
          <div class="prodx-wizard-actions-right">
            <?php if ($editProd): ?>
              <a href="<?= baseUrl('staff/products.php?tab=list') ?>" class="prodx-btn prodx-btn--ghost">Cancel</a>
            <?php endif; ?>
            <button type="button" class="prodx-btn prodx-btn--primary" id="prodxNextBtn">Next <i class="fas fa-arrow-right"></i></button>
            <button type="submit" class="prodx-btn prodx-btn--primary" id="prodxFinishBtn" hidden>
              <i class="fas fa-check"></i> <?= $editProd ? 'Update product' : 'Finish & Save' ?>
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- ============ CATEGORIES TAB ============ -->
  <div id="tab-categories" class="prodx-panel <?= $activeTab === 'categories' ? 'active' : '' ?>">
    <div class="prodx-cat-grid">

      <div class="prodx-card prodx-cat-form">
        <h2><?= $categoryFormTitle ?></h2>
        <p class="prodx-muted">Categories feed the product form and the shop filter.</p>
        <form method="post">
          <?= authContextField() ?>
          <input type="hidden" name="action" value="save_category">
          <?php if ($editCategory): ?><input type="hidden" name="category_id" value="<?= (int)$editCategory['id'] ?>"><?php endif; ?>
          <label class="prodx-label">
            <span>Category name</span>
            <input type="text" name="category_name" required value="<?= htmlspecialchars($editCategory['name'] ?? '') ?>" placeholder="e.g. Engine Oil">
          </label>
          <div class="prodx-cat-form-actions">
            <button type="submit" class="prodx-btn prodx-btn--primary">
              <i class="fas fa-<?= $editCategory ? 'check' : 'plus' ?>"></i> <?= $editCategory ? 'Update category' : 'Add category' ?>
            </button>
            <?php if ($editCategory): ?>
              <a href="<?= baseUrl('staff/products.php?tab=categories') ?>" class="prodx-btn prodx-btn--ghost">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <div class="prodx-card prodx-cat-list-card">
        <h2>Category List</h2>
        <?php if ($categories): ?>
          <div class="prodx-cat-list">
            <?php foreach ($categories as $cat): ?>
              <div class="prodx-cat-row">
                <span class="prodx-cat-icon"><i class="fas fa-tag"></i></span>
                <div class="prodx-cat-copy">
                  <strong><?= htmlspecialchars($cat['name']) ?></strong>
                  <span><?= (int)$cat['product_count'] ?> product<?= (int)$cat['product_count'] === 1 ? '' : 's' ?></span>
                </div>
                <div class="prodx-cat-actions">
                  <a href="<?= baseUrl('staff/products.php?tab=categories&edit_category=' . (int)$cat['id']) ?>#tab-categories" class="prodx-btn prodx-btn--ghost prodx-btn--sm">
                    <i class="fas fa-pen"></i> Edit
                  </a>
                  <form method="post" onsubmit="return confirm('Delete category \'<?= htmlspecialchars(addslashes($cat['name'])) ?>\'?')">
                    <?= authContextField() ?>
                    <input type="hidden" name="action" value="delete_category">
                    <input type="hidden" name="category_id" value="<?= (int)$cat['id'] ?>">
                    <button type="submit" class="prodx-btn prodx-btn--danger prodx-btn--sm"><i class="fas fa-trash"></i> Delete</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="prodx-empty">
            <i class="fas fa-tags"></i>
            <h3>No categories yet.</h3>
            <p>Create your first category on the left to start organizing products.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="image-modal" id="productImageModal" aria-hidden="true">
  <div class="image-modal__backdrop" data-close-modal></div>
  <div class="image-modal__dialog">
    <button type="button" class="image-modal__close" data-close-modal aria-label="Close preview">&times;</button>
    <img src="" alt="Preview image" id="productImageModalImg">
  </div>
</div>

<script>
(function () {
  /* ---------- Tabs ---------- */
  const tabs = Array.from(document.querySelectorAll('.prodx-tab'));
  const panels = Array.from(document.querySelectorAll('.prodx-panel'));

  function openTab(name) {
    tabs.forEach(tab => tab.classList.toggle('active', tab.dataset.tab === name));
    panels.forEach(panel => panel.classList.toggle('active', panel.id === `tab-${name}`));
  }
  tabs.forEach(tab => tab.addEventListener('click', () => openTab(tab.dataset.tab)));

  // Hash routing (incl. legacy #tab-manage links from the admin dashboard)
  const legacyManageHash = window.location.hash === '#tab-manage';
  if (window.location.hash === '#tab-categories') openTab('categories');
  if (['#tab-list', '#product-form'].includes(window.location.hash) || legacyManageHash) openTab('list');

  /* ---------- Toasts ---------- */
  document.querySelectorAll('.prodx-toast').forEach(toast => {
    setTimeout(() => toast.classList.add('is-leaving'), 4200);
    setTimeout(() => toast.remove(), 4800);
    toast.addEventListener('click', () => toast.remove());
  });

  /* ---------- Wizard ---------- */
  const wizard = document.getElementById('product-form');
  const wizardForm = document.getElementById('prodxWizardForm');
  const steps = Array.from(document.querySelectorAll('.prodx-step'));
  const dots = Array.from(document.querySelectorAll('[data-step-dot]'));
  const prevBtn = document.getElementById('prodxPrevBtn');
  const nextBtn = document.getElementById('prodxNextBtn');
  const finishBtn = document.getElementById('prodxFinishBtn');
  let step = 1;
  const LAST = steps.length;

  function showWizard() {
    if (!wizard) return;
    wizard.hidden = false;
    wizard.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function setStep(n) {
    step = Math.min(LAST, Math.max(1, n));
    steps.forEach(s => s.classList.toggle('active', Number(s.dataset.step) === step));
    dots.forEach(d => {
      const dn = Number(d.dataset.stepDot);
      d.classList.toggle('active', dn === step);
      d.classList.toggle('done', dn < step);
    });
    prevBtn.disabled = step === 1;
    nextBtn.hidden = step === LAST;
    finishBtn.hidden = step !== LAST;
  }

  function stepIsValid(n) {
    const fields = steps[n - 1].querySelectorAll('input, select, textarea');
    for (const field of fields) {
      if (!field.checkValidity()) { field.reportValidity(); return false; }
    }
    return true;
  }

  if (wizard) {
    document.getElementById('prodxAddProductBtn')?.addEventListener('click', showWizard);
    document.querySelectorAll('[data-open-wizard]').forEach(b => b.addEventListener('click', showWizard));
    document.getElementById('prodxWizardClose')?.addEventListener('click', () => { wizard.hidden = true; });
    nextBtn.addEventListener('click', () => { if (stepIsValid(step)) setStep(step + 1); });
    prevBtn.addEventListener('click', () => setStep(step - 1));
    dots.forEach(d => d.addEventListener('click', () => {
      const target = Number(d.dataset.stepDot);
      if (target < step || stepIsValid(step)) setStep(target);
    }));
    // If a hidden required field blocks submit, jump to its step
    wizardForm.addEventListener('invalid', (e) => {
      const owner = e.target.closest('.prodx-step');
      if (owner) setStep(Number(owner.dataset.step));
    }, true);
    setStep(1);
    if (legacyManageHash) showWizard();
  }

  /* ---------- Dropzone ---------- */
  const dropzone = document.getElementById('prodxDropzone');
  const fileInput = document.getElementById('productImageInput');
  const dzInner = document.getElementById('prodxDropzoneInner');
  const dzPreview = document.getElementById('prodxDropzonePreview');
  const dzImg = document.getElementById('prodxPreviewImg');

  function previewFile(file) {
    if (!file || !file.type.startsWith('image/')) return;
    const reader = new FileReader();
    reader.onload = e => {
      dzImg.src = e.target.result;
      dzInner.hidden = true;
      dzPreview.hidden = false;
    };
    reader.readAsDataURL(file);
  }

  if (dropzone && fileInput) {
    document.getElementById('prodxBrowseBtn')?.addEventListener('click', () => fileInput.click());
    document.getElementById('prodxChangeImageBtn')?.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', () => previewFile(fileInput.files && fileInput.files[0]));
    ['dragenter', 'dragover'].forEach(ev => dropzone.addEventListener(ev, e => {
      e.preventDefault();
      dropzone.classList.add('is-dragover');
    }));
    ['dragleave', 'drop'].forEach(ev => dropzone.addEventListener(ev, e => {
      e.preventDefault();
      dropzone.classList.remove('is-dragover');
    }));
    dropzone.addEventListener('drop', e => {
      const file = e.dataTransfer.files && e.dataTransfer.files[0];
      if (file) {
        const dt = new DataTransfer();
        dt.items.add(file);
        fileInput.files = dt.files;
        previewFile(file);
      }
    });
  }

  /* ---------- Row action menus ---------- */
  document.querySelectorAll('.prodx-menu-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const menu = btn.nextElementSibling;
      const isOpen = !menu.hidden;
      document.querySelectorAll('.prodx-menu').forEach(m => { m.hidden = true; });
      document.querySelectorAll('.prodx-menu-btn').forEach(b => b.setAttribute('aria-expanded', 'false'));
      menu.hidden = isOpen;
      btn.setAttribute('aria-expanded', String(!isOpen));
    });
  });
  document.addEventListener('click', () => {
    document.querySelectorAll('.prodx-menu').forEach(m => { m.hidden = true; });
    document.querySelectorAll('.prodx-menu-btn').forEach(b => b.setAttribute('aria-expanded', 'false'));
  });

  /* ---------- Client-side status filter + sort ---------- */
  const list = document.getElementById('prodxList');
  const statusFilter = document.getElementById('prodxStatusFilter');
  const sortSelect = document.getElementById('prodxSort');
  const noMatches = document.getElementById('prodxNoMatches');

  function applyListView() {
    if (!list) return;
    const rows = Array.from(list.querySelectorAll('.prodx-row'));
    const status = statusFilter.value;
    let visible = 0;
    rows.forEach(row => {
      const show = !status || row.dataset.status === status;
      row.hidden = !show;
      if (show) visible++;
    });
    if (noMatches) noMatches.hidden = visible > 0;

    const mode = sortSelect.value;
    const sorted = rows.slice().sort((a, b) => {
      switch (mode) {
        case 'name':       return a.dataset.name.localeCompare(b.dataset.name);
        case 'price_asc':  return a.dataset.price - b.dataset.price;
        case 'price_desc': return b.dataset.price - a.dataset.price;
        case 'stock_asc':  return a.dataset.stock - b.dataset.stock;
        default:           return a.dataset.index - b.dataset.index;
      }
    });
    sorted.forEach(row => list.appendChild(row));
  }
  statusFilter?.addEventListener('change', applyListView);
  sortSelect?.addEventListener('change', applyListView);

  /* ---------- Image preview modal ---------- */
  const modal = document.getElementById('productImageModal');
  const modalImg = document.getElementById('productImageModalImg');
  document.querySelectorAll('.prodx-thumb').forEach(button => {
    button.addEventListener('click', () => {
      const src = button.dataset.previewSrc;
      if (!src) return;
      modalImg.src = src;
      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden', 'false');
    });
  });
  document.querySelectorAll('[data-close-modal]').forEach(close => {
    close.addEventListener('click', () => {
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
      modalImg.src = '';
    });
  });

  /* ---------- Product identification (step 5) ---------- */
  const idModes = Array.from(document.querySelectorAll('input[name="code_mode"]'));
  const idPanels = Array.from(document.querySelectorAll('[data-idpanel]'));
  const codeInput = document.getElementById('prodxCodeInput');
  const codeStatus = document.getElementById('prodxCodeStatus');
  const genCodeEl = document.getElementById('prodxGenCode');
  const previewBox = document.getElementById('prodxPreview');
  const previewSymbols = document.getElementById('prodxPreviewSymbols');
  const editingId = <?= $editProd ? (int)$editProd['id'] : 0 ?>;
  const lookupUrl = '<?= baseUrl('api/product-lookup.php') ?>';
  const previewUrl = '<?= baseUrl('api/code-preview.php') ?>';

  function currentMode() {
    const checked = idModes.find(r => r.checked);
    return checked ? checked.value : 'none';
  }

  function activeCodeValue() {
    const mode = currentMode();
    if (mode === 'generate') return genCodeEl ? genCodeEl.textContent.trim() : '';
    if (mode === 'existing') return codeInput ? codeInput.value.trim() : '';
    return '';
  }

  let previewTimer = null;
  function refreshPreview() {
    if (!previewBox || !previewSymbols) return;
    const code = activeCodeValue();
    const symbology = (document.querySelector('input[name="code_symbology"]:checked') || {}).value || 'both';

    if (!code) {
      previewBox.hidden = true;
      previewSymbols.innerHTML = '';
      return;
    }

    clearTimeout(previewTimer);
    previewTimer = setTimeout(() => {
      fetch(previewUrl + '?code=' + encodeURIComponent(code) + '&symbology=' + encodeURIComponent(symbology))
        .then(r => r.json())
        .then(data => {
          if (!data.ok || !data.svg) {
            previewBox.hidden = true;
            return;
          }
          previewSymbols.innerHTML = data.svg;
          previewBox.hidden = false;
        })
        .catch(() => { previewBox.hidden = true; });
    }, 220);
  }

  function syncIdPanels() {
    const mode = currentMode();
    idPanels.forEach(panel => {
      const which = panel.dataset.idpanel;
      panel.hidden = which === 'symbology' ? mode === 'none' : which !== mode;
    });
    // Only require a typed code when that mode is actually selected.
    if (codeInput) codeInput.required = mode === 'existing';
    if (mode !== 'existing' && codeStatus) codeStatus.hidden = true;
    refreshPreview();
  }

  idModes.forEach(radio => radio.addEventListener('change', syncIdPanels));
  document.querySelectorAll('input[name="code_symbology"]').forEach(radio => {
    radio.addEventListener('change', refreshPreview);
  });

  // Live duplicate check against the codes already in the database.
  let checkTimer = null;
  function checkCode() {
    if (!codeInput || !codeStatus) return;
    const value = codeInput.value.trim();
    if (!value) {
      codeStatus.hidden = true;
      codeInput.setCustomValidity('');
      return;
    }

    clearTimeout(checkTimer);
    checkTimer = setTimeout(() => {
      fetch(lookupUrl + '?check=' + encodeURIComponent(value) + '&product_id=' + editingId)
        .then(r => r.json())
        .then(data => {
          const ok = !!data.available;
          codeStatus.hidden = false;
          codeStatus.className = 'prodx-codestatus ' + (ok ? 'is-ok' : 'is-bad');
          codeStatus.innerHTML = ok
            ? '<i class="fas fa-circle-check"></i> Code is available.'
            : '<i class="fas fa-circle-exclamation"></i> ' + (data.error || 'That code is already in use.');
          // Block submit while the code belongs to another product.
          codeInput.setCustomValidity(ok ? '' : (data.error || 'That code is already in use.'));
        })
        .catch(() => { codeStatus.hidden = true; });
    }, 300);
  }

  if (codeInput) {
    codeInput.addEventListener('input', () => { codeInput.setCustomValidity(''); checkCode(); refreshPreview(); });
    // A scanner ends its input with Enter; that must not submit the half-filled form.
    codeInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') { e.preventDefault(); checkCode(); }
    });
  }

  if (idModes.length) syncIdPanels();

  <?php if ($editProd): ?>
  showWizard();
  <?php endif; ?>
})();
</script>
<?= authContextScriptTag() ?>
</main></div></div></body></html>
