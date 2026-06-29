<?php
$pageTitle = 'Settings';
require_once __DIR__ . '/../includes/admin-sidebar.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/MotorcycleApiService.php';

function saveOptimizedHeroImage(array $file, string $basename = 'hero_image', int $maxWidth = 1920, int $webpQuality = 90): string
{
    $tmpPath = $file['tmp_name'] ?? '';
    if (!is_uploaded_file($tmpPath)) {
        throw new RuntimeException('Invalid hero image upload.');
    }

    $imageInfo = @getimagesize($tmpPath);
    if (!$imageInfo) {
        throw new RuntimeException('Unable to read the uploaded hero image.');
    }

    [$sourceWidth, $sourceHeight, $imageType] = $imageInfo;
    $extensionMap = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
        IMAGETYPE_GIF => 'gif',
    ];
    $fallbackExt = $extensionMap[$imageType] ?? strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if ($fallbackExt === '') {
        $fallbackExt = 'jpg';
    }

    $uploadDir = __DIR__ . '/../uploads';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $gdAvailable = function_exists('imagecreatetruecolor');
    if (!$gdAvailable) {
        $filename = $basename . '_' . time() . '.' . $fallbackExt;
        $destination = $uploadDir . '/' . $filename;
        if (!move_uploaded_file($tmpPath, $destination)) {
            throw new RuntimeException('Failed to save the uploaded hero image.');
        }

        foreach (glob($uploadDir . '/' . $basename . '*.*') ?: [] as $existingFile) {
            if (basename($existingFile) !== $filename && is_file($existingFile)) {
                @unlink($existingFile);
            }
        }

        return $filename;
    }

    $targetWidth = $sourceWidth > $maxWidth ? $maxWidth : $sourceWidth;
    $targetHeight = (int)round(($sourceHeight / max($sourceWidth, 1)) * $targetWidth);

    switch ($imageType) {
        case IMAGETYPE_JPEG:
            $source = @imagecreatefromjpeg($tmpPath);
            break;
        case IMAGETYPE_PNG:
            $source = @imagecreatefrompng($tmpPath);
            break;
        case IMAGETYPE_WEBP:
            $source = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmpPath) : null;
            break;
        case IMAGETYPE_GIF:
            $source = @imagecreatefromgif($tmpPath);
            break;
        default:
            $source = null;
            break;
    }

    if (!$source) {
        throw new RuntimeException('Unsupported hero image format on this server.');
    }

    $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
    imagealphablending($canvas, true);
    imagesavealpha($canvas, true);
    $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
    imagefill($canvas, 0, 0, $transparent);

    imagecopyresampled(
        $canvas,
        $source,
        0,
        0,
        0,
        0,
        $targetWidth,
        $targetHeight,
        $sourceWidth,
        $sourceHeight
    );

    $hasAlpha = in_array($imageType, [IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true);
    $filename = $basename . '_' . time() . ($hasAlpha ? '.png' : '.webp');
    $destination = $uploadDir . '/' . $filename;

    if ($hasAlpha) {
        if (!imagepng($canvas, $destination, 6)) {
            imagedestroy($canvas);
            imagedestroy($source);
            throw new RuntimeException('Failed to save the hero image.');
        }
    } else {
        if (!function_exists('imagewebp') || !imagewebp($canvas, $destination, $webpQuality)) {
            imagedestroy($canvas);
            imagedestroy($source);
            throw new RuntimeException('Failed to optimize and save the hero image.');
        }
    }

    imagedestroy($canvas);
    imagedestroy($source);

    foreach (glob($uploadDir . '/' . $basename . '*.*') ?: [] as $existingFile) {
        if (basename($existingFile) !== $filename && is_file($existingFile)) {
            @unlink($existingFile);
        }
    }

    return $filename;
}

function parseAppliesTo(string $applies, array $allIds): array
{
    if ($applies === 'all' || $applies === '') {
        return $allIds;
    }
    return array_map('intval', explode(',', $applies));
}

function tabStyle(bool $active): string
{
    return 'padding:.6rem 1.4rem;font-weight:600;text-decoration:none;border-bottom:2px solid '
        . ($active ? '#c0392b;color:#c0392b' : 'transparent;color:#555')
        . ';margin-bottom:-2px;display:inline-block;';
}

function normalizeCcInput(string $cc): ?int
{
    if (preg_match('/([1-9][0-9]{1,3})/', $cc, $match)) {
        return (int)$match[1];
    }
    return null;
}

