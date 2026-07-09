<?php
$pageTitle = 'Vehicle Options';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
requireStaff();

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

require_once __DIR__ . '/../includes/staff-sidebar.php';

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

<section class="vhx-page">
  <?php if ($flash): ?><div class="alert success"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
  <?php if ($flashErr): ?><div class="alert error"><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>

  <?php
  $vehicleOptionsUi = [
      'reset_url' => baseUrl('staff/vehicles.php'),
      'filter_hidden' => '',
  ];
  require __DIR__ . '/../includes/vehicle-options-ui.php';
  ?>
</section>


<script src="<?= baseUrl('assets/js/main.js?v=' . filemtime(__DIR__ . '/../assets/js/main.js')) ?>"></script>
</main></div></div></body></html>
