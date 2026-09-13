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
  <header class="customer-page-heading shop-page-heading">
    <div>
      <h1>Shop motorcycle parts</h1>
      <p>Search available products, narrow the catalog, and compare options.</p>
    </div>
    <span class="shop-result-count"><?= count($products) ?> product<?= count($products) !== 1 ? 's' : '' ?></span>
  </header>

  <!-- Filter / Sort Bar -->
  <form class="filter-bar shop-toolbar" method="get" aria-label="Product filters">
    <?= authContextField() ?>
    <label class="shop-search-field"><span>Search products</span>
      <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Product or brand">
    </label>
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
    <button class="btn btn-dark shop-filter-submit" type="submit">Apply filters</button>
    <?php if ($q || $categoryId || $sort !== 'featured'): ?>
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
      <?php
        // Add low-stock badge dynamically
        $stockBadge = '';
        $stock = (int)$product['stock'];
        if ($stock <= 5 && $stock > 0) {
            $stockBadge = '<span class="product-status-badge is-low-stock">Only ' . $stock . ' left</span>';
        }
        if ($product['featured']) {
            $stockBadge .= '<span class="product-status-badge is-featured">Featured</span>';
        }
      ?>
      <div class="product-card-shell">
        <?= $stockBadge ?>
        <?= productCard($product) ?>
      </div>
    <?php endforeach; ?>
  </div>

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

  const closeAll = (except) => {
    document.querySelectorAll('.mtx-select[data-open]').forEach((el) => {
      if (el === except) return;
      el.removeAttribute('data-open');
      el.querySelector('.mtx-select-trigger').setAttribute('aria-expanded', 'false');
      el.querySelector('.mtx-select-menu').hidden = true;
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

    const open = () => {
      closeAll(wrap);
      wrap.setAttribute('data-open', '');
      trigger.setAttribute('aria-expanded', 'true');
      menu.hidden = false;
      setActive(select.selectedIndex < 0 ? 0 : select.selectedIndex, true);
      menu.focus();
    };
    const close = (focusTrigger) => {
      wrap.removeAttribute('data-open');
      trigger.setAttribute('aria-expanded', 'false');
      menu.hidden = true;
      if (focusTrigger) trigger.focus();
    };
    const choose = (idx) => {
      const opt = select.options[idx];
      if (!opt) return;
      if (select.selectedIndex !== idx) {
        select.selectedIndex = idx;
        select.dispatchEvent(new Event('change', { bubbles: true }));
      }
      valueEl.textContent = opt.text.trim();
      options.forEach((o, i) => o.setAttribute('aria-selected', i === idx ? 'true' : 'false'));
      close(true);
    };

    trigger.addEventListener('click', () => {
      wrap.hasAttribute('data-open') ? close(true) : open();
    });
    trigger.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        open();
      }
    });
    menu.addEventListener('keydown', (e) => {
      switch (e.key) {
        case 'ArrowDown': e.preventDefault(); setActive(activeIndex + 1); break;
        case 'ArrowUp': e.preventDefault(); setActive(activeIndex - 1); break;
        case 'Home': e.preventDefault(); setActive(0); break;
        case 'End': e.preventDefault(); setActive(options.length - 1); break;
        case 'Enter':
        case ' ': e.preventDefault(); choose(activeIndex); break;
        case 'Escape': e.preventDefault(); close(true); break;
        case 'Tab': close(false); break;
        default: break;
      }
    });
    options.forEach((li, i) => {
      li.addEventListener('click', () => choose(i));
      li.addEventListener('mousemove', () => { if (activeIndex !== i) setActive(i, false); });
    });

    wrap.append(trigger, menu);
    select.classList.add('mtx-select-native');
    select.setAttribute('tabindex', '-1');
    select.setAttribute('aria-hidden', 'true');
    select.parentNode.insertBefore(wrap, select.nextSibling);
  });

  document.addEventListener('mousedown', (e) => {
    if (!e.target.closest('.mtx-select')) closeAll(null);
  });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