function parseCcRange(string $text): ?array
{
    if (!preg_match_all('/([1-9][0-9]{1,3})\s?cc/i', $text, $matches) || empty($matches[1])) {
        if ($single = normalizeCcInput($text)) {
            return [$single, $single];
        }
        return null;
    }

    $values = array_map('intval', $matches[1]);
    sort($values);
    return [$values[0], $values[count($values) - 1]];
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

$tab = $_GET['tab'] ?? 'homepage';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_homepage') {
        foreach (['hero_eyebrow', 'hero_heading', 'hero_subtext'] as $field) {
            setSiteSetting($field, trim($_POST[$field] ?? ''));
        }

        if (!empty($_FILES['hero_background_image']['name'])) {
            $file = $_FILES['hero_background_image'];
            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

            if (!in_array($file['type'], $allowed, true)) {
                flashMessage('settings_error', 'Invalid background image type. Use JPG, PNG, WebP, or GIF.');
            } elseif ($file['size'] > 6 * 1024 * 1024) {
                flashMessage('settings_error', 'Background image must be under 6 MB.');
            } else {
                try {
                    $savedFilename = saveOptimizedHeroImage($file, 'hero_background_image', 1920, 92);
                    setSiteSetting('hero_background_image', $savedFilename);
                } catch (Throwable $e) {
                    flashMessage('settings_error', $e->getMessage());
                }
            }
        }

        if (!getFlash('settings_error')) {
            flashMessage('settings_success', 'Homepage settings saved.');
        }
        redirect(baseUrl('admin/settings.php?tab=homepage'));
    }

    if ($action === 'save_service') {
        $serviceId = (int)($_POST['service_id'] ?? 0);
        $name = trim($_POST['svc_name'] ?? '');
        $description = trim($_POST['svc_description'] ?? '');
        $laborFee = (float)($_POST['svc_labor_fee'] ?? 0);
        $requiredCategoryId = (int)($_POST['svc_required_category_id'] ?? 0);
        $requiredCategoryRow = $requiredCategoryId > 0
            ? fetchOne("SELECT id, name FROM categories WHERE id = ?", [$requiredCategoryId])
            : null;
        $requiredCategory = $requiredCategoryRow['name'] ?? null;
        $selectedIds = array_map('intval', $_POST['svc_type_ids'] ?? []);

        if ($name === '') {
            flashMessage('settings_error', 'Service name is required.');
        } else {
            $allTypeIds = array_map('intval', array_column(fetchAllRows("SELECT id FROM motorcycle_types ORDER BY id"), 'id'));
            sort($selectedIds);
            sort($allTypeIds);
            $appliesTo = ($selectedIds === $allTypeIds || empty($selectedIds)) ? 'all' : implode(',', $selectedIds);

            if ($serviceId) {
                getDB()->prepare(
                    "UPDATE service_types SET name = ?, description = ?, labor_fee = ?, applies_to = ?, required_category = ?, required_category_id = ? WHERE id = ?"
                )->execute([$name, $description, $laborFee, $appliesTo, $requiredCategory, $requiredCategoryRow['id'] ?? null, $serviceId]);
                flashMessage('settings_success', 'Service updated.');
            } else {
                getDB()->prepare(
                    "INSERT INTO service_types (name, description, labor_fee, applies_to, required_category, required_category_id) VALUES (?, ?, ?, ?, ?, ?)"
                )->execute([$name, $description, $laborFee, $appliesTo, $requiredCategory, $requiredCategoryRow['id'] ?? null]);
                flashMessage('settings_success', 'Service added.');
            }
        }

        redirect(baseUrl('admin/settings.php?tab=services'));
    }

    if ($action === 'delete_service') {
        $serviceId = (int)($_POST['service_id'] ?? 0);
        $linked = fetchOne(
            "SELECT
                (SELECT COUNT(*) FROM booking_services WHERE service_id = ?) AS booking_refs,
                (SELECT COUNT(*) FROM booking_products WHERE service_id = ?) AS product_refs",
            [$serviceId, $serviceId]
        );

        if ((int)($linked['booking_refs'] ?? 0) > 0 || (int)($linked['product_refs'] ?? 0) > 0) {
            flashMessage('settings_error', 'This service cannot be deleted because it is used in service bookings.');
            redirect(baseUrl('admin/settings.php?tab=services'));
        }

        getDB()->prepare("DELETE FROM service_products WHERE service_id = ?")->execute([$serviceId]);
        getDB()->prepare("DELETE FROM service_material_rules WHERE service_id = ?")->execute([$serviceId]);
        getDB()->prepare("DELETE FROM service_types WHERE id = ?")->execute([$serviceId]);
        flashMessage('settings_success', 'Service deleted.');
        redirect(baseUrl('admin/settings.php?tab=services'));
    }

    if ($action === 'delete_motorcycle_type') {
        $typeId = (int)($_POST['type_id'] ?? 0);
        if ($typeId <= 0) {
            flashMessage('settings_error', 'Invalid motorcycle type.');
            redirect(baseUrl('admin/settings.php?tab=services'));
        }

        $db = getDB();
        try {
            $db->beginTransaction();
            $db->prepare(
                "UPDATE bookings
                 SET vehicle_id = NULL
                 WHERE vehicle_id IN (SELECT id FROM customer_vehicles WHERE type_id = ?)"
            )->execute([$typeId]);
            $db->prepare("DELETE FROM customer_vehicles WHERE type_id = ?")->execute([$typeId]);
            $db->prepare("DELETE FROM motorcycle_models WHERE type_id = ?")->execute([$typeId]);

            $services = $db->query("SELECT id, applies_to FROM service_types")->fetchAll();
            foreach ($services as $service) {
                $appliesTo = trim((string)($service['applies_to'] ?? ''));
                if ($appliesTo === '' || strtolower($appliesTo) === 'all') {
                    continue;
                }

                $typeIds = array_values(array_filter(
                    array_map('intval', preg_split('/\s*,\s*/', $appliesTo) ?: []),
                    static fn(int $id): bool => $id !== $typeId
                ));
                $nextAppliesTo = $typeIds ? implode(',', $typeIds) : 'all';
                $db->prepare("UPDATE service_types SET applies_to = ? WHERE id = ?")
                    ->execute([$nextAppliesTo, (int)$service['id']]);
            }

            $db->prepare("DELETE FROM motorcycle_types WHERE id = ?")->execute([$typeId]);
            $db->commit();
            flashMessage('settings_success', 'Motorcycle type deleted.');
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            flashMessage('settings_error', 'Unable to delete this motorcycle type.');
        }

        redirect(baseUrl('admin/settings.php?tab=services'));
    }

    if ($action === 'save_motorcycle_catalog') {
        $modelId = (int)($_POST['motorcycle_id'] ?? 0);

        try {
            saveMotorcycleCatalogEntry($_POST, $modelId ?: null);
            flashMessage('settings_success', $modelId ? 'Motorcycle updated.' : 'Motorcycle added to the catalog.');
        } catch (Throwable $e) {
            flashMessage('settings_error', $e->getMessage());
        }

        redirect(baseUrl('admin/settings.php?tab=vehicle-options'));
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
                flashMessage('settings_success', 'Motorcycle removed from the catalog.');
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                flashMessage('settings_error', 'Unable to delete this motorcycle.');
            }
        }

        redirect(baseUrl('admin/settings.php?tab=vehicle-options'));
    }

    if ($action === 'delete_motorcycle_catalog_bulk') {
        $modelIds = array_values(array_unique(array_filter(array_map('intval', $_POST['motorcycle_ids'] ?? []))));

        if (!$modelIds) {
            flashMessage('settings_error', 'Select at least one motorcycle to delete.');
            redirect(baseUrl('admin/settings.php?tab=vehicle-options'));
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
            flashMessage('settings_success', $deletedCount . ' motorcycle' . ($deletedCount === 1 ? '' : 's') . ' removed from the catalog.');
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            flashMessage('settings_error', 'Unable to delete the selected motorcycles.');
        }

        redirect(baseUrl('admin/settings.php?tab=vehicle-options'));
    }
}

