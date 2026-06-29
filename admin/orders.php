<?php
$pageTitle = 'Orders';
require_once __DIR__ . '/../includes/admin-sidebar.php';
require_once __DIR__ . '/../includes/db.php';

$validStatuses = ['pending', 'processing', 'completed', 'cancelled'];

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
        redirect(baseUrl('admin/orders.php'));
    }
}

$flash    = getFlash('orders_success');
$flashErr = getFlash('orders_error');

// Filters
$statusFilter = $_GET['status'] ?? '';
$statusFilter = in_array($statusFilter, $validStatuses, true) ? $statusFilter : '';
$search       = trim($_GET['q'] ?? '');
$dateFrom     = trim($_GET['date_from'] ?? '');
$dateTo       = trim($_GET['date_to'] ?? '');

$where  = [];
$params = [];
if ($statusFilter !== '') {
    $where[]  = 'o.status = ?';
    $params[] = $statusFilter;
}
if ($search !== '') {
    $where[]  = '(u.name LIKE ? OR u.email LIKE ? OR o.id = ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = (int)$search;
}
if ($dateFrom !== '') {
    $where[]  = 'DATE(o.created_at) >= ?';
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $where[]  = 'DATE(o.created_at) <= ?';
    $params[] = $dateTo;
}

$orders = fetchAllRows(
    "SELECT
        o.*,
        COALESCE(u.name, 'Guest') AS customer_name,
        COALESCE(u.email, '') AS customer_email,
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
$filteredRevenue = array_sum(array_column(
    array_filter($orders, fn($o) => $o['payment_status'] === 'paid'),
    'total'
));
?>

<section class="admin-hero">
  <div>
    <span class="eyebrow">Admin</span>
    <h1>Orders</h1>
    <p>Review purchases, payment status, items, and fulfillment.</p>
  </div>
</section>

<section class="metric-grid" style="margin-bottom:18px;">
  <article><span>Showing</span><strong><?= count($orders) ?></strong><i class="fas fa-list"></i></article>
  <article><span>Revenue (filtered)</span><strong><?= formatPrice($filteredRevenue) ?></strong><i class="fas fa-peso-sign"></i></article>
  <article><span>Paid</span><strong><?= count(array_filter($orders, fn($o)=>$o['payment_status']==='paid')) ?></strong><i class="fas fa-check-circle"></i></article>
  <article><span>Pending</span><strong><?= count(array_filter($orders, fn($o)=>$o['status']==='pending')) ?></strong><i class="fas fa-clock"></i></article>
</section>

<section class="admin-card admin-page-stack">
  <div class="admin-page-head">
    <div>
      <h2>All Orders</h2>
    </div>
    <form method="get" class="admin-inline-form" style="flex-wrap:wrap;gap:8px;">
      <input type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Name, email or #ID">
      <select name="status">
        <option value="">All statuses</option>
        <?php foreach ($validStatuses as $s): ?>
          <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" title="From date" style="font-size:.85rem;">
      <input type="date" name="date_to"   value="<?= htmlspecialchars($dateTo) ?>"   title="To date"   style="font-size:.85rem;">
      <button type="submit" class="btn btn-outline">Filter</button>
      <?php if ($search || $statusFilter || $dateFrom || $dateTo): ?>
        <a class="btn btn-outline" href="<?= baseUrl('admin/orders.php') ?>">Reset</a>
      <?php endif; ?>
    </form>
  </div>

  <?php if ($flash): ?><div class="alert success"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
  <?php if ($flashErr): ?><div class="alert error"><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>

  <?php if ($orders): ?>
    <div class="admin-table-wrap">
      <table class="admin-data-table">
        <thead>
          <tr>
            <th>Order</th><th>Customer</th><th>Items</th><th>Payment</th><th>Total</th><th>Status</th><th>Update</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $order): ?>
            <?php
              $orderId = (int)$order['id'];
              $sc = ['pending'=>'#6b7280','processing'=>'#d97706','completed'=>'#15803d','cancelled'=>'#b91c1c'][$order['status']] ?? '#6b7280';
            ?>
            <tr>
              <td>
                <strong>#<?= $orderId ?></strong>
                <div class="subtext"><?= htmlspecialchars(date('M j, Y g:i A', strtotime($order['created_at']))) ?></div>
              </td>
              <td>
                <strong><?= htmlspecialchars($order['customer_name']) ?></strong>
                <?php if ($order['customer_email']): ?><div class="subtext"><?= htmlspecialchars($order['customer_email']) ?></div><?php endif; ?>
              </td>
              <td>
                <div class="stacked-lines">
                  <?php foreach ($itemsByOrder[$orderId] ?? [] as $item): ?>
                    <span><?= htmlspecialchars($item['product_name']) ?> x<?= (int)$item['quantity'] ?></span>
                  <?php endforeach; ?>
                  <?php if (empty($itemsByOrder[$orderId])): ?><span>No items</span><?php endif; ?>
                </div>
              </td>
              <td>
                <?= htmlspecialchars(ucfirst((string)$order['payment_method'])) ?>
                <div class="subtext" style="color:<?= $order['payment_status']==='paid' ? '#15803d' : '#d97706' ?>;font-weight:700;">
                  <?= strtoupper($order['payment_status'] ?? '') ?>
                </div>
              </td>
              <td><strong><?= formatPrice((float)$order['total']) ?></strong></td>
              <td><span class="status-pill" style="--status-color:<?= $sc ?>;"><?= ucfirst($order['status']) ?></span></td>
              <td>
                <form method="post" class="admin-row-form">
                  <?= authContextField() ?>
                  <input type="hidden" name="action" value="update_status">
                  <input type="hidden" name="order_id" value="<?= $orderId ?>">
                  <select name="status">
                    <?php foreach ($validStatuses as $s): ?>
                      <option value="<?= $s ?>" <?= $order['status']===$s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" class="btn btn-outline">Save</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div style="padding:12px 24px;color:var(--muted);font-size:.85rem;border-top:1px solid var(--line);">
      Showing <?= count($orders) ?> order<?= count($orders)!==1?'s':'' ?>
      <?php if ($filteredRevenue > 0): ?> &mdash; <?= formatPrice($filteredRevenue) ?> in paid revenue<?php endif; ?>
    </div>
  <?php else: ?>
    <p class="empty-note">No orders match your filters.</p>
  <?php endif; ?>
</section>

<?= authContextScriptTag() ?>
</main></div></div></body></html>
