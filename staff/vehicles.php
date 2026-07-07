<?php
$pageTitle = 'Vehicle Options';
require_once __DIR__ . '/../includes/staff-sidebar.php';
require_once __DIR__ . '/../includes/db.php';

function normalizeCcInput(string $cc): ?int
{
    if (preg_match('/([1-9][0-9]{1,3})/', $cc, $match)) {
        return (int)$match[1];
    }
    return null;
}

function normalizeSelectedMotorcycleRows(array $payload): array
{
    if (isset($payload['selected_candidates']) && is_array($payload['selected_candidates'])) {
        $type = cleanMotorcycleLabel($payload['type'] ?? '');
        $brand = cleanMotorcycleLabel($payload['brand'] ?? '');
        $rows = [];

        foreach ($payload['selected_candidates'] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $rows[] = [
                'type' => cleanMotorcycleLabel((string)($row['type'] ?? $type)),
                'brand' => cleanMotorcycleLabel((string)($row['brand'] ?? $brand)),
                'model' => cleanMotorcycleLabel((string)($row['model'] ?? '')),
                'cc' => normalizeCcInput((string)($row['cc'] ?? '')),
            ];
        }

        return array_values(array_filter($rows, static function (array $row): bool {
            return $row['type'] !== '' && $row['brand'] !== '' && $row['model'] !== '' && !empty($row['cc']);
        }));
    }

    $type = cleanMotorcycleLabel($payload['type'] ?? '');
    $brand = cleanMotorcycleLabel($payload['brand'] ?? '');
    $model = cleanMotorcycleLabel($payload['model'] ?? '');
    $cc = cleanMotorcycleLabel($payload['cc'] ?? '');
    if ($type === '' || $brand === '' || $model === '' || $cc === '') {
        return [];
    }

    return [[
        'type' => $type,
        'brand' => $brand,
        'model' => $model,
        'cc' => $cc,
    ]];
}

function saveMotorcycleCatalogRow(array $row, ?int $typeId = null, ?int $brandId = null): void
{
    $type = cleanMotorcycleLabel($row['type'] ?? '');
    $brand = cleanMotorcycleLabel($row['brand'] ?? '');
    $model = cleanMotorcycleLabel($row['model'] ?? '');
    $cc = normalizeCcInput((string)($row['cc'] ?? ''));

    if ($type === '' || $brand === '' || $model === '' || !$cc) {
        throw new InvalidArgumentException('Type, brand, model, and cc are required.');
    }

    $typeId = $typeId ?: findOrCreateTypeId($type);
    $brandId = $brandId ?: findOrCreateBrandId($brand);

    $duplicate = fetchOne(
        "SELECT id FROM motorcycle_models WHERE brand_id = ? AND type_id = ? AND LOWER(name) = LOWER(?) AND cc = ? LIMIT 1",
        [$brandId, $typeId, $model, $cc]
    );
    if ($duplicate) {
        return;
    }

    getDB()->prepare(
        "INSERT INTO motorcycle_models (brand_id, type_id, name, cc, cc_source, cc_confidence, last_verified_at, is_active)
         VALUES (?, ?, ?, ?, NULL, NULL, NULL, 1)"
    )->execute([$brandId, $typeId, $model, $cc]);
}

function findOrCreateTypeId(string $typeName): int
{
    $typeName = cleanMotorcycleLabel($typeName);
    $existing = fetchOne("SELECT id FROM motorcycle_types WHERE LOWER(name) = LOWER(?) LIMIT 1", [$typeName]);
    if ($existing) {
        return (int)$existing['id'];
    }

    getDB()->prepare(
        "INSERT INTO motorcycle_types (name, description, is_active) VALUES (?, NULL, 1)"
    )->execute([$typeName]);

    return (int)getDB()->lastInsertId();
}

function findOrCreateBrandId(string $brandName): int
{
    $brandName = cleanMotorcycleLabel($brandName);
    $existing = fetchOne("SELECT id FROM motorcycle_brands WHERE LOWER(name) = LOWER(?) LIMIT 1", [$brandName]);
    if ($existing) {
        return (int)$existing['id'];
    }

    getDB()->prepare(
        "INSERT INTO motorcycle_brands (name, is_active) VALUES (?, 1)"
    )->execute([$brandName]);

    return (int)getDB()->lastInsertId();
}

