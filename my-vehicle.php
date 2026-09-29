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
        $existingVehicle = fetchOne(
            "SELECT id FROM customer_vehicles
             WHERE user_id = ? AND model_id = ? AND year <=> ? AND COALESCE(plate_number, '') = ?
             LIMIT 1",
            [$user['id'], $modelId, $year, $plate]
        );
        if ($existingVehicle) {
            flashMessage('vehicle_msg', 'This motorcycle is already saved.');
            redirect(baseUrl('my-vehicle.php'));
        }
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

<section class="section container vehicle-garage">
  <header class="vehicle-garage-heading">
    <div>
      <h1>My Motorcycles</h1>
      <p>Manage your saved motorcycles and quickly book compatible services.</p>
    </div>
    <button type="button" class="btn btn-primary vehicle-garage-add" data-mv-add>
      <i class="fas fa-plus" aria-hidden="true"></i>
      <span>Add Motorcycle</span>
    </button>
  </header>

  <?php if ($flash): ?><div class="alert success vehicle-garage-alert"><?= htmlspecialchars($flash) ?></div><?php endif; ?>

  <div class="mv-vehicle-modal" id="mvVehicleModal" hidden data-auto-open="<?= ($editVehicle || $error) ? 'true' : 'false' ?>">
    <div class="mv-vehicle-modal-backdrop" data-mv-vehicle-dismiss></div>
    <section class="mv-vehicle-dialog" role="dialog" aria-modal="true" aria-labelledby="mvVehicleModalTitle" aria-describedby="mvVehicleModalDescription" tabindex="-1">
      <button type="button" class="mv-vehicle-modal-close" aria-label="Close motorcycle form" data-mv-vehicle-dismiss>
        <i class="fas fa-times" aria-hidden="true"></i>
      </button>
      <div class="mv-vehicle-modal-heading">
        <div class="mv-vehicle-modal-icon" aria-hidden="true"><i class="fas fa-motorcycle"></i></div>
        <div>
          <h2 id="mvVehicleModalTitle"><?= $editVehicle ? 'Edit Motorcycle' : 'Add Motorcycle' ?></h2>
          <p id="mvVehicleModalDescription">Save your motorcycle details for compatible services and products.</p>
        </div>
      </div>

  <form class="vehicle-editor" id="vehicleEditorForm" method="post">
    <?= authContextField() ?>

    <input type="hidden" name="action" id="vehicleFormAction" value="<?= $editVehicle ? 'edit' : 'add' ?>">
    <input type="hidden" name="vehicle_id" id="vehicleFormId" value="<?= $editVehicle ? (int)$editVehicle['id'] : '' ?>">
    <!-- The wizard moves inactive steps into a DocumentFragment. Keep add-flow
         values in form-owned controls so Review can still submit them. -->
    <input type="hidden" name="type_name" id="wizardTypePayload" value="" disabled>
    <input type="hidden" name="brand_name" id="wizardBrandPayload" value="" disabled>
    <input type="hidden" name="model_id" id="wizardModelPayload" value="" disabled>

    <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="mv-add-wizard" id="mvAddWizard" aria-live="polite">
      <div class="mv-add-wizard-progress" aria-label="Add motorcycle progress">
        <div class="mv-add-wizard-progress-step" data-mv-progress-step="1"><span class="mv-add-wizard-progress-dot"><b>1</b><i class="fas fa-check" aria-hidden="true"></i></span><small>Type</small></div>
        <i class="mv-add-wizard-progress-line" aria-hidden="true"></i>
        <div class="mv-add-wizard-progress-step" data-mv-progress-step="2"><span class="mv-add-wizard-progress-dot"><b>2</b><i class="fas fa-check" aria-hidden="true"></i></span><small>Brand</small></div>
        <i class="mv-add-wizard-progress-line" aria-hidden="true"></i>
        <div class="mv-add-wizard-progress-step" data-mv-progress-step="3"><span class="mv-add-wizard-progress-dot"><b>3</b><i class="fas fa-check" aria-hidden="true"></i></span><small>Model</small></div>
        <i class="mv-add-wizard-progress-line" aria-hidden="true"></i>
        <div class="mv-add-wizard-progress-step" data-mv-progress-step="4"><span class="mv-add-wizard-progress-dot"><b>4</b><i class="fas fa-check" aria-hidden="true"></i></span><small>Year &amp; Plate</small></div>
        <i class="mv-add-wizard-progress-line" aria-hidden="true"></i>
        <div class="mv-add-wizard-progress-step" data-mv-progress-step="5"><span class="mv-add-wizard-progress-dot"><b>5</b><i class="fas fa-check" aria-hidden="true"></i></span><small>Review</small></div>
      </div>
      <p class="mv-add-wizard-count" id="mvAddWizardCount">Step 1 of 5</p>
      <h3 id="mvAddWizardTitle">What type of motorcycle do you have?</h3>
      <p id="mvAddWizardDescription">Choose the motorcycle type that best matches your vehicle.</p>
      <div class="mv-add-wizard-error" id="mvAddWizardError" role="alert" hidden></div>
    </div>

    <div class="mv-vehicle-stage" id="mvVehicleStage" aria-live="polite"></div>

    <div class="mv-vehicle-edit-fields" id="mvVehicleEditFields">
    <div class="mv-vehicle-step" data-mv-step="1">
    <label>Motorcycle type
      <select name="type_name" id="typeSelect" required data-mtx-enhance>
        <option value="">Select type</option>
        <?php foreach ($typeOptions as $typeName): ?>
          <option value="<?= htmlspecialchars($typeName) ?>" <?= $editTypeName !== '' && strcasecmp($editTypeName, $typeName) === 0 ? 'selected' : '' ?>>
            <?= htmlspecialchars($typeName) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
      <div class="mv-add-choice-grid" data-mv-choice="type"></div>
    </div>

    <div class="mv-vehicle-step" data-mv-step="2">
    <label>Brand
      <select name="brand_name" id="brandSelect" required data-mtx-enhance>
        <option value="">Select brand</option>
        <?php foreach ($brandOptions as $brandName): ?>
          <option value="<?= htmlspecialchars($brandName) ?>" <?= $editBrandName !== '' && strcasecmp($editBrandName, $brandName) === 0 ? 'selected' : '' ?>>
            <?= htmlspecialchars($brandName) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
      <div class="mv-add-choice-grid" data-mv-choice="brand"></div>
    </div>

    <div class="mv-vehicle-step" data-mv-step="3">
    <label>Model
      <select name="model_id" id="modelSelect" required data-mtx-enhance>
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
      <div class="mv-add-choice-grid" data-mv-choice="model"></div>
    </div>

    <div class="mv-vehicle-step" data-mv-step="4">
    <label class="mv-add-engine-display">Engine cc
      <div id="ccDisplay" class="cc-display"><?= $editVehicle ? (int)$editVehicle['cc'] . 'cc' : '-' ?></div>
    </label>

    <div class="mv-add-vehicle-details-inputs">
      <div class="mv-add-vehicle-details-heading">
        <span aria-hidden="true"><i class="fas fa-clipboard-list"></i></span>
        <strong>Vehicle Details</strong>
      </div>
      <div class="mv-add-vehicle-details-fields">
        <label><span><i class="fas fa-calendar-alt" aria-hidden="true"></i>Year</span>
          <input type="number" name="year" id="vehicleYear" min="1970" max="<?= date('Y') + 1 ?>" placeholder="e.g. 2023"
                 value="<?= $editVehicle && $editVehicle['year'] ? (int)$editVehicle['year'] : '' ?>">
        </label>

        <label><span><i class="fas fa-id-card" aria-hidden="true"></i>Plate number</span>
          <input type="text" name="plate_number" id="vehiclePlate" placeholder="e.g. 578QIM"
                 value="<?= $editVehicle ? htmlspecialchars($editVehicle['plate_number'] ?? '') : '' ?>">
        </label>
      </div>
    </div>
    </div>

    <section class="mv-vehicle-step mv-add-review" data-mv-step="5" aria-labelledby="mvAddReviewDetailsTitle">
      <div class="mv-add-review-icon" aria-hidden="true"><i class="fas fa-motorcycle"></i></div>
      <div class="mv-add-review-identity">
        <span id="mvAddReviewBrand"></span>
        <strong id="mvAddReviewModel"></strong>
        <p id="mvAddReviewMeta"></p>
      </div>
      <section class="mv-add-review-details-panel" aria-labelledby="mvAddReviewDetailsTitle">
        <header>
          <span class="mv-add-review-details-icon" aria-hidden="true"><i class="fas fa-clipboard-list"></i></span>
          <h3 id="mvAddReviewDetailsTitle">Vehicle Details</h3>
        </header>
        <dl class="mv-add-review-details">
          <div>
            <dt><i class="fas fa-calendar-alt" aria-hidden="true"></i><span>Year</span></dt>
            <dd id="mvAddReviewYear"></dd>
          </div>
          <div>
            <dt><i class="fas fa-motorcycle" aria-hidden="true"></i><span>Motorcycle type</span></dt>
            <dd id="mvAddReviewType"></dd>
          </div>
          <div>
            <dt><i class="fas fa-id-card" aria-hidden="true"></i><span>Plate number</span></dt>
            <dd id="mvAddReviewPlate"></dd>
          </div>
          <div>
            <dt><i class="fas fa-cog" aria-hidden="true"></i><span>Engine</span></dt>
            <dd id="mvAddReviewCc"></dd>
          </div>
        </dl>
      </section>
      <aside class="mv-add-review-note">
        <i class="fas fa-info-circle" aria-hidden="true"></i>
        <div>
          <strong>Double-check your information</strong>
          <span>Make sure all details are correct before saving.</span>
        </div>
      </aside>
    </section>
    </div>

    <div class="mv-vehicle-modal-actions" id="mvVehicleModalActions">
      <button type="button" class="mv-vehicle-modal-cancel" id="mvVehicleModalCancel" data-mv-vehicle-dismiss><i class="fas fa-times" aria-hidden="true"></i><span>Cancel</span></button>
      <div class="mv-add-wizard-navigation">
        <button type="button" class="mv-add-wizard-back" id="mvAddWizardBack" hidden>
          <i class="fas fa-arrow-left" aria-hidden="true"></i><span>Back</span>
        </button>
        <button type="button" class="mv-add-wizard-next" id="mvAddWizardNext" hidden>
          <span>Next</span><i class="fas fa-arrow-right" aria-hidden="true"></i>
        </button>
        <button class="mv-vehicle-modal-save" type="submit" id="vehicleFormSubmit">
          <i class="fas fa-plus" aria-hidden="true"></i>
          <span><?= $editVehicle ? 'Save Changes' : 'Save Motorcycle' ?></span>
        </button>
      </div>
    </div>
  </form>

    </section>
  </div>

  <aside class="vehicle-collection" id="vehicleCollectionPanel">
    <?php if ($vehicles): ?>
      <div class="garage-vehicle-grid">
        <?php foreach ($vehicles as $v): ?>
          <article class="garage-vehicle-card <?= $editVehicle && (int)$editVehicle['id'] === (int)$v['id'] ? 'is-active' : '' ?>">
            <div class="garage-vehicle-identity">
              <div class="garage-vehicle-icon" aria-hidden="true"><i class="fas fa-motorcycle"></i></div>
              <div>
                <span class="garage-vehicle-brand"><?= htmlspecialchars($v['brand_name']) ?></span>
                <h2><?= htmlspecialchars($v['model_name']) ?></h2>
                <p><?= htmlspecialchars($v['type_name']) ?> <span aria-hidden="true">&bull;</span> <?= (int)$v['cc'] ?>cc</p>
              </div>
            </div>
            <div class="garage-vehicle-specs">
              <div class="mv-detail">
                <span class="mv-detail-label"><i class="fas fa-calendar-alt" aria-hidden="true"></i>Year</span>
                <span class="mv-detail-value"><?= $v['year'] ? (int)$v['year'] : '—' ?></span>
              </div>
              <div class="mv-detail">
                <span class="mv-detail-label"><i class="fas fa-id-card" aria-hidden="true"></i>Plate number</span>
                <?php if ($v['plate_number']): ?>
                  <span class="mv-plate"><?= htmlspecialchars($v['plate_number']) ?></span>
                <?php else: ?>
                  <span class="mv-detail-value">—</span>
                <?php endif; ?>
              </div>
            </div>

            <div class="garage-vehicle-actions">
              <a href="<?= baseUrl('book-service.php?vehicle_id=' . (int)$v['id']) ?>" class="garage-vehicle-book">
                <i class="fas fa-calendar-check" aria-hidden="true"></i>
                <span>Book Service</span>
              </a>
              <button type="button" class="garage-vehicle-edit" data-mv-edit
                      data-mv-vehicle-id="<?= (int)$v['id'] ?>"
                      data-mv-type="<?= htmlspecialchars($v['type_name'], ENT_QUOTES) ?>"
                      data-mv-brand="<?= htmlspecialchars($v['brand_name'], ENT_QUOTES) ?>"
                      data-mv-model-id="<?= (int)$v['model_id'] ?>"
                      data-mv-year="<?= $v['year'] ? (int)$v['year'] : '' ?>"
                      data-mv-plate="<?= htmlspecialchars($v['plate_number'] ?? '', ENT_QUOTES) ?>">
                <i class="fas fa-pen" aria-hidden="true"></i>
                <span>Edit</span>
              </button>
              <?php
                $vehicleRemoveName = trim($v['brand_name'] . ' ' . $v['model_name']);
                $vehicleRemoveMeta = implode(' • ', array_filter([
                    $v['type_name'] ?? '',
                    !empty($v['cc']) ? (int)$v['cc'] . 'cc' : '',
                    !empty($v['plate_number']) ? 'Plate ' . $v['plate_number'] : ''
                ]));
              ?>
              <form method="post" class="mv-remove-form" data-mv-remove-form
                    data-mv-vehicle-name="<?= htmlspecialchars($vehicleRemoveName, ENT_QUOTES) ?>"
                    data-mv-vehicle-meta="<?= htmlspecialchars($vehicleRemoveMeta, ENT_QUOTES) ?>">
                <?= authContextField() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="vehicle_id" value="<?= (int)$v['id'] ?>">
                <button type="submit" class="garage-vehicle-remove">
                  <i class="fas fa-trash" aria-hidden="true"></i>
                  <span>Remove</span>
                </button>
              </form>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <section class="garage-vehicle-empty">
        <div class="garage-vehicle-empty-icon" aria-hidden="true"><i class="fas fa-motorcycle"></i></div>
        <h2>No motorcycles saved yet.</h2>
        <p>Add your motorcycle to get compatible service and product recommendations.</p>
        <button type="button" class="btn btn-primary" data-mv-add>
          <i class="fas fa-plus" aria-hidden="true"></i>
          <span>Add Your First Motorcycle</span>
        </button>
      </section>
    <?php endif; ?>
  </aside>
