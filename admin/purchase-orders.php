<?php
$pageTitle = 'Purchase Orders';
require_once __DIR__ . '/../includes/admin-sidebar.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/PurchaseOrderService.php';

$currentAdmin = getCurrentUser();

// --- POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $poId   = (int)($_POST['po_id'] ?? 0);

    if ($action === 'auto_generate') {
        $result = poAutoGenerate();
        if ($result['items'] > 0) {
            flashMessage('po_success',
                "Auto-generated {$result['created']} new PO(s), updated {$result['updated']} existing draft(s) — {$result['items']} line item(s) total."
            );
        } else {
            flashMessage('po_success', 'No low-stock products without an open PO. Everything is adequately stocked!');
        }
        redirect(baseUrl('admin/purchase-orders.php'));
    }

    if ($action === 'approve' && $poId > 0) {
        $ok = poApprove($poId, (int)$currentAdmin['id']);
        flashMessage($ok ? 'po_success' : 'po_error', $ok ? "PO #$poId approved." : "Could not approve PO #$poId.");
        redirect(baseUrl('admin/purchase-orders.php?id=' . $poId));
    }

    if ($action === 'mark_ordered' && $poId > 0) {
        $ok = poAdvanceStatus($poId, 'ordered');
        flashMessage($ok ? 'po_success' : 'po_error', $ok ? "PO #$poId marked as Ordered." : "Could not update PO #$poId.");
        redirect(baseUrl('admin/purchase-orders.php?id=' . $poId));
    }

    if ($action === 'mark_received' && $poId > 0) {
        $ok = poAdvanceStatus($poId, 'received');
        flashMessage($ok ? 'po_success' : 'po_error', $ok ? "PO #$poId marked as Received. Stock updated." : "Could not update PO #$poId.");
        redirect(baseUrl('admin/purchase-orders.php'));
    }

    if ($action === 'cancel_po' && $poId > 0) {
        $ok = poAdvanceStatus($poId, 'cancelled');
        flashMessage($ok ? 'po_success' : 'po_error', $ok ? "PO #$poId cancelled." : "Could not cancel PO #$poId.");
        redirect(baseUrl('admin/purchase-orders.php'));
    }

    if ($action === 'save_notes' && $poId > 0) {
        $notes = trim($_POST['notes'] ?? '');
        getDB()->prepare("UPDATE purchase_orders SET notes = ? WHERE id = ?")->execute([$notes ?: null, $poId]);
        flashMessage('po_success', 'Notes saved.');
        redirect(baseUrl('admin/purchase-orders.php?id=' . $poId));
    }
}

$flash    = getFlash('po_success');
$flashErr = getFlash('po_error');

// --- Detail view ---
$detailId = (int)($_GET['id'] ?? 0);
if ($detailId > 0) {
    $po = fetchOne(
        "SELECT po.*, s.name AS supplier_name, s.email AS supplier_email, s.phone AS supplier_phone,
                u.name AS approved_by_name
         FROM purchase_orders po
         LEFT JOIN suppliers s ON s.id = po.supplier_id
         LEFT JOIN users u ON u.id = po.approved_by
         WHERE po.id = ?",
        [$detailId]
    );
    if (!$po) {
        flashMessage('po_error', "PO #$detailId not found.");
        redirect(baseUrl('admin/purchase-orders.php'));
    }
    $poItems = fetchAllRows(
        "SELECT poi.*, p.name AS product_name, p.stock AS current_stock, p.min_stock,
                cat.name AS category_name
         FROM purchase_order_items poi
         JOIN products p ON p.id = poi.product_id
         LEFT JOIN categories cat ON cat.id = p.category_id
         WHERE poi.po_id = ?
         ORDER BY cat.name, p.name",
        [$detailId]
    );
} else {
    $po = null;
}

