<?php
/**
 * Shared Vehicle Options UI (admin + staff).
 * Pure markup — consumes the page's existing $catalogRows / filter variables and
 * $vehicleOptionsUi = ['reset_url' => string, 'filter_hidden' => string].
 * All element IDs / classes consumed by assets/js/main.js are preserved.
 */
$vehicleOptionsUi = $vehicleOptionsUi ?? [];
$vhxResetUrl = $vehicleOptionsUi['reset_url'] ?? baseUrl('staff/vehicles.php');
$vhxFilterHidden = $vehicleOptionsUi['filter_hidden'] ?? '';

$vhxTotal = count($catalogOptionRows);
$vhxTypeCounts = [];
foreach ($catalogOptionRows as $vhxRow) {
    $vhxKey = strtolower(trim($vhxRow['type_name']));
    $vhxTypeCounts[$vhxKey] = ($vhxTypeCounts[$vhxKey] ?? 0) + 1;
}
$vhxScooter   = $vhxTypeCounts['scooter'] ?? 0;
$vhxUnderbone = $vhxTypeCounts['underbone'] ?? 0;
$vhxBackbone  = $vhxTypeCounts['backbone'] ?? 0;

$vhxBadgePalette = [
    'scooter'   => '#4f8df9',
    'underbone' => '#f0883e',
    'backbone'  => '#a78bfa',
];
$vhxHasFilters = $vehicleTypeFilter !== '' || $vehicleBrandFilter !== '' || $vehicleModelFilter !== '' || $vehicleCcFilter !== '';
?>

