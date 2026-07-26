<?php
$pageTitle = 'Point of Sale';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/PosService.php';
requireStaff();

$currentStaff = getCurrentUser();
$successOrderId = (int)($_GET['order_id'] ?? 0);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedItems = [];
    foreach ((array)($_POST['qty'] ?? []) as $productId => $qty) {
        $postedItems[(int)$productId] = (int)$qty;
    }

    try {
        $orderId = createPosSale(
            $postedItems,
            [
                'name' => sanitize($_POST['customer_name'] ?? ''),
                'phone' => sanitize($_POST['customer_phone'] ?? ''),
                'email' => sanitize($_POST['customer_email'] ?? ''),
            ],
            sanitize($_POST['payment_method'] ?? 'cash'),
            (float)($_POST['amount_tendered'] ?? 0),
            (int)$currentStaff['id'],
            sanitize($_POST['notes'] ?? '')
        );
        redirect(baseUrl('staff/pos.php?order_id=' . $orderId));
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$products = fetchAllRows(
    "SELECT p.*, c.name AS category_name
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.stock > 0 AND p.status != 'out_of_stock'
     ORDER BY c.name, p.name"
);
$categoryNames = [];
$totalUnits = 0;
foreach ($products as $product) {
    $categoryNames[(string)($product['category_name'] ?? 'Uncategorized')] = true;
    $totalUnits += (int)$product['stock'];
}
$categoryNames = array_keys($categoryNames);
sort($categoryNames);

$customerExpression = posColumnExists('orders', 'walk_in_customer_name')
    ? "COALESCE(NULLIF(o.walk_in_customer_name, ''), u.name, 'Walk-in Customer')"
    : "COALESCE(u.name, 'Walk-in Customer')";
$servedByJoin = posColumnExists('orders', 'served_by_staff_id')
    ? "LEFT JOIN users sb ON sb.id = o.served_by_staff_id"
    : "";
$servedBySelect = posColumnExists('orders', 'served_by_staff_id')
    ? "sb.name AS served_by_name,"
    : "NULL AS served_by_name,";

$recentOrders = fetchAllRows(
    "SELECT o.id, o.total, o.payment_method, o.created_at,
            $servedBySelect
            $customerExpression AS customer_name
     FROM orders o
     LEFT JOIN users u ON u.id = o.user_id
     $servedByJoin
     WHERE o.payment_method = 'cash'
     ORDER BY o.created_at DESC
     LIMIT 8"
);

// Line items for the recent POS orders (drives the itemized rows + receipt modal)
$recentOrderIds = array_map('intval', array_column($recentOrders, 'id'));
$itemsByOrder = [];
if ($recentOrderIds) {
    $ph = implode(',', array_fill(0, count($recentOrderIds), '?'));
    foreach (fetchAllRows(
        "SELECT oi.order_id, oi.quantity, oi.price,
                COALESCE(p.name, CONCAT('Product #', oi.product_id)) AS product_name
         FROM order_items oi
         LEFT JOIN products p ON p.id = oi.product_id
         WHERE oi.order_id IN ($ph)
         ORDER BY oi.id",
        $recentOrderIds
    ) as $item) {
        $itemsByOrder[(int)$item['order_id']][] = $item;
    }
}

// Receipt modal payload
$posReceipts = [];
foreach ($recentOrders as $order) {
    $oid = (int)$order['id'];
    $posReceipts[$oid] = [
        'id'       => $oid,
        'date'     => date('M j, Y g:i A', strtotime($order['created_at'])),
        'customer' => $order['customer_name'],
        'payment'  => strtoupper(str_replace('_', ' ', (string)$order['payment_method'])),
        'servedBy' => $order['served_by_name'] ?: '—',
        'total'    => formatPrice((float)$order['total']),
        'items'    => array_map(static fn(array $it) => [
            'name'     => $it['product_name'],
            'qty'      => (int)$it['quantity'],
            'price'    => formatPrice((float)$it['price']),
            'subtotal' => formatPrice((float)$it['price'] * (int)$it['quantity']),
        ], $itemsByOrder[$oid] ?? []),
    ];
}

require_once __DIR__ . '/../includes/staff-sidebar.php';
?>

<div class="mtx-shell pos-shell">
  <header class="mtx-page-head">
    <div class="mtx-page-head-copy">
      <span class="eyebrow">Staff Counter</span>
      <h1>Point of Sale</h1>
      <p>Process walk-in purchases and update inventory immediately after payment.</p>
    </div>
    <div class="pos-session">
      <span><i class="fas fa-user-tie"></i> <?= htmlspecialchars($currentStaff['name']) ?></span>
      <strong><?= htmlspecialchars(date('M j, Y')) ?></strong>
    </div>
  </header>

  <?php if ($successOrderId): ?>
    <div class="alert success">POS order #<?= $successOrderId ?> completed. Inventory has been updated.</div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="post" class="pos-layout" id="posForm">
    <?= authContextField() ?>

    <section class="mtx-card pos-products">
      <div class="mtx-card-head">
        <div>
          <h2><i class="fas fa-barcode"></i> Products</h2>
          <p><?= count($products) ?> sellable products · <?= $totalUnits ?> units available</p>
        </div>
        <div class="mtx-field-search pos-search">
          <i class="fas fa-magnifying-glass"></i>
          <input type="search" id="productSearch" placeholder="Search product, brand, category">
        </div>
      </div>

      <?php if ($products): ?>
        <div class="pos-category-rail" id="categoryRail" aria-label="Product categories">
          <button type="button" class="active" data-category-filter="">All</button>
          <?php foreach ($categoryNames as $categoryName): ?>
            <button type="button" data-category-filter="<?= htmlspecialchars(strtolower($categoryName), ENT_QUOTES, 'UTF-8') ?>">
              <?= htmlspecialchars($categoryName) ?>
            </button>
          <?php endforeach; ?>
        </div>
        <div class="pos-product-grid" id="productGrid">
          <?php foreach ($products as $product): ?>
            <?php
              $productName = (string)$product['name'];
              $brand = (string)($product['brand'] ?? '');
              $category = (string)($product['category_name'] ?? 'Uncategorized');
              $searchText = strtolower($productName . ' ' . $brand . ' ' . $category);
              $stock = (int)$product['stock'];
              $stockClass = $stock <= (int)($product['min_stock'] ?? 5) ? 'low' : 'ok';
            ?>
            <button
              type="button"
              class="pos-product"
              data-id="<?= (int)$product['id'] ?>"
              data-name="<?= htmlspecialchars($productName, ENT_QUOTES, 'UTF-8') ?>"
              data-price="<?= htmlspecialchars((string)$product['price'], ENT_QUOTES, 'UTF-8') ?>"
              data-stock="<?= $stock ?>"
              data-search="<?= htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8') ?>"
              data-category="<?= htmlspecialchars(strtolower($category), ENT_QUOTES, 'UTF-8') ?>"
            >
              <span class="pos-product-add"><i class="fas fa-plus"></i></span>
              <span class="pos-product-main">
                <strong><?= htmlspecialchars($productName) ?></strong>
                <small><?= htmlspecialchars(trim($brand . ($brand ? ' / ' : '') . $category)) ?></small>
                <em>Add to sale</em>
              </span>
              <span class="pos-product-meta">
                <b><?= formatPrice((float)$product['price']) ?></b>
                <small class="<?= $stockClass ?>"><?= $stock ?> in stock</small>
              </span>
              <span class="pos-product-count" data-count-for="<?= (int)$product['id'] ?>" hidden>0</span>
            </button>
          <?php endforeach; ?>
        </div>
        <div class="pos-no-results" id="productNoResults" hidden>
          <i class="fas fa-magnifying-glass"></i>
          <strong>No products found.</strong>
          <span>Try another search or category.</span>
        </div>
      <?php else: ?>
        <div class="mtx-empty">
          <i class="fas fa-box-open"></i>
          <strong>No sellable products.</strong>
          <span>Add stock in Products before using the POS.</span>
        </div>
      <?php endif; ?>
    </section>

    <aside class="mtx-card pos-cart">
      <div class="mtx-card-head">
        <div>
          <h2><i class="fas fa-cash-register"></i> Current Sale</h2>
          <p>Walk-in customer details are optional.</p>
        </div>
        <button type="button" class="pos-clear" id="clearSaleBtn" title="Clear sale">
          <i class="fas fa-rotate-left"></i>
        </button>
      </div>

      <div class="pos-panel-section">
        <div class="pos-section-title">
          <span><i class="fas fa-user"></i> Customer</span>
          <small>Optional</small>
        </div>
        <div class="pos-customer-grid">
          <label class="mtx-field">
            <span>Name</span>
            <input type="text" name="customer_name" placeholder="Walk-in Customer">
          </label>
          <label class="mtx-field">
            <span>Phone</span>
            <input type="text" name="customer_phone" placeholder="Optional">
          </label>
          <label class="mtx-field pos-full">
            <span>Email</span>
            <input type="email" name="customer_email" placeholder="Optional">
          </label>
        </div>
      </div>

      <div class="pos-panel-section">
        <div class="pos-section-title">
          <span><i class="fas fa-basket-shopping"></i> Cart</span>
          <small id="cartMiniCount">0 items</small>
        </div>
        <div class="pos-cart-list" id="cartList">
          <div class="pos-cart-empty">
            <i class="fas fa-basket-shopping"></i>
            <strong>Cart is empty</strong>
          </div>
        </div>
      </div>

      <div class="pos-summary">
        <div><span>Items</span><strong id="itemCountText">0 items</strong></div>
        <div><span>Subtotal</span><strong id="subtotalText">PHP 0.00</strong></div>
        <div class="pos-summary-total"><span>Total</span><strong id="totalText">PHP 0.00</strong></div>
      </div>

      <input type="hidden" name="payment_method" id="paymentMethod" value="cash">
      <div class="pos-panel-section">
        <div class="pos-section-title">
          <span><i class="fas fa-wallet"></i> Payment</span>
          <small>Cash only</small>
        </div>
        <div class="pos-cash-badge">
          <i class="fas fa-money-bill-wave"></i>
          <span>Cash payment</span>
        </div>

        <div class="pos-payment-grid" id="cashPanel">
          <label class="mtx-field">
            <span>Cash Tendered</span>
            <input type="number" name="amount_tendered" id="amountTendered" min="0" step="0.01" value="0">
          </label>
          <div class="pos-quick-cash">
            <button type="button" data-tender="exact">Exact</button>
            <button type="button" data-tender="50">PHP 50</button>
            <button type="button" data-tender="100">PHP 100</button>
            <button type="button" data-tender="500">PHP 500</button>
            <button type="button" data-tender="1000">PHP 1000</button>
          </div>
        </div>
      </div>

      <div class="pos-change">
        <span>Change</span>
        <strong id="changeText">PHP 0.00</strong>
      </div>

      <label class="mtx-field">
        <span>Notes</span>
        <textarea name="notes" rows="2" placeholder="Optional sale note"></textarea>
      </label>

      <button type="submit" class="mtx-btn mtx-btn--dark pos-checkout" id="checkoutBtn" disabled>
        <i class="fas fa-check"></i> <span id="checkoutLabel">Complete Sale</span>
      </button>
      <div class="pos-checkout-hint" id="checkoutHint">Add products to start a sale.</div>
    </aside>
  </form>

  <section class="mtx-card mtx-card--flush">
    <div class="mtx-card-head">
      <div>
        <h2><i class="fas fa-clock-rotate-left"></i> Recent POS Sales</h2>
        <p>Latest counter transactions processed by staff.</p>
      </div>
    </div>
    <?php if ($recentOrders): ?>
      <div class="mtx-table-wrap">
        <table class="mtx-table">
          <thead>
            <tr>
              <th>Order</th>
              <th>Customer</th>
              <th>Payment</th>
              <th>Purchased Items</th>
              <th class="num">Total</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentOrders as $order): ?>
              <?php
                $oid = (int)$order['id'];
                $lineItems = $itemsByOrder[$oid] ?? [];
                $shownItems = array_slice($lineItems, 0, 2);
                $moreItems = count($lineItems) - count($shownItems);
              ?>
              <tr data-pos-receipt="<?= $oid ?>" role="button" tabindex="0" aria-haspopup="dialog">
                <td>
                  <div class="mtx-cell-main">
                    <strong>#<?= $oid ?></strong>
                    <span class="mtx-cell-sub"><?= htmlspecialchars(date('M j, Y g:i A', strtotime($order['created_at']))) ?></span>
                  </div>
                </td>
                <td><?= htmlspecialchars($order['customer_name']) ?></td>
                <td><?= htmlspecialchars(strtoupper(str_replace('_', ' ', (string)$order['payment_method']))) ?></td>
                <td>
                  <div class="mtx-cell-main">
                    <?php if ($shownItems): ?>
                      <?php foreach ($shownItems as $item): ?>
                        <span style="font-size:.82rem;">
                          <?= htmlspecialchars($item['product_name']) ?>
                          <span class="mtx-cell-sub">&times;<?= (int)$item['quantity'] ?> @ <?= formatPrice((float)$item['price']) ?></span>
                        </span>
                      <?php endforeach; ?>
                      <?php if ($moreItems > 0): ?>
                        <span class="mtx-cell-sub">+<?= $moreItems ?> more product<?= $moreItems === 1 ? '' : 's' ?> — view details</span>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="mtx-cell-sub">No items recorded</span>
                    <?php endif; ?>
                  </div>
                </td>
                <td class="num"><span class="mtx-money"><?= formatPrice((float)$order['total']) ?></span></td>
                <td class="num"><button type="button" class="mtx-btn mtx-btn--ghost mtx-btn--sm" data-pos-receipt-btn="<?= $oid ?>"><i class="fas fa-eye"></i> View Details</button></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div style="padding:24px;">
        <div class="mtx-empty">
          <i class="fas fa-receipt"></i>
          <strong>No POS sales yet.</strong>
          <span>Completed counter sales will appear here.</span>
        </div>
      </div>
    <?php endif; ?>
  </section>
