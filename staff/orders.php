<?php
$pageTitle = 'Orders';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/PosService.php';
requireStaff();
$currentUser = getCurrentUser();

$validStatuses = ['pending', 'processing', 'completed', 'cancelled'];

// Staff operational permission: update fulfillment status of online orders.
// (POS orders are finalized at the counter and are never editable here.)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $orderId = (int)($_POST['order_id'] ?? 0);

    if ($action === 'update_status' && $orderId > 0) {
        $status = $_POST['status'] ?? '';
        if (in_array($status, $validStatuses, true)) {
            getDB()->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$status, $orderId]);
            flashMessage('orders_success', 'Order status updated.');
        } else {
            flashMessage('orders_error', 'Invalid order status.');
        }
        redirect(baseUrl('staff/orders.php'));
    }
}

$flash    = getFlash('orders_success');
$flashErr = getFlash('orders_error');

// Channel tabs: online shop checkouts vs in-store POS sales (same rules as Admin Orders).
$channel = $_GET['channel'] ?? 'online';
$channel = in_array($channel, ['online', 'pos'], true) ? $channel : 'online';
$posCondition = "(o.payment_reference LIKE 'POS-%' OR o.payment_method IN ('cash','gcash','card','bank_transfer','other'))";

// Filters
$statusFilter = $_GET['status'] ?? '';
$statusFilter = in_array($statusFilter, $validStatuses, true) ? $statusFilter : '';
$search       = trim($_GET['q'] ?? '');
$dateFrom     = trim($_GET['date_from'] ?? '');
$dateTo       = trim($_GET['date_to'] ?? '');