$flash = getFlash('settings_success');
$flashErr = getFlash('settings_error');

$homepageSettings = [
    'hero_eyebrow' => getSiteSetting('hero_eyebrow', 'Parts, accessories, and maintenance'),
    'hero_heading' => getSiteSetting('hero_heading', 'Keep your motorcycle ready for every ride.'),
    'hero_subtext' => getSiteSetting('hero_subtext', 'Shop reliable products, save your motorcycle profile, and book compatible services with instant cost estimates.'),
    'hero_background_image' => getSiteSetting('hero_background_image', ''),
];

$motoTypes = getMotorcycleTypes();
$motoBrands = getMotorcycleBrands();
$motoModels = getMotorcycleModels();
$serviceTypes = fetchAllRows("SELECT * FROM service_types ORDER BY name");
$productCategories = fetchAllRows("SELECT id, name FROM categories ORDER BY name");
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

$editSvcId = (int)($_GET['edit_service'] ?? 0);
$editSvc = null;
if ($editSvcId && $tab === 'services') {
    foreach ($serviceTypes as $serviceType) {
        if ((int)$serviceType['id'] === $editSvcId) {
            $editSvc = $serviceType;
            break;
        }
    }
}

$editMotorcycleId = (int)($_GET['edit_motorcycle'] ?? 0);
$editMotorcycle = null;
if ($editMotorcycleId && $tab === 'vehicle-options') {
    foreach ($catalogRows as $row) {
        if ((int)$row['id'] === $editMotorcycleId) {
            $editMotorcycle = $row;
            break;
        }
    }
}
?>