<div class="vhx-shell" id="vehicleManager" data-base-url="<?= htmlspecialchars(baseUrl('')) ?>">

  <!-- Header card -->
  <div class="vhx-hero">
    <div class="vhx-hero-copy">
      <span class="vhx-hero-icon"><i class="fas fa-motorcycle"></i></span>
      <div>
        <h1>Vehicle Options</h1>
        <p>Build and maintain the motorcycle catalog customers pick from when booking a service.</p>
      </div>
    </div>
    <button type="button" class="vhx-btn vhx-btn--primary vhx-btn--lg" id="openMotorcycleWizard">
      <i class="fas fa-plus"></i> Add Motorcycle
    </button>
  </div>

  <!-- Stat cards -->
  <section class="bk-stats-grid vhx-stats">
    <article class="bk-stat-card" style="--stat-color:#d71920;">
      <span class="bk-stat-icon"><i class="fas fa-motorcycle"></i></span>
      <span class="bk-stat-label">Total Motorcycles</span>
      <span class="bk-stat-value"><?= $vhxTotal ?></span>
      <span class="bk-stat-desc">Models in the catalog</span>
    </article>
    <article class="bk-stat-card" style="--stat-color:#4f8df9;">
      <span class="bk-stat-icon"><i class="fas fa-gauge-high"></i></span>
      <span class="bk-stat-label">Scooter</span>
      <span class="bk-stat-value"><?= $vhxScooter ?></span>
      <span class="bk-stat-desc">Automatic scooter models</span>
    </article>
    <article class="bk-stat-card" style="--stat-color:#f0883e;">
      <span class="bk-stat-icon"><i class="fas fa-gears"></i></span>
      <span class="bk-stat-label">Underbone</span>
      <span class="bk-stat-value"><?= $vhxUnderbone ?></span>
      <span class="bk-stat-desc">Underbone / cub models</span>
    </article>
    <article class="bk-stat-card" style="--stat-color:#a78bfa;">
      <span class="bk-stat-icon"><i class="fas fa-road"></i></span>
      <span class="bk-stat-label">Backbone</span>
      <span class="bk-stat-value"><?= $vhxBackbone ?></span>
      <span class="bk-stat-desc">Backbone / standard models</span>
    </article>
  </section>

  <!-- Catalog card -->
  <section class="vhx-card">
    <div class="vhx-card-head">
      <div>
        <h2>Motorcycle Catalog</h2>
        <p><?= count($catalogRows) ?> record<?= count($catalogRows) === 1 ? '' : 's' ?> in the current view</p>
      </div>
      <?php if ($catalogRows): ?>
        <form method="post" id="bulkMotorcycleDeleteForm" onsubmit="return confirm('Delete the selected motorcycles from the catalog?')">
          <?= authContextField() ?>
          <input type="hidden" name="action" value="delete_motorcycle_catalog_bulk">
        </form>
        <button type="submit" class="vhx-btn vhx-btn--danger vhx-bulk-btn" id="bulkMotorcycleDeleteBtn" form="bulkMotorcycleDeleteForm" disabled>Delete Selected</button>
      <?php endif; ?>
    </div>

    <!-- Filter toolbar -->
    <form method="get" class="vhx-toolbar">
      <?= $vhxFilterHidden ?>
      <label class="vhx-filter">
        <i class="fas fa-shapes"></i>
        <select name="vehicle_type" aria-label="Filter by type">
          <option value="">All types</option>
          <?php foreach ($catalogTypes as $catalogType): ?>
            <option value="<?= htmlspecialchars($catalogType) ?>" <?= strcasecmp($vehicleTypeFilter, $catalogType) === 0 ? 'selected' : '' ?>>
              <?= htmlspecialchars($catalogType) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="vhx-filter">
        <i class="fas fa-tag"></i>
        <select name="vehicle_brand" aria-label="Filter by brand">
          <option value="">All brands</option>
          <?php foreach ($catalogBrands as $catalogBrand): ?>
            <option value="<?= htmlspecialchars($catalogBrand) ?>" <?= strcasecmp($vehicleBrandFilter, $catalogBrand) === 0 ? 'selected' : '' ?>>
              <?= htmlspecialchars($catalogBrand) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="vhx-filter">
        <i class="fas fa-motorcycle"></i>
        <select name="vehicle_model" aria-label="Filter by model">
          <option value="">All models</option>
          <?php foreach ($catalogModels as $catalogModel): ?>
            <option value="<?= htmlspecialchars($catalogModel) ?>" <?= strcasecmp($vehicleModelFilter, $catalogModel) === 0 ? 'selected' : '' ?>>
              <?= htmlspecialchars($catalogModel) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="vhx-filter">
        <i class="fas fa-gauge-high"></i>
        <select name="vehicle_cc" aria-label="Filter by engine cc">
          <option value="">All engine CC</option>
          <?php foreach ($catalogCcs as $catalogCc): ?>
            <option value="<?= (int)$catalogCc ?>cc" <?= preg_replace('/[^0-9]/', '', $vehicleCcFilter) === (string)(int)$catalogCc ? 'selected' : '' ?>>
              <?= (int)$catalogCc ?>cc
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <button type="submit" class="vhx-btn vhx-btn--dark"><i class="fas fa-filter"></i> Filter</button>
      <a href="<?= $vhxResetUrl ?>" class="vhx-btn vhx-btn--ghost"><i class="fas fa-rotate-left"></i> Reset</a>
    </form>

    <?php if ($catalogRows): ?>
      <div class="vhx-table-wrap">
        <table class="vhx-table">
          <thead>
            <tr>
              <th class="vhx-col-check">
                <input type="checkbox" id="selectAllMotorcycles" aria-label="Select all motorcycles">
              </th>
              <th>Type</th>
              <th>Brand</th>
              <th>Model</th>
              <th>Engine CC</th>
              <th class="vhx-col-actions">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($catalogRows as $row): ?>
              <?php $vhxBadgeColor = $vhxBadgePalette[strtolower(trim($row['type_name']))] ?? '#8b93a1'; ?>
              <tr>
                <td class="vhx-col-check">
                  <input type="checkbox" name="motorcycle_ids[]" value="<?= (int)$row['id'] ?>" class="js-motorcycle-select" form="bulkMotorcycleDeleteForm" aria-label="Select <?= htmlspecialchars($row['brand_name'] . ' ' . $row['model_name']) ?>">
                </td>
                <td>
                  <span class="vhx-badge" style="--badge-color:<?= $vhxBadgeColor ?>;"><?= htmlspecialchars($row['type_name']) ?></span>
                </td>
                <td class="vhx-cell-brand"><?= htmlspecialchars($row['brand_name']) ?></td>
                <td class="vhx-cell-model"><?= htmlspecialchars($row['model_name']) ?></td>
                <td><span class="vhx-cc"><?= (int)$row['cc'] ?>cc</span></td>
                <td class="vhx-col-actions">
                  <div class="vhx-row-actions">
                    <button
                      type="button"
                      class="vhx-act-btn js-edit-motorcycle"
                      data-id="<?= (int)$row['id'] ?>"
                      data-type="<?= htmlspecialchars($row['type_name']) ?>"
                      data-brand="<?= htmlspecialchars($row['brand_name']) ?>"
                      data-model="<?= htmlspecialchars($row['model_name']) ?>"
                      data-cc="<?= (int)$row['cc'] ?>cc"
                    >
                      <i class="fas fa-pen"></i> Edit
                    </button>
                    <form method="post" onsubmit="return confirm('Delete this motorcycle from the catalog?')" class="vhx-inline-form">
                      <?= authContextField() ?>
                      <input type="hidden" name="action" value="delete_motorcycle_catalog">
                      <input type="hidden" name="motorcycle_id" value="<?= (int)$row['id'] ?>">
                      <button type="submit" class="vhx-act-btn vhx-act-btn--danger"><i class="fas fa-trash-can"></i> Delete</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="vhx-empty">
        <span class="vhx-empty-icon"><i class="fas fa-motorcycle"></i></span>
        <?php if ($vhxHasFilters): ?>
          <h3>No motorcycles match your filters</h3>
          <p>Try widening the filters or reset them to see the full catalog.</p>
          <a href="<?= $vhxResetUrl ?>" class="vhx-btn vhx-btn--ghost"><i class="fas fa-rotate-left"></i> Reset filters</a>
        <?php else: ?>
          <h3>No motorcycles yet</h3>
          <p>Start building the catalog so customers can pick their exact motorcycle.</p>
          <button type="button" class="vhx-btn vhx-btn--primary" onclick="document.getElementById('openMotorcycleWizard')?.click()">
            <i class="fas fa-plus"></i> Add First Motorcycle
          </button>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </section>