</section>

<div class="mv-remove-modal" id="mvRemoveModal" hidden>
  <div class="mv-remove-modal-backdrop" data-mv-modal-dismiss></div>
  <section class="mv-remove-dialog" role="dialog" aria-modal="true" aria-labelledby="mvRemoveModalTitle" aria-describedby="mvRemoveModalDescription" tabindex="-1">
    <button type="button" class="mv-remove-modal-close" aria-label="Close remove motorcycle dialog" data-mv-modal-dismiss>
      <i class="fas fa-times" aria-hidden="true"></i>
    </button>
    <div class="mv-remove-modal-icon" aria-hidden="true">
      <i class="fas fa-trash-alt"></i>
    </div>
    <h2 id="mvRemoveModalTitle">Remove motorcycle?</h2>
    <p id="mvRemoveModalDescription">You’re about to remove this saved motorcycle.</p>
    <div class="mv-remove-modal-vehicle" aria-live="polite">
      <strong id="mvRemoveVehicleName"></strong>
      <span id="mvRemoveVehicleMeta"></span>
    </div>
    <p class="mv-remove-modal-note">This motorcycle will be removed from your saved vehicles.</p>
    <div class="mv-remove-modal-actions">
      <button type="button" class="mv-remove-modal-cancel" data-mv-modal-dismiss>Cancel</button>
      <button type="button" class="mv-remove-modal-confirm" id="mvRemoveModalConfirm">
        <i class="fas fa-trash-alt" aria-hidden="true"></i>
        <span>Remove Motorcycle</span>
      </button>
    </div>
  </section>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
