<?php
$pageTitle = 'Purchase Orders';
require_once __DIR__ . '/../includes/admin-sidebar.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/PurchaseOrderService.php';

$currentAdmin = getCurrentUser();

// --- POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    $action = $_POST['action'] ?? '';
    $poId   = (int)($_POST['po_id'] ?? 0);

    if ($action === 'auto_generate') {
        $result = poAutoGenerate();
        $summary = [];
        if ($result['created'] > 0) $summary[] = "{$result['created']} draft PO(s) created";
        if ($result['updated'] > 0) $summary[] = "{$result['updated']} existing draft PO(s) updated";
        if ($result['needs_supplier'] > 0) $summary[] = "{$result['needs_supplier']} low-stock product(s) need a supplier";
        if ($result['inactive_supplier'] > 0) $summary[] = "{$result['inactive_supplier']} low-stock product(s) have inactive suppliers";
        if ($result['invalid_supplier'] > 0) $summary[] = "{$result['invalid_supplier']} low-stock product(s) have invalid suppliers";
        if ($result['covered'] > 0) $summary[] = "{$result['covered']} low-stock product(s) already covered";
        if (!$summary) $summary[] = 'No low-stock products. Everything is adequately stocked!';
        flashMessage('po_success', implode('. ', $summary) . '.');
        redirect(baseUrl('admin/purchase-orders.php'));

        if ($result['items'] > 0) {
            flashMessage('po_success',
                "Auto-generated {$result['created']} new PO(s), updated {$result['updated']} existing draft(s) — {$result['items']} line item(s) total."
            );
        } else {
            $lowStockCount = (int)(fetchOne("SELECT COUNT(*) AS n FROM products WHERE stock <= min_stock")['n'] ?? 0);
            $coveredCount = (int)(fetchOne(
                "SELECT COUNT(DISTINCT p.id) AS n
                 FROM products p
                 JOIN purchase_order_items poi ON poi.product_id = p.id
                 JOIN purchase_orders po ON po.id = poi.po_id
                 WHERE p.stock <= p.min_stock
                   AND po.status IN ('draft','approved','ordered')
                   AND ((p.supplier_id IS NULL AND po.supplier_id IS NULL)
                        OR (p.supplier_id IS NOT NULL AND po.supplier_id = p.supplier_id))"
            )['n'] ?? 0);
            if ($lowStockCount > 0 && $coveredCount === $lowStockCount) {
                flashMessage('po_success', "$coveredCount low-stock product" . ($coveredCount === 1 ? '' : 's') . " already covered by an open Purchase Order.");
            } elseif ($lowStockCount > 0) {
                flashMessage('po_success', "$lowStockCount low-stock product" . ($lowStockCount === 1 ? '' : 's') . " still need attention; no new draft PO was created.");
            } else {
                flashMessage('po_success', 'No low-stock products. Everything is adequately stocked!');
            }
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

    if ($action === 'send_supplier' && $poId > 0) {
        try {
            $sent = poSendToSupplier($poId, !empty($_POST['resend']));
            flashMessage('po_success', "Purchase order sent to supplier. Gmail message {$sent['message_id']} recorded.");
        } catch (Throwable $e) {
            error_log('PO supplier send failed: ' . $e->getMessage());
            flashMessage('po_error', $e->getMessage());
        }
        redirect(baseUrl('admin/purchase-orders.php?id=' . $poId));
    }

    if ($action === 'check_replies') {
        try {
            unset($_SESSION['po_reply_results']);
            $result = poProcessSupplierReplies();
            flashMessage('po_success', $result['processed'] > 0
                ? ($result['processed'] === 1 ? '1 new supplier reply processed.' : "{$result['processed']} new supplier replies processed.")
                : "No new supplier replies. {$result['skipped']} already processed.");
        } catch (Throwable $e) {
            error_log('Supplier reply check failed: ' . $e->getMessage());
            flashMessage('po_error', 'Supplier reply check failed. Check server configuration.');
        }
        redirect(baseUrl('admin/purchase-orders.php'));
    }

    if ($action === 'update_item_quantity' && $poId > 0) {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 0);
        if ($itemId < 1 || $quantity < 1) {
            flashMessage('po_error', 'Quantity must be at least 1.');
        } else {
            $stmt = getDB()->prepare(
                "UPDATE purchase_order_items poi
                 JOIN purchase_orders po ON po.id = poi.po_id
                 SET poi.quantity = ?
                 WHERE poi.id = ? AND poi.po_id = ? AND po.status = 'draft'"
            );
            $stmt->execute([$quantity, $itemId, $poId]);
            $changed = $stmt->rowCount() === 1;
            flashMessage($changed ? 'po_success' : 'po_error', $changed ? 'Order quantity updated.' : 'Only draft PO items can be updated.');
        }
        redirect(baseUrl('admin/purchase-orders.php?id=' . $poId));
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
    getDB()->prepare(
        "UPDATE notifications SET is_read = 1
         WHERE user_id = ? AND type LIKE 'purchase_order_reply%'
           AND action_url LIKE ?"
    )->execute([(int)$currentAdmin['id'], '%purchase-orders.php?id=' . $detailId . '%']);
    $poItems = fetchAllRows(
        "SELECT poi.*, p.name AS product_name, p.stock AS current_stock, p.min_stock, p.reorder_qty,
                cat.name AS category_name
         FROM purchase_order_items poi
         JOIN products p ON p.id = poi.product_id
         LEFT JOIN categories cat ON cat.id = p.category_id
         WHERE poi.po_id = ?
         ORDER BY cat.name, p.name",
        [$detailId]
    );
    $poReply = fetchOne(
        "SELECT * FROM purchase_order_replies WHERE po_id = ? ORDER BY received_at DESC LIMIT 1",
        [$detailId]
    );
} else {
    $po = null;
}

// --- List view ---
$statusFilter = (string)($_GET['status'] ?? '');
$validStatuses = ['draft','approved','ordered','received','cancelled'];
$communicationFilter = (string)($_GET['communication_status'] ?? '');
$communicationValues = ['not_sent','awaiting_response','supplier_confirmed','partially_available','supplier_declined','out_of_stock','declined','needs_clarification','delivery_update','preparing','shipped','in_transit','out_for_delivery','delayed','supplier_says_delivered','needs_review','unknown'];
$search = trim((string)($_GET['q'] ?? ''));
$supplierFilter = (int)($_GET['supplier_id'] ?? 0);
$approvedByFilter = (int)($_GET['approved_by'] ?? 0);
$dateFrom = trim((string)($_GET['generated_from'] ?? ''));
$dateTo = trim((string)($_GET['generated_to'] ?? ''));
$unreadOnly = !empty($_GET['unread']);
$noSupplierOnly = !empty($_GET['no_supplier']);
$sortKey = (string)($_GET['sort'] ?? 'generated');
$sortDirection = strtolower((string)($_GET['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
$sortMap = [
    'po' => 'po.id',
    'supplier' => 's.name',
    'items' => 'item_count',
    'status' => 'po.status',
    'email' => 'po.communication_status',
    'generated' => 'po.generated_at',
    'approved_by' => 'u.name',
];
$sortKey = array_key_exists($sortKey, $sortMap) ? $sortKey : 'generated';
$whereParts = [];
$params = [];
if (in_array($statusFilter, $validStatuses, true)) { $whereParts[] = 'po.status = ?'; $params[] = $statusFilter; }
if (in_array($communicationFilter, $communicationValues, true)) { $whereParts[] = 'po.communication_status = ?'; $params[] = $communicationFilter; }
if ($search !== '') { $whereParts[] = '(po.po_reference LIKE ? OR s.name LIKE ?)'; $params[] = '%' . $search . '%'; $params[] = '%' . $search . '%'; }
if ($supplierFilter > 0) { $whereParts[] = 'po.supplier_id = ?'; $params[] = $supplierFilter; }
if ($approvedByFilter > 0) { $whereParts[] = 'po.approved_by = ?'; $params[] = $approvedByFilter; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) { $whereParts[] = 'DATE(po.generated_at) >= ?'; $params[] = $dateFrom; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) { $whereParts[] = 'DATE(po.generated_at) <= ?'; $params[] = $dateTo; }
if ($unreadOnly) { $whereParts[] = "EXISTS (SELECT 1 FROM notifications n WHERE n.user_id = ? AND n.is_read = 0 AND n.type LIKE 'purchase_order_reply%' AND n.action_url LIKE CONCAT('%purchase-orders.php?id=', po.id, '%'))"; $params[] = (int)$currentAdmin['id']; }
if ($noSupplierOnly) { $whereParts[] = 'po.supplier_id IS NULL'; }
$where = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

$purchaseOrders = fetchAllRows(
    "SELECT po.*, s.name AS supplier_name,
            COUNT(poi.id) AS item_count,
            u.name AS approved_by_name,
            EXISTS (SELECT 1 FROM notifications n
                    WHERE n.user_id = ? AND n.is_read = 0
                      AND n.type LIKE 'purchase_order_reply%'
                      AND n.action_url LIKE CONCAT('%purchase-orders.php?id=', po.id, '%')) AS has_unread_reply
     FROM purchase_orders po
     LEFT JOIN suppliers s ON s.id = po.supplier_id
     LEFT JOIN purchase_order_items poi ON poi.po_id = po.id
     LEFT JOIN users u ON u.id = po.approved_by
     $where
     GROUP BY po.id
     ORDER BY {$sortMap[$sortKey]} $sortDirection",
    array_merge([(int)$currentAdmin['id']], $params)
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
$supplierAttentionProducts = fetchAllRows(
    "SELECT p.id, p.name,
            CASE WHEN p.supplier_id IS NULL THEN 'No supplier assigned'
                 WHEN s.id IS NULL THEN 'Supplier record missing'
                 ELSE 'Supplier inactive' END AS supplier_issue
     FROM products p
     LEFT JOIN suppliers s ON s.id = p.supplier_id
     WHERE p.stock <= p.min_stock
       AND (p.supplier_id IS NULL OR s.id IS NULL OR s.is_active <> 1)
     ORDER BY p.name"
);
$gmail = new GmailService();
$communicationLabels = [
    'not_sent' => 'Not Sent',
    'awaiting_response' => 'Awaiting Response',
    'supplier_confirmed' => 'Supplier Confirmed',
    'partially_available' => 'Partially Available',
    'supplier_declined' => 'Supplier Declined (Legacy)',
    'out_of_stock' => 'Out of Stock',
    'declined' => 'Declined',
    'needs_clarification' => 'Needs Clarification',
    'delivery_update' => 'Delivery Update',
    'preparing' => 'Preparing',
    'shipped' => 'Shipped',
    'in_transit' => 'In Transit',
    'out_for_delivery' => 'Out for Delivery',
    'delayed' => 'Delayed',
    'supplier_says_delivered' => 'Supplier Says Delivered',
    'needs_review' => 'Needs Review',
    'unknown' => 'Unknown',
];
$supplierOptions = fetchAllRows('SELECT id, name FROM suppliers ORDER BY name');
$approverOptions = fetchAllRows("SELECT id, name FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY name");
$listParams = $_GET;
$listParams['ctx'] = currentAuthContext();
$listUrl = static function (array $changes = []) use ($listParams): string {
    $params = $listParams;
    foreach ($changes as $key => $value) {
        if ($value === null || $value === '') unset($params[$key]);
        else $params[$key] = $value;
    }
    $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    return baseUrl('admin/purchase-orders.php') . ($query ? '?' . $query : '');
};
?>

<div class="mtx-shell">

<?php if ($flash): ?><div class="alert success"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if ($flashErr): ?><div class="alert error"><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>
<?php if ($supplierAttentionProducts): ?>
  <div class="po-supplier-attention" role="status">
    <span class="po-supplier-attention-icon"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i></span>
    <div><strong><?= count($supplierAttentionProducts) ?> low-stock product<?= count($supplierAttentionProducts) === 1 ? '' : 's' ?> need supplier attention</strong><span>Assign or reactivate supplier before generating a Purchase Order.</span></div>
    <a href="<?= baseUrl('admin/products.php?tab=manage&edit=' . (int)$supplierAttentionProducts[0]['id']) ?>#product-form" class="mtx-btn mtx-btn--ghost mtx-btn--sm">Assign Supplier</a>
  </div>
<?php endif; ?>
<?php if (false): ?>
  <section class="mtx-card" style="margin-bottom:20px;">
    <div class="mtx-card-head"><div><h2><i class="fas fa-envelope-open-text"></i> New Supplier Replies</h2><p class="subtext">Review each reply directly from this summary.</p></div></div>
    <?php foreach ($replyResults as $replyResult): ?>
      <div style="border:1px solid var(--line);border-radius:12px;padding:14px;margin-top:12px;">
        <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;">
          <div><strong><?= htmlspecialchars($replyResult['po_reference']) ?></strong><div><?= htmlspecialchars($replyResult['supplier_name']) ?></div></div>
          <a class="mtx-btn mtx-btn--ghost mtx-btn--sm" href="<?= baseUrl('admin/purchase-orders.php?id=' . (int)$replyResult['po_id']) ?>">View PO <i class="fas fa-arrow-right"></i></a>
        </div>
        <p style="white-space:pre-wrap;margin:10px 0 6px;">“<?= htmlspecialchars($replyResult['reply']) ?>”</p>
        <div class="subtext"><strong><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $replyResult['classification']))) ?></strong><?php if ($replyResult['confirmed_quantity'] !== null): ?> · Quantity: <?= (int)$replyResult['confirmed_quantity'] ?><?php endif; ?><?php if (!empty($replyResult['expected_delivery_date'])): ?> · Delivery: <?= htmlspecialchars(date('M j, Y', strtotime($replyResult['expected_delivery_date']))) ?><?php endif; ?> · Confidence: <?= number_format((float)$replyResult['confidence'] * 100, 0) ?>% · <?= !empty($replyResult['needs_admin_review']) ? 'Needs review' : 'No review needed' ?></div>
      </div>
    <?php endforeach; ?>
  </section>
<?php endif; ?>

<?php if ($po): ?>
  <!-- ===== DETAIL VIEW ===== -->
  <header class="mtx-page-head po-page-head">
    <div class="mtx-page-head-copy" style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
      <a href="<?= baseUrl('admin/purchase-orders.php') ?>" class="mtx-btn mtx-btn--ghost mtx-btn--sm">
        <i class="fas fa-arrow-left"></i> All POs
      </a>
      <h1 style="margin:0;">Purchase Order #<?= $detailId ?></h1>
      <span class="mtx-pill" style="--pill-color:<?= $statusColor[$po['status']] ?? '#6b7280' ?>;">
        <?= ucfirst($po['status']) ?>
      </span>
    </div>
    <div class="mtx-page-head-actions po-action-group">
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
          <button type="submit" class="mtx-btn mtx-btn--primary" style="background:#15803d;border-color:#15803d;">
            <i class="fas fa-boxes-stacked"></i> Mark as Received
          </button>
        </form>
      <?php endif; ?>
      <?php if (in_array($po['status'], ['approved', 'ordered'], true)): ?>
        <?php if (!$gmail->isConnected()): ?>
          <a href="<?= baseUrl('admin/gmail-connect.php') ?>" class="mtx-btn mtx-btn--ghost">Connect Gmail</a>
        <?php else: ?>
          <form method="post" style="display:inline;" onsubmit="return confirm('Send this purchase order to the supplier?');">
            <?= authContextField() ?>
            <input type="hidden" name="action" value="send_supplier">
            <input type="hidden" name="po_id" value="<?= $detailId ?>">
            <?php if (($po['communication_status'] ?? 'not_sent') !== 'not_sent'): ?><input type="hidden" name="resend" value="1"><?php endif; ?>
            <button type="submit" class="mtx-btn mtx-btn--primary"><i class="fas fa-paper-plane"></i> <?= ($po['communication_status'] ?? 'not_sent') === 'not_sent' ? 'Send to Supplier' : 'Resend Email' ?></button>
          </form>
        <?php endif; ?>
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
        <div class="detail-row"><span>Email Status</span><span class="mtx-pill"><?= htmlspecialchars($communicationLabels[$po['communication_status'] ?? 'not_sent'] ?? 'Needs Review') ?></span></div>
        <?php if ($po['last_sent_at']): ?><div class="detail-row"><span>Last Sent</span><?= date('M j, Y g:i A', strtotime($po['last_sent_at'])) ?></div><?php endif; ?>
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
                <th>Suggested Reorder</th>
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
                  <td><?= (int)$item['reorder_qty'] ?></td>
                  <td>
                    <span style="background:rgba(37,99,235,.1);color:#2563eb;border-radius:20px;padding:2px 10px;font-weight:700;font-size:.85rem;">
                      +<?= (int)$item['quantity'] ?>
                    </span>
                    <?php if ($po['status'] === 'draft'): ?>
                      <form method="post" style="display:inline-flex;gap:4px;margin-left:5px;">
                        <?= authContextField() ?>
                        <input type="hidden" name="action" value="update_item_quantity">
                        <input type="hidden" name="po_id" value="<?= $detailId ?>">
                        <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                        <input type="number" name="quantity" min="1" value="<?= (int)$item['quantity'] ?>" style="width:70px;padding:2px 5px;">
                        <button type="submit" class="mtx-btn mtx-btn--ghost mtx-btn--xs">Set</button>
                      </form>
                    <?php endif; ?>
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

  <?php if ($poReply): ?>
    <section class="mtx-card" style="margin-top:20px;">
      <div class="mtx-card-head"><div><h2><i class="fas fa-envelope-open-text"></i> Supplier Reply</h2></div></div>
      <div class="detail-row"><span>From</span><strong><?= htmlspecialchars($poReply['sender']) ?></strong></div>
      <div class="detail-row"><span>Received</span><?= date('M j, Y g:i A', strtotime($poReply['received_at'])) ?></div>
      <pre style="white-space:pre-wrap;background:var(--line);padding:14px;border-radius:10px;"><?= htmlspecialchars($poReply['clean_body'] ?? $poReply['raw_body']) ?></pre>
      <?php if (!empty($poReply['clean_body_needs_review'])): ?>
        <p class="subtext">Reply quote separation was uncertain. Full original message is preserved for review.</p>
      <?php endif; ?>
      <h3>AI Interpretation</h3>
      <div class="detail-row"><span>Classification</span><strong><?= htmlspecialchars($poReply['classification']) ?></strong></div>
      <div class="detail-row"><span>Confirmed Quantity</span><?= $poReply['confirmed_quantity'] === null ? '—' : (int)$poReply['confirmed_quantity'] ?></div>
      <div class="detail-row"><span>Expected Delivery</span><?= htmlspecialchars($poReply['expected_delivery_date'] ?? '—') ?></div>
      <div class="detail-row"><span>Confidence</span><?= number_format((float)$poReply['confidence'] * 100, 0) ?>%</div>
      <div class="detail-row"><span>Needs Review</span><strong><?= (int)$poReply['needs_admin_review'] ? 'Yes' : 'No' ?></strong></div>
      <p><?= htmlspecialchars($poReply['supplier_message_summary'] ?? '') ?></p>
    </section>
  <?php endif; ?>

<?php else: ?>
  <!-- ===== LIST VIEW ===== -->
  <header class="mtx-page-head">
    <div class="mtx-page-head-copy">
      <h1><i class="fas fa-file-invoice" style="color:#2563eb;"></i> Purchase Orders</h1>
      <p class="subtext">Track and approve purchase orders for restocking low-inventory products.</p>
    </div>
    <div class="mtx-page-head-actions po-page-actions">
      <div class="po-primary-actions">
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
      <?php if ($gmail->isConnected()): ?>
        <form method="post">
          <?= authContextField() ?>
          <input type="hidden" name="action" value="check_replies">
          <button type="submit" class="mtx-btn mtx-btn--ghost"><i class="fas fa-inbox"></i> Check Replies</button>
        </form>
      <?php else: ?>
        <a href="<?= baseUrl('admin/gmail-connect.php') ?>" class="mtx-btn mtx-btn--ghost"><i class="fab fa-google"></i> Connect Gmail</a>
      <?php endif; ?>
      </div>
      <div class="po-secondary-actions">
      <?php if ($gmail->isConnected()): ?>
        <form method="post" action="<?= baseUrl('admin/gmail-disconnect.php') ?>" onsubmit="return confirm('Disconnect MotoTrack Gmail?');">
          <?= authContextField() ?>
          <button type="submit" class="mtx-btn mtx-btn--ghost">Disconnect Gmail</button>
        </form>
      <?php endif; ?>
      <a href="<?= baseUrl('admin/suppliers.php') ?>" class="mtx-btn mtx-btn--ghost">
        <i class="fas fa-truck"></i> Suppliers
      </a>
      </div>
    </div>
  </header>

  <form method="get" class="po-filter-bar" aria-label="Purchase order filters">
    <?php if (currentAuthContext() !== 'default'): ?><input type="hidden" name="ctx" value="<?= htmlspecialchars(currentAuthContext()) ?>"><?php endif; ?>
    <div class="po-filter-row po-filter-row--primary">
      <label class="po-filter-field po-filter-field--search">Search <input type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="PO reference or supplier"></label>
      <label class="po-filter-field">Supplier <select name="supplier_id"><option value="0">All suppliers</option><?php foreach ($supplierOptions as $supplier): ?><option value="<?= (int)$supplier['id'] ?>" <?= $supplierFilter === (int)$supplier['id'] ? 'selected' : '' ?>><?= htmlspecialchars($supplier['name']) ?></option><?php endforeach; ?></select></label>
      <label class="po-filter-field">PO Status <select name="status"><option value="">All statuses</option><?php foreach ($validStatuses as $value): ?><option value="<?= $value ?>" <?= $statusFilter === $value ? 'selected' : '' ?>><?= ucfirst($value) ?></option><?php endforeach; ?></select></label>
      <label class="po-filter-field po-filter-field--email">Email Status <select name="communication_status"><option value="">All email statuses</option><?php foreach ($communicationValues as $value): ?><option value="<?= $value ?>" <?= $communicationFilter === $value ? 'selected' : '' ?>><?= htmlspecialchars($communicationLabels[$value] ?? ucfirst(str_replace('_', ' ', $value))) ?></option><?php endforeach; ?></select></label>
    </div>
    <div class="po-filter-row po-filter-row--secondary">
      <label class="po-filter-field">Approved By <select name="approved_by"><option value="0">Anyone</option><?php foreach ($approverOptions as $approver): ?><option value="<?= (int)$approver['id'] ?>" <?= $approvedByFilter === (int)$approver['id'] ? 'selected' : '' ?>><?= htmlspecialchars($approver['name']) ?></option><?php endforeach; ?></select></label>
      <label class="po-filter-field">From Date <input type="date" name="generated_from" value="<?= htmlspecialchars($dateFrom) ?>"></label>
      <label class="po-filter-field">To Date <input type="date" name="generated_to" value="<?= htmlspecialchars($dateTo) ?>"></label>
      <label class="po-filter-check"><input type="checkbox" name="unread" value="1" <?= $unreadOnly ? 'checked' : '' ?>> <span>Unread reply</span></label>
      <label class="po-filter-check"><input type="checkbox" name="no_supplier" value="1" <?= $noSupplierOnly ? 'checked' : '' ?>> <span>No supplier</span></label>
      <div class="po-filter-actions">
        <button type="submit" class="mtx-btn mtx-btn--primary mtx-btn--sm">Apply filters</button>
        <a href="<?= $listUrl(['q' => null, 'supplier_id' => null, 'status' => null, 'communication_status' => null, 'approved_by' => null, 'generated_from' => null, 'generated_to' => null, 'unread' => null, 'no_supplier' => null, 'sort' => null, 'dir' => null]) ?>" class="mtx-btn mtx-btn--ghost mtx-btn--sm">Clear</a>
      </div>
    </div>
  </form>

  <!-- Status filter tabs -->
  <div class="mtx-seg mtx-seg--card po-status-tabs" role="tablist" aria-label="Purchase order status">
    <?php
    $tabs = ['' => 'All', 'draft' => 'Draft', 'approved' => 'Approved', 'ordered' => 'Ordered', 'received' => 'Received', 'cancelled' => 'Cancelled'];
    foreach ($tabs as $val => $label):
    ?>
      <a href="<?= $listUrl(['status' => $val ?: null]) ?>"
         class="<?= $statusFilter === $val ? 'active' : '' ?>" role="tab">
        <?= $label ?>
      </a>
    <?php endforeach; ?>
  </div>

  <section class="mtx-card po-table-card">
    <?php if ($purchaseOrders): ?>
      <div class="po-table-scroll">
        <table class="mtx-table">
          <thead>
            <tr>
              <?php foreach (['po' => 'PO #', 'supplier' => 'Supplier', 'items' => 'Items', 'status' => 'Status', 'email' => 'Email', 'generated' => 'Generated', 'approved_by' => 'Approved By'] as $column => $label): ?>
                <?php $nextDir = $sortKey === $column && $sortDirection === 'ASC' ? 'desc' : 'asc'; ?>
                <th><a href="<?= $listUrl(['sort' => $column, 'dir' => $nextDir]) ?>" class="po-sort-link"><?= $label ?><?php if ($sortKey === $column): ?> <span aria-hidden="true"><?= $sortDirection === 'ASC' ? '↑' : '↓' ?></span><?php endif; ?></a></th>
              <?php endforeach; ?>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($purchaseOrders as $order): ?>
              <tr>
                <td><strong>#<?= (int)$order['id'] ?></strong><?php if (!empty($order['has_unread_reply'])): ?> <span class="po-unread-dot" title="Unread supplier reply" aria-label="Unread supplier reply"></span><?php endif; ?></td>
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
                <td><span class="mtx-pill"><?= htmlspecialchars($communicationLabels[$order['communication_status'] ?? 'not_sent'] ?? 'Needs Review') ?></span><?php if (!empty($order['has_unread_reply'])): ?> <span class="po-unread-dot" title="Unread supplier reply" aria-label="Unread supplier reply"></span><?php endif; ?></td>
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
      <div class="mtx-empty po-empty-state">
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