</div>

<!-- Edit modal -->
<div class="vehicle-modal vhx-modal" id="motorcycleEditModal" aria-hidden="true">
  <div class="vehicle-modal__backdrop" data-close-edit-modal></div>
  <div class="vehicle-modal__dialog vhx-dialog" role="dialog" aria-modal="true" aria-labelledby="motorcycleEditTitle">
    <div class="vhx-dialog-head">
      <div class="vhx-dialog-title">
        <span class="vhx-dialog-badge"><i class="fas fa-pen"></i></span>
        <div>
          <span class="vhx-dialog-kicker">Motorcycle Catalog</span>
          <h3 id="motorcycleEditTitle">Edit Motorcycle</h3>
        </div>
      </div>
      <button type="button" class="vhx-dialog-close" data-close-edit-modal aria-label="Close"><i class="fas fa-xmark"></i></button>
    </div>

    <div class="vehicle-modal__step is-active">
      <form method="post" class="vehicle-quick-form vhx-form" id="motorcycleEditForm">
        <?= authContextField() ?>
        <input type="hidden" name="action" value="save_motorcycle_catalog">
        <input type="hidden" name="motorcycle_id" id="editMotorcycleId" value="">

        <div class="vhx-form-grid">
          <label class="vhx-field">
            <span>Motorcycle type</span>
            <input type="text" name="type" id="editMotorcycleType" placeholder="Scooter">
          </label>
          <label class="vhx-field">
            <span>Brand</span>
            <input type="text" name="brand" id="editMotorcycleBrand" placeholder="Honda">
          </label>
          <label class="vhx-field">
            <span>Model</span>
            <input type="text" name="model" id="editMotorcycleModel" placeholder="Click 125">
          </label>
          <label class="vhx-field">
            <span>Engine CC</span>
            <input type="text" name="cc" id="editMotorcycleCc" placeholder="125cc">
          </label>
        </div>

        <div class="vhx-dialog-actions">
          <button type="button" class="vhx-btn vhx-btn--ghost" data-close-edit-modal>Cancel</button>
          <button type="submit" class="vhx-btn vhx-btn--primary" id="editMotorcycleSaveBtn"><i class="fas fa-check"></i> Update Motorcycle</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Add Motorcycle wizard modal -->