(() => {
  // Enhance the Type / Brand / Model <select>s with the shared custom listbox
  // (same visual language as Shop and Book Service). The native <select> stays in
  // the form as the source of truth, so names, values, selected value, `required`
  // validation and the dependent Type->Brand->Model->CC logic in main.js are all
  // preserved. This runs AFTER main.js, so our resync fires after filterVehicleModels.
  const selects = document.querySelectorAll('select[data-mtx-enhance]');
  if (!selects.length || !('closest' in Element.prototype)) return;
  let counter = 0;
  const wraps = [];

  const closeAll = (except) => {
    document.querySelectorAll('.mtx-select[data-open]').forEach((el) => {
      if (el === except) return;
      el.removeAttribute('data-open');
      el.querySelector('.mtx-select-trigger').setAttribute('aria-expanded', 'false');
      el.querySelector('.mtx-select-menu').hidden = true;
    });
  };

  selects.forEach((select) => {
    const uid = 'mtxmv-' + (counter++);
    const lab = select.closest('label');
    let lblId = '';
    if (lab) { if (!lab.id) lab.id = uid + '-lbl'; lblId = lab.id; }

    const wrap = document.createElement('div');
    wrap.className = 'mtx-select';

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'mtx-select-trigger';
    trigger.id = uid + '-trg';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');

    const valueEl = document.createElement('span');
    valueEl.className = 'mtx-select-value';
    valueEl.id = uid + '-val';

    const chevron = document.createElement('i');
    chevron.className = 'fas fa-chevron-down mtx-select-chevron';
    chevron.setAttribute('aria-hidden', 'true');
    trigger.append(valueEl, chevron);

    const menu = document.createElement('ul');
    menu.className = 'mtx-select-menu';
    menu.id = uid + '-menu';
    menu.setAttribute('role', 'listbox');
    menu.tabIndex = -1;
    menu.hidden = true;
    if (lblId) menu.setAttribute('aria-labelledby', lblId);
    trigger.setAttribute('aria-controls', menu.id);
    trigger.setAttribute('aria-labelledby', (lblId ? lblId + ' ' : '') + valueEl.id);

    const options = Array.from(select.options).map((opt, i) => {
      const li = document.createElement('li');
      li.className = 'mtx-select-option';
      li.id = uid + '-opt-' + i;
      li.setAttribute('role', 'option');
      li.dataset.index = String(i);
      const label = document.createElement('span');
      label.className = 'mtx-select-option-label';
      label.textContent = opt.text.trim();
      const check = document.createElement('i');
      check.className = 'fas fa-check mtx-select-check';
      check.setAttribute('aria-hidden', 'true');
      li.append(label, check);
      menu.appendChild(li);
      return li;
    });

    let activeIndex = select.selectedIndex < 0 ? 0 : select.selectedIndex;
    const firstVisible = () => options.findIndex((o) => !o.hidden);
    const lastVisible = () => { let last = -1; options.forEach((o, i) => { if (!o.hidden) last = i; }); return last; };

    // Mirror native state (option.hidden, selected, trigger label) onto the custom UI.
    const sync = () => {
      options.forEach((li, i) => {
        li.hidden = !!select.options[i].hidden;
        li.setAttribute('aria-selected', select.options[i].selected ? 'true' : 'false');
      });
      const sel = select.options[select.selectedIndex];
      valueEl.textContent = sel ? sel.text.trim() : '';
    };

    const setActive = (idx, scroll) => {
      if (!options.length) return;
      const n = options.length;
      let j = ((idx % n) + n) % n, guard = 0;
      while (options[j].hidden && guard < n) { j = (j + 1) % n; guard++; }
      activeIndex = j;
      options.forEach((o, i) => o.classList.toggle('is-active', i === activeIndex));
      menu.setAttribute('aria-activedescendant', options[activeIndex].id);
      if (scroll !== false) options[activeIndex].scrollIntoView({ block: 'nearest' });
    };
    const step = (dir) => {
      const n = options.length;
      let j = activeIndex, guard = 0;
      do { j = ((j + dir) % n + n) % n; guard++; } while (options[j].hidden && guard <= n);
      setActive(j);
    };

    const open = () => {
      closeAll(wrap);
      wrap.setAttribute('data-open', '');
      if (select.closest('.mv-vehicle-dialog')) {
        const viewportHeight = window.visualViewport?.height || window.innerHeight;
        const triggerBounds = trigger.getBoundingClientRect();
        const roomBelow = viewportHeight - triggerBounds.bottom - 16;
        const roomAbove = triggerBounds.top - 16;
        const openUpward = roomBelow < 180 && roomAbove > roomBelow;
        const availableHeight = Math.max(104, Math.min(236, (openUpward ? roomAbove : roomBelow) - 6));
        wrap.toggleAttribute('data-open-upward', openUpward);
        menu.style.maxHeight = availableHeight + 'px';
        menu.style.top = openUpward ? 'auto' : 'calc(100% + 6px)';
        menu.style.bottom = openUpward ? 'calc(100% + 6px)' : 'auto';
      }
      trigger.setAttribute('aria-expanded', 'true');
      menu.hidden = false;
      const cur = select.selectedIndex;
      const start = (cur >= 0 && !options[cur].hidden) ? cur : firstVisible();
      setActive(start < 0 ? 0 : start, true);
      menu.focus();
    };
    const close = (focusTrigger) => {
      wrap.removeAttribute('data-open');
      wrap.removeAttribute('data-open-upward');
      trigger.setAttribute('aria-expanded', 'false');
      menu.hidden = true;
      if (focusTrigger) trigger.focus();
    };
    const choose = (idx) => {
      const opt = select.options[idx];
      if (!opt || opt.hidden) return;
      if (select.selectedIndex !== idx) {
        select.selectedIndex = idx;
        select.dispatchEvent(new Event('change', { bubbles: true }));
      }
      sync();
      close(true);
    };

    trigger.addEventListener('click', () => { wrap.hasAttribute('data-open') ? close(true) : open(); });
    trigger.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(); }
    });
    menu.addEventListener('keydown', (e) => {
      switch (e.key) {
        case 'ArrowDown': e.preventDefault(); step(1); break;
        case 'ArrowUp': e.preventDefault(); step(-1); break;
        case 'Home': e.preventDefault(); { const f = firstVisible(); if (f >= 0) setActive(f); } break;
        case 'End': e.preventDefault(); { const l = lastVisible(); if (l >= 0) setActive(l); } break;
        case 'Enter':
        case ' ': e.preventDefault(); choose(activeIndex); break;
        case 'Escape': e.preventDefault(); close(true); break;
        case 'Tab': close(false); break;
        default: break;
      }
    });
    options.forEach((li, i) => {
      li.addEventListener('click', () => choose(i));
      li.addEventListener('mousemove', () => { if (!li.hidden && activeIndex !== i) setActive(i, false); });
    });

    wrap.append(trigger, menu);
    select.classList.add('mtx-select-native');
    select.setAttribute('tabindex', '-1');
    select.setAttribute('aria-hidden', 'true');
    select.parentNode.insertBefore(wrap, select.nextSibling);

    // Native select changing programmatically (dependent filter clears the model,
    // or toggles option visibility) must reflect on this control too.
    select.addEventListener('change', sync);
    sync();
    wraps.push({ select, sync });
  });

  // A change on any enhanced select can affect another (Type/Brand -> Model options).
  // Resync every control after the change so hidden options / cleared values show.
  wraps.forEach(({ select }) => {
    select.addEventListener('change', () => { wraps.forEach((w) => w.sync()); });
  });

  document.addEventListener('mousedown', (e) => { if (!e.target.closest('.mtx-select')) closeAll(null); });
})();
</script>
<script>
(() => {
  const modal = document.getElementById('mvRemoveModal');
  const dialog = modal?.querySelector('.mv-remove-dialog');
  const confirmButton = document.getElementById('mvRemoveModalConfirm');
  const vehicleName = document.getElementById('mvRemoveVehicleName');
  const vehicleMeta = document.getElementById('mvRemoveVehicleMeta');
  const forms = Array.from(document.querySelectorAll('[data-mv-remove-form]'));
  if (!modal || !dialog || !confirmButton || !vehicleName || !vehicleMeta || !forms.length) return;

  let activeForm = null;
  let lastTrigger = null;
  let isSubmitting = false;

  const focusableElements = () => Array.from(dialog.querySelectorAll(
    'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
  )).filter((element) => !element.hidden);

  const closeModal = () => {
    if (modal.hidden || isSubmitting) return;
    modal.hidden = true;
    document.body.classList.remove('mv-remove-modal-open');
    activeForm = null;
    if (lastTrigger) lastTrigger.focus();
    lastTrigger = null;
  };

  const openModal = (form, trigger) => {
    activeForm = form;
    lastTrigger = trigger;
    isSubmitting = false;
    confirmButton.disabled = false;
    confirmButton.querySelector('span').textContent = 'Remove Motorcycle';
    vehicleName.textContent = form.dataset.mvVehicleName || 'Saved motorcycle';
    vehicleMeta.textContent = form.dataset.mvVehicleMeta || 'Vehicle details unavailable';
    modal.hidden = false;
    document.body.classList.add('mv-remove-modal-open');
    requestAnimationFrame(() => confirmButton.focus());
  };

  forms.forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (form.dataset.mvModalConfirmed === 'true') return;
      event.preventDefault();
      openModal(form, form.querySelector('button[type="submit"]'));
    });
  });

  modal.querySelectorAll('[data-mv-modal-dismiss]').forEach((button) => {
    button.addEventListener('click', closeModal);
  });

  confirmButton.addEventListener('click', () => {
    if (!activeForm || isSubmitting) return;
    isSubmitting = true;
    confirmButton.disabled = true;
    confirmButton.querySelector('span').textContent = 'Removing…';
    activeForm.dataset.mvModalConfirmed = 'true';
    activeForm.submit();
  });

  document.addEventListener('keydown', (event) => {
    if (modal.hidden) return;
    if (event.key === 'Escape') {
      event.preventDefault();
      closeModal();
      return;
    }
    if (event.key !== 'Tab') return;
    const focusable = focusableElements();
    if (!focusable.length) {
      event.preventDefault();
      dialog.focus();
      return;
    }
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });
})();
</script>
<script>
(() => {
  const modal = document.getElementById('mvVehicleModal');
  const dialog = modal?.querySelector('.mv-vehicle-dialog');
  const form = document.getElementById('vehicleEditorForm');
  const actionInput = document.getElementById('vehicleFormAction');
  const idInput = document.getElementById('vehicleFormId');
  const typeSelect = document.getElementById('typeSelect');
  const brandSelect = document.getElementById('brandSelect');
  const modelSelect = document.getElementById('modelSelect');
  const wizardTypePayload = document.getElementById('wizardTypePayload');
  const wizardBrandPayload = document.getElementById('wizardBrandPayload');
  const wizardModelPayload = document.getElementById('wizardModelPayload');
  const ccDisplay = document.getElementById('ccDisplay');
  const yearInput = document.getElementById('vehicleYear');
  const plateInput = document.getElementById('vehiclePlate');
  const title = document.getElementById('mvVehicleModalTitle');
  const submitButton = document.getElementById('vehicleFormSubmit');
  const addWizard = document.getElementById('mvAddWizard');
  const wizardCount = document.getElementById('mvAddWizardCount');
  const wizardTitle = document.getElementById('mvAddWizardTitle');
  const wizardDescription = document.getElementById('mvAddWizardDescription');
  const wizardError = document.getElementById('mvAddWizardError');
  const wizardBack = document.getElementById('mvAddWizardBack');
  const wizardNext = document.getElementById('mvAddWizardNext');
  const stageMount = document.getElementById('mvVehicleStage');
  const editFields = document.getElementById('mvVehicleEditFields');
  const actionFooter = document.getElementById('mvVehicleModalActions');
  const cancelButton = document.getElementById('mvVehicleModalCancel');
  if (!modal || !dialog || !form || !actionInput || !idInput || !typeSelect || !brandSelect || !modelSelect || !wizardTypePayload || !wizardBrandPayload || !wizardModelPayload || !ccDisplay || !yearInput || !plateInput || !title || !submitButton || !addWizard || !wizardCount || !wizardTitle || !wizardDescription || !wizardError || !wizardBack || !wizardNext || !stageMount || !editFields || !actionFooter || !cancelButton) return;

  let lastTrigger = null;
  let wizardStep = 1;
  let wizardActive = false;
  let isSubmitting = false;
  let priorType = '';
  let priorBrand = '';
  const wizardSteps = Array.from(form.querySelectorAll('[data-mv-step]'));
  const stageStash = document.createDocumentFragment();
  const stepFor = (number) => wizardSteps.find((step) => Number(step.dataset.mvStep) === number);

  const focusableElements = () => Array.from(dialog.querySelectorAll(
    'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
  )).filter((element) => !element.hidden && !element.closest('[hidden]'));

  const setSelect = (select, value) => {
    select.value = value || '';
    select.dispatchEvent(new Event('change', { bubbles: true }));
  };

  const syncWizardPayload = () => {
    wizardTypePayload.value = typeSelect.value;
    wizardBrandPayload.value = brandSelect.value;
    wizardModelPayload.value = modelSelect.value;
  };

  const optionText = (select) => select.selectedOptions[0]?.textContent.trim() || '—';
  const normalizeChoice = (value) => String(value || '').trim().toLowerCase();
  const modelOptionsFor = (type = typeSelect.value, brand = brandSelect.value) => Array.from(modelSelect.options).filter((option) => {
    if (!option.value) return false;
    const typeMatches = !normalizeChoice(type) || normalizeChoice(option.dataset.type) === normalizeChoice(type);
    const brandMatches = !normalizeChoice(brand) || normalizeChoice(option.dataset.brand) === normalizeChoice(brand);
    return typeMatches && brandMatches;
  });
  const compatibleBrandValues = () => new Set(modelOptionsFor(typeSelect.value, '').map((option) => option.dataset.brand));
  const compatibleModelOptions = () => modelOptionsFor(typeSelect.value, brandSelect.value);

  const clearWizardError = () => {
    wizardError.hidden = true;
    wizardError.textContent = '';
  };

  const showWizardError = (message) => {
    wizardError.textContent = message;
    wizardError.hidden = false;
  };

  const choiceEntries = (kind) => {
    if (kind === 'type') {
      return Array.from(typeSelect.options).filter((option) => option.value).map((option) => ({ value: option.value, label: option.textContent.trim(), icon: 'fa-motorcycle' }));
    }
    if (kind === 'brand') {
      const allowed = compatibleBrandValues();
      return Array.from(brandSelect.options).filter((option) => option.value && allowed.has(option.value)).map((option) => ({ value: option.value, label: option.textContent.trim() }));
    }
    return compatibleModelOptions().map((option) => ({ value: option.value, label: option.textContent.trim() }));
  };

  const selectForKind = (kind) => kind === 'type' ? typeSelect : kind === 'brand' ? brandSelect : modelSelect;

  const renderChoices = (kind) => {
    const step = stepFor(kind === 'type' ? 1 : kind === 'brand' ? 2 : 3);
    const container = step?.querySelector('[data-mv-choice="' + kind + '"]');
    if (!step || !container) return;
    const entries = choiceEntries(kind);
    const select = selectForKind(kind);
    const useSelect = kind !== 'brand' && entries.length > 8;
    step.toggleAttribute('data-mv-use-select', useSelect);
    container.replaceChildren();
    entries.forEach((entry) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'mv-add-choice mv-add-choice-' + kind + (select.value === entry.value ? ' is-selected' : '');
      button.setAttribute('aria-pressed', select.value === entry.value ? 'true' : 'false');
      if (kind === 'type') {
        const icon = document.createElement('span');
        icon.className = 'mv-add-choice-type-icon';
        icon.innerHTML = '<i class="fas ' + entry.icon + '" aria-hidden="true"></i>';
        button.append(icon);
      }
      const label = document.createElement(kind === 'type' ? 'span' : 'span');
      label.className = kind === 'type' ? 'mv-add-choice-copy' : 'mv-add-choice-label';
      if (kind === 'type') {
        const name = document.createElement('strong');
        name.textContent = entry.label;
        label.append(name);
      } else {
        label.textContent = entry.label;
      }
      const check = document.createElement('i');
      check.className = 'fas fa-check';
      check.setAttribute('aria-hidden', 'true');
      button.append(label, check);
      button.addEventListener('click', () => {
        setSelect(select, entry.value);
        clearWizardError();
        if (kind === 'type') renderChoices('brand');
        if (kind !== 'model') renderChoices('model');
        renderChoices(kind);
      });
      container.append(button);
    });
  };

  const refreshReview = () => {
    const review = stepFor(5);
    const setReviewText = (id, value) => {
      const target = review?.querySelector('#' + id);
      if (target) target.textContent = value;
    };
    const type = optionText(typeSelect);
    const brand = optionText(brandSelect);
    const model = optionText(modelSelect);
    const engineCc = ccDisplay.textContent || '—';
    setReviewText('mvAddReviewBrand', brand);
    setReviewText('mvAddReviewModel', model);
    setReviewText('mvAddReviewMeta', type + ' • ' + engineCc);
    setReviewText('mvAddReviewType', type);
    setReviewText('mvAddReviewCc', engineCc);
    setReviewText('mvAddReviewYear', yearInput.value || 'Not set');
    setReviewText('mvAddReviewPlate', plateInput.value || 'Not set');
  };

  const stepCopy = {
    1: ['What type of motorcycle do you have?', 'Choose the motorcycle type that best matches your vehicle.'],
    2: ['Choose your motorcycle brand.', 'Available brands are matched to your selected motorcycle type.'],
    3: ['Choose your motorcycle model.', 'Only models compatible with your type and brand are shown.'],
    4: ['Complete the remaining details.', 'Engine CC is set automatically from the selected model.'],
    5: ['Review Your Motorcycle', 'Please review all the information below before saving.']
  };

  const parkWizardSteps = () => wizardSteps.forEach((step) => stageStash.append(step));

  const showWizardStage = () => {
    parkWizardSteps();
    stageMount.replaceChildren(stepFor(wizardStep));
    stageMount.classList.remove('is-entering');
    void stageMount.offsetWidth;
    stageMount.classList.add('is-entering');
  };

  const showEditFields = () => {
    parkWizardSteps();
    editFields.replaceChildren(...wizardSteps.slice(0, 4));
  };

  const renderWizardStep = () => {
    addWizard.querySelectorAll('[data-mv-progress-step]').forEach((marker) => {
      const number = Number(marker.dataset.mvProgressStep);
      marker.classList.toggle('is-current', number === wizardStep);
      marker.classList.toggle('is-complete', number < wizardStep);
    });
    addWizard.querySelectorAll('.mv-add-wizard-progress-line').forEach((line, index) => line.classList.toggle('is-complete', index < wizardStep - 1));
    wizardCount.textContent = 'Step ' + wizardStep + ' of 5';
    wizardTitle.textContent = stepCopy[wizardStep][0];
    wizardDescription.textContent = stepCopy[wizardStep][1];
    actionFooter.dataset.mvWizardStep = String(wizardStep);
    cancelButton.hidden = false;
    wizardBack.hidden = wizardStep === 1;
    wizardNext.hidden = wizardStep === 5;
    wizardNext.querySelector('span').textContent = wizardStep === 4 ? 'Review' : 'Next';
    submitButton.hidden = wizardStep !== 5;
    submitButton.querySelector('span').textContent = 'Save Motorcycle';
    submitButton.querySelector('i').className = 'fas fa-plus';
    if (wizardStep === 5) refreshReview();
    renderChoices('type');
    renderChoices('brand');
    renderChoices('model');
    showWizardStage();
  };

  const validateWizardStep = () => {
    clearWizardError();
    if (wizardStep === 1 && !typeSelect.value) { showWizardError('Select a motorcycle type to continue.'); return false; }
    if (wizardStep === 2 && !brandSelect.value) { showWizardError('Select a motorcycle brand to continue.'); return false; }
    if (wizardStep === 3 && (!modelSelect.value || !compatibleModelOptions().some((option) => option.value === modelSelect.value))) { showWizardError('Select a compatible motorcycle model to continue.'); return false; }
    if (wizardStep === 4 && yearInput.value && !yearInput.checkValidity()) {
      yearInput.reportValidity();
      return false;
    }
    return true;
  };

  const configureWizard = (mode) => {
    wizardActive = mode === 'add';
    form.classList.toggle('is-add-wizard', wizardActive);
    [wizardTypePayload, wizardBrandPayload, wizardModelPayload].forEach((input) => {
      input.disabled = !wizardActive;
    });
    addWizard.hidden = !wizardActive;
    actionFooter.classList.toggle('is-add-wizard', wizardActive);
    if (!wizardActive) {
      delete actionFooter.dataset.mvWizardStep;
      stageMount.hidden = true;
      editFields.hidden = false;
      showEditFields();
      cancelButton.hidden = false;
      wizardBack.hidden = true;
      wizardNext.hidden = true;
      submitButton.hidden = false;
      return;
    }
    stageMount.hidden = false;
    editFields.hidden = true;
    wizardStep = 1;
    priorType = typeSelect.value;
    priorBrand = brandSelect.value;
    syncWizardPayload();
    clearWizardError();
    renderWizardStep();
  };

  typeSelect.addEventListener('change', () => {
    if (!wizardActive) return;
    if (typeSelect.value !== priorType) {
      priorType = typeSelect.value;
      const allowedBrands = compatibleBrandValues();
      if (!allowedBrands.has(brandSelect.value)) setSelect(brandSelect, '');
      setSelect(modelSelect, '');
      priorBrand = brandSelect.value;
    }
    syncWizardPayload();
    renderChoices('brand');
    renderChoices('model');
  });

  brandSelect.addEventListener('change', () => {
    if (!wizardActive) return;
    if (brandSelect.value !== priorBrand) {
      priorBrand = brandSelect.value;
      setSelect(modelSelect, '');
    }
    syncWizardPayload();
    renderChoices('model');
  });

  modelSelect.addEventListener('change', () => {
    if (!wizardActive) return;
    syncWizardPayload();
    renderChoices('model');
  });

  const closeModal = () => {
    if (modal.hidden) return;
    modal.hidden = true;
    document.body.classList.remove('mv-vehicle-modal-open');
    if (lastTrigger) lastTrigger.focus();
    lastTrigger = null;
  };

  const setMode = (mode, data = {}) => {
    const editing = mode === 'edit';
    actionInput.value = editing ? 'edit' : 'add';
    idInput.value = editing ? (data.vehicleId || '') : '';
    title.textContent = editing ? 'Edit Motorcycle' : 'Add Motorcycle';
    isSubmitting = false;
    submitButton.disabled = false;
    submitButton.querySelector('span').textContent = editing ? 'Save Changes' : 'Save Motorcycle';
    submitButton.querySelector('i').className = 'fas ' + (editing ? 'fa-check' : 'fa-plus');
    if (!editing) {
      setSelect(typeSelect, '');
      setSelect(brandSelect, '');
      setSelect(modelSelect, '');
      yearInput.value = '';
      plateInput.value = '';
      return;
    }
    setSelect(typeSelect, data.type || '');
    setSelect(brandSelect, data.brand || '');
    setSelect(modelSelect, data.modelId || '');
    yearInput.value = data.year || '';
    plateInput.value = data.plate || '';
  };

  const openModal = (mode, data, trigger) => {
    lastTrigger = trigger || null;
    // A dismissed Add flow must not leave its step listeners active while the
    // efficient full-form Edit dialog is being populated.
    wizardActive = false;
    form.classList.remove('is-add-wizard');
    setMode(mode, data);
    configureWizard(mode);
    modal.hidden = false;
    document.body.classList.add('mv-vehicle-modal-open');
    requestAnimationFrame(() => dialog.querySelector('.mtx-select-trigger, input')?.focus());
  };

  document.querySelectorAll('[data-mv-add]').forEach((button) => {
    button.addEventListener('click', () => openModal('add', {}, button));
  });

  document.querySelectorAll('[data-mv-edit]').forEach((button) => {
    button.addEventListener('click', () => openModal('edit', {
      vehicleId: button.dataset.mvVehicleId,
      type: button.dataset.mvType,
      brand: button.dataset.mvBrand,
      modelId: button.dataset.mvModelId,
      year: button.dataset.mvYear,
      plate: button.dataset.mvPlate
    }, button));
  });

  modal.querySelectorAll('[data-mv-vehicle-dismiss]').forEach((button) => {
    button.addEventListener('click', closeModal);
  });

  form.addEventListener('submit', (event) => {
    if (wizardActive && wizardStep !== 5) {
      event.preventDefault();
      validateWizardStep();
      return;
    }
    if (isSubmitting) {
      event.preventDefault();
      return;
    }
    if (wizardActive) syncWizardPayload();
    isSubmitting = true;
    submitButton.disabled = true;
    submitButton.querySelector('span').textContent = 'Saving…';
  });

  wizardNext.addEventListener('click', () => {
    if (!validateWizardStep()) return;
    wizardStep = Math.min(5, wizardStep + 1);
    renderWizardStep();
  });

  wizardBack.addEventListener('click', () => {
    clearWizardError();
    wizardStep = Math.max(1, wizardStep - 1);
    renderWizardStep();
  });

  document.addEventListener('keydown', (event) => {
    if (modal.hidden) return;
    if (event.key === 'Escape') {
      event.preventDefault();
      closeModal();
      return;
    }
    if (event.key !== 'Tab') return;
    const focusable = focusableElements();
    if (!focusable.length) {
      event.preventDefault();
      dialog.focus();
      return;
    }
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });

  if (modal.dataset.autoOpen === 'true') {
    openModal(actionInput.value === 'edit' ? 'edit' : 'add', {
      vehicleId: idInput.value,
      type: typeSelect.value,
      brand: brandSelect.value,
      modelId: modelSelect.value,
      year: yearInput.value,
      plate: plateInput.value
    });
  }
})();
</script>
