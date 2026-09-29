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

$where  = ["p.status != 'archived'"];
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
    "SELECT p.*, c.name AS category_name,
            " . availableStockSql('p') . " AS available_stock
     FROM products p
     JOIN categories c ON c.id = p.category_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY $orderBy",
    $params
);
$hasActiveFilters = $q !== '' || $categoryId || $sort !== 'featured';

// Shop-only presentation. It preserves the established add-to-cart POST,
// product links, and availability calculation while keeping the card layout
// independent from product-detail related-product cards.
$renderShopProductCard = static function (array $product): string {
    $productId = (int)$product['id'];
    $available = max(0, (int)($product['available_stock'] ?? 0));
    $minimum = max(0, (int)($product['min_stock'] ?? 0));
    $brand = trim((string)($product['brand'] ?? '')) ?: (string)($product['category_name'] ?? 'Product');
    $stockClass = 'is-in-stock';
    $stockLabel = 'In Stock';
    if ($available <= 0) {
        $stockClass = 'is-out-of-stock';
        $stockLabel = 'Out of Stock';
    } elseif ($minimum > 0 && $available <= $minimum) {
        $stockClass = 'is-low-stock';
        $stockLabel = 'Low Stock (' . $available . ' left)';
    }

    $oldPrice = !empty($product['original_price'])
        ? '<span class="old-price">' . formatPrice((float)$product['original_price']) . '</span>'
        : '';
    $button = $available > 0
        ? '<button type="submit" class="btn btn-dark"><i class="fas fa-shopping-cart" aria-hidden="true"></i><span>Add to Cart</span></button>'
        : '<button type="submit" class="btn btn-dark" disabled aria-disabled="true"><i class="fas fa-ban" aria-hidden="true"></i><span>Unavailable</span></button>';

    return '<article class="product-card mtx-tilt-card" data-tilt-card>
        <a href="' . baseUrl('product.php?id=' . $productId) . '" class="product-media">'
            . productImageHtml($product['image'] ?? '', (string)$product['name'], '') .
        '</a>
        <div class="product-info">
            <span class="eyebrow">' . htmlspecialchars($brand) . '</span>
            <h3><a href="' . baseUrl('product.php?id=' . $productId) . '">' . htmlspecialchars((string)$product['name']) . '</a></h3>
            <div class="price-line">' . $oldPrice . '<strong>' . formatPrice((float)$product['price']) . '</strong></div>
            <span class="shop-product-stock ' . $stockClass . '"><i class="fas fa-circle" aria-hidden="true"></i>' . htmlspecialchars($stockLabel) . '</span>
            <form method="post" action="' . baseUrl('cart.php') . '">'
                . authContextField() . '
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="' . $productId . '">'
                . $button . '
            </form>
        </div>
    </article>';
};
?>

