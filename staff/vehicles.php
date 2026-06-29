<?php
$pageTitle = 'Vehicle Options';
require_once __DIR__ . '/../includes/staff-sidebar.php';
require_once __DIR__ . '/../includes/db.php';

// ---------- POST HANDLER ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ---- TYPES ----
    if ($action === 'save_type') {
        $id   = (int)($_POST['type_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if ($name === '') { flashMessage('veh_error', 'Type name required.'); redirect(baseUrl('staff/vehicles.php')); }
        if ($id > 0) {
            getDB()->prepare("UPDATE motorcycle_types SET name=?, description=? WHERE id=?")->execute([$name, $desc, $id]);
            flashMessage('veh_success', 'Type updated.');
        } else {
            getDB()->prepare("INSERT INTO motorcycle_types (name, description) VALUES (?,?)")->execute([$name, $desc]);
            flashMessage('veh_success', 'Type added.');
        }
        redirect(baseUrl('staff/vehicles.php') . '#types');
    }
    if ($action === 'toggle_type') {
        $id = (int)($_POST['type_id'] ?? 0);
        $active = isset($_POST['is_active']) ? 1 : 0;
        getDB()->prepare("UPDATE motorcycle_types SET is_active=? WHERE id=?")->execute([$active, $id]);
        flashMessage('veh_success', $active ? 'Type enabled.' : 'Type disabled.');
        redirect(baseUrl('staff/vehicles.php') . '#types');
    }

    // ---- BRANDS ----
    if ($action === 'save_brand') {
        $id   = (int)($_POST['brand_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($name === '') { flashMessage('veh_error', 'Brand name required.'); redirect(baseUrl('staff/vehicles.php')); }
        if ($id > 0) {
            getDB()->prepare("UPDATE motorcycle_brands SET name=? WHERE id=?")->execute([$name, $id]);
            flashMessage('veh_success', 'Brand updated.');
        } else {
            getDB()->prepare("INSERT INTO motorcycle_brands (name) VALUES (?)")->execute([$name]);
            flashMessage('veh_success', 'Brand added.');
        }
        redirect(baseUrl('staff/vehicles.php') . '#brands');
    }
    if ($action === 'toggle_brand') {
        $id = (int)($_POST['brand_id'] ?? 0);
        $active = isset($_POST['is_active']) ? 1 : 0;
        getDB()->prepare("UPDATE motorcycle_brands SET is_active=? WHERE id=?")->execute([$active, $id]);
        flashMessage('veh_success', $active ? 'Brand enabled.' : 'Brand disabled.');
        redirect(baseUrl('staff/vehicles.php') . '#brands');
    }

    // ---- MODELS ----
    if ($action === 'save_model') {
        $id      = (int)($_POST['model_id'] ?? 0);
        $brandId = (int)($_POST['brand_id'] ?? 0);
        $typeId  = (int)($_POST['type_id'] ?? 0);
        $name    = trim($_POST['name'] ?? '');
        $cc      = (int)($_POST['cc'] ?? 125);
        if ($name === '' || !$brandId || !$typeId) { flashMessage('veh_error', 'Model name, brand, and type are required.'); redirect(baseUrl('staff/vehicles.php')); }
        if ($id > 0) {
            getDB()->prepare("UPDATE motorcycle_models SET brand_id=?, type_id=?, name=?, cc=? WHERE id=?")->execute([$brandId, $typeId, $name, $cc, $id]);
            flashMessage('veh_success', 'Model updated.');
        } else {
            getDB()->prepare("INSERT INTO motorcycle_models (brand_id, type_id, name, cc) VALUES (?,?,?,?)")->execute([$brandId, $typeId, $name, $cc]);
            flashMessage('veh_success', 'Model added.');
        }
        redirect(baseUrl('staff/vehicles.php') . '#models');
    }
    if ($action === 'toggle_model') {
        $id = (int)($_POST['model_id'] ?? 0);
        $active = isset($_POST['is_active']) ? 1 : 0;
        getDB()->prepare("UPDATE motorcycle_models SET is_active=? WHERE id=?")->execute([$active, $id]);
        flashMessage('veh_success', $active ? 'Model enabled.' : 'Model disabled.');
        redirect(baseUrl('staff/vehicles.php') . '#models');
    }
}

$flash    = getFlash('veh_success');
$flashErr = getFlash('veh_error');

$types  = fetchAllRows("SELECT * FROM motorcycle_types ORDER BY name");
$brands = fetchAllRows("SELECT * FROM motorcycle_brands ORDER BY name");
$models = fetchAllRows(
    "SELECT m.*, b.name AS brand_name, t.name AS type_name
     FROM motorcycle_models m
     JOIN motorcycle_brands b ON b.id = m.brand_id
     JOIN motorcycle_types t ON t.id = m.type_id
     ORDER BY b.name, m.name"
);

$editTypeId  = (int)($_GET['edit_type'] ?? 0);
$editBrandId = (int)($_GET['edit_brand'] ?? 0);
$editModelId = (int)($_GET['edit_model'] ?? 0);
$editType    = $editTypeId  ? fetchOne("SELECT * FROM motorcycle_types WHERE id=?", [$editTypeId]) : null;
$editBrand   = $editBrandId ? fetchOne("SELECT * FROM motorcycle_brands WHERE id=?", [$editBrandId]) : null;
$editModel   = $editModelId ? fetchOne("SELECT * FROM motorcycle_models WHERE id=?", [$editModelId]) : null;
?>

<?php if ($flash): ?><div class="alert success"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if ($flashErr): ?><div class="alert error"><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>

<!-- ===== TYPES ===== -->
<section class="admin-card admin-page-stack" id="types" style="margin-bottom:22px;">
  <div class="admin-page-head">
    <div><h1>Vehicle Options</h1><p>Manage motorcycle types, brands, and models available in the booking system.</p></div>
  </div>
  <div class="admin-page-head" style="border-top:1px solid var(--line);padding-top:18px;">
    <h2 style="margin:0;">Motorcycle Types</h2>
    <a href="?edit_type=new#types" class="btn btn-primary" style="font-size:.85rem;"><i class="fas fa-plus"></i> Add Type</a>
  </div>

  <?php if ($editType || (isset($_GET['edit_type']) && $_GET['edit_type'] === 'new')): ?>
    <div class="admin-form-box">
      <h3><?= $editType ? 'Edit Type' : 'New Type' ?></h3>
      <form method="post">
        <?= authContextField() ?>
        <input type="hidden" name="action" value="save_type">
        <input type="hidden" name="type_id" value="<?= $editType ? (int)$editType['id'] : 0 ?>">
        <div class="form-grid-2">
          <label>Type Name * <input type="text" name="name" value="<?= htmlspecialchars($editType['name'] ?? '') ?>" required></label>
          <label>Description <input type="text" name="description" value="<?= htmlspecialchars($editType['description'] ?? '') ?>"></label>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn-primary"><?= $editType ? 'Update' : 'Add' ?> Type</button>
          <a href="<?= baseUrl('staff/vehicles.php') ?>#types" class="btn btn-outline">Cancel</a>
        </div>
      </form>
    </div>
  <?php endif; ?>

  <div class="admin-table-wrap">
    <table class="admin-data-table">
      <thead><tr><th>Name</th><th>Description</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($types as $t): ?>
          <tr>
            <td><strong><?= htmlspecialchars($t['name']) ?></strong></td>
            <td><?= htmlspecialchars($t['description'] ?: '—') ?></td>
            <td>
              <form method="post" class="admin-toggle-form">
                <?= authContextField() ?>
                <input type="hidden" name="action" value="toggle_type">
                <input type="hidden" name="type_id" value="<?= (int)$t['id'] ?>">
                <label><input type="checkbox" name="is_active" value="1" <?= $t['is_active'] ? 'checked' : '' ?> onchange="this.form.submit()"> Active</label>
              </form>
            </td>
            <td><a href="?edit_type=<?= (int)$t['id'] ?>#types" class="btn btn-outline" style="font-size:.8rem;padding:5px 12px;">Edit</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<!-- ===== BRANDS ===== -->
<section class="admin-card admin-page-stack" id="brands" style="margin-bottom:22px;">
  <div class="admin-page-head">
    <h2 style="margin:0;">Motorcycle Brands</h2>
    <a href="?edit_brand=new#brands" class="btn btn-primary" style="font-size:.85rem;"><i class="fas fa-plus"></i> Add Brand</a>
  </div>

  <?php if ($editBrand || (isset($_GET['edit_brand']) && $_GET['edit_brand'] === 'new')): ?>
    <div class="admin-form-box">
      <h3><?= $editBrand ? 'Edit Brand' : 'New Brand' ?></h3>
      <form method="post">
        <?= authContextField() ?>
        <input type="hidden" name="action" value="save_brand">
        <input type="hidden" name="brand_id" value="<?= $editBrand ? (int)$editBrand['id'] : 0 ?>">
        <label>Brand Name * <input type="text" name="name" value="<?= htmlspecialchars($editBrand['name'] ?? '') ?>" required></label>
        <div class="form-actions">
          <button type="submit" class="btn btn-primary"><?= $editBrand ? 'Update' : 'Add' ?> Brand</button>
          <a href="<?= baseUrl('staff/vehicles.php') ?>#brands" class="btn btn-outline">Cancel</a>
        </div>
      </form>
    </div>
  <?php endif; ?>

  <div class="admin-table-wrap">
    <table class="admin-data-table">
      <thead><tr><th>Brand</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($brands as $br): ?>
          <tr>
            <td><strong><?= htmlspecialchars($br['name']) ?></strong></td>
            <td>
              <form method="post" class="admin-toggle-form">
                <?= authContextField() ?>
                <input type="hidden" name="action" value="toggle_brand">
                <input type="hidden" name="brand_id" value="<?= (int)$br['id'] ?>">
                <label><input type="checkbox" name="is_active" value="1" <?= $br['is_active'] ? 'checked' : '' ?> onchange="this.form.submit()"> Active</label>
              </form>
            </td>
            <td><a href="?edit_brand=<?= (int)$br['id'] ?>#brands" class="btn btn-outline" style="font-size:.8rem;padding:5px 12px;">Edit</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<!-- ===== MODELS ===== -->
<section class="admin-card admin-page-stack" id="models">
  <div class="admin-page-head">
    <h2 style="margin:0;">Motorcycle Models</h2>
    <a href="?edit_model=new#models" class="btn btn-primary" style="font-size:.85rem;"><i class="fas fa-plus"></i> Add Model</a>
  </div>

  <?php if ($editModel || (isset($_GET['edit_model']) && $_GET['edit_model'] === 'new')): ?>
    <div class="admin-form-box">
      <h3><?= $editModel ? 'Edit Model' : 'New Model' ?></h3>
      <form method="post">
        <?= authContextField() ?>
        <input type="hidden" name="action" value="save_model">
        <input type="hidden" name="model_id" value="<?= $editModel ? (int)$editModel['id'] : 0 ?>">
        <div class="form-grid-2">
          <label>Brand *
            <select name="brand_id" required>
              <option value="">— Select Brand —</option>
              <?php foreach ($brands as $br): ?>
                <option value="<?= (int)$br['id'] ?>" <?= (isset($editModel['brand_id']) && (int)$editModel['brand_id'] === (int)$br['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($br['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Type *
            <select name="type_id" required>
              <option value="">— Select Type —</option>
              <?php foreach ($types as $ty): ?>
                <option value="<?= (int)$ty['id'] ?>" <?= (isset($editModel['type_id']) && (int)$editModel['type_id'] === (int)$ty['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($ty['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Model Name * <input type="text" name="name" value="<?= htmlspecialchars($editModel['name'] ?? '') ?>" required></label>
          <label>Engine CC * <input type="number" name="cc" value="<?= (int)($editModel['cc'] ?? 125) ?>" min="50" max="2000" required></label>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn-primary"><?= $editModel ? 'Update' : 'Add' ?> Model</button>
          <a href="<?= baseUrl('staff/vehicles.php') ?>#models" class="btn btn-outline">Cancel</a>
        </div>
      </form>
    </div>
  <?php endif; ?>

  <div class="admin-table-wrap">
    <table class="admin-data-table">
      <thead><tr><th>Model</th><th>Brand</th><th>Type</th><th>CC</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($models as $m): ?>
          <tr>
            <td><strong><?= htmlspecialchars($m['name']) ?></strong></td>
            <td><?= htmlspecialchars($m['brand_name']) ?></td>
            <td><?= htmlspecialchars($m['type_name']) ?></td>
            <td><?= (int)$m['cc'] ?> cc</td>
            <td>
              <form method="post" class="admin-toggle-form">
                <?= authContextField() ?>
                <input type="hidden" name="action" value="toggle_model">
                <input type="hidden" name="model_id" value="<?= (int)$m['id'] ?>">
                <label><input type="checkbox" name="is_active" value="1" <?= $m['is_active'] ? 'checked' : '' ?> onchange="this.form.submit()"> Active</label>
              </form>
            </td>
            <td><a href="?edit_model=<?= (int)$m['id'] ?>#models" class="btn btn-outline" style="font-size:.8rem;padding:5px 12px;">Edit</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<?= authContextScriptTag() ?>
</main></div></div></body></html>