</div>

<!-- POS receipt modal -->
<div class="mtx-modal" id="posReceiptModal" aria-hidden="true">
  <div class="mtx-modal__backdrop" data-close-modal></div>
  <div class="mtx-modal__dialog">
    <button type="button" class="mtx-modal__close" data-close-modal aria-label="Close">&times;</button>
    <h2 class="mtx-modal__title" id="rcTitle">POS Order</h2>
    <p class="mtx-modal__meta" id="rcMeta"></p>

    <div class="mtx-modal-section">
      <h3>Transaction</h3>
      <div class="mtx-kv-grid">
        <div class="mtx-kv"><span>Customer</span><strong id="rcCustomer"></strong></div>
        <div class="mtx-kv"><span>Payment Method</span><strong id="rcPayment"></strong></div>
        <div class="mtx-kv"><span>Date &amp; Time</span><strong id="rcDate"></strong></div>
        <div class="mtx-kv"><span>Processed By</span><strong id="rcServedBy"></strong></div>
      </div>
    </div>

    <div class="mtx-modal-section">
      <h3>Purchased Items</h3>
      <div class="mtx-line-rows" id="rcItems"></div>
      <div class="mtx-total-row"><span>Grand Total</span><span id="rcTotal"></span></div>
    </div>
  </div>