<section class="section container">
  <header class="customer-page-heading shop-page-heading">
    <div>
      <h1>Shop motorcycle parts</h1>
      <p>Search products, narrow the catalog, and compare options.</p>
    </div>
    <span class="shop-result-count"><?= count($products) ?> product<?= count($products) !== 1 ? 's' : '' ?></span>
  </header>

  <!-- Filter / Sort Bar -->
  <form class="filter-bar shop-toolbar" method="get" aria-label="Product filters"<?= $hasActiveFilters ? ' data-mobile-filters-active="true"' : '' ?>>
    <?= authContextField() ?>
    <label class="shop-search-field"><span>Search products</span>
      <i class="fas fa-search" aria-hidden="true"></i>
      <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Product or brand...">
    </label>
    <button class="shop-mobile-filter-toggle" type="button" aria-expanded="false">
      <i class="fas fa-sliders-h" aria-hidden="true"></i>
      <span>Filters</span>
      <span class="shop-filter-active-dot" aria-hidden="true"></span>
    </button>
    <label><span>Category</span><select name="category">
      <option value="">All categories</option>
      <?php foreach ($categories as $category): ?>
        <option value="<?= (int)$category['id'] ?>" <?= $categoryId === (int)$category['id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($category['name']) ?>
        </option>
      <?php endforeach; ?>
    </select></label>
    <label><span>Sort by</span><select name="sort">
      <option value="featured"   <?= $sort==='featured'   ? 'selected':'' ?>>Featured</option>
      <option value="newest"     <?= $sort==='newest'     ? 'selected':'' ?>>Newest</option>
      <option value="price_asc"  <?= $sort==='price_asc'  ? 'selected':'' ?>>Price: Low to High</option>
      <option value="price_desc" <?= $sort==='price_desc' ? 'selected':'' ?>>Price: High to Low</option>
    </select></label>
    <button class="btn btn-primary shop-filter-submit" type="submit"><i class="fas fa-filter" aria-hidden="true"></i><span>Apply Filters</span></button>
    <?php if ($hasActiveFilters): ?>
      <a href="<?= baseUrl('shop.php') ?>" class="btn btn-outline shop-filter-reset">Reset</a>
    <?php endif; ?>
  </form>

  <!-- Category Filter Bar -->
  <div class="category-pills-shell">
    <button type="button" class="pill-nav" id="pillNavLeft" aria-label="Scroll categories left" hidden>
      <i class="fas fa-chevron-left"></i>
    </button>
    <div class="category-pills" id="categoryPills">
      <a href="<?= baseUrl('shop.php') ?>" class="<?= !$categoryId ? 'active' : '' ?>"<?= !$categoryId ? ' aria-current="page"' : '' ?>>All</a>
      <?php foreach ($categories as $category): ?>
        <a href="<?= baseUrl('shop.php?category=' . (int)$category['id']) ?>"
           class="<?= $categoryId === (int)$category['id'] ? 'active' : '' ?>"<?= $categoryId === (int)$category['id'] ? ' aria-current="page"' : '' ?>>
          <?= htmlspecialchars($category['name']) ?>
        </a>
      <?php endforeach; ?>
    </div>
    <button type="button" class="pill-nav" id="pillNavRight" aria-label="Scroll categories right" hidden>
      <i class="fas fa-chevron-right"></i>
    </button>
  </div>

  <!-- Results count -->
  <?php if ($q || $categoryId): ?>
    <p class="shop-results-context">
      <?= count($products) ?> result<?= count($products)!==1?'s':'' ?>
      <?= $q ? ' for "<strong>' . htmlspecialchars($q) . '</strong>"' : '' ?>
    </p>
  <?php endif; ?>

  <!-- Product Grid -->
  <div class="product-grid">
    <?php foreach ($products as $product): ?>
      <div class="product-card-shell" data-shop-product>
        <?= $renderShopProductCard($product) ?>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if (count($products) > 10): ?>
    <nav class="shop-mobile-pagination" id="shopMobilePagination" aria-label="Product pages" hidden></nav>
  <?php endif; ?>

  <?php if (!$products): ?>
    <div class="empty-state customer-empty-state">
      <h2>No matching products</h2>
      <p>Try a different search or clear the current filters.</p>
      <a class="btn btn-outline" href="<?= baseUrl('shop.php') ?>">Clear filters</a>
    </div>
  <?php endif; ?>
</section>

<script>
(() => {
  // Category filter bar: horizontal scroll with arrows that appear only on overflow
  const pills = document.getElementById('categoryPills');
  const left = document.getElementById('pillNavLeft');
  const right = document.getElementById('pillNavRight');
  if (!pills || !left || !right) return;

  const sync = () => {
    const overflowing = pills.scrollWidth > pills.clientWidth + 4;
    left.hidden = right.hidden = !overflowing;
    if (overflowing) {
      left.disabled = pills.scrollLeft <= 4;
      right.disabled = pills.scrollLeft + pills.clientWidth >= pills.scrollWidth - 4;
    }
  };

  const step = () => Math.max(160, Math.round(pills.clientWidth * 0.6));
  left.addEventListener('click', () => pills.scrollBy({ left: -step(), behavior: 'smooth' }));
  right.addEventListener('click', () => pills.scrollBy({ left: step(), behavior: 'smooth' }));
  pills.addEventListener('scroll', sync, { passive: true });
  window.addEventListener('resize', sync);
  sync();
})();

(() => {
  // Enhance the Category and Sort selects with a custom listbox. The native
  // <select> stays in the form (sr-only) and remains the single source of
  // truth: choosing an option writes back to it and fires `change`, so filtering,
  // Apply Filters, and URL/query behavior are unchanged. No JS = native select.
  const toolbar = document.querySelector('.shop-toolbar');
  if (!toolbar || !('closest' in Element.prototype)) return;
  const selects = toolbar.querySelectorAll('select');
  let counter = 0;

  const closeMenu = (wrap, menu, focusTrigger, animate) => {
    wrap.removeAttribute('data-open');
    wrap.querySelector('.mtx-select-trigger').setAttribute('aria-expanded', 'false');
    if (!animate) {
      menu.classList.remove('is-opening', 'is-closing');
      menu.hidden = true;
    } else {
      menu.classList.remove('is-opening');
      menu.classList.add('is-closing');
      window.setTimeout(() => {
        if (!menu.classList.contains('is-closing')) return;
        menu.classList.remove('is-closing');
        menu.hidden = true;
      }, 120);
    }
    if (focusTrigger) wrap.querySelector('.mtx-select-trigger').focus();
  };
  const closeAll = (except, animate) => {
    document.querySelectorAll('.mtx-select[data-open]').forEach((el) => {
      if (el === except) return;
      closeMenu(el, el.querySelector('.mtx-select-menu'), false, animate);
    });
  };

  selects.forEach((select) => {
    const uid = 'mtxsel-' + (counter++);
    const labelSpan = select.closest('label') ? select.closest('label').querySelector('span') : null;
    if (labelSpan && !labelSpan.id) labelSpan.id = uid + '-lbl';

    const wrap = document.createElement('div');
    wrap.className = 'mtx-select';

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'mtx-select-trigger';
    trigger.id = uid + '-trg';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');

    const valueEl = document.createElement('span');
    valueEl.className = 'mtx-select-value';
    valueEl.id = uid + '-val';
    const selText = () => (select.options[select.selectedIndex] ? select.options[select.selectedIndex].text.trim() : '');
    valueEl.textContent = selText();

    const chevron = document.createElement('i');
    chevron.className = 'fas fa-chevron-down mtx-select-chevron';
    chevron.setAttribute('aria-hidden', 'true');
    trigger.append(valueEl, chevron);

    const menu = document.createElement('ul');
    menu.className = 'mtx-select-menu';
    menu.id = uid + '-menu';
    menu.setAttribute('role', 'listbox');
    menu.tabIndex = -1;
    menu.hidden = true;
    if (labelSpan) menu.setAttribute('aria-labelledby', labelSpan.id);
    trigger.setAttribute('aria-controls', menu.id);
    trigger.setAttribute('aria-labelledby', (labelSpan ? labelSpan.id + ' ' : '') + valueEl.id);

    const options = Array.from(select.options).map((opt, i) => {
      const li = document.createElement('li');
      li.className = 'mtx-select-option';
      li.id = uid + '-opt-' + i;
      li.setAttribute('role', 'option');
      li.dataset.index = String(i);
      li.setAttribute('aria-selected', opt.selected ? 'true' : 'false');
      const label = document.createElement('span');
      label.className = 'mtx-select-option-label';
      label.textContent = opt.text.trim();
      const check = document.createElement('i');
      check.className = 'fas fa-check mtx-select-check';
      check.setAttribute('aria-hidden', 'true');
      li.append(label, check);
      menu.appendChild(li);
      return li;
    });

    let activeIndex = select.selectedIndex < 0 ? 0 : select.selectedIndex;
    const setActive = (idx, scroll) => {
      activeIndex = (idx + options.length) % options.length;
      options.forEach((o, i) => o.classList.toggle('is-active', i === activeIndex));
      menu.setAttribute('aria-activedescendant', options[activeIndex].id);
      if (scroll !== false) options[activeIndex].scrollIntoView({ block: 'nearest' });
    };

    const open = (animate) => {
      closeAll(wrap, animate);
      menu.classList.remove('is-closing');
      wrap.setAttribute('data-open', '');
      trigger.setAttribute('aria-expanded', 'true');
      menu.hidden = false;
      if (animate) {
        menu.classList.add('is-opening');
        requestAnimationFrame(() => menu.classList.remove('is-opening'));
      }
      setActive(select.selectedIndex < 0 ? 0 : select.selectedIndex, true);
      menu.focus();
    };
    const close = (focusTrigger, animate) => closeMenu(wrap, menu, focusTrigger, animate);
    const choose = (idx, animate) => {
      const opt = select.options[idx];
      if (!opt) return;
      if (select.selectedIndex !== idx) {
        select.selectedIndex = idx;
        select.dispatchEvent(new Event('change', { bubbles: true }));
      }
      valueEl.textContent = opt.text.trim();
      options.forEach((o, i) => o.setAttribute('aria-selected', i === idx ? 'true' : 'false'));
      close(true, animate);
    };

    trigger.addEventListener('click', () => {
      wrap.hasAttribute('data-open') ? close(true, true) : open(true);
    });
    trigger.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        open(false);
      }
    });
    menu.addEventListener('keydown', (e) => {
      switch (e.key) {
        case 'ArrowDown': e.preventDefault(); setActive(activeIndex + 1); break;
        case 'ArrowUp': e.preventDefault(); setActive(activeIndex - 1); break;
        case 'Home': e.preventDefault(); setActive(0); break;
        case 'End': e.preventDefault(); setActive(options.length - 1); break;
        case 'Enter':
        case ' ': e.preventDefault(); choose(activeIndex, false); break;
        case 'Escape': e.preventDefault(); close(true, false); break;
        case 'Tab': close(false, false); break;
        default: break;
      }
    });
    options.forEach((li, i) => {
      li.addEventListener('click', () => choose(i, true));
      li.addEventListener('mousemove', () => { if (activeIndex !== i) setActive(i, false); });
    });

    wrap.append(trigger, menu);
    select.classList.add('mtx-select-native');
    select.setAttribute('tabindex', '-1');
    select.setAttribute('aria-hidden', 'true');
    select.parentNode.insertBefore(wrap, select.nextSibling);
  });

  document.addEventListener('mousedown', (e) => {
    if (!e.target.closest('.mtx-select')) closeAll(null, true);
  });
})();

