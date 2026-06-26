<?php
$pageTitle = 'Compatible Services';
require_once __DIR__ . '/../includes/staff-sidebar.php';
require_once __DIR__ . '/../includes/db.php';

// ---------- POST HANDLER ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- Save / Update service type ---
    if ($action === 'save_service') {
        $id          = (int)($_POST['service_id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $laborFee    = (float)($_POST['labor_fee'] ?? 0);
        $appliesTo   = trim($_POST['applies_to'] ?? 'all');

        if ($name === '') {
            flashMessage('svc_error', 'Service name is required.');
            redirect(baseUrl('staff/services.php'));
        }

        if ($id > 0) {
            getDB()->prepare("UPDATE service_types SET name=?, description=?, labor_fee=?, applies_to=? WHERE id=?")
                   ->execute([$name, $description, $laborFee, $appliesTo, $id]);
            flashMessage('svc_success', 'Service updated.');
        } else {
            getDB()->prepare("INSERT INTO service_types (name, description, labor_fee, applies_to) VALUES (?,?,?,?)")
                   ->execute([$name, $description, $laborFee, $appliesTo]);
            flashMessage('svc_success', 'Service added.');
        }
        redirect(baseUrl('staff/services.php'));
    }

    // --- Delete service type ---
    if ($action === 'delete_service') {
        $id = (int)($_POST['service_id'] ?? 0);
        if ($id > 0) {
            // Remove linked material rules and products
            getDB()->prepare("DELETE FROM service_material_rules WHERE service_id = ?")->execute([$id]);
            getDB()->prepare("DELETE FROM service_products WHERE service_id = ?")->execute([$id]);
            getDB()->prepare("DELETE FROM service_types WHERE id = ?")->execute([$id]);
            flashMessage('svc_success', 'Service deleted.');
        }
        redirect(baseUrl('staff/services.php'));
    }

    // --- Save / Update material rule ---
    if ($action === 'save_rule') {
        $ruleId    = (int)($_POST['rule_id'] ?? 0);
        $serviceId = (int)($_POST['service_id'] ?? 0);
        $productId = (int)($_POST['product_id'] ?? 0) ?: null;
        $label     = trim($_POST['material_label'] ?? '');
        $ccMin     = (int)($_POST['cc_min'] ?? 0);
        $ccMax     = (int)($_POST['cc_max'] ?? 9999);
        $quantity  = (float)($_POST['quantity'] ?? 1);
        $unit      = trim($_POST['unit'] ?? 'pcs');

        if ($serviceId <= 0 || $label === '') {
            flashMessage('svc_error', 'Service and material label are required.');
            redirect(baseUrl('staff/services.php'));
        }

        if ($ruleId > 0) {
            getDB()->prepare("UPDATE service_material_rules SET service_id=?, product_id=?, material_label=?, cc_min=?, cc_max=?, quantity=?, unit=? WHERE id=?")
                   ->execute([$serviceId, $productId, $label, $ccMin, $ccMax, $quantity, $unit, $ruleId]);
            flashMessage('svc_success', 'Material rule updated.');
        } else {
            getDB()->prepare("INSERT INTO service_material_rules (service_id, product_id, material_label, cc_min, cc_max, quantity, unit) VALUES (?,?,?,?,?,?,?)")
                   ->execute([$serviceId, $productId, $label, $ccMin, $ccMax, $quantity, $unit]);
            flashMessage('svc_success', 'Material rule added.');
        }
        redirect(baseUrl('staff/services.php'));
    }

    // --- Delete material rule ---
    if ($action === 'delete_rule') {
        $ruleId = (int)($_POST['rule_id'] ?? 0);
        if ($ruleId > 0) {
            getDB()->prepare("DELETE FROM service_material_rules WHERE id = ?")->execute([$ruleId]);
            flashMessage('svc_success', 'Rule deleted.');
        }
        redirect(baseUrl('staff/services.php'));
    }
}

$flash    = getFlash('svc_success');
$flashErr = getFlash('svc_error');

$services = fetchAllRows("SELECT * FROM service_types ORDER BY name");
$rules    = fetchAllRows(
    "SELECT r.*, p.name AS product_name, st.name AS service_name
     FROM service_material_rules r
     JOIN service_types st ON st.id = r.service_id
     LEFT JOIN products p ON p.id = r.product_id
     ORDER BY st.name, r.cc_min"
);
$allProducts = fetchAllRows("SELECT id, name, brand FROM products WHERE status != 'out_of_stock' ORDER BY name");
$motorcycleTypes = fetchAllRows("SELECT * FROM motorcycle_types ORDER BY name");

// Build applies_to label helper
function appliesLabel(string $val, array $types): string {
    if (strtolower(trim($val)) === 'all' || $val === '') return 'All types';
    $ids = array_map('intval', preg_split('/\s*,\s*/', $val) ?: []);
    $typeMap = array_column($types, 'name', 'id');
    $names = array_filter(array_map(fn($id) => $typeMap[$id] ?? null, $ids));
    return $names ? implode(', ', $names) : $val;
}

$editServiceId = (int)($_GET['edit_service'] ?? 0);
$editRuleId    = (int)($_GET['edit_rule'] ?? 0);
$editService   = $editServiceId ? fetchOne("SELECT * FROM service_types WHERE id = ?", [$editServiceId]) : null;
$editRule      = $editRuleId ? fetchOne("SELECT * FROM service_material_rules WHERE id = ?", [$editRuleId]) : null;
?>

<?php if ($flash): ?><div class="alert success"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if ($flashErr): ?><div class="alert error"><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>

<!-- ===== SERVICE TYPES ===== -->
<section class="admin-card admin-page-stack" style="margin-bottom:28px;">
  <div class="admin-page-head">
    <div>
      <h1>Compatible Services</h1>
      <p>Define service types, labor fees, and which motorcycle types they apply to.</p>
    </div>
    <a href="?edit_service=new" class="btn btn-primary"><i class="fas fa-plus"></i> Add Service</a>
  </div>

  <?php if ($editService || isset($_GET['edit_service']) && $_GET['edit_service'] === 'new'): ?>
    <div class="admin-form-box">
      <h3><?= $editService ? 'Edit Service' : 'New Service' ?></h3>
      <form method="post">
        <?= authContextField() ?>
        <input type="hidden" name="action" value="save_service">
        <input type="hidden" name="service_id" value="<?= $editService ? (int)$editService['id'] : 0 ?>">

        <div class="form-grid-2">
          <label>Service Name *
            <input type="text" name="name" value="<?= htmlspecialchars($editService['name'] ?? '') ?>" required maxlength="100">
          </label>
          <label>Labor Fee (PHP)
            <input type="number" name="labor_fee" value="<?= $editService ? htmlspecialchars($editService['labor_fee']) : '0' ?>" min="0" step="0.01">
          </label>
        </div>

        <label>Description
          <textarea name="description" rows="3"><?= htmlspecialchars($editService['description'] ?? '') ?></textarea>
        </label>

        <label>Applies To (Motorcycle Types)
          <div class="checkbox-group">
            <label class="inline-check">
              <input type="radio" name="applies_to" value="all"
                <?= (!$editService || strtolower($editService['applies_to'] ?? '') === 'all' || $editService['applies_to'] === '') ? 'checked' : '' ?>>
              All Types
            </label>
            <?php foreach ($motorcycleTypes as $mt): ?>
              <?php
                $selected = false;
                if ($editService && $editService['applies_to'] !== 'all') {
                    $ids = array_map('intval', preg_split('/\s*,\s*/', $editService['applies_to']) ?: []);
                    $selected = in_array((int)$mt['id'], $ids, true);
                }
              ?>
              <label class="inline-check">
                <input type="radio" name="applies_to" value="<?= (int)$mt['id'] ?>" <?= $selected ? 'checked' : '' ?>>
                <?= htmlspecialchars($mt['name']) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </label>

        <div class="form-actions">
          <button type="submit" class="btn btn-primary"><?= $editService ? 'Update Service' : 'Add Service' ?></button>
          <a href="<?= baseUrl('staff/services.php') ?>" class="btn btn-outline">Cancel</a>
        </div>
      </form>
    </div>
  <?php endif; ?>

  <?php if ($services): ?>
    <div class="admin-table-wrap">
      <table class="admin-data-table">
        <thead>
          <tr><th>Service Name</th><th>Description</th><th>Labor Fee</th><th>Applies To</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($services as $svc): ?>
            <tr>
              <td><strong><?= htmlspecialchars($svc['name']) ?></strong></td>
              <td><?= htmlspecialchars($svc['description'] ?: '—') ?></td>
              <td><?= formatPrice((float)$svc['labor_fee']) ?></td>
              <td><?= htmlspecialchars(appliesLabel($svc['applies_to'], $motorcycleTypes)) ?></td>
              <td>
                <a href="?edit_service=<?= (int)$svc['id'] ?>" class="btn btn-outline" style="font-size:.8rem;padding:5px 12px;">Edit</a>
                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this service type?');">
                  <?= authContextField() ?>
                  <input type="hidden" name="action" value="delete_service">
                  <input type="hidden" name="service_id" value="<?= (int)$svc['id'] ?>">
                  <button type="submit" class="btn btn-outline" style="font-size:.8rem;padding:5px 12px;color:#b91c1c;border-color:#b91c1c;">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p class="empty-note">No services defined yet.</p>
  <?php endif; ?>
</section>

<!-- ===== MATERIAL RULES ===== -->
<section class="admin-card admin-page-stack">
  <div class="admin-page-head">
    <div>
      <h2>Material Rules</h2>
      <p>Define which products/materials are required per service and CC range.</p>
    </div>
    <a href="?edit_rule=new" class="btn btn-primary"><i class="fas fa-plus"></i> Add Rule</a>
  </div>

  <?php if ($editRule || isset($_GET['edit_rule']) && $_GET['edit_rule'] === 'new'): ?>
    <div class="admin-form-box">
      <h3><?= $editRule ? 'Edit Rule' : 'New Material Rule' ?></h3>
      <form method="post">
        <?= authContextField() ?>
        <input type="hidden" name="action" value="save_rule">
        <input type="hidden" name="rule_id" value="<?= $editRule ? (int)$editRule['id'] : 0 ?>">

        <div class="form-grid-2">
          <label>Service *
            <select name="service_id" required>
              <option value="">— Select Service —</option>
              <?php foreach ($services as $svc): ?>
                <option value="<?= (int)$svc['id'] ?>" <?= (isset($editRule['service_id']) && (int)$editRule['service_id'] === (int)$svc['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($svc['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Linked Product (optional)
            <select name="product_id">
              <option value="">— None —</option>
              <?php foreach ($allProducts as $p): ?>
                <option value="<?= (int)$p['id'] ?>" <?= (isset($editRule['product_id']) && (int)$editRule['product_id'] === (int)$p['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($p['name']) ?><?= $p['brand'] ? ' (' . htmlspecialchars($p['brand']) . ')' : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Material Label *
            <input type="text" name="material_label" value="<?= htmlspecialchars($editRule['material_label'] ?? '') ?>" required>
          </label>
          <label>Unit
            <input type="text" name="unit" value="<?= htmlspecialchars($editRule['unit'] ?? 'pcs') ?>" placeholder="pcs, bottle, can...">
          </label>
          <label>CC Min
            <input type="number" name="cc_min" value="<?= (int)($editRule['cc_min'] ?? 0) ?>" min="0">
          </label>
          <label>CC Max
            <input type="number" name="cc_max" value="<?= (int)($editRule['cc_max'] ?? 9999) ?>" min="0">
          </label>
          <label>Quantity
            <input type="number" name="quantity" value="<?= htmlspecialchars($editRule['quantity'] ?? '1') ?>" min="0.01" step="0.01">
          </label>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn-primary"><?= $editRule ? 'Update Rule' : 'Add Rule' ?></button>
          <a href="<?= baseUrl('staff/services.php') ?>" class="btn btn-outline">Cancel</a>
        </div>
      </form>
    </div>
  <?php endif; ?>

  <?php if ($rules): ?>
    <div class="admin-table-wrap">
      <table class="admin-data-table">
        <thead>
          <tr><th>Service</th><th>Material Label</th><th>Product</th><th>CC Range</th><th>Qty / Unit</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($rules as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['service_name']) ?></td>
              <td><?= htmlspecialchars($r['material_label']) ?></td>
              <td><?= $r['product_name'] ? htmlspecialchars($r['product_name']) : '<span class="subtext">None</span>' ?></td>
              <td><?= (int)$r['cc_min'] ?> – <?= (int)$r['cc_max'] ?> cc</td>
              <td><?= htmlspecialchars($r['quantity']) ?> <?= htmlspecialchars($r['unit']) ?></td>
              <td>
                <a href="?edit_rule=<?= (int)$r['id'] ?>" class="btn btn-outline" style="font-size:.8rem;padding:5px 12px;">Edit</a>
                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this rule?');">
                  <?= authContextField() ?>
                  <input type="hidden" name="action" value="delete_rule">
                  <input type="hidden" name="rule_id" value="<?= (int)$r['id'] ?>">
                  <button type="submit" class="btn btn-outline" style="font-size:.8rem;padding:5px 12px;color:#b91c1c;border-color:#b91c1c;">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p class="empty-note">No material rules defined yet.</p>
  <?php endif; ?>
</section>

</main></div></div></body></html>