// --- List view ---
$statusFilter = $_GET['status'] ?? '';
$validStatuses = ['draft','approved','ordered','received','cancelled'];
$params = [];
$where  = '';
if (in_array($statusFilter, $validStatuses, true)) {
    $where = 'WHERE po.status = ?';
    $params[] = $statusFilter;
}

$purchaseOrders = fetchAllRows(
    "SELECT po.*, s.name AS supplier_name,
            COUNT(poi.id) AS item_count,
            u.name AS approved_by_name
     FROM purchase_orders po
     LEFT JOIN suppliers s ON s.id = po.supplier_id
     LEFT JOIN purchase_order_items poi ON poi.po_id = po.id
     LEFT JOIN users u ON u.id = po.approved_by
     $where
     GROUP BY po.id
     ORDER BY FIELD(po.status,'draft','approved','ordered','received','cancelled'), po.generated_at DESC",
    $params
);

$statusColor = [
    'draft'     => '#6b7280',
    'approved'  => '#2563eb',
    'ordered'   => '#d97706',
    'received'  => '#15803d',
    'cancelled' => '#b91c1c',
];

$lowStockCount = (int)(fetchOne(
    "SELECT COUNT(*) AS n FROM products WHERE stock <= min_stock"
)['n'] ?? 0);
?>

<div class="mtx-shell">

<?php if ($flash): ?><div class="alert success"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if ($flashErr): ?><div class="alert error"><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>

