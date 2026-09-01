<?php
$pageTitle = 'Suppliers';
require_once __DIR__ . '/../includes/admin-sidebar.php';
require_once __DIR__ . '/../includes/db.php';

// --- POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_supplier') {
        $id      = (int)($_POST['supplier_id'] ?? 0);
        $name    = trim($_POST['name'] ?? '');
        $contact = trim($_POST['contact'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if ($name === '') {
            flashMessage('sup_error', 'Supplier name is required.');
        } else {
            if ($id > 0) {
                getDB()->prepare(
                    "UPDATE suppliers SET name=?,contact=?,email=?,phone=?,address=? WHERE id=?"
                )->execute([$name, $contact ?: null, $email ?: null, $phone ?: null, $address ?: null, $id]);
                flashMessage('sup_success', 'Supplier updated: ' . $name . '.');
            } else {
                getDB()->prepare(
                    "INSERT INTO suppliers (name,contact,email,phone,address) VALUES (?,?,?,?,?)"
                )->execute([$name, $contact ?: null, $email ?: null, $phone ?: null, $address ?: null]);
                flashMessage('sup_success', 'Supplier added: ' . $name . '.');
            }
        }
        redirect(baseUrl('admin/suppliers.php'));
    }

    if ($action === 'toggle_supplier') {
        $id = (int)($_POST['supplier_id'] ?? 0);
        $sup = fetchOne("SELECT id, name, is_active FROM suppliers WHERE id = ?", [$id]);
        if ($sup) {
            $newStatus = (int)$sup['is_active'] === 1 ? 0 : 1;
            getDB()->prepare("UPDATE suppliers SET is_active = ? WHERE id = ?")->execute([$newStatus, $id]);
            flashMessage('sup_success', 'Supplier ' . $sup['name'] . ' ' . ($newStatus ? 'activated' : 'deactivated') . '.');
        }
        redirect(baseUrl('admin/suppliers.php'));
    }

    if ($action === 'delete_supplier') {
        $id = (int)($_POST['supplier_id'] ?? 0);
        $linked = fetchOne("SELECT COUNT(*) AS n FROM products WHERE supplier_id = ?", [$id]);
        if ((int)($linked['n'] ?? 0) > 0) {
            flashMessage('sup_error', 'Cannot delete �" this supplier is linked to products. Remove the link first.');
        } else {
            getDB()->prepare("DELETE FROM suppliers WHERE id = ?")->execute([$id]);
            flashMessage('sup_success', 'Supplier deleted.');
        }
        redirect(baseUrl('admin/suppliers.php'));
    }
}

$flash    = getFlash('sup_success');
$flashErr = getFlash('sup_error');

$editId = (int)($_GET['edit'] ?? 0);
$editRow = $editId ? fetchOne("SELECT * FROM suppliers WHERE id = ?", [$editId]) : null;

$suppliers = fetchAllRows(
    "SELECT s.*,
            (SELECT COUNT(*) FROM products p WHERE p.supplier_id = s.id) AS product_count,
            (SELECT COUNT(*) FROM purchase_orders po WHERE po.supplier_id = s.id AND po.status IN ('draft','approved','ordered')) AS open_pos
     FROM suppliers s ORDER BY s.is_active DESC, s.name ASC"
);
?>

<div class="mtx-shell">

<?php if ($flash): ?><div class="alert success"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if ($flashErr): ?><div class="alert error"><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>

<header class="mtx-page-head">
  <div class="mtx-page-head-copy">
    <h1><i class="fas fa-truck" style="color:#2563eb;"></i> Suppliers</h1>
    <p class="subtext">Manage your parts suppliers. Link them to products to enable automatic PO grouping.</p>
  </div>
  <div class="mtx-page-head-actions">
    <a href="<?= baseUrl('admin/purchase-orders.php') ?>" class="mtx-btn mtx-btn--ghost mtx-btn--sm">
      <i class="fas fa-file-invoice"></i> Purchase Orders
    </a>
  </div>
</header>