</div>

<script type="application/json" id="posReceiptsData"><?= json_encode($posReceipts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?></script>

<script>
(function () {
  var receipts = {};
  try { receipts = JSON.parse(document.getElementById('posReceiptsData').textContent || '{}'); } catch (e) { receipts = {}; }
  var modal = document.getElementById('posReceiptModal');

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }
  function setText(id, v) { document.getElementById(id).textContent = v || '—'; }

  function openReceipt(id) {
    var r = receipts[id];
    if (!r) return;
    setText('rcTitle', 'POS Order #' + r.id);
    document.getElementById('rcMeta').textContent = r.date + ' · ' + r.payment;
    setText('rcCustomer', r.customer);
    setText('rcPayment', r.payment);
    setText('rcDate', r.date);
    setText('rcServedBy', r.servedBy);
    setText('rcTotal', r.total);

    var wrap = document.getElementById('rcItems');
    wrap.innerHTML = '';
    if (!r.items.length) {
      wrap.innerHTML = '<div class="mtx-line-row"><span class="mtx-cell-sub">No items recorded for this order.</span></div>';
    } else {
      r.items.forEach(function (it) {
        var row = document.createElement('div');
        row.className = 'mtx-line-row';
        row.innerHTML = '<span><i class="fas fa-box" style="color:#d97706;"></i>' + esc(it.name) +
          ' <span class="mtx-cell-sub">&times;' + it.qty + ' @ ' + esc(it.price) + '</span></span>' +
          '<strong class="mtx-money">' + esc(it.subtotal) + '</strong>';
        wrap.appendChild(row);
      });
    }
    modal.classList.add('is-open');
  }

  document.querySelectorAll('[data-pos-receipt-btn]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      openReceipt(btn.getAttribute('data-pos-receipt-btn'));
    });
  });
  document.querySelectorAll('[data-pos-receipt]').forEach(function (row) {
    row.addEventListener('click', function () { openReceipt(row.getAttribute('data-pos-receipt')); });
    row.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openReceipt(row.getAttribute('data-pos-receipt')); }
    });
  });
  modal.querySelectorAll('[data-close-modal]').forEach(function (el) {
    el.addEventListener('click', function () { modal.classList.remove('is-open'); });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') modal.classList.remove('is-open');
  });
})();
</script>