<?php if ($po): ?>
  <!-- ===== DETAIL VIEW ===== -->
  <header class="mtx-page-head">
    <div class="mtx-page-head-copy" style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
      <a href="<?= baseUrl('admin/purchase-orders.php') ?>" class="mtx-btn mtx-btn--ghost mtx-btn--sm">
        <i class="fas fa-arrow-left"></i> All POs
      </a>
      <h1 style="margin:0;">Purchase Order #<?= $detailId ?></h1>
      <span class="mtx-pill" style="--pill-color:<?= $statusColor[$po['status']] ?? '#6b7280' ?>;">
        <?= ucfirst($po['status']) ?>
      </span>
    </div>
    <div class="mtx-page-head-actions">
      <?php if ($po['status'] === 'draft'): ?>
        <form method="post" style="display:inline;">
          <?= authContextField() ?>
          <input type="hidden" name="action" value="approve">
          <input type="hidden" name="po_id" value="<?= $detailId ?>">
          <button type="submit" class="mtx-btn mtx-btn--primary">
            <i class="fas fa-check"></i> Approve PO
          </button>
        </form>
      <?php elseif ($po['status'] === 'approved'): ?>
        <form method="post" style="display:inline;">
          <?= authContextField() ?>
          <input type="hidden" name="action" value="mark_ordered">
          <input type="hidden" name="po_id" value="<?= $detailId ?>">
          <button type="submit" class="mtx-btn mtx-btn--primary">
            <i class="fas fa-truck"></i> Mark as Ordered
          </button>
        </form>
      <?php elseif ($po['status'] === 'ordered'): ?>
        <form method="post" onsubmit="return confirm('Mark as received? This will add quantities to product stock.');" style="display:inline;">
          <?= authContextField() ?>
          <input type="hidden" name="action" value="mark_received">
          <input type="hidden" name="po_id" value="<?= $detailId ?>">
          <button type="submit" class="mtx-btn mtx-btn--primary" style="background:linear-gradient(135deg,#15803d,#16a34a);">
            <i class="fas fa-boxes-stacked"></i> Mark as Received
          </button>
        </form>
      <?php endif; ?>
      <?php if (in_array($po['status'], ['draft','approved'], true)): ?>
        <form method="post" onsubmit="return confirm('Cancel this PO?');" style="display:inline;">
          <?= authContextField() ?>
          <input type="hidden" name="action" value="cancel_po">
          <input type="hidden" name="po_id" value="<?= $detailId ?>">
          <button type="submit" class="mtx-btn mtx-btn--ghost" style="color:#ef4444;border-color:#f3c1c1;">
            <i class="fas fa-times"></i> Cancel
          </button>
        </form>
      <?php endif; ?>
    </div>
  </header>

  <div class="mtx-grid mtx-grid--half" style="align-items:start;">
    <!-- Info -->
    <div class="mtx-stack">
      <section class="mtx-card">
        <div class="mtx-card-head"><div><h2><i class="fas fa-info-circle"></i> PO Details</h2></div></div>
        <div class="detail-row"><span>Generated</span><strong><?= date('M j, Y g:i A', strtotime($po['generated_at'])) ?></strong></div>
        <div class="detail-row"><span>Status</span>
          <span class="mtx-pill" style="--pill-color:<?= $statusColor[$po['status']] ?? '#6b7280' ?>;"><?= ucfirst($po['status']) ?></span>
        </div>
        <div class="detail-row"><span>Supplier</span><strong><?= htmlspecialchars($po['supplier_name'] ?? '— No Supplier —') ?></strong></div>
        <?php if ($po['supplier_email']): ?>
          <div class="detail-row"><span>Supplier Email</span><?= htmlspecialchars($po['supplier_email']) ?></div>
        <?php endif; ?>
        <?php if ($po['supplier_phone']): ?>
          <div class="detail-row"><span>Supplier Phone</span><?= htmlspecialchars($po['supplier_phone']) ?></div>
        <?php endif; ?>
        <?php if ($po['approved_by_name']): ?>
          <div class="detail-row"><span>Approved By</span><?= htmlspecialchars($po['approved_by_name']) ?></div>
          <div class="detail-row"><span>Approved At</span><?= date('M j, Y g:i A', strtotime($po['approved_at'])) ?></div>
        <?php endif; ?>
      </section>

      <!-- Notes -->
      <section class="mtx-card">
        <div class="mtx-card-head"><div><h2><i class="fas fa-sticky-note"></i> Notes</h2></div></div>
        <form method="post">
          <?= authContextField() ?>
          <input type="hidden" name="action" value="save_notes">
          <input type="hidden" name="po_id" value="<?= $detailId ?>">
          <textarea name="notes" rows="3" style="width:100%;margin-bottom:10px;" placeholder="Add notes or instructions for the supplier..."><?= htmlspecialchars($po['notes'] ?? '') ?></textarea>
          <button type="submit" class="mtx-btn mtx-btn--ghost mtx-btn--sm"><i class="fas fa-save"></i> Save Notes</button>
        </form>
      </section>
    </div>

    <!-- Line items -->
    <section class="mtx-card">
      <div class="mtx-card-head"><div><h2><i class="fas fa-list"></i> Line Items (<?= count($poItems) ?>)</h2></div></div>
      <?php if ($poItems): ?>
        <div style="overflow-x:auto;">
          <table class="mtx-table">
            <thead>
              <tr>
                <th>Product</th>
                <th>Category</th>
                <th>Current Stock</th>
                <th>Min Stock</th>
                <th>Order Qty</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($poItems as $item): ?>
                <tr>
                  <td style="font-weight:700;"><?= htmlspecialchars($item['product_name']) ?></td>
                  <td style="font-size:.8rem;color:var(--muted);"><?= htmlspecialchars($item['category_name'] ?? '—') ?></td>
                  <td>
                    <span style="color:<?= (int)$item['current_stock'] <= (int)$item['min_stock'] ? '#ef4444' : 'inherit' ?>;font-weight:700;">
                      <?= (int)$item['current_stock'] ?>
                    </span>
                  </td>
                  <td><?= (int)$item['min_stock'] ?></td>
                  <td>
                    <span style="background:rgba(37,99,235,.1);color:#2563eb;border-radius:20px;padding:2px 10px;font-weight:700;font-size:.85rem;">
                      +<?= (int)$item['quantity'] ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="subtext">No items in this PO.</p>
      <?php endif; ?>
    </section>
  </div>