<div class="mtx-grid mtx-grid--half" style="align-items:start;">

  <!-- Form -->
  <section class="mtx-card">
    <div class="mtx-card-head">
      <div><h2><?= $editRow ? '<i class="fas fa-pen"></i> Edit Supplier' : '<i class="fas fa-plus-circle"></i> Add Supplier' ?></h2></div>
      <?php if ($editRow): ?>
        <a href="<?= baseUrl('admin/suppliers.php') ?>" class="mtx-btn mtx-btn--ghost mtx-btn--sm"><i class="fas fa-times"></i> Cancel</a>
      <?php endif; ?>
    </div>
    <form method="post" class="mtx-stack" style="gap:14px;">
      <?= authContextField() ?>
      <input type="hidden" name="action" value="save_supplier">
      <input type="hidden" name="supplier_id" value="<?= $editRow ? (int)$editRow['id'] : 0 ?>">

      <label class="mtx-field">
        <span>Supplier Name <span style="color:#ef4444;">*</span></span>
        <input type="text" name="name" value="<?= htmlspecialchars($editRow['name'] ?? '') ?>" placeholder="e.g. Motul Philippines" required>
      </label>
      <label class="mtx-field">
        <span>Contact Person</span>
        <input type="text" name="contact" value="<?= htmlspecialchars($editRow['contact'] ?? '') ?>" placeholder="Sales representative name">
      </label>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <label class="mtx-field">
          <span>Email</span>
          <input type="email" name="email" value="<?= htmlspecialchars($editRow['email'] ?? '') ?>" placeholder="orders@supplier.com">
        </label>
        <label class="mtx-field">
          <span>Phone</span>
          <input type="text" name="phone" value="<?= htmlspecialchars($editRow['phone'] ?? '') ?>" placeholder="09xx-xxx-xxxx">
        </label>
      </div>
      <label class="mtx-field">
        <span>Address</span>
        <textarea name="address" rows="2" placeholder="Supplier address (optional)"><?= htmlspecialchars($editRow['address'] ?? '') ?></textarea>
      </label>
      <button type="submit" class="mtx-btn mtx-btn--primary">
        <i class="fas fa-save"></i> <?= $editRow ? 'Update Supplier' : 'Add Supplier' ?>
      </button>
    </form>
  </section>

  <!-- Supplier List -->
  <section class="mtx-card">
    <div class="mtx-card-head"><div><h2><i class="fas fa-list"></i> All Suppliers</h2></div></div>
    <?php if ($suppliers): ?>
      <div style="overflow-x:auto;">
        <table class="mtx-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Contact</th>
              <th>Products</th>
              <th>Open POs</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($suppliers as $s): ?>
              <tr style="<?= !(int)$s['is_active'] ? 'opacity:.55;' : '' ?>">
                <td>
                  <div style="font-weight:700;"><?= htmlspecialchars($s['name']) ?></div>
                  <?php if ($s['email']): ?>
                    <div style="font-size:.75rem;color:var(--muted);"><?= htmlspecialchars($s['email']) ?></div>
                  <?php endif; ?>
                </td>
                <td style="font-size:.83rem;"><?= htmlspecialchars($s['contact'] ?? '�"') ?></td>
                <td>
                  <span style="background:var(--line);border-radius:20px;padding:2px 10px;font-size:.8rem;font-weight:700;">
                    <?= (int)$s['product_count'] ?>
                  </span>
                </td>
                <td>
                  <?php if ((int)$s['open_pos'] > 0): ?>
                    <span style="background:rgba(245,158,11,.15);color:#f59e0b;border-radius:20px;padding:2px 10px;font-size:.8rem;font-weight:700;">
                      <?= (int)$s['open_pos'] ?> open
                    </span>
                  <?php else: ?>
                    <span style="color:var(--muted);font-size:.8rem;">none</span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="mtx-pill" style="--pill-color:<?= (int)$s['is_active'] ? '#15803d' : '#6b7280' ?>;">
                    <?= (int)$s['is_active'] ? 'Active' : 'Inactive' ?>
                  </span>
                </td>
                <td style="text-align:right;">
                  <div style="display:flex;gap:6px;justify-content:flex-end;">
                    <a href="?edit=<?= (int)$s['id'] ?>" class="mtx-btn mtx-btn--ghost mtx-btn--xs">
                      <i class="fas fa-pen"></i>
                    </a>
                    <form method="post" style="display:inline;">
                      <?= authContextField() ?>
                      <input type="hidden" name="action" value="toggle_supplier">
                      <input type="hidden" name="supplier_id" value="<?= (int)$s['id'] ?>">
                      <button type="submit" class="mtx-btn mtx-btn--ghost mtx-btn--xs" title="<?= (int)$s['is_active'] ? 'Deactivate' : 'Activate' ?>">
                        <i class="fas fa-<?= (int)$s['is_active'] ? 'pause' : 'play' ?>"></i>
                      </button>
                    </form>
                    <?php if ((int)$s['product_count'] === 0): ?>
                      <form method="post" onsubmit="return confirm('Delete this supplier?');" style="display:inline;">
                        <?= authContextField() ?>
                        <input type="hidden" name="action" value="delete_supplier">
                        <input type="hidden" name="supplier_id" value="<?= (int)$s['id'] ?>">
                        <button type="submit" class="mtx-btn mtx-btn--ghost mtx-btn--xs" style="color:#ef4444;">
                          <i class="fas fa-trash"></i>
                        </button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="mtx-empty">
        <i class="fas fa-truck"></i>
        <strong>No suppliers yet.</strong>
        <span>Add your first supplier using the form on the left.</span>
      </div>
    <?php endif; ?>
  </section>

</div>

</div><!-- /.mtx-shell -->
<?= authContextScriptTag() ?>
</main></div></div></body></html>