(() => {
  const toolbar = document.querySelector('.shop-toolbar');
  const toggle = toolbar?.querySelector('.shop-mobile-filter-toggle');
  const mobile = window.matchMedia('(max-width: 720px)');
  if (!toolbar || !toggle) return;

  const syncFilters = () => {
    if (!mobile.matches) {
      toolbar.removeAttribute('data-mobile-filters-open');
      toggle.setAttribute('aria-expanded', 'false');
      return;
    }
    toggle.setAttribute('aria-expanded', toolbar.hasAttribute('data-mobile-filters-open') ? 'true' : 'false');
  };

  toggle.addEventListener('click', () => {
    if (!mobile.matches) return;
    toolbar.toggleAttribute('data-mobile-filters-open');
    syncFilters();
  });
  mobile.addEventListener('change', syncFilters);
  syncFilters();
})();

(() => {
  const grid = document.querySelector('.page-shop .product-grid');
  const pagination = document.getElementById('shopMobilePagination');
  const mobile = window.matchMedia('(max-width: 720px)');
  if (!grid || !pagination) return;

  const products = Array.from(grid.querySelectorAll('[data-shop-product]'));
  const productsPerPage = 10;
  const totalPages = Math.ceil(products.length / productsPerPage);
  let currentPage = 1;

  const pageNumbers = (page, compact) => {
    const fullLimit = compact ? 4 : 6;
    if (totalPages <= fullLimit) {
      return Array.from({ length: totalPages }, (_, index) => index + 1);
    }
    if (compact) {
      if (page <= 2) return [1, 2, 'ellipsis', totalPages];
      if (page >= totalPages - 1) return [1, 'ellipsis', totalPages - 1, totalPages];
      return [1, 'ellipsis', page, 'ellipsis', totalPages];
    }
    if (page <= 3) return [1, 2, 3, 'ellipsis', totalPages];
    if (page >= totalPages - 2) return [1, 'ellipsis', totalPages - 2, totalPages - 1, totalPages];
    return [1, 'ellipsis', page - 1, page, page + 1, 'ellipsis', totalPages];
  };

  const makeControl = (label, options = {}) => {
    const control = document.createElement('button');
    control.type = 'button';
    control.className = 'shop-page-control' + (options.active ? ' is-active' : '');
    control.disabled = Boolean(options.disabled);
    if (options.ariaLabel) control.setAttribute('aria-label', options.ariaLabel);
    if (options.active) control.setAttribute('aria-current', 'page');
    if (options.icon) {
      const icon = document.createElement('i');
      icon.className = 'fas ' + options.icon;
      icon.setAttribute('aria-hidden', 'true');
      control.append(icon);
    } else {
      control.textContent = label;
    }
    if (options.onClick) control.addEventListener('click', options.onClick);
    return control;
  };

  const scrollToResults = () => {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    grid.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
  };

  const render = (page = 1, shouldScroll = false) => {
    if (!mobile.matches) {
      products.forEach((product) => { product.hidden = false; });
      pagination.hidden = true;
      return;
    }

    currentPage = Math.min(Math.max(page, 1), totalPages);
    const start = (currentPage - 1) * productsPerPage;
    const end = start + productsPerPage;
    products.forEach((product, index) => { product.hidden = index < start || index >= end; });

    pagination.replaceChildren();
    pagination.hidden = totalPages <= 1;
    if (pagination.hidden) return;

    const controls = document.createElement('div');
    controls.className = 'shop-pagination-controls';
    controls.append(makeControl('', {
      icon: 'fa-chevron-left',
      ariaLabel: 'Previous product page',
      disabled: currentPage === 1,
      onClick: () => render(currentPage - 1, true)
    }));

    const compact = window.innerWidth <= 380;
    pageNumbers(currentPage, compact).forEach((item) => {
      if (item === 'ellipsis') {
        const ellipsis = document.createElement('span');
        ellipsis.className = 'shop-page-ellipsis';
        ellipsis.setAttribute('aria-hidden', 'true');
        ellipsis.textContent = '\u2026';
        controls.append(ellipsis);
        return;
      }
      controls.append(makeControl(String(item), {
        active: item === currentPage,
        ariaLabel: 'Go to product page ' + item,
        onClick: () => render(item, true)
      }));
    });

    controls.append(makeControl('', {
      icon: 'fa-chevron-right',
      ariaLabel: 'Next product page',
      disabled: currentPage === totalPages,
      onClick: () => render(currentPage + 1, true)
    }));
    pagination.append(controls);
    if (shouldScroll) scrollToResults();
  };

  mobile.addEventListener('change', () => render(1));
  window.addEventListener('resize', () => {
    if (mobile.matches) render(currentPage);
  });
  render(1);
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