<script>
(function(){
  var cart = {};
  var grid = document.getElementById('productGrid');
  var search = document.getElementById('productSearch');
  var productNoResults = document.getElementById('productNoResults');
  var categoryRail = document.getElementById('categoryRail');
  var cartList = document.getElementById('cartList');
  var cartMiniCount = document.getElementById('cartMiniCount');
  var checkoutBtn = document.getElementById('checkoutBtn');
  var checkoutLabel = document.getElementById('checkoutLabel');
  var checkoutHint = document.getElementById('checkoutHint');
  var paymentMethod = document.getElementById('paymentMethod');
  var amountTendered = document.getElementById('amountTendered');
  var cashPanel = document.getElementById('cashPanel');
  var clearSaleBtn = document.getElementById('clearSaleBtn');
  var activeCategory = '';

  function normalizeText(value) {
    return String(value || '').toLowerCase().replace(/\s+/g, ' ').trim();
  }

  function money(value) {
    return 'PHP ' + Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function total() {
    return Object.keys(cart).reduce(function(sum, id) {
      return sum + (cart[id].price * cart[id].qty);
    }, 0);
  }

  function itemCount() {
    return Object.keys(cart).reduce(function(sum, id) {
      return sum + cart[id].qty;
    }, 0);
  }

  function refreshProductStates() {
    if (!grid) return;
    grid.querySelectorAll('.pos-product').forEach(function(button) {
      var id = button.getAttribute('data-id');
      var item = cart[id];
      var count = button.querySelector('[data-count-for]');
      button.classList.toggle('selected', !!item);
      if (count) {
        count.hidden = !item;
        count.textContent = item ? item.qty : '0';
      }
    });
  }

  function filterProducts() {
    if (!grid) return;
    var term = normalizeText(search ? search.value : '');
    var visibleCount = 0;
    grid.querySelectorAll('.pos-product').forEach(function(button) {
      var matchesSearch = term === '' || normalizeText(button.getAttribute('data-search')).includes(term);
      var matchesCategory = activeCategory === '' || normalizeText(button.getAttribute('data-category')) === activeCategory;
      var visible = matchesSearch && matchesCategory;
      button.hidden = !visible;
      if (visible) visibleCount += 1;
    });
    if (productNoResults) {
      productNoResults.hidden = visibleCount !== 0;
    }
  }

  function renderCart() {
    var ids = Object.keys(cart);
    if (!ids.length) {
      cartList.innerHTML = '<div class="pos-cart-empty"><i class="fas fa-basket-shopping"></i><strong>Cart is empty</strong></div>';
    } else {
      cartList.innerHTML = ids.map(function(id, index) {
        var item = cart[id];
        return '<div class="pos-cart-row">' +
          '<input type="hidden" name="qty[' + id + ']" value="' + item.qty + '">' +
          '<span class="pos-cart-index">' + (index + 1) + '</span>' +
          '<div class="pos-cart-name"><strong>' + escapeHtml(item.name) + '</strong><small>' + money(item.price) + ' each / ' + item.stock + ' stock</small></div>' +
          '<div class="pos-cart-qty">' +
            '<button type="button" data-dec="' + id + '"><i class="fas fa-minus"></i></button>' +
            '<span>' + item.qty + '</span>' +
            '<button type="button" data-inc="' + id + '"><i class="fas fa-plus"></i></button>' +
          '</div>' +
          '<strong class="pos-line-total">' + money(item.price * item.qty) + '</strong>' +
          '<button type="button" class="pos-remove" data-remove="' + id + '" title="Remove"><i class="fas fa-xmark"></i></button>' +
        '</div>';
      }).join('');
    }

    var currentTotal = total();
    var currentItemCount = itemCount();
    document.getElementById('itemCountText').textContent = currentItemCount + (currentItemCount === 1 ? ' item' : ' items');
    if (cartMiniCount) cartMiniCount.textContent = currentItemCount + (currentItemCount === 1 ? ' item' : ' items');
    document.getElementById('subtotalText').textContent = money(currentTotal);
    document.getElementById('totalText').textContent = money(currentTotal);
    var tendered = parseFloat(amountTendered.value || '0');
    paymentMethod.value = 'cash';
    document.getElementById('changeText').textContent = money(Math.max(0, tendered - currentTotal));
    if (checkoutLabel) checkoutLabel.textContent = 'Complete Sale / ' + money(currentTotal);
    if (cashPanel) cashPanel.hidden = false;
    amountTendered.disabled = false;
    var blocked = !ids.length || tendered < currentTotal;
    checkoutBtn.disabled = blocked;
    if (!ids.length) {
      checkoutHint.textContent = 'Add products to start a sale.';
    } else if (tendered < currentTotal) {
      checkoutHint.textContent = 'Enter enough cash tendered to complete this sale.';
    } else {
      checkoutHint.textContent = 'Ready to complete. Stock will update immediately.';
    }
    refreshProductStates();
  }

  function escapeHtml(text) {
    return String(text).replace(/[&<>"']/g, function(ch) {
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]);
    });
  }

  if (grid) {
    grid.addEventListener('click', function(event) {
      var button = event.target.closest('.pos-product');
      if (!button) return;
      var id = button.getAttribute('data-id');
      var stock = parseInt(button.getAttribute('data-stock'), 10);
      if (!cart[id]) {
        cart[id] = {
          name: button.getAttribute('data-name'),
          price: parseFloat(button.getAttribute('data-price')),
          stock: stock,
          qty: 0
        };
      }
      if (cart[id].qty < stock) {
        cart[id].qty += 1;
      }
      renderCart();
    });
  }

  cartList.addEventListener('click', function(event) {
    var inc = event.target.closest('[data-inc]');
    var dec = event.target.closest('[data-dec]');
    var remove = event.target.closest('[data-remove]');
    var id = inc ? inc.getAttribute('data-inc') : dec ? dec.getAttribute('data-dec') : remove ? remove.getAttribute('data-remove') : '';
    if (!id || !cart[id]) return;
    if (inc && cart[id].qty < cart[id].stock) cart[id].qty += 1;
    if (dec) cart[id].qty -= 1;
    if (remove || cart[id].qty <= 0) delete cart[id];
    renderCart();
  });

  if (search && grid) {
    search.addEventListener('input', filterProducts);
  }

  if (categoryRail) {
    categoryRail.addEventListener('click', function(event) {
      var button = event.target.closest('[data-category-filter]');
      if (!button) return;
      activeCategory = normalizeText(button.getAttribute('data-category-filter'));
      categoryRail.querySelectorAll('button').forEach(function(item) {
        item.classList.toggle('active', item === button);
      });
      filterProducts();
    });
  }

  document.querySelectorAll('[data-tender]').forEach(function(button) {
    button.addEventListener('click', function() {
      var value = button.getAttribute('data-tender');
      if (value === 'exact') {
        amountTendered.value = total().toFixed(2);
      } else {
        amountTendered.value = parseFloat(value).toFixed(2);
      }
      renderCart();
    });
  });

  if (clearSaleBtn) {
    clearSaleBtn.addEventListener('click', function() {
      cart = {};
      amountTendered.value = '0';
      renderCart();
    });
  }

  amountTendered.addEventListener('input', renderCart);
  renderCart();
})();
</script>

<?= authContextScriptTag() ?>
</main></div></div></body></html>
