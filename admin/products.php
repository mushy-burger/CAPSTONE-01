<?php
$pageTitle = 'Products';
require_once __DIR__ . '/../includes/admin-sidebar.php';
require_once __DIR__ . '/../includes/db.php';

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
            redirect(baseUrl('admin/products.php') . '#tab-manage');
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

        redirect(baseUrl('admin/products.php') . '#tab-manage');
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

        redirect(baseUrl('admin/products.php') . '#tab-manage');
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
            redirect(baseUrl('admin/products.php') . '#tab-list');
        }

        $row = fetchOne("SELECT image FROM products WHERE id = ?", [$pid]);
        if ($row && $row['image'] && file_exists(__DIR__ . '/../uploads/' . $row['image'])) {
            unlink(__DIR__ . '/../uploads/' . $row['image']);
        }
        getDB()->prepare("DELETE FROM products WHERE id = ?")->execute([$pid]);
        flashMessage('prod_success', 'Product deleted.');
        redirect(baseUrl('admin/products.php') . '#tab-list');
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
        $status      = in_array($_POST['status'] ?? '', ['available', 'low_stock', 'out_of_stock'], true) ? $_POST['status'] : 'available';
        $featured    = isset($_POST['featured']) ? 1 : 0;

        if (!$name || !$categoryId || $price <= 0) {
            flashMessage('prod_error', 'Name, category, and a valid price are required.');
            redirect(baseUrl('admin/products.php' . ($pid ? '?edit=' . $pid : '')) . '#tab-manage');
        }

        $imageName = $_POST['existing_image'] ?? null;
        if (!empty($_FILES['image']['name'])) {
            $file = $_FILES['image'];
            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            if (!in_array($file['type'], $allowed, true)) {
                flashMessage('prod_error', 'Invalid image type.');
                redirect(baseUrl('admin/products.php' . ($pid ? '?edit=' . $pid : '')) . '#tab-manage');
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
                "UPDATE products SET name=?,category_id=?,brand=?,description=?,price=?,original_price=?,stock=?,status=?,featured=?,image=? WHERE id=?"
            )->execute([$name, $categoryId, $brand, $description, $price, $origPrice, $stock, $status, $featured, $imageName, $pid]);
            flashMessage('prod_success', 'Product updated.');
        } else {
            getDB()->prepare(
                "INSERT INTO products (name,category_id,brand,description,price,original_price,stock,status,featured,image) VALUES (?,?,?,?,?,?,?,?,?,?)"
            )->execute([$name, $categoryId, $brand, $description, $price, $origPrice, $stock, $status, $featured, $imageName]);
            flashMessage('prod_success', 'Product added.');
        }

        redirect(baseUrl('admin/products.php') . '#tab-manage');
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

$editId = (int)($_GET['edit'] ?? 0);
$editProd = $editId ? fetchOne("SELECT * FROM products WHERE id = ?", [$editId]) : null;
$editCategoryId = (int)($_GET['edit_category'] ?? 0);
$editCategory = $editCategoryId ? fetchOne("SELECT * FROM categories WHERE id = ?", [$editCategoryId]) : null;
$formTitle = $editProd ? 'Edit Product' : 'Add New Product';
$categoryFormTitle = $editCategory ? 'Edit Category' : 'Add New Category';

$activeTab = ($_GET['tab'] ?? 'manage') === 'list' ? 'list' : 'manage';
if ($editProd || $editCategory) {
    $activeTab = 'manage';
}
?>

