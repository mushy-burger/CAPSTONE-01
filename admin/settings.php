<?php
$pageTitle = 'Settings';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/MotorcycleApiService.php';
require_once __DIR__ . '/../includes/BookingDeposit.php';
requireAdminOnly();

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

    // Reservation deposit charged before a booking can be confirmed.
    // Stored in site_settings and read from there at runtime.
    if ($action === 'save_reservation_deposit') {
        $parsed = depositValidateAmount((string)($_POST['reservation_deposit_amount'] ?? ''));
        if (!$parsed['ok']) {
            flashMessage('settings_error', $parsed['error']);
        } else {
            setSiteSetting(DEPOSIT_SETTING_KEY, number_format($parsed['amount'], 2, '.', ''));
            flashMessage('settings_success', $parsed['amount'] > 0
                ? 'Reservation deposit set to ' . formatPrice($parsed['amount']) . '. It applies to new booking payments.'
                : 'Reservation deposit disabled — bookings no longer require a deposit.');
        }
        redirect(baseUrl('admin/settings.php?tab=homepage'));
    }

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

require_once __DIR__ . '/../includes/admin-sidebar.php';

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

<div class="mtx-shell">

  <header class="mtx-page-head">
    <div class="mtx-page-head-copy">
      <span class="eyebrow">Configuration</span>
      <h1>Settings</h1>
      <p>Manage homepage content, compatible services, and motorcycle catalog options.</p>
    </div>
  </header>

  <div class="mtx-seg mtx-seg--card" role="tablist" aria-label="Settings sections">
    <a href="<?= baseUrl('admin/settings.php?tab=homepage') ?>" class="<?= $tab === 'homepage' ? 'active' : '' ?>" role="tab"><i class="fas fa-house"></i>Homepage</a>
    <a href="<?= baseUrl('admin/settings.php?tab=services') ?>" class="<?= $tab === 'services' ? 'active' : '' ?>" role="tab"><i class="fas fa-tools"></i>Compatible Services</a>
    <a href="<?= baseUrl('admin/settings.php?tab=vehicle-options') ?>" class="<?= $tab === 'vehicle-options' ? 'active' : '' ?>" role="tab"><i class="fas fa-motorcycle"></i>Vehicle Options</a>
  </div>

  <?php if ($flash): ?>
    <div class="alert success"><?= htmlspecialchars($flash) ?></div>
  <?php endif; ?>
  <?php if ($flashErr): ?>
    <div class="alert error"><?= htmlspecialchars($flashErr) ?></div>
  <?php endif; ?>

  <?php if ($tab === 'homepage'): ?>
    <form method="post" enctype="multipart/form-data" id="homepageSettingsForm" class="mtx-stack">
      <?= authContextField() ?>
      <input type="hidden" name="action" value="save_homepage">

      <div class="mtx-grid mtx-grid--half">
        <!-- Hero content -->
        <section class="mtx-card">
          <div class="mtx-card-head">
            <div>
              <h2><i class="fas fa-pen-to-square"></i> Hero Content</h2>
              <p>The headline copy shown on the public homepage banner.</p>
            </div>
          </div>
          <div class="mtx-stack" style="gap:16px;">
            <label class="mtx-field">
              <span>Eyebrow text</span>
              <input type="text" name="hero_eyebrow" value="<?= htmlspecialchars($homepageSettings['hero_eyebrow']) ?>" placeholder="Parts, accessories, and maintenance">
              <span class="mtx-help">Short kicker line displayed above the main heading.</span>
            </label>

            <label class="mtx-field">
              <span>Heading</span>
              <input type="text" name="hero_heading" value="<?= htmlspecialchars($homepageSettings['hero_heading']) ?>" placeholder="Keep your motorcycle ready for every ride.">
              <span class="mtx-help">The main hero headline. The second half is automatically highlighted in red.</span>
            </label>

            <label class="mtx-field">
              <span>Subtext</span>
              <textarea name="hero_subtext" rows="4" placeholder="A short supporting sentence under the heading."><?= htmlspecialchars($homepageSettings['hero_subtext']) ?></textarea>
              <span class="mtx-help">Supporting copy under the heading — keep it to one or two sentences.</span>
            </label>
          </div>
        </section>

        <!-- Hero image -->
        <section class="mtx-card">
          <div class="mtx-card-head">
            <div>
              <h2><i class="fas fa-image"></i> Hero Background Image</h2>
              <p>Full-width banner image behind the hero content.</p>
            </div>
            <span class="mtx-pill" style="--pill-color:#2563eb;"><i class="fas fa-expand"></i> 1920 &times; 1080</span>
          </div>

          <?php
          $bgFile = $homepageSettings['hero_background_image'];
          $bgExists = $bgFile && file_exists(__DIR__ . '/../uploads/' . $bgFile);
          ?>
          <div class="mtx-media-preview" id="heroPreviewWrap" <?= $bgExists ? '' : 'hidden' ?>>
            <img id="heroBackgroundPreview"
                 src="<?= $bgExists ? baseUrl('uploads/' . rawurlencode($bgFile) . '?v=' . filemtime(__DIR__ . '/../uploads/' . $bgFile)) : '' ?>"
                 alt="Hero background preview">
            <label class="mtx-media-overlay" for="heroBackgroundInput">
              <i class="fas fa-arrows-rotate"></i> Replace image
            </label>
          </div>

          <label class="mtx-media-drop" id="heroDropZone" for="heroBackgroundInput" <?= $bgExists ? 'hidden' : '' ?>>
            <i class="fas fa-cloud-arrow-up"></i>
            <strong>Click to upload or drag &amp; drop</strong>
            <span>JPG, PNG, WebP or GIF · up to 6 MB</span>
          </label>

          <input type="file" name="hero_background_image" id="heroBackgroundInput" accept="image/*" style="position:absolute;width:1px;height:1px;opacity:0;overflow:hidden;">

          <div class="mtx-media-meta">
            <span class="mtx-media-filename" id="heroFileName">
              <?= $bgExists ? '<i class="fas fa-circle-check"></i>' . htmlspecialchars($bgFile) : 'No new file selected' ?>
            </span>
            <label for="heroBackgroundInput" class="mtx-btn mtx-btn--ghost mtx-btn--sm" style="cursor:pointer;">
              <i class="fas fa-upload"></i> <?= $bgExists ? 'Replace Image' : 'Upload Image' ?>
            </label>
          </div>
          <p class="mtx-help" style="margin:10px 0 0;">Recommended: 1920&times;1080 workshop background image for the full hero banner. Uploads are optimized automatically.</p>
        </section>
      </div>

      <!-- Save bar -->
      <div class="mtx-form-footer">
        <span class="mtx-help"><i class="fas fa-circle-info" style="color:#2563eb;margin-right:6px;"></i>Changes go live on the homepage immediately after saving.</span>
        <button type="submit" class="mtx-btn mtx-btn--primary mtx-btn--lg" id="homepageSaveBtn">
          <i class="fas fa-floppy-disk" id="homepageSaveIcon"></i> Save Homepage Settings
        </button>
      </div>
    </form>

    <!-- Reservation deposit -->
    <section class="mtx-card">
      <div class="mtx-card-head">
        <div>
          <h2><i class="fas fa-hand-holding-dollar"></i> Reservation Deposit</h2>
          <p>Customers must pay this online through PayMongo before a booking can be confirmed.</p>
        </div>
        <span class="mtx-pill" style="--pill-color:<?= depositIsRequired() ? '#15803d' : '#6b7280' ?>;">
          <i class="fas fa-<?= depositIsRequired() ? 'lock' : 'lock-open' ?>"></i>
          <?= depositIsRequired() ? 'Required' : 'Disabled' ?>
        </span>
      </div>

      <form method="post" style="padding:18px;">
        <?= authContextField() ?>
        <input type="hidden" name="action" value="save_reservation_deposit">
        <div style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap;">
          <label class="mtx-field" style="flex:1;min-width:220px;">
            <span>Deposit amount (PHP)</span>
            <input type="number" name="reservation_deposit_amount"
                   min="0" max="100000" step="0.01"
                   value="<?= htmlspecialchars(number_format(depositAmount(), 2, '.', '')) ?>"
                   required>
          </label>
          <button type="submit" class="mtx-btn mtx-btn--primary">
            <i class="fas fa-floppy-disk"></i> Save Deposit
          </button>
        </div>
        <p class="mtx-help" style="margin:12px 0 0;">
          <i class="fas fa-circle-info" style="color:#2563eb;margin-right:6px;"></i>
          Current deposit: <strong><?= formatPrice(depositAmount()) ?></strong>.
          Set it to <strong>0</strong> to stop requiring a deposit.
          Changes apply to new payment attempts only — deposits already paid are never altered.
        </p>
      </form>
    </section>

    <script>
      (() => {
        const input = document.getElementById('heroBackgroundInput');
        const preview = document.getElementById('heroBackgroundPreview');
        const previewWrap = document.getElementById('heroPreviewWrap');
        const dropZone = document.getElementById('heroDropZone');
        const fileName = document.getElementById('heroFileName');

        const showFile = (file) => {
          if (!file) return;
          preview.src = URL.createObjectURL(file);
          previewWrap.hidden = false;
          if (dropZone) dropZone.hidden = true;
          fileName.innerHTML = '<i class="fas fa-circle-check"></i>' +
            file.name.replace(/&/g, '&amp;').replace(/</g, '&lt;');
        };

        if (input && preview) {
          input.addEventListener('change', () => {
            const [file] = input.files || [];
            showFile(file);
          });
        }

        // Drag & drop onto the dropzone or the preview
        [dropZone, previewWrap].forEach((zone) => {
          if (!zone) return;
          ['dragenter', 'dragover'].forEach((evt) => zone.addEventListener(evt, (e) => {
            e.preventDefault();
            zone.classList.add('is-dragover');
          }));
          ['dragleave', 'drop'].forEach((evt) => zone.addEventListener(evt, (e) => {
            e.preventDefault();
            zone.classList.remove('is-dragover');
          }));
          zone.addEventListener('drop', (e) => {
            const files = e.dataTransfer && e.dataTransfer.files;
            if (files && files.length && input) {
              input.files = files;
              showFile(files[0]);
            }
          });
        });

        // Loading state (UI only)
        const form = document.getElementById('homepageSettingsForm');
        const saveBtn = document.getElementById('homepageSaveBtn');
        const saveIcon = document.getElementById('homepageSaveIcon');
        if (form && saveBtn) {
          form.addEventListener('submit', () => {
            saveBtn.classList.add('is-loading');
            if (saveIcon) saveIcon.className = 'fas fa-spinner fa-spin';
          });
        }
      })();
    </script>
  <?php elseif ($tab === 'services'): ?>
    <section class="mtx-card">
      <div class="mtx-card-head">
        <div>
          <h2><i class="fas fa-screwdriver-wrench"></i> <?= $editSvc ? 'Edit Service' : 'New Service' ?></h2>
          <p>Define service types, labor fees, required product categories, and supported motorcycle types.</p>
        </div>
      </div>
      <form method="post" class="mtx-stack" style="gap:16px;max-width:960px;">
        <?= authContextField() ?>
        <input type="hidden" name="action" value="save_service">
        <?php if ($editSvc): ?>
          <input type="hidden" name="service_id" value="<?= (int)$editSvc['id'] ?>">
        <?php endif; ?>

        <div class="mtx-form-grid">
          <label class="mtx-field"><span>Service name <em>*</em></span>
            <input type="text" name="svc_name" value="<?= htmlspecialchars($editSvc['name'] ?? '') ?>" required>
          </label>
          <label class="mtx-field"><span>Labor fee (PHP)</span>
            <input type="number" name="svc_labor_fee" min="0" step="0.01" value="<?= isset($editSvc) ? (float)$editSvc['labor_fee'] : '0' ?>">
          </label>
        </div>

        <label class="mtx-field"><span>Description</span>
          <textarea name="svc_description" rows="3"><?= htmlspecialchars($editSvc['description'] ?? '') ?></textarea>
        </label>

        <label class="mtx-field"><span>Required product category</span>
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
          <span class="mtx-help">Customers must pick a product from this category when booking the service.</span>
        </label>

        <div class="mtx-field">
          <span>Applies to motorcycle types</span>
          <?php
          $allTypeIds = array_map('intval', array_column($motoTypes, 'id'));
          $checkedIds = $editSvc ? parseAppliesTo($editSvc['applies_to'], $allTypeIds) : $allTypeIds;
          ?>
          <div class="mtx-chip-group">
            <?php foreach ($motoTypes as $type): ?>
              <label class="mtx-chip">
                <input type="checkbox" name="svc_type_ids[]" value="<?= (int)$type['id'] ?>" <?= in_array((int)$type['id'], $checkedIds, true) ? 'checked' : '' ?>>
                <i class="fas fa-check"></i><?= htmlspecialchars($type['name']) ?>
              </label>
            <?php endforeach; ?>
          </div>
          <span class="mtx-help">Selecting all types (or none) makes the service available to every motorcycle.</span>
        </div>

        <div class="form-actions" style="margin-top:2px;">
          <button type="submit" class="mtx-btn mtx-btn--primary"><i class="fas fa-floppy-disk"></i> <?= $editSvc ? 'Update Service' : 'Add Service' ?></button>
          <?php if ($editSvc): ?>
            <a href="<?= baseUrl('admin/settings.php?tab=services') ?>" class="mtx-btn mtx-btn--ghost">Cancel</a>
          <?php endif; ?>
        </div>
      </form>
    </section>

    <section class="mtx-card mtx-card--flush">
      <div class="mtx-card-head">
        <div>
          <h2><i class="fas fa-motorcycle"></i> Motorcycle Types</h2>
          <p>Review service compatibility groups and delete unused motorcycle types when needed.</p>
        </div>
      </div>

      <?php if ($motoTypes): ?>
        <div class="mtx-table-wrap">
          <table class="mtx-table settings-data-table">
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
                      <button type="submit" class="mtx-btn mtx-btn--ghost mtx-btn--sm" style="color:#b91c1c;border-color:#f3c1c1;"><i class="fas fa-trash-can"></i> Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div style="padding:0 24px 24px;">
          <div class="mtx-empty">
            <i class="fas fa-motorcycle"></i>
            <strong>No motorcycle types yet.</strong>
          </div>
        </div>
      <?php endif; ?>
    </section>

    <section class="mtx-card mtx-card--flush">
      <div class="mtx-card-head">
        <div>
          <h2><i class="fas fa-list-check"></i> Existing Services</h2>
          <p>Manage the services shown to customers and staff during booking.</p>
        </div>
      </div>

      <?php if ($serviceTypes): ?>
        <div class="mtx-table-wrap">
          <table class="mtx-table settings-data-table">
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
                    <a href="<?= baseUrl('admin/settings.php?tab=services&edit_service=' . (int)$serviceType['id']) ?>" class="mtx-btn mtx-btn--ghost mtx-btn--sm"><i class="fas fa-pen"></i> Edit</a>
                    <form method="post" onsubmit="return confirm('Delete this service?')">
                      <?= authContextField() ?>
                      <input type="hidden" name="action" value="delete_service">
                      <input type="hidden" name="service_id" value="<?= (int)$serviceType['id'] ?>">
                      <button type="submit" class="mtx-btn mtx-btn--ghost mtx-btn--sm" style="color:#b91c1c;border-color:#f3c1c1;"><i class="fas fa-trash-can"></i> Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div style="padding:0 24px 24px;">
          <div class="mtx-empty">
            <i class="fas fa-tools"></i>
            <strong>No services yet.</strong>
            <span>Add your first service using the form above.</span>
          </div>
        </div>
      <?php endif; ?>
    </section>
  <?php else: ?>
    <?php
    $vehicleOptionsUi = [
        'reset_url' => baseUrl('admin/settings.php?tab=vehicle-options'),
        'filter_hidden' => '<input type="hidden" name="tab" value="vehicle-options">',
    ];
    require __DIR__ . '/../includes/vehicle-options-ui.php';
    ?>
  <?php endif; ?>
</div><!-- /.mtx-shell -->

<?php if ($tab === 'vehicle-options'): ?>
  <script src="<?= baseUrl('assets/js/main.js?v=' . filemtime(__DIR__ . '/../assets/js/main.js')) ?>"></script>
<?php endif; ?>
</main></div></div></body></html>