<div class="vehicle-modal vhx-modal" id="motorcycleWizardModal" aria-hidden="true">
  <div class="vehicle-modal__backdrop" data-close-modal></div>
  <div class="vehicle-modal__dialog vhx-dialog vhx-dialog--wizard" role="dialog" aria-modal="true" aria-labelledby="motorcycleWizardTitle">
    <div class="vhx-dialog-head">
      <div class="vhx-dialog-title">
        <span class="vhx-dialog-badge"><i class="fas fa-motorcycle"></i></span>
        <div>
          <span class="vhx-dialog-kicker">Motorcycle Catalog</span>
          <h3 id="motorcycleWizardTitle">Add Motorcycle</h3>
        </div>
      </div>
      <button type="button" class="vhx-dialog-close" data-close-modal aria-label="Close"><i class="fas fa-xmark"></i></button>
    </div>

    <div class="vhx-stepper" aria-hidden="true">
      <div class="vhx-step" data-stepper-item="1">
        <span class="vhx-step-dot"><i class="fas fa-shapes"></i><i class="fas fa-check vhx-step-check"></i></span>
        <span class="vhx-step-copy"><small>Step 1</small><strong>Type</strong></span>
      </div>
      <span class="vhx-step-bar"></span>
      <div class="vhx-step" data-stepper-item="2">
        <span class="vhx-step-dot"><i class="fas fa-tag"></i><i class="fas fa-check vhx-step-check"></i></span>
        <span class="vhx-step-copy"><small>Step 2</small><strong>Brand</strong></span>
      </div>
      <span class="vhx-step-bar"></span>
      <div class="vhx-step" data-stepper-item="3">
        <span class="vhx-step-dot"><i class="fas fa-gauge-high"></i><i class="fas fa-check vhx-step-check"></i></span>
        <span class="vhx-step-copy"><small>Step 3</small><strong>Model / Engine CC</strong></span>
      </div>
    </div>

    <div class="vhx-status" id="wizardSearchStatus" aria-live="polite"></div>

    <div class="vehicle-modal__step is-active" data-step="1">
      <div class="vhx-step-intro">
        <h4>What type of motorcycle is it?</h4>
        <p>Enter the motorcycle type exactly as you want it saved, e.g. Scooter, Underbone, or Backbone.</p>
      </div>
      <label class="vehicle-modal__field vhx-field">
        <span>Motorcycle Type</span>
        <input type="text" id="wizardTypeInput" placeholder="e.g. Scooter">
      </label>
      <div class="vhx-dialog-actions vhx-dialog-actions--end">
        <button type="button" class="vhx-btn vhx-btn--primary" data-next-step>Next <i class="fas fa-arrow-right"></i></button>
      </div>
    </div>

    <div class="vehicle-modal__step" data-step="2">
      <div class="vhx-step-intro">
        <h4>Which brand makes it?</h4>
        <p>Brand names are entered manually and reused across the catalog.</p>
      </div>
      <label class="vehicle-modal__field vhx-field">
        <span>Brand Name</span>
        <input type="text" id="wizardBrandInput" placeholder="e.g. Honda">
      </label>
      <div class="vhx-dialog-actions">
        <button type="button" class="vhx-btn vhx-btn--ghost" data-prev-step><i class="fas fa-arrow-left"></i> Back</button>
        <button type="button" class="vhx-btn vhx-btn--primary" data-next-step>Next <i class="fas fa-arrow-right"></i></button>
      </div>
    </div>

    <div class="vehicle-modal__step" data-step="3">
      <div class="vhx-step-intro">
        <h4>Which model is it?</h4>
        <p>We'll automatically look up the engine specification for this model after this step.</p>
      </div>
      <label class="vehicle-modal__field vhx-field">
        <span>Model Name</span>
        <input type="text" id="wizardModelInput" placeholder="e.g. Click 125">
      </label>
      <div class="vhx-dialog-actions">
        <button type="button" class="vhx-btn vhx-btn--ghost" data-prev-step><i class="fas fa-arrow-left"></i> Back</button>
        <button type="button" class="vhx-btn vhx-btn--primary" id="searchMotorcycleSpecBtn"><i class="fas fa-magnifying-glass"></i> Search Specification</button>
      </div>
    </div>

    <div class="vehicle-modal__step" data-step="result">
      <div class="vhx-step-intro">
        <h4>Review before saving</h4>
        <p id="wizardResultMessage">Review the engine cc before saving.</p>
      </div>

      <div class="vehicle-result-card vhx-result">
        <div><span>Type</span><strong id="resultTypeValue">-</strong></div>
        <div><span>Brand</span><strong id="resultBrandValue">-</strong></div>
        <div><span>Model</span><strong id="resultModelValue">-</strong></div>
        <div>
          <span>Engine CC</span>
          <strong id="resultCcValue">-</strong>
          <input type="text" id="manualCcInput" class="vehicle-manual-cc" placeholder="125cc" hidden>
        </div>
      </div>

      <div class="vehicle-candidate-panel vhx-candidates" id="candidatePanel" hidden>
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

        <div class="vhx-dialog-actions">
          <button type="button" class="vhx-btn vhx-btn--ghost" id="wizardEditBtn"><i class="fas fa-arrow-left"></i> Edit Details</button>
          <button type="submit" class="vhx-btn vhx-btn--primary" id="wizardSaveBtn"><i class="fas fa-check"></i> Save Motorcycle</button>
        </div>
      </form>
    </div>
  </div>
</div>