<section class="admin-card settings-admin-shell">
  <div class="admin-page-head">
    <div>
      <h1>Settings</h1>
      <p>Manage homepage content, compatible services, and motorcycle catalog options.</p>
    </div>
  </div>

  <div class="admin-tabs settings-tabs">
    <a href="<?= baseUrl('admin/settings.php?tab=homepage') ?>" class="admin-tab <?= $tab === 'homepage' ? 'active' : '' ?>">Homepage</a>
    <a href="<?= baseUrl('admin/settings.php?tab=services') ?>" class="admin-tab <?= $tab === 'services' ? 'active' : '' ?>">Compatible Services</a>
    <a href="<?= baseUrl('admin/settings.php?tab=vehicle-options') ?>" class="admin-tab <?= $tab === 'vehicle-options' ? 'active' : '' ?>">Vehicle Options</a>
  </div>

  <?php if ($flash): ?>
    <div class="alert success" style="margin-bottom:1rem;"><?= htmlspecialchars($flash) ?></div>
  <?php endif; ?>
  <?php if ($flashErr): ?>
    <div class="alert error" style="margin-bottom:1rem;"><?= htmlspecialchars($flashErr) ?></div>
  <?php endif; ?>

  <?php if ($tab === 'homepage'): ?>
    <h2 style="font-size:.9rem;font-weight:600;color:#888;text-transform:uppercase;letter-spacing:.06em;margin-bottom:1rem;">Hero Section</h2>

    <form method="post" enctype="multipart/form-data" style="max-width:600px;">
      <?= authContextField() ?>
      <input type="hidden" name="action" value="save_homepage">

      <label style="display:block;margin-bottom:1rem;">
        <span style="display:block;font-weight:600;margin-bottom:.3rem;">Eyebrow text</span>
        <input type="text" name="hero_eyebrow" value="<?= htmlspecialchars($homepageSettings['hero_eyebrow']) ?>" style="width:100%;padding:.5rem .75rem;border:1px solid #ddd;border-radius:6px;">
      </label>

      <label style="display:block;margin-bottom:1rem;">
        <span style="display:block;font-weight:600;margin-bottom:.3rem;">Heading</span>
        <input type="text" name="hero_heading" value="<?= htmlspecialchars($homepageSettings['hero_heading']) ?>" style="width:100%;padding:.5rem .75rem;border:1px solid #ddd;border-radius:6px;">
      </label>

      <label style="display:block;margin-bottom:1rem;">
        <span style="display:block;font-weight:600;margin-bottom:.3rem;">Subtext</span>
        <textarea name="hero_subtext" rows="3" style="width:100%;padding:.5rem .75rem;border:1px solid #ddd;border-radius:6px;resize:vertical;"><?= htmlspecialchars($homepageSettings['hero_subtext']) ?></textarea>
      </label>

      <label style="display:block;margin-bottom:1.5rem;">
        <span style="display:block;font-weight:600;margin-bottom:.5rem;">Hero background image</span>
        <?php
        $bgFile = $homepageSettings['hero_background_image'];
        $bgExists = $bgFile && file_exists(__DIR__ . '/../uploads/' . $bgFile);
        ?>
        <?php if ($bgExists): ?>
          <img id="heroBackgroundPreview" src="<?= baseUrl('uploads/' . rawurlencode($bgFile) . '?v=' . filemtime(__DIR__ . '/../uploads/' . $bgFile)) ?>" alt="Hero background" style="display:block;max-width:320px;width:100%;margin-bottom:.6rem;border-radius:12px;border:1px solid #eee;object-fit:cover;aspect-ratio:16/9;">
        <?php else: ?>
          <img id="heroBackgroundPreview" src="" alt="Hero background" style="display:none;max-width:320px;width:100%;margin-bottom:.6rem;border-radius:12px;border:1px solid #eee;object-fit:cover;aspect-ratio:16/9;">
        <?php endif; ?>
        <input type="file" name="hero_background_image" id="heroBackgroundInput" accept="image/*">
        <small style="display:block;color:#777;margin-top:.45rem;">Recommended: 1920x1080 workshop background image for the full hero banner.</small>
      </label>

      <button type="submit" class="btn btn-primary">Save homepage settings</button>
    </form>
    <script>
      (() => {
        const bindPreview = (inputId, previewId) => {
          const input = document.getElementById(inputId);
          const preview = document.getElementById(previewId);
          if (!input || !preview) return;

          input.addEventListener('change', () => {
            const [file] = input.files || [];
            if (!file) return;

            const objectUrl = URL.createObjectURL(file);
            preview.src = objectUrl;
            preview.style.display = 'block';
          });
        };

        bindPreview('heroBackgroundInput', 'heroBackgroundPreview');
      })();
    </script>
  <?php elseif ($tab === 'services'): ?>
    <section class="admin-page-stack settings-services-panel">
      <div class="admin-page-head">
        <div>
          <h1>Compatible Services</h1>
          <p>Define service types, labor fees, required product categories, and supported motorcycle types.</p>
        </div>
        <a href="<?= baseUrl('admin/settings.php?tab=services') ?>" class="btn btn-primary"><i class="fas fa-plus"></i> Add Service</a>
      </div>

      <div class="admin-form-box settings-service-form">
        <h3><?= $editSvc ? 'Edit Service' : 'New Service' ?></h3>
        <form method="post">
          <?= authContextField() ?>
          <input type="hidden" name="action" value="save_service">
          <?php if ($editSvc): ?>
            <input type="hidden" name="service_id" value="<?= (int)$editSvc['id'] ?>">
          <?php endif; ?>

          <div class="form-grid-2">
            <label>Service name
              <input type="text" name="svc_name" value="<?= htmlspecialchars($editSvc['name'] ?? '') ?>" required>
            </label>
            <label>Labor fee (PHP)
              <input type="number" name="svc_labor_fee" min="0" step="0.01" value="<?= isset($editSvc) ? (float)$editSvc['labor_fee'] : '0' ?>">
            </label>
          </div>

          <label>Description
            <textarea name="svc_description" rows="3"><?= htmlspecialchars($editSvc['description'] ?? '') ?></textarea>
          </label>

          <label>Required product category
            <select name="svc_required_category_id">
              <option value="">No category required</option>
              <?php foreach ($productCategories as $category): ?>
                <option
                  value="<?= (int)$category['id'] ?>"
                  <?= (int)($editSvc['required_category_id'] ?? 0) === (int)$category['id'] ? 'selected' : '' ?>
                >
                  <?= htmlspecialchars($category['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>

          <label>Applies to motorcycle types
            <?php
            $allTypeIds = array_map('intval', array_column($motoTypes, 'id'));
            $checkedIds = $editSvc ? parseAppliesTo($editSvc['applies_to'], $allTypeIds) : $allTypeIds;
            ?>
            <div class="checkbox-group">
              <?php foreach ($motoTypes as $type): ?>
                <label class="inline-check">
                  <input type="checkbox" name="svc_type_ids[]" value="<?= (int)$type['id'] ?>" <?= in_array((int)$type['id'], $checkedIds, true) ? 'checked' : '' ?>>
                  <?= htmlspecialchars($type['name']) ?>
                </label>
              <?php endforeach; ?>
            </div>
          </label>

          <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $editSvc ? 'Update Service' : 'Add Service' ?></button>
            <?php if ($editSvc): ?>
              <a href="<?= baseUrl('admin/settings.php?tab=services') ?>" class="btn btn-outline">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </section>

    <section class="admin-page-stack settings-services-panel">
      <div class="admin-page-head">
        <div>
          <h2>Motorcycle Types</h2>
          <p>Review service compatibility groups and delete unused motorcycle types when needed.</p>
        </div>
      </div>

      <?php if ($motoTypes): ?>
        <div class="admin-table-wrap">
          <table class="admin-data-table settings-data-table">
            <thead>
              <tr>
                <th>Type</th>
                <th>Catalog Models</th>
                <th>Saved Vehicles</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($motoTypes as $type): ?>
                <?php
                $typeStats = fetchOne(
                    "SELECT
                        (SELECT COUNT(*) FROM motorcycle_models WHERE type_id = ?) AS model_count,
                        (SELECT COUNT(*) FROM customer_vehicles WHERE type_id = ?) AS vehicle_count",
                    [(int)$type['id'], (int)$type['id']]
                );
                ?>
                <tr>
                  <td><strong><?= htmlspecialchars($type['name']) ?></strong></td>
                  <td><span class="settings-count-badge"><?= (int)($typeStats['model_count'] ?? 0) ?></span></td>
                  <td><span class="settings-count-badge"><?= (int)($typeStats['vehicle_count'] ?? 0) ?></span></td>
                  <td class="settings-table-actions">
                    <form method="post" onsubmit="return confirm('Delete this motorcycle type? Related catalog models and saved customer vehicles will also be removed.')">
                      <?= authContextField() ?>
                      <input type="hidden" name="action" value="delete_motorcycle_type">
                      <input type="hidden" name="type_id" value="<?= (int)$type['id'] ?>">
                      <button type="submit" class="btn btn-outline danger-btn settings-action-btn">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="empty-note">No motorcycle types yet.</p>
      <?php endif; ?>
    </section>

    <section class="admin-page-stack settings-services-panel">
      <div class="admin-page-head">
        <div>
          <h2>Existing Services</h2>
          <p>Manage the services shown to customers and staff during booking.</p>
        </div>
      </div>

      <?php if ($serviceTypes): ?>
        <div class="admin-table-wrap">
          <table class="admin-data-table settings-data-table">
            <thead>
              <tr>
                <th>Service</th>
                <th>Labor</th>
                <th>Required Category</th>
                <th>Applies To</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($serviceTypes as $serviceType): ?>
                <?php
                $appIds = parseAppliesTo($serviceType['applies_to'], $allTypeIds);
                $typeNames = [];
                foreach ($motoTypes as $type) {
                    if (in_array((int)$type['id'], $appIds, true)) {
                        $typeNames[] = $type['name'];
                    }
                }
                $appLabel = count($typeNames) === count($motoTypes) ? 'All types' : implode(', ', $typeNames);
                ?>
                <tr>
                  <td>
                    <strong><?= htmlspecialchars($serviceType['name']) ?></strong>
                    <?php if ($serviceType['description']): ?>
                      <div class="subtext"><?= htmlspecialchars(mb_strimwidth($serviceType['description'], 0, 80, '...')) ?></div>
                    <?php endif; ?>
                  </td>
                  <td><?= formatPrice((float)$serviceType['labor_fee']) ?></td>
                  <td><?= htmlspecialchars($serviceType['required_category'] ?: 'None') ?></td>
                  <td><span class="settings-tag"><?= htmlspecialchars($appLabel) ?></span></td>
                  <td class="settings-table-actions">
                    <a href="<?= baseUrl('admin/settings.php?tab=services&edit_service=' . (int)$serviceType['id']) ?>" class="btn btn-outline settings-action-btn">Edit</a>
                    <form method="post" onsubmit="return confirm('Delete this service?')">
                      <?= authContextField() ?>
                      <input type="hidden" name="action" value="delete_service">
                      <input type="hidden" name="service_id" value="<?= (int)$serviceType['id'] ?>">
                      <button type="submit" class="btn btn-outline danger-btn settings-action-btn">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="empty-note">No services yet. Add one above.</p>
      <?php endif; ?>
    </section>
  <?php else: ?>
    <div class="vehicle-admin-shell" id="vehicleManager" data-base-url="<?= htmlspecialchars(baseUrl('')) ?>">
      <div class="vehicle-admin-hero">
        <div>
          <h2>Admin Motorcycle Management</h2>
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
          <input type="hidden" name="tab" value="vehicle-options">
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
          <a href="<?= baseUrl('admin/settings.php?tab=vehicle-options') ?>" class="btn btn-outline">Reset</a>
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
          <p>Brand names are manually entered by the admin.</p>
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
  <?php endif; ?>
</section>

<?php if ($tab === 'vehicle-options'): ?>
  <script src="<?= baseUrl('assets/js/main.js?v=' . filemtime(__DIR__ . '/../assets/js/main.js')) ?>"></script>
<?php endif; ?>
</main></div></div></body></html>