<section class="admin-card products-admin-shell">
  <div class="admin-page-head">
    <div>
      <h1>Products</h1>
      <p>Manage categories, add products, and review inventory from one place.</p>
    </div>
    <a href="#tab-manage" class="btn btn-primary">+ Add new product</a>
  </div>

  <?php if ($flash): ?><div class="alert success"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
  <?php if ($flashErr): ?><div class="alert error"><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>

  <div class="admin-tabs">
    <a href="#tab-manage" class="admin-tab <?= $activeTab === 'manage' ? 'active' : '' ?>" data-tab="manage">Add / Manage</a>
    <a href="#tab-list" class="admin-tab <?= $activeTab === 'list' ? 'active' : '' ?>" data-tab="list">Products</a>
  </div>

  <div id="tab-manage" class="admin-tab-panel <?= $activeTab === 'manage' ? 'active' : '' ?>">
    <div class="product-category-shell">
      <section class="product-category-panel">
        <div class="product-section-heading">
          <h2><?= $categoryFormTitle ?></h2>
          <p>Add, update, or remove product categories from one place.</p>
        </div>

        <form method="post" class="category-form">
          <?= authContextField() ?>
          <input type="hidden" name="action" value="save_category">
          <?php if ($editCategory): ?><input type="hidden" name="category_id" value="<?= (int)$editCategory['id'] ?>"><?php endif; ?>
          <label>
            <span>Category name</span>
            <input type="text" name="category_name" required value="<?= htmlspecialchars($editCategory['name'] ?? '') ?>" placeholder="Enter category name">
          </label>
          <div class="category-form-actions">
            <button type="submit" class="btn btn-primary"><?= $editCategory ? 'Update category' : 'Add category' ?></button>
            <?php if ($editCategory): ?><a href="#tab-manage" class="btn btn-outline">Cancel</a><?php endif; ?>
          </div>
        </form>
      </section>

      <section class="product-category-panel">
        <div class="product-section-heading">
          <h2>Category List</h2>
          <p>These values feed the category dropdown above and the product filter.</p>
        </div>
        <div class="category-list">
          <?php foreach ($categories as $cat): ?>
            <div class="category-row">
              <div class="category-row-copy">
                <strong><?= htmlspecialchars($cat['name']) ?></strong>
                <span><?= htmlspecialchars($cat['slug']) ?> · <?= (int)$cat['product_count'] ?> product<?= (int)$cat['product_count'] === 1 ? '' : 's' ?></span>
              </div>
              <div class="category-row-actions">
                <a href="#tab-manage" class="btn btn-outline js-edit-category" data-edit-category="<?= (int)$cat['id'] ?>">Edit</a>
                <form method="post" onsubmit="return confirm('Delete category \'<?= htmlspecialchars(addslashes($cat['name'])) ?>\'?')">
                  <?= authContextField() ?>
                  <input type="hidden" name="action" value="delete_category">
                  <input type="hidden" name="category_id" value="<?= (int)$cat['id'] ?>">
                  <button type="submit" class="btn btn-outline danger-btn">Delete</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    </div>

    <div id="product-form" class="product-form-panel">
      <h2><?= $formTitle ?></h2>
      <form method="post" enctype="multipart/form-data" class="product-form-grid">
        <?= authContextField() ?>
        <input type="hidden" name="action" value="save_product">
        <?php if ($editProd): ?>
          <input type="hidden" name="product_id" value="<?= (int)$editProd['id'] ?>">
          <input type="hidden" name="existing_image" value="<?= htmlspecialchars($editProd['image'] ?? '') ?>">
        <?php endif; ?>

        <label class="span-2">
          <span>Product name <span class="required">*</span></span>
          <input type="text" name="name" required value="<?= htmlspecialchars($editProd['name'] ?? '') ?>">
        </label>

        <label>
          <span>Category <span class="required">*</span></span>
          <select name="category_id" required>
            <option value="">Select category</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= (int)$cat['id'] ?>" <?= $editProd && (int)$editProd['category_id'] === (int)$cat['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($cat['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>

        <label>
          <span>Brand</span>
          <input type="text" name="brand" value="<?= htmlspecialchars($editProd['brand'] ?? '') ?>">
        </label>

        <label>
          <span>Price (PHP) <span class="required">*</span></span>
          <input type="number" name="price" min="0" step="0.01" required value="<?= isset($editProd) ? (float)$editProd['price'] : '' ?>">
        </label>

        <label>
          <span>Original price</span>
          <input type="number" name="original_price" min="0" step="0.01" value="<?= isset($editProd) && $editProd['original_price'] ? (float)$editProd['original_price'] : '' ?>">
        </label>

        <label>
          <span>Stock</span>
          <input type="number" name="stock" min="0" value="<?= isset($editProd) ? (int)$editProd['stock'] : '0' ?>">
        </label>

        <label>
          <span>Status</span>
          <select name="status">
            <?php foreach (['available' => 'Available', 'low_stock' => 'Low Stock', 'out_of_stock' => 'Out of Stock'] as $val => $label): ?>
              <option value="<?= $val ?>" <?= $editProd && $editProd['status'] === $val ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </label>

        <label class="span-2">
          <span>Description</span>
          <textarea name="description" rows="4"><?= htmlspecialchars($editProd['description'] ?? '') ?></textarea>
        </label>

        <label class="span-2">
          <span>Product image</span>
          <?php
            $existingImage = $editProd['image'] ?? null;
            $existingImageOk = $existingImage && file_exists(__DIR__ . '/../uploads/' . $existingImage);
          ?>
          <?php if ($existingImageOk): ?>
            <div class="upload-preview">
              <img src="<?= baseUrl('uploads/' . rawurlencode($existingImage)) ?>" alt="Current image">
            </div>
          <?php endif; ?>
          <input type="file" name="image" id="productImageInput" accept="image/*">
          <div class="upload-preview" id="productImagePreview" style="display:none;">
            <img src="" alt="Preview">
          </div>
        </label>

        <label class="span-2 inline-toggle">
          <input type="checkbox" name="featured" value="1" <?= $editProd && $editProd['featured'] ? 'checked' : '' ?>>
          <span>Mark as featured</span>
        </label>

        <div class="span-2 form-actions">
          <button type="submit" class="btn btn-primary"><?= $editProd ? 'Update product' : 'Add product' ?></button>
          <?php if ($editProd): ?><a href="#tab-manage" class="btn btn-outline">Cancel</a><?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <div id="tab-list" class="admin-tab-panel <?= $activeTab === 'list' ? 'active' : '' ?>">
    <div class="product-list-toolbar">
      <form method="get" class="product-filter-bar">
        <?= authContextField() ?>
        <input type="hidden" name="tab" value="list">
        <input type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name or brand">
        <select name="cat">
          <option value="">All categories</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= (int)$cat['id'] ?>" <?= $catFilter === (int)$cat['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($cat['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-dark">Filter</button>
        <?php if ($search || $catFilter): ?><a href="<?= baseUrl('admin/products.php?tab=list') ?>" class="btn btn-outline">Clear</a><?php endif; ?>
      </form>
    </div>

    <?php if ($products): ?>
      <div class="product-list-wrap">
        <table class="product-list-table">
          <thead>
            <tr>
              <th></th>
              <th>Name</th>
              <th>Category</th>
              <th>Price</th>
              <th>Stock</th>
              <th>Status</th>
              <th>Featured</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($products as $p): ?>
              <?php
                $imgSrc = ($p['image'] && file_exists(__DIR__ . '/../uploads/' . $p['image']))
                    ? baseUrl('uploads/' . rawurlencode($p['image']))
                    : null;
                $statusColors = ['available' => '#27ae60', 'low_stock' => '#e67e22', 'out_of_stock' => '#c0392b'];
                $sc = $statusColors[$p['status']] ?? '#888';
              ?>
              <tr>
                <td>
                  <button type="button" class="product-thumb-btn" data-preview-src="<?= $imgSrc ? htmlspecialchars($imgSrc) : '' ?>">
                    <?php if ($imgSrc): ?>
                      <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($p['name']) ?>">
                    <?php else: ?>
                      <div class="thumb-placeholder"><?= strtoupper(substr($p['name'], 0, 1)) ?></div>
                    <?php endif; ?>
                  </button>
                </td>
                <td>
                  <strong><?= htmlspecialchars($p['name']) ?></strong>
                  <?php if ($p['brand']): ?><div class="subtext"><?= htmlspecialchars($p['brand']) ?></div><?php endif; ?>
                </td>
                <td><?= htmlspecialchars($p['category_name']) ?></td>
                <td>
                  <?php if ($p['original_price']): ?><span class="strike">PHP <?= number_format((float)$p['original_price'], 2) ?></span><br><?php endif; ?>
                  <strong>PHP <?= number_format((float)$p['price'], 2) ?></strong>
                </td>
                <td><?= (int)$p['stock'] ?></td>
                <td><span class="status-pill" style="--status-color: <?= $sc ?>;"><?= ucfirst(str_replace('_', ' ', $p['status'])) ?></span></td>
                <td><?= $p['featured'] ? '★' : '☆' ?></td>
                <td class="product-actions">
                  <a href="<?= baseUrl('product.php?id=' . (int)$p['id']) ?>" target="_blank" class="btn btn-outline" title="Preview"><i class="fas fa-eye"></i></a>
                  <a href="<?= baseUrl('admin/products.php?tab=manage&edit=' . (int)$p['id']) ?>#product-form" class="btn btn-outline">Edit</a>
                  <form method="post" onsubmit="return confirm('Delete \'<?= htmlspecialchars(addslashes($p['name'])) ?>\'?')">
                    <?= authContextField() ?>
                    <input type="hidden" name="action" value="delete_product">
                    <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                    <button type="submit" class="btn btn-outline danger-btn">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p class="empty-note">No products found<?= ($search || $catFilter) ? ' for your filter' : '' ?>.</p>
    <?php endif; ?>
  </div>
