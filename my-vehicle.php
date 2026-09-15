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
  <header class="customer-page-heading vehicle-page-heading">
    <div>
      <h1>My motorcycles</h1>
      <p>Save motorcycle details once, then use them for compatible service booking.</p>
    </div>
  </header>

  <form class="form-panel vehicle-editor" method="post">
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
      <select name="type_name" id="typeSelect" required data-mtx-enhance>
        <option value="">Select type</option>
        <?php foreach ($typeOptions as $typeName): ?>
          <option value="<?= htmlspecialchars($typeName) ?>" <?= $editTypeName !== '' && strcasecmp($editTypeName, $typeName) === 0 ? 'selected' : '' ?>>
            <?= htmlspecialchars($typeName) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>

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

  <aside class="summary-box vehicle-collection">
    <h2>My Motorcycles</h2>
    <?php if ($vehicles): ?>
      <div class="mv-list">
        <?php foreach ($vehicles as $v): ?>
          <article class="mv-card <?= $editVehicle && (int)$editVehicle['id'] === (int)$v['id'] ? 'is-active' : '' ?>">
            <div class="mv-details">
              <div class="mv-detail">
                <span class="mv-detail-label">Brand</span>
                <span class="mv-detail-value"><?= htmlspecialchars($v['brand_name']) ?></span>
              </div>
              <div class="mv-detail">
                <span class="mv-detail-label">Model</span>
                <span class="mv-detail-value"><?= htmlspecialchars($v['model_name']) ?></span>
              </div>
              <div class="mv-detail">
                <span class="mv-detail-label">Type</span>
                <span class="mv-detail-value"><?= htmlspecialchars($v['type_name']) ?></span>
              </div>
              <div class="mv-detail">
                <span class="mv-detail-label">Engine CC</span>
                <span class="mv-detail-value"><?= (int)$v['cc'] ?>cc</span>
              </div>
              <div class="mv-detail">
                <span class="mv-detail-label">Year</span>
                <span class="mv-detail-value"><?= $v['year'] ? (int)$v['year'] : '—' ?></span>
              </div>
              <div class="mv-detail">
                <span class="mv-detail-label">Plate</span>
                <?php if ($v['plate_number']): ?>
                  <span class="mv-plate"><?= htmlspecialchars($v['plate_number']) ?></span>
                <?php else: ?>
                  <span class="mv-detail-value">—</span>
                <?php endif; ?>
              </div>
            </div>

            <div class="mv-actions">
              <a href="<?= baseUrl('book-service.php?vehicle_id=' . (int)$v['id']) ?>" class="mv-btn mv-book">
                <i class="fas fa-calendar-check" aria-hidden="true"></i>
                <span>Book Service</span>
              </a>
              <a href="<?= baseUrl('my-vehicle.php?edit=' . (int)$v['id']) ?>" class="mv-btn mv-edit">
                <i class="fas fa-pen" aria-hidden="true"></i>
                <span>Edit</span>
              </a>
              <form method="post" class="mv-remove-form" onsubmit="return confirm('Remove this motorcycle?')">
                <?= authContextField() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="vehicle_id" value="<?= (int)$v['id'] ?>">
                <button type="submit" class="mv-btn mv-remove">
                  <i class="fas fa-trash" aria-hidden="true"></i>
                  <span>Remove</span>
                </button>
              </form>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="customer-empty-state vehicle-empty-state">
        <h3>No saved motorcycles yet</h3>
        <p>Use the form to add the motorcycle you want to service.</p>
      </div>
    <?php endif; ?>
  </aside>
</section>

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
      trigger.setAttribute('aria-expanded', 'true');
      menu.hidden = false;
      const cur = select.selectedIndex;
      const start = (cur >= 0 && !options[cur].hidden) ? cur : firstVisible();
      setActive(start < 0 ? 0 : start, true);
      menu.focus();
    };
    const close = (focusTrigger) => {
      wrap.removeAttribute('data-open');
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