<?php else: ?>
  <!-- ===== LIST VIEW ===== -->
  <header class="mtx-page-head">
    <div class="mtx-page-head-copy">
      <h1><i class="fas fa-file-invoice" style="color:#2563eb;"></i> Purchase Orders</h1>
      <p class="subtext">Track and approve purchase orders for restocking low-inventory products.</p>
    </div>
    <div class="mtx-page-head-actions">
      <form method="post" onsubmit="return confirm('Scan for low-stock products and auto-generate draft POs?');">
        <?= authContextField() ?>
        <input type="hidden" name="action" value="auto_generate">
        <button type="submit" class="mtx-btn mtx-btn--primary">
          <i class="fas fa-magic"></i> Auto-Generate POs
          <?php if ($lowStockCount > 0): ?>
            <span style="background:rgba(255,255,255,.25);border-radius:20px;padding:1px 8px;font-size:.78rem;"><?= $lowStockCount ?> low</span>
          <?php endif; ?>
        </button>
      </form>
      <a href="<?= baseUrl('admin/suppliers.php') ?>" class="mtx-btn mtx-btn--ghost">
        <i class="fas fa-truck"></i> Suppliers
      </a>
    </div>
  </header>

  <!-- Status filter tabs -->
  <div class="mtx-seg mtx-seg--card" role="tablist">
    <?php
    $tabs = ['' => 'All', 'draft' => 'Draft', 'approved' => 'Approved', 'ordered' => 'Ordered', 'received' => 'Received', 'cancelled' => 'Cancelled'];
    foreach ($tabs as $val => $label):
    ?>
      <a href="<?= baseUrl('admin/purchase-orders.php' . ($val ? '?status=' . $val : '')) ?>"
         class="<?= $statusFilter === $val ? 'active' : '' ?>" role="tab">
        <?= $label ?>
      </a>
    <?php endforeach; ?>
  </div>

  <section class="mtx-card" style="margin-top:20px;">
    <?php if ($purchaseOrders): ?>
      <div style="overflow-x:auto;">
        <table class="mtx-table">
          <thead>
            <tr>
              <th>PO #</th>
              <th>Supplier</th>
              <th>Items</th>
              <th>Status</th>
              <th>Generated</th>
              <th>Approved By</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($purchaseOrders as $order): ?>
              <tr>
                <td><strong>#<?= (int)$order['id'] ?></strong></td>
                <td><?= htmlspecialchars($order['supplier_name'] ?? '— No Supplier —') ?></td>
                <td>
                  <span style="background:var(--line);border-radius:20px;padding:2px 10px;font-size:.8rem;font-weight:700;">
                    <?= (int)$order['item_count'] ?> item<?= (int)$order['item_count'] !== 1 ? 's' : '' ?>
                  </span>
                </td>
                <td>
                  <span class="mtx-pill" style="--pill-color:<?= $statusColor[$order['status']] ?? '#6b7280' ?>;">
                    <?= ucfirst($order['status']) ?>
                  </span>
                </td>
                <td style="font-size:.82rem;color:var(--muted);white-space:nowrap;">
                  <?= date('M j, Y', strtotime($order['generated_at'])) ?>
                </td>
                <td style="font-size:.82rem;"><?= htmlspecialchars($order['approved_by_name'] ?? '—') ?></td>
                <td>
                  <a href="?id=<?= (int)$order['id'] ?>" class="mtx-btn mtx-btn--ghost mtx-btn--xs">
                    <i class="fas fa-eye"></i> View
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="mtx-empty">
        <i class="fas fa-file-invoice"></i>
        <strong>No purchase orders <?= $statusFilter ? "with status \"$statusFilter\"" : 'yet' ?>.</strong>
        <span>Click "Auto-Generate POs" to create draft orders for low-stock products.</span>
      </div>
    <?php endif; ?>
  </section>
<?php endif; ?>

</div><!-- /.mtx-shell -->
<?= authContextScriptTag() ?>
</main></div></div></body></html>