</section>

<div class="image-modal" id="productImageModal" aria-hidden="true">
  <div class="image-modal__backdrop" data-close-modal></div>
  <div class="image-modal__dialog">
    <button type="button" class="image-modal__close" data-close-modal aria-label="Close preview">&times;</button>
    <img src="" alt="Preview image" id="productImageModalImg">
  </div>
</div>

<script>
(function () {
  const tabs = Array.from(document.querySelectorAll('.admin-tab'));
  const panels = Array.from(document.querySelectorAll('.admin-tab-panel'));
  const managePanel = document.getElementById('tab-manage');
  const listPanel = document.getElementById('tab-list');
  const imageInput = document.getElementById('productImageInput');
  const previewBox = document.getElementById('productImagePreview');
  const previewImg = previewBox ? previewBox.querySelector('img') : null;
  const modal = document.getElementById('productImageModal');
  const modalImg = document.getElementById('productImageModalImg');

  function openTab(name) {
    tabs.forEach(tab => tab.classList.toggle('active', tab.dataset.tab === name));
    panels.forEach(panel => panel.classList.toggle('active', panel.id === `tab-${name}`));
  }

  tabs.forEach(tab => {
    tab.addEventListener('click', () => openTab(tab.dataset.tab));
  });

  if (window.location.hash === '#tab-list') {
    openTab('list');
  } else if (window.location.hash === '#tab-manage') {
    openTab('manage');
  }

  if (imageInput && previewBox && previewImg) {
    imageInput.addEventListener('change', () => {
      const file = imageInput.files && imageInput.files[0];
      if (!file) {
        previewBox.style.display = 'none';
        previewImg.src = '';
        return;
      }
      const reader = new FileReader();
      reader.onload = e => {
        previewImg.src = e.target.result;
        previewBox.style.display = 'block';
      };
      reader.readAsDataURL(file);
    });
  }

  document.querySelectorAll('.product-thumb-btn').forEach(button => {
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

  <?php if ($editCategory): ?>
  openTab('manage');
  <?php elseif ($editProd): ?>
  openTab('manage');
  <?php endif; ?>
})();
</script>
</main></div></div></body></html>