$where  = [];
$params = [];
$where[] = $channel === 'pos' ? $posCondition : "NOT $posCondition";
if ($statusFilter !== '') {
    $where[]  = 'o.status = ?';
    $params[] = $statusFilter;
}
if ($search !== '') {
    $walkInSearch = posColumnExists('orders', 'walk_in_customer_name') ? ' OR o.walk_in_customer_name LIKE ? OR o.walk_in_customer_phone LIKE ? OR o.walk_in_customer_email LIKE ?' : '';
    $where[]  = '(u.name LIKE ? OR u.email LIKE ? OR o.id = ?' . $walkInSearch . ')';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = (int)$search;
    if ($walkInSearch !== '') {
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
}
if ($dateFrom !== '') {
    $where[]  = 'DATE(o.created_at) >= ?';
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $where[]  = 'DATE(o.created_at) <= ?';
    $params[] = $dateTo;
}

$customerFallback = $channel === 'pos' ? 'Walk-in Customer' : 'Guest';
$customerNameExpression = posColumnExists('orders', 'walk_in_customer_name')
    ? "COALESCE(NULLIF(o.walk_in_customer_name, ''), u.name, '$customerFallback')"
    : "COALESCE(u.name, '$customerFallback')";
$customerEmailExpression = posColumnExists('orders', 'walk_in_customer_email')
    ? "COALESCE(NULLIF(o.walk_in_customer_email, ''), u.email, '')"
    : "COALESCE(u.email, '')";

$orders = fetchAllRows(
    "SELECT
        o.*,
        $customerNameExpression AS customer_name,
        $customerEmailExpression AS customer_email,
        COALESCE(ic.item_count, 0) AS item_count
     FROM orders o
     LEFT JOIN users u ON u.id = o.user_id
     LEFT JOIN (
       SELECT order_id, COUNT(*) AS item_count
       FROM order_items GROUP BY order_id
     ) ic ON ic.order_id = o.id
     " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
     ORDER BY o.created_at DESC",
    $params
);

$orderItems = fetchAllRows(
    "SELECT oi.order_id, oi.quantity, oi.price,
            COALESCE(p.name, CONCAT('Product #', oi.product_id)) AS product_name
     FROM order_items oi
     LEFT JOIN products p ON p.id = oi.product_id
     ORDER BY oi.id"
);
$itemsByOrder = [];
foreach ($orderItems as $item) {
    $itemsByOrder[(int)$item['order_id']][] = $item;
}

// Totals for filtered results
$paidOrders      = array_filter($orders, fn($o) => $o['payment_status'] === 'paid');
$filteredRevenue = array_sum(array_column($paidOrders, 'total'));
$paidCount       = count($paidOrders);
$pendingCount    = count(array_filter($orders, fn($o) => $o['status'] === 'pending'));

$statusColor = ['pending'=>'#6b7280','processing'=>'#d97706','completed'=>'#15803d','cancelled'=>'#b91c1c'];
$hasFilters = $search || $statusFilter || $dateFrom || $dateTo;

require_once __DIR__ . '/../includes/staff-sidebar.php';
?>

<div class="mtx-shell">

  <!-- Header -->
  <header class="mtx-page-head">
    <div class="mtx-page-head-copy">
      <span class="eyebrow">Staff Panel</span>
      <h1>Orders</h1>
      <p>Review purchases, payment status, items, and fulfillment across the online shop and in-store counter.</p>
    </div>
    <div class="mtx-head-actions">
      <a href="<?= baseUrl('staff/pos.php') ?>" class="mtx-btn mtx-btn--primary"><i class="fas fa-cash-register"></i> Open POS</a>
    </div>
  </header>

  <?php if ($flash): ?><div class="alert success"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
  <?php if ($flashErr): ?><div class="alert error"><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>

  <!-- Channel tabs -->
  <div class="mtx-seg mtx-seg--card" role="tablist" aria-label="Order channels">
    <a href="<?= baseUrl('staff/orders.php?channel=online') ?>" class="<?= $channel === 'online' ? 'active' : '' ?>" role="tab"><i class="fas fa-globe"></i>Online Shop Purchases</a>
    <a href="<?= baseUrl('staff/orders.php?channel=pos') ?>" class="<?= $channel === 'pos' ? 'active' : '' ?>" role="tab"><i class="fas fa-cash-register"></i>In-Store Purchases (POS)</a>
  </div>

  <!-- Summary cards -->
  <section class="mtx-kpi-grid mtx-kpi-grid--3" aria-label="Order summary">
    <article class="mtx-kpi" style="--kpi-color:#111317;">
      <div class="mtx-kpi-top">
        <span class="mtx-kpi-label"><?= $channel === 'pos' ? 'In-Store Orders' : 'Online Orders' ?><?= $hasFilters ? ' (filtered)' : '' ?></span>
        <span class="mtx-kpi-icon"><i class="fas <?= $channel === 'pos' ? 'fa-cash-register' : 'fa-globe' ?>"></i></span>
      </div>
      <span class="mtx-kpi-value"><?= count($orders) ?></span>
      <span class="mtx-kpi-sub"><?= $channel === 'pos' ? 'Finalized counter sales' : $pendingCount . ' pending fulfillment' ?></span>
    </article>
    <article class="mtx-kpi" style="--kpi-color:#15803d;">
      <div class="mtx-kpi-top">
        <span class="mtx-kpi-label">Paid Revenue</span>
        <span class="mtx-kpi-icon"><i class="fas fa-peso-sign"></i></span>
      </div>
      <span class="mtx-kpi-value" style="color:#15803d;"><?= formatPrice($filteredRevenue) ?></span>
      <span class="mtx-kpi-sub"><?= $hasFilters ? 'Within current filters' : 'All time' ?></span>
    </article>
    <article class="mtx-kpi" style="--kpi-color:#2563eb;">
      <div class="mtx-kpi-top">
        <span class="mtx-kpi-label">Paid Orders</span>
        <span class="mtx-kpi-icon"><i class="fas fa-check-circle"></i></span>
      </div>
      <span class="mtx-kpi-value"><?= $paidCount ?></span>
      <span class="mtx-kpi-sub">of <?= count($orders) ?> shown</span>
    </article>
  </section>

  <!-- Orders table -->
  <section class="mtx-card mtx-card--flush">
    <div class="mtx-card-head">
      <div>
        <h2><i class="fas <?= $channel === 'pos' ? 'fa-cash-register' : 'fa-receipt' ?>"></i> <?= $channel === 'pos' ? 'In-Store Purchases (POS)' : 'Online Shop Purchases' ?></h2>
        <p><?= $channel === 'pos' ? 'Newest first. POS sales are finalized at the counter — statuses are not editable.' : 'Newest first. Update fulfillment status inline.' ?></p>
      </div>
      <form method="get" class="mtx-toolbar">
        <input type="hidden" name="channel" value="<?= htmlspecialchars($channel) ?>">
        <div class="mtx-field-search">
          <i class="fas fa-magnifying-glass"></i>
          <input type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Name, email or #ID">
        </div>
        <?php if ($channel === 'online'): ?>
          <select name="status">
            <option value="">All statuses</option>
            <?php foreach ($validStatuses as $s): ?>
              <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>
        <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" title="From date">
        <input type="date" name="date_to"   value="<?= htmlspecialchars($dateTo) ?>"   title="To date">
        <button type="submit" class="mtx-btn mtx-btn--dark">Filter</button>
        <?php if ($hasFilters): ?>
          <a class="mtx-btn mtx-btn--ghost" href="<?= baseUrl('staff/orders.php?channel=' . $channel) ?>">Reset</a>
        <?php endif; ?>
      </form>
    </div>

    <?php if ($orders): ?>
      <div class="mtx-table-wrap">
        <table class="mtx-table">
          <thead>
            <tr>
              <th>Order</th>
              <th>Customer</th>
              <th>Items</th>
              <th>Payment</th>
              <th class="num">Total</th>
              <th>Status</th>
              <?php if ($channel === 'online'): ?><th>Update</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($orders as $order): ?>
              <?php
                $orderId = (int)$order['id'];
                $sc = $statusColor[$order['status']] ?? '#6b7280';
                $isPaid = ($order['payment_status'] ?? '') === 'paid';
              ?>
              <tr>
                <td>
                  <div class="mtx-cell-main">
                    <strong>#<?= $orderId ?></strong>
                    <span class="mtx-cell-sub"><?= htmlspecialchars(date('M j, Y g:i A', strtotime($order['created_at']))) ?></span>
                  </div>
                </td>
                <td>
                  <div class="mtx-cell-main">
                    <strong><?= htmlspecialchars($order['customer_name']) ?></strong>
                    <?php if ($order['customer_email']): ?><span class="mtx-cell-sub"><?= htmlspecialchars($order['customer_email']) ?></span><?php endif; ?>
                  </div>
                </td>
                <td>
                  <div class="mtx-cell-main">
                    <?php foreach ($itemsByOrder[$orderId] ?? [] as $item): ?>
                      <span style="font-size:.82rem;"><?= htmlspecialchars($item['product_name']) ?> <span class="mtx-cell-sub">&times;<?= (int)$item['quantity'] ?></span></span>
                    <?php endforeach; ?>
                    <?php if (empty($itemsByOrder[$orderId])): ?><span class="mtx-cell-sub">No items</span><?php endif; ?>
                  </div>
                </td>
                <td>
                  <div class="mtx-cell-main">
                    <span class="mtx-pill" style="--pill-color:<?= $isPaid ? '#15803d' : '#d97706' ?>;">
                      <i class="fas <?= $isPaid ? 'fa-check' : 'fa-hourglass-half' ?>"></i>
                      <?= strtoupper($order['payment_status'] ?? 'UNPAID') ?>
                    </span>
                    <span class="mtx-cell-sub"><?= htmlspecialchars(ucfirst((string)$order['payment_method'])) ?></span>
                  </div>
                </td>
                <td class="num"><span class="mtx-money"><?= formatPrice((float)$order['total']) ?></span></td>
                <td><span class="mtx-pill" style="--pill-color:<?= $sc ?>;"><?= ucfirst($order['status']) ?></span></td>
                <?php if ($channel === 'online'): ?>
                <td>
                  <form method="post" class="admin-row-form" style="flex-wrap:nowrap;">
                    <?= authContextField() ?>
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="order_id" value="<?= $orderId ?>">
                    <select name="status" style="min-width:120px;height:34px;padding:0 8px;border-radius:8px;font-size:.82rem;">
                      <?php foreach ($validStatuses as $s): ?>
                        <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button type="submit" class="mtx-btn mtx-btn--ghost mtx-btn--sm">Save</button>
                  </form>
                </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="mtx-card-foot">
        <span>Showing <?= count($orders) ?> order<?= count($orders) !== 1 ? 's' : '' ?><?= $hasFilters ? ' (filtered)' : '' ?></span>
        <?php if ($filteredRevenue > 0): ?>
          <span><strong style="color:#15803d;"><?= formatPrice($filteredRevenue) ?></strong>&nbsp;in paid revenue</span>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div style="padding:24px;">
        <div class="mtx-empty">
          <i class="fas fa-receipt"></i>
          <strong>No orders match your filters.</strong>
          <span>Try widening the date range or clearing the search.</span>
        </div>
      </div>
    <?php endif; ?>
  </section>

</div><!-- /.mtx-shell -->

<?= authContextScriptTag() ?>
</main></div></div></body></html>