function saveMotorcycleCatalogEntry(array $payload, ?int $modelId = null): void
{
    $rows = normalizeSelectedMotorcycleRows($payload);
    if (!$rows) {
        throw new InvalidArgumentException('Type, brand, model, and cc are required.');
    }

    $type = cleanMotorcycleLabel($payload['type'] ?? $rows[0]['type']);
    $brand = cleanMotorcycleLabel($payload['brand'] ?? $rows[0]['brand']);
    $typeId = findOrCreateTypeId($type);
    $brandId = findOrCreateBrandId($brand);

    if ($modelId) {
        $row = $rows[0];
        $cc = normalizeCcInput((string)$row['cc']);
        getDB()->prepare(
            "UPDATE motorcycle_models
             SET brand_id = ?, type_id = ?, name = ?, cc = ?, cc_source = NULL, cc_confidence = NULL, last_verified_at = NULL, is_active = 1
             WHERE id = ?"
        )->execute([
            $brandId,
            $typeId,
            cleanMotorcycleLabel($row['model']),
            $cc,
            $modelId,
        ]);
        return;
    }

    $db = getDB();
    $db->beginTransaction();
    try {
        foreach ($rows as $row) {
            saveMotorcycleCatalogRow($row, $typeId, $brandId);
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_motorcycle_catalog') {
        $modelId = (int)($_POST['motorcycle_id'] ?? 0);

        try {
            saveMotorcycleCatalogEntry($_POST, $modelId ?: null);
            flashMessage('veh_success', $modelId ? 'Motorcycle updated.' : 'Motorcycle added to the catalog.');
        } catch (Throwable $e) {
            flashMessage('veh_error', $e->getMessage());
        }

        redirect(baseUrl('staff/vehicles.php'));
    }

    if ($action === 'delete_motorcycle_catalog') {
        $modelId = (int)($_POST['motorcycle_id'] ?? 0);
        if ($modelId > 0) {
            $db = getDB();
            try {
                $db->beginTransaction();
                $db->prepare(
                    "UPDATE bookings
                     SET vehicle_id = NULL
                     WHERE vehicle_id IN (SELECT id FROM customer_vehicles WHERE model_id = ?)"
                )->execute([$modelId]);
                $db->prepare("DELETE FROM customer_vehicles WHERE model_id = ?")->execute([$modelId]);
                $db->prepare("DELETE FROM motorcycle_models WHERE id = ?")->execute([$modelId]);
                $db->commit();
                flashMessage('veh_success', 'Motorcycle removed from the catalog.');
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                flashMessage('veh_error', 'Unable to delete this motorcycle.');
            }
        }

        redirect(baseUrl('staff/vehicles.php'));
    }

    if ($action === 'delete_motorcycle_catalog_bulk') {
        $modelIds = array_values(array_unique(array_filter(array_map('intval', $_POST['motorcycle_ids'] ?? []))));

        if (!$modelIds) {
            flashMessage('veh_error', 'Select at least one motorcycle to delete.');
            redirect(baseUrl('staff/vehicles.php'));
        }

        $db = getDB();
        try {
            $placeholders = implode(',', array_fill(0, count($modelIds), '?'));
            $db->beginTransaction();
            $db->prepare(
                "UPDATE bookings
                 SET vehicle_id = NULL
                 WHERE vehicle_id IN (SELECT id FROM customer_vehicles WHERE model_id IN ($placeholders))"
            )->execute($modelIds);
            $db->prepare("DELETE FROM customer_vehicles WHERE model_id IN ($placeholders)")->execute($modelIds);
            $db->prepare("DELETE FROM motorcycle_models WHERE id IN ($placeholders)")->execute($modelIds);
            $db->commit();
            $deletedCount = count($modelIds);
            flashMessage('veh_success', $deletedCount . ' motorcycle' . ($deletedCount === 1 ? '' : 's') . ' removed from the catalog.');
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            flashMessage('veh_error', 'Unable to delete the selected motorcycles.');
        }

        redirect(baseUrl('staff/vehicles.php'));
    }
}

$flash = getFlash('veh_success');
$flashErr = getFlash('veh_error');

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

$catalogOptionRows = $catalogRows;
$vehicleTypeFilter = trim($_GET['vehicle_type'] ?? '');
$vehicleBrandFilter = trim($_GET['vehicle_brand'] ?? '');
$vehicleModelFilter = trim($_GET['vehicle_model'] ?? '');
$vehicleCcFilter = trim($_GET['vehicle_cc'] ?? '');

$catalogTypes = array_values(array_unique(array_map(
    fn(array $row) => $row['type_name'],
    $catalogOptionRows
)));
$catalogBrands = array_values(array_unique(array_map(
    fn(array $row) => $row['brand_name'],
    $catalogOptionRows
)));
$catalogModels = array_values(array_unique(array_map(
    fn(array $row) => $row['model_name'],
    $catalogOptionRows
)));
$catalogCcs = array_values(array_unique(array_map(
    fn(array $row) => (int)$row['cc'],
    $catalogOptionRows
)));
sort($catalogTypes, SORT_NATURAL | SORT_FLAG_CASE);
sort($catalogBrands, SORT_NATURAL | SORT_FLAG_CASE);
sort($catalogModels, SORT_NATURAL | SORT_FLAG_CASE);
sort($catalogCcs, SORT_NUMERIC);

if ($vehicleTypeFilter !== '' || $vehicleBrandFilter !== '' || $vehicleModelFilter !== '' || $vehicleCcFilter !== '') {
    $catalogRows = array_values(array_filter($catalogRows, function (array $row) use ($vehicleTypeFilter, $vehicleBrandFilter, $vehicleModelFilter, $vehicleCcFilter): bool {
        if ($vehicleTypeFilter !== '' && strcasecmp($row['type_name'], $vehicleTypeFilter) !== 0) {
            return false;
        }
        if ($vehicleBrandFilter !== '' && strcasecmp($row['brand_name'], $vehicleBrandFilter) !== 0) {
            return false;
        }
        if ($vehicleModelFilter !== '' && !str_contains(strtolower($row['model_name']), strtolower($vehicleModelFilter))) {
            return false;
        }
        if ($vehicleCcFilter !== '' && !str_contains((string)$row['cc'], preg_replace('/[^0-9]/', '', $vehicleCcFilter))) {
            return false;
        }
        return true;
    }));
}
?>

<section class="admin-card settings-admin-shell">
  <div class="admin-page-head">
    <div>
      <h1>Vehicle Options</h1>
      <p>Build the customer motorcycle catalog with a modal workflow and automatic specification lookup.</p>
    </div>
  </div>

  <?php if ($flash): ?><div class="alert success"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
  <?php if ($flashErr): ?><div class="alert error"><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>

  <div class="vehicle-admin-shell" id="vehicleManager" data-base-url="<?= htmlspecialchars(baseUrl('')) ?>">
    <div class="vehicle-admin-hero">
      <div>
        <h2>Motorcycle Management</h2>
        <p>Build the customer motorcycle catalog with a modal workflow and automatic specification lookup.</p>
      </div>
      <button type="button" class="btn btn-primary" id="openMotorcycleWizard">Add Motorcycle</button>
    </div>

    <div class="vehicle-admin-toolbar">
      <div></div>
    </div>

    <section class="admin-card" style="margin-top:20px;">
      <div class="vehicle-list-header">
        <div>
          <h2>Motorcycle Catalog</h2>
          <p><?= count($catalogRows) ?> record<?= count($catalogRows) === 1 ? '' : 's' ?> in the current view.</p>
        </div>
      </div>

      <form method="get" class="vehicle-admin-filters vehicle-admin-filters--stacked" style="margin-bottom:18px;">
        <select name="vehicle_type">
          <option value="">Type</option>
          <?php foreach ($catalogTypes as $catalogType): ?>
            <option value="<?= htmlspecialchars($catalogType) ?>" <?= strcasecmp($vehicleTypeFilter, $catalogType) === 0 ? 'selected' : '' ?>>
              <?= htmlspecialchars($catalogType) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <select name="vehicle_brand">
          <option value="">Brand</option>
          <?php foreach ($catalogBrands as $catalogBrand): ?>
            <option value="<?= htmlspecialchars($catalogBrand) ?>" <?= strcasecmp($vehicleBrandFilter, $catalogBrand) === 0 ? 'selected' : '' ?>>
              <?= htmlspecialchars($catalogBrand) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <select name="vehicle_model">
          <option value="">Model</option>
          <?php foreach ($catalogModels as $catalogModel): ?>
            <option value="<?= htmlspecialchars($catalogModel) ?>" <?= strcasecmp($vehicleModelFilter, $catalogModel) === 0 ? 'selected' : '' ?>>
              <?= htmlspecialchars($catalogModel) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <select name="vehicle_cc">
          <option value="">Engine CC</option>
          <?php foreach ($catalogCcs as $catalogCc): ?>
            <option value="<?= (int)$catalogCc ?>cc" <?= preg_replace('/[^0-9]/', '', $vehicleCcFilter) === (string)(int)$catalogCc ? 'selected' : '' ?>>
              <?= (int)$catalogCc ?>cc
            </option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-outline">Filter</button>
        <a href="<?= baseUrl('staff/vehicles.php') ?>" class="btn btn-outline">Reset</a>
      </form>

      <?php if ($catalogRows): ?>
        <form method="post" id="bulkMotorcycleDeleteForm" onsubmit="return confirm('Delete the selected motorcycles from the catalog?')">
          <?= authContextField() ?>
          <input type="hidden" name="action" value="delete_motorcycle_catalog_bulk">
        </form>
        <div class="vehicle-bulk-actions">
          <button type="submit" class="btn btn-outline danger-btn" id="bulkMotorcycleDeleteBtn" form="bulkMotorcycleDeleteForm" disabled>Delete Selected</button>
        </div>
        <div class="vehicle-table-wrap">
          <table class="vehicle-table">
            <thead>
                <tr>
                  <th class="vehicle-select-col">
                    <input type="checkbox" id="selectAllMotorcycles" aria-label="Select all motorcycles">
                  </th>
                  <th>Type</th>
                  <th>Brand</th>
                  <th>Model</th>
                  <th>Engine CC</th>
                  <th>Actions</th>
                </tr>
            </thead>
            <tbody>
              <?php foreach ($catalogRows as $row): ?>
                <tr>
                  <td class="vehicle-select-col">
                    <input type="checkbox" name="motorcycle_ids[]" value="<?= (int)$row['id'] ?>" class="js-motorcycle-select" form="bulkMotorcycleDeleteForm" aria-label="Select <?= htmlspecialchars($row['brand_name'] . ' ' . $row['model_name']) ?>">
                  </td>
                  <td><?= htmlspecialchars($row['type_name']) ?></td>
                  <td><?= htmlspecialchars($row['brand_name']) ?></td>
                  <td><?= htmlspecialchars($row['model_name']) ?></td>
                  <td><?= (int)$row['cc'] ?>cc</td>
                  <td>
                    <div class="vehicle-table-actions">
                      <button
                        type="button"
                        class="btn btn-outline js-edit-motorcycle"
                        data-id="<?= (int)$row['id'] ?>"
                        data-type="<?= htmlspecialchars($row['type_name']) ?>"
                        data-brand="<?= htmlspecialchars($row['brand_name']) ?>"
                        data-model="<?= htmlspecialchars($row['model_name']) ?>"
                        data-cc="<?= (int)$row['cc'] ?>cc"
                      >
                        Edit
                      </button>
                      <form method="post" onsubmit="return confirm('Delete this motorcycle from the catalog?')" style="display:inline;">
                        <?= authContextField() ?>
                        <input type="hidden" name="action" value="delete_motorcycle_catalog">
                        <input type="hidden" name="motorcycle_id" value="<?= (int)$row['id'] ?>">
                        <button type="submit" class="btn btn-outline danger-btn">Delete</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="empty-note">No motorcycles matched the current filters.</p>
      <?php endif; ?>
    </section>
  </div>

  <div class="vehicle-modal" id="motorcycleEditModal" aria-hidden="true">
    <div class="vehicle-modal__backdrop" data-close-edit-modal></div>
    <div class="vehicle-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="motorcycleEditTitle">
      <button type="button" class="vehicle-modal__close" data-close-edit-modal aria-label="Close">x</button>
      <div class="vehicle-modal__step is-active">
        <div class="vehicle-modal__eyebrow">Edit Motorcycle</div>
        <h3 id="motorcycleEditTitle">Update Catalog Entry</h3>
        <p>Change the saved motorcycle record directly.</p>

        <form method="post" class="vehicle-quick-form" id="motorcycleEditForm">
          <?= authContextField() ?>
          <input type="hidden" name="action" value="save_motorcycle_catalog">
          <input type="hidden" name="motorcycle_id" id="editMotorcycleId" value="">

          <label>
            <span>Motorcycle type</span>
            <input type="text" name="type" id="editMotorcycleType" placeholder="Scooter">
          </label>
          <label>
            <span>Brand</span>
            <input type="text" name="brand" id="editMotorcycleBrand" placeholder="Honda">
          </label>
          <label>
            <span>Model</span>
            <input type="text" name="model" id="editMotorcycleModel" placeholder="Click 125">
          </label>
          <label>
            <span>Engine cc</span>
            <input type="text" name="cc" id="editMotorcycleCc" placeholder="125cc">
          </label>

          <div class="vehicle-quick-actions">
            <button type="submit" class="btn btn-primary" id="editMotorcycleSaveBtn">Update motorcycle</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="vehicle-modal" id="motorcycleWizardModal" aria-hidden="true">
    <div class="vehicle-modal__backdrop" data-close-modal></div>
    <div class="vehicle-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="motorcycleWizardTitle">
      <button type="button" class="vehicle-modal__close" data-close-modal aria-label="Close">x</button>

      <div class="vehicle-modal__step is-active" data-step="1">
        <div class="vehicle-modal__eyebrow">Step 1 of 3</div>
        <h3 id="motorcycleWizardTitle">Add Motorcycle Type</h3>
        <p>Enter the motorcycle type exactly as you want it saved.</p>
        <label class="vehicle-modal__field">
          <span>Motorcycle Type</span>
          <input type="text" id="wizardTypeInput" placeholder="Scooter">
        </label>
        <div class="vehicle-modal__actions">
          <button type="button" class="btn btn-primary" data-next-step>Next</button>
        </div>
      </div>

      <div class="vehicle-modal__step" data-step="2">
        <div class="vehicle-modal__eyebrow">Step 2 of 3</div>
        <h3>Enter Motorcycle Brand</h3>
        <p>Brand names are manually entered by staff.</p>
        <label class="vehicle-modal__field">
          <span>Brand Name</span>
          <input type="text" id="wizardBrandInput" placeholder="Honda">
        </label>
        <div class="vehicle-modal__actions vehicle-modal__actions--split">
          <button type="button" class="btn btn-outline" data-prev-step>Back</button>
          <button type="button" class="btn btn-primary" data-next-step>Next</button>
        </div>
      </div>

      <div class="vehicle-modal__step" data-step="3">
        <div class="vehicle-modal__eyebrow">Step 3 of 3</div>
        <h3>Enter Motorcycle Model</h3>
        <p>We will search for the engine specification after this step.</p>
        <label class="vehicle-modal__field">
          <span>Model Name</span>
          <input type="text" id="wizardModelInput" placeholder="Click 125">
        </label>
        <div class="vehicle-modal__hint" id="wizardSearchStatus"></div>
        <div class="vehicle-modal__actions vehicle-modal__actions--split">
          <button type="button" class="btn btn-outline" data-prev-step>Back</button>
          <button type="button" class="btn btn-primary" id="searchMotorcycleSpecBtn">Search Specification</button>
        </div>
      </div>

      <div class="vehicle-modal__step" data-step="result">
        <div class="vehicle-modal__eyebrow">Review Result</div>
        <h3>Motorcycle Information Found</h3>
        <p id="wizardResultMessage">Review the engine cc before saving.</p>

          <div class="vehicle-result-card">
          <div><span>Type</span><strong id="resultTypeValue">-</strong></div>
          <div><span>Brand</span><strong id="resultBrandValue">-</strong></div>
          <div><span>Model</span><strong id="resultModelValue">-</strong></div>
          <div>
            <span>Engine CC</span>
            <strong id="resultCcValue">-</strong>
            <input type="text" id="manualCcInput" class="vehicle-manual-cc" placeholder="125cc" hidden>
          </div>
        </div>

        <div class="vehicle-candidate-panel" id="candidatePanel" hidden>
          <div class="vehicle-candidate-panel__header">
            <strong>Related results</strong>
            <span>Select one or more variants to save.</span>
          </div>
          <div class="vehicle-candidate-list" id="candidateList"></div>
        </div>

        <form method="post" id="wizardSaveForm">
          <?= authContextField() ?>
          <input type="hidden" name="action" value="save_motorcycle_catalog">
          <div id="candidateHiddenInputs"></div>
          <input type="hidden" name="type" id="saveTypeInput">

          <div class="vehicle-modal__actions vehicle-modal__actions--split">
            <button type="button" class="btn btn-outline" id="wizardEditBtn">Edit</button>
            <button type="submit" class="btn btn-primary" id="wizardSaveBtn">Save</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</section>

<script src="<?= baseUrl('assets/js/main.js?v=' . filemtime(__DIR__ . '/../assets/js/main.js')) ?>"></script>
</main></div></div></body></html>
