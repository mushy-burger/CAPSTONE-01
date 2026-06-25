<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
requireLogin();

$user = getCurrentUser();
$catalogRows = fetchAllRows(
    "SELECT
        mm.id,
        mm.name AS model_name,
        mm.cc,
        mt.id AS type_id,
        mt.name AS type_name,
        mb.id AS brand_id,
        mb.name AS brand_name
     FROM motorcycle_models mm
     INNER JOIN motorcycle_types mt ON mt.id = mm.type_id
     INNER JOIN motorcycle_brands mb ON mb.id = mm.brand_id
     ORDER BY mt.name, mb.name, mm.name"
);

$typeOptions = array_values(array_unique(array_map(fn(array $row) => $row['type_name'], $catalogRows)));
$brandOptions = array_values(array_unique(array_map(fn(array $row) => $row['brand_name'], $catalogRows)));
sort($typeOptions, SORT_NATURAL | SORT_FLAG_CASE);
sort($brandOptions, SORT_NATURAL | SORT_FLAG_CASE);

$editId = (int)($_GET['edit'] ?? 0);
$editVehicle = null;
$editTypeName = '';
$editBrandName = '';
if ($editId) {
    $editVehicle = fetchOne("SELECT * FROM customer_vehicles WHERE id = ? AND user_id = ?", [$editId, $user['id']]);
    if (!$editVehicle) {
        $editId = 0;
    } else {
        $editType = fetchOne("SELECT name FROM motorcycle_types WHERE id = ? AND is_active = 1", [$editVehicle['type_id']]);
        $editBrand = fetchOne("SELECT name FROM motorcycle_brands WHERE id = ? AND is_active = 1", [$editVehicle['brand_id']]);
        $editTypeName = $editType['name'] ?? '';
        $editBrandName = $editBrand['name'] ?? '';
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add';

    if ($action === 'delete') {
        $vid = (int)($_POST['vehicle_id'] ?? 0);
        getDB()->prepare("DELETE FROM customer_vehicles WHERE id = ? AND user_id = ?")
            ->execute([$vid, $user['id']]);
        flashMessage('vehicle_msg', 'Motorcycle removed.');
        redirect(baseUrl('my-vehicle.php'));
    }

    $typeName = trim($_POST['type_name'] ?? '');
    $brandName = trim($_POST['brand_name'] ?? '');
    $modelId = (int)($_POST['model_id'] ?? 0);
    $year = (($_POST['year'] ?? '') !== '') ? (int)$_POST['year'] : null;
    $plate = sanitize($_POST['plate_number'] ?? '');
    $modelRow = null;
    foreach ($catalogRows as $row) {
        if ((int)$row['id'] === $modelId) {
            $modelRow = $row;
            break;
        }
    }

    $typeOk = $modelRow && $typeName !== '' && strcasecmp($typeName, $modelRow['type_name']) === 0;
    $brandOk = $modelRow && $brandName !== '' && strcasecmp($brandName, $modelRow['brand_name']) === 0;
    $cc = $modelRow ? (int)$modelRow['cc'] : 0;

    if (!$typeOk || !$brandOk || !$modelRow || $cc <= 0) {
        $error = 'Please select motorcycle type, brand, and model from the list.';
    } elseif ($action === 'edit') {
        $vid = (int)($_POST['vehicle_id'] ?? 0);
        getDB()->prepare(
            "UPDATE customer_vehicles
             SET type_id=?, brand_id=?, model_id=?, cc=?, year=?, plate_number=?
             WHERE id=? AND user_id=?"
        )->execute([(int)$modelRow['type_id'], (int)$modelRow['brand_id'], $modelId, $cc, $year, $plate, $vid, $user['id']]);
        flashMessage('vehicle_msg', 'Motorcycle updated.');
        redirect(baseUrl('my-vehicle.php'));
    } else {
        getDB()->prepare(
            "INSERT INTO customer_vehicles (user_id, type_id, brand_id, model_id, cc, year, plate_number)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        )->execute([$user['id'], (int)$modelRow['type_id'], (int)$modelRow['brand_id'], $modelId, $cc, $year, $plate]);
        flashMessage('vehicle_msg', 'Motorcycle saved.');
        redirect(baseUrl('my-vehicle.php'));
    }
}

$vehicles = getCustomerVehicles($user['id']);
$flash = getFlash('vehicle_msg');
$pageTitle = 'My Vehicle - MotoTrack';
require_once __DIR__ . '/includes/header.php';
?>

<section class="section container form-layout vehicle-page-layout">
  <form class="form-panel" method="post">
    <?= authContextField() ?>

    <?php if ($editVehicle): ?>
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="vehicle_id" value="<?= (int)$editVehicle['id'] ?>">
      <h2>Edit Motorcycle</h2>
    <?php else: ?>
      <input type="hidden" name="action" value="add">
      <h2>Add Motorcycle</h2>
    <?php endif; ?>

    <?php if ($flash): ?><div class="alert success"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <label>Motorcycle type
      <select name="type_name" id="typeSelect" required>
        <option value="">Select type</option>
        <?php foreach ($typeOptions as $typeName): ?>
          <option value="<?= htmlspecialchars($typeName) ?>" <?= $editTypeName !== '' && strcasecmp($editTypeName, $typeName) === 0 ? 'selected' : '' ?>>
            <?= htmlspecialchars($typeName) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>

    <label>Brand
      <select name="brand_name" id="brandSelect" required>
        <option value="">Select brand</option>
        <?php foreach ($brandOptions as $brandName): ?>
          <option value="<?= htmlspecialchars($brandName) ?>" <?= $editBrandName !== '' && strcasecmp($editBrandName, $brandName) === 0 ? 'selected' : '' ?>>
            <?= htmlspecialchars($brandName) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>

    <label>Model
      <select name="model_id" id="modelSelect" required>
        <option value="">Select model</option>
        <?php foreach ($catalogRows as $m): ?>
          <option value="<?= (int)$m['id'] ?>"
                  data-brand="<?= htmlspecialchars($m['brand_name']) ?>"
                  data-type="<?= htmlspecialchars($m['type_name']) ?>"
                  data-cc="<?= (int)$m['cc'] ?>"
            <?= $editVehicle && (int)$editVehicle['model_id'] === (int)$m['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($m['model_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>

    <label>Engine cc
      <div id="ccDisplay" class="cc-display"><?= $editVehicle ? (int)$editVehicle['cc'] . 'cc' : '-' ?></div>
    </label>

    <label>Year
      <input type="number" name="year" min="1970" max="<?= date('Y') + 1 ?>"
             value="<?= $editVehicle && $editVehicle['year'] ? (int)$editVehicle['year'] : '' ?>">
    </label>

    <label>Plate number
      <input type="text" name="plate_number"
             value="<?= $editVehicle ? htmlspecialchars($editVehicle['plate_number'] ?? '') : '' ?>">
    </label>

    <div class="form-actions">
      <button class="btn btn-primary" type="submit">
        <?= $editVehicle ? 'Update motorcycle' : 'Save motorcycle' ?>
      </button>
      <?php if ($editVehicle): ?>
        <a href="<?= baseUrl('my-vehicle.php') ?>" class="btn btn-outline">Cancel</a>
      <?php endif; ?>
    </div>
  </form>

  <aside class="summary-box">
    <h2>My Motorcycles</h2>
    <?php if ($vehicles): ?>
      <div class="motorcycle-list">
        <?php foreach ($vehicles as $v): ?>
          <article class="motorcycle-card <?= $editVehicle && (int)$editVehicle['id'] === (int)$v['id'] ? 'is-active' : '' ?>">
            <div class="motorcycle-details">
              <div class="detail-row">
                <span class="label">BRAND</span>
                <span class="value"><?= htmlspecialchars($v['brand_name']) ?></span>
              </div>
              <div class="detail-row">
                <span class="label">MODEL</span>
                <span class="value"><?= htmlspecialchars($v['model_name']) ?></span>
              </div>
              <div class="detail-row">
                <span class="label">TYPE</span>
                <span class="value"><?= htmlspecialchars($v['type_name']) ?></span>
              </div>
              <div class="detail-row">
                <span class="label">ENGINE CC</span>
                <span class="value"><?= (int)$v['cc'] ?>cc</span>
              </div>
              <div class="detail-row">
                <span class="label">YEAR</span>
                <span class="value"><?= $v['year'] ? (int)$v['year'] : '-' ?></span>
              </div>
              <div class="detail-row">
                <span class="label">PLATE</span>
                <span class="value<?= $v['plate_number'] ? ' plate-badge' : '' ?>"><?= $v['plate_number'] ? htmlspecialchars($v['plate_number']) : '-' ?></span>
              </div>
            </div>

            <div class="motorcycle-actions">
              <a href="<?= baseUrl('book-service.php?vehicle_id=' . (int)$v['id']) ?>" class="book-btn">
                <i class="fas fa-calendar-check"></i>
                <span>Book Service</span>
              </a>
              <a href="<?= baseUrl('my-vehicle.php?edit=' . (int)$v['id']) ?>" class="edit-btn">
                <i class="fas fa-pen"></i>
                <span>Edit</span>
              </a>
              <form method="post" class="motorcycle-remove-form" onsubmit="return confirm('Remove this motorcycle?')">
                <?= authContextField() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="vehicle_id" value="<?= (int)$v['id'] ?>">
                <button type="submit" class="remove-btn">
                  <i class="fas fa-trash"></i>
                  <span>Remove</span>
                </button>
              </form>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p>No saved motorcycles yet.</p>
    <?php endif; ?>
  </aside>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
