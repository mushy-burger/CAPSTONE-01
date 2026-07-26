<?php
$pageTitle = 'New Booking';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/BookingSlots.php';
requireStaff();
$currentUser = getCurrentUser();

// Load data for form
$techs    = fetchAllRows("SELECT id, name FROM users WHERE role='technician' AND is_active=1 ORDER BY name");
$services = fetchAllRows("SELECT id, name, labor_fee, applies_to FROM service_types ORDER BY name");

// Full motorcycle catalog (Vehicle Options): every active brand/model/type combo.
$catalogModels = fetchAllRows(
    "SELECT mm.id, mm.name AS model_name, mm.cc, mm.type_id, mm.brand_id,
            mb.name AS brand_name, mt.name AS type_name
     FROM motorcycle_models mm
     INNER JOIN motorcycle_brands mb ON mb.id = mm.brand_id AND mb.is_active = 1
     INNER JOIN motorcycle_types mt ON mt.id = mm.type_id AND mt.is_active = 1
     WHERE mm.is_active = 1
     ORDER BY mb.name, mm.name"
);

/**
 * Normalize a booking date to Y-m-d. Native date inputs already submit ISO;
 * text fallbacks may submit localized values such as 18/07/2026 (d/m/Y).
 */
function nbNormalizeDate(string $date): string {
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    if ($dt && $dt->format('Y-m-d') === $date) {
        return $date;
    }
    foreach (['d/m/Y', 'd-m-Y'] as $format) {
        $dt = DateTime::createFromFormat($format, $date);
        if ($dt && $dt->format($format) === $date) {
            return $dt->format('Y-m-d');
        }
    }
    return '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerId = (int)($_POST['customer_id'] ?? 0);
    $modelId    = (int)($_POST['vehicle_model_id'] ?? 0);
    $techId     = (int)($_POST['technician_id'] ?? 0);
    $serviceIds = array_map('intval', (array)($_POST['service_ids'] ?? []));
    $schedDate  = nbNormalizeDate(trim($_POST['scheduled_date'] ?? ''));
    $schedTime  = trim($_POST['scheduled_time'] ?? '');
    $notes      = trim($_POST['notes'] ?? '');

    if (!$customerId || !$modelId || empty($serviceIds) || !$schedDate || !$schedTime) {
        flashMessage('nb_error', 'Please fill in all required fields (customer, motorcycle, services, date, time).');
        redirect(baseUrl('staff/new-booking.php'));
    }

    // Validate the selected customer + catalog motorcycle
    $customer = fetchOne("SELECT id, name FROM users WHERE id = ? AND role = 'customer' AND is_active = 1", [$customerId]);
    if (!$customer) {
        flashMessage('nb_error', 'Please pick a valid customer from the search results.');
        redirect(baseUrl('staff/new-booking.php'));
    }

    $model = fetchOne(
        "SELECT mm.id, mm.name AS model_name, mm.cc, mm.type_id, mm.brand_id
         FROM motorcycle_models mm
         INNER JOIN motorcycle_brands mb ON mb.id = mm.brand_id AND mb.is_active = 1
         INNER JOIN motorcycle_types mt ON mt.id = mm.type_id AND mt.is_active = 1
         WHERE mm.id = ? AND mm.is_active = 1",
        [$modelId]
    );
    if (!$model) {
        flashMessage('nb_error', 'Please pick a valid motorcycle from the catalog.');
        redirect(baseUrl('staff/new-booking.php'));
    }

    // Same date / operating-hours / capacity rules as the customer booking page
    if ($schedDate < date('Y-m-d')) {
        flashMessage('nb_error', 'The appointment date cannot be in the past.');
        redirect(baseUrl('staff/new-booking.php'));
    }
    if (!array_key_exists($schedTime, bookingTimeSlots())) {
        flashMessage('nb_error', 'Please choose a time slot within shop hours (8:00 AM – 5:00 PM).');
        redirect(baseUrl('staff/new-booking.php'));
    }
    $availability = bookingSlotAvailability($schedDate);
    if (($availability[$schedTime] ?? 0) <= 0) {
        flashMessage('nb_error', 'That time slot is fully booked (max ' . BOOKING_MAX_PER_SLOT . '). Please pick another time.');
        redirect(baseUrl('staff/new-booking.php'));
    }

    // Same compatibility + product rules as the customer booking page:
    // services filtered by motorcycle type, and each service's product chosen
    // from its compatible options (validated by calculateBookingSelection).
    $selectedProducts = [];
    foreach ((array)($_POST['service_products'] ?? []) as $svcId => $prodId) {
        $selectedProducts[(int)$svcId] = (int)$prodId;
    }

    $catalog = getBookingServiceCatalog((int)$model['type_id'], (int)$model['cc']);
    $allowedServiceIds = array_map(static fn(array $svc): int => (int)$svc['id'], $catalog);
    $serviceIds = array_values(array_intersect($serviceIds, $allowedServiceIds));
    if (!$serviceIds) {
        flashMessage('nb_error', 'None of the selected services are compatible with this motorcycle.');
        redirect(baseUrl('staff/new-booking.php'));
    }

    $selection = calculateBookingSelection($catalog, $serviceIds, $selectedProducts);
    if ($selection['errors']) {
        flashMessage('nb_error', $selection['errors'][0]);
        redirect(baseUrl('staff/new-booking.php'));
    }

    try {
        getDB()->beginTransaction();

        // The booking references a customer vehicle: reuse the customer's saved
        // vehicle for this catalog model, or register one from the catalog entry.
        $vehicleRow = fetchOne(
            "SELECT id FROM customer_vehicles WHERE user_id = ? AND model_id = ? LIMIT 1",
            [$customerId, (int)$model['id']]
        );
        if ($vehicleRow) {
            $vehicleId = (int)$vehicleRow['id'];
        } else {
            getDB()->prepare(
                "INSERT INTO customer_vehicles (user_id, type_id, brand_id, model_id, cc) VALUES (?, ?, ?, ?, ?)"
            )->execute([$customerId, (int)$model['type_id'], (int)$model['brand_id'], (int)$model['id'], (int)$model['cc']]);
            $vehicleId = (int)getDB()->lastInsertId();
        }

        $status = $techId > 0 ? 'confirmed' : 'pending';

        getDB()->prepare(
            "INSERT INTO bookings (user_id, vehicle_id, technician_id, scheduled_date, scheduled_time,
                                   status, labor_total, products_total, total_amount, notes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        )->execute([
            $customerId, $vehicleId, $techId ?: null, $schedDate, $schedTime, $status,
            $selection['labor_total'], $selection['products_total'], $selection['total_amount'], $notes,
        ]);

        $bookingId = (int)getDB()->lastInsertId();

        $svcStmt = getDB()->prepare(
            "INSERT INTO booking_services (booking_id, service_id, service_name, labor_fee) VALUES (?, ?, ?, ?)"
        );
        foreach ($selection['services'] as $svc) {
            $svcStmt->execute([$bookingId, (int)$svc['id'], $svc['name'], (float)$svc['labor_fee']]);
        }

        $prodStmt = getDB()->prepare(
            "INSERT INTO booking_products (booking_id, service_id, product_id, product_price, product_name) VALUES (?, ?, ?, ?, ?)"
        );
        foreach ($selection['products'] as $prod) {
            $prodStmt->execute([
                $bookingId, (int)$prod['service_id'], (int)$prod['product_id'],
                (float)$prod['product_price'], $prod['product_name'],
            ]);
        }

        // Notify tech if assigned
        if ($techId > 0 && function_exists('createNotification')) {
            createNotification($techId, "You have been assigned to Booking #{$bookingId} by staff.", 'booking', $bookingId);
        }
        // Notify customer
        if (function_exists('createNotification')) {
            createNotification($customerId, "A service booking (#{$bookingId}) has been created for you by the shop.", 'booking', $bookingId);
        }

        getDB()->commit();
        flashMessage('nb_success', "Booking #{$bookingId} created successfully!");
        redirect(baseUrl('staff/bookings.php?highlight=' . $bookingId));
    } catch (Throwable $e) {
        if (getDB()->inTransaction()) getDB()->rollBack();
        flashMessage('nb_error', 'Error creating booking: ' . $e->getMessage());
        redirect(baseUrl('staff/new-booking.php'));
    }
}

$flash    = getFlash('nb_success');
$flashErr = getFlash('nb_error');

// Client-side payloads
$catalogJson = array_map(static fn(array $m) => [
    'id'     => (int)$m['id'],
    'label'  => $m['brand_name'] . ' ' . $m['model_name'],
    'sub'    => $m['type_name'] . ' · ' . (int)$m['cc'] . 'cc',
    'typeId' => (int)$m['type_id'],
    'cc'     => (int)$m['cc'],
], $catalogModels);

require_once __DIR__ . '/../includes/staff-sidebar.php';
?>

<div class="mtx-shell">

<header class="mtx-page-head">
  <div class="mtx-page-head-copy">
    <span class="eyebrow">Staff Panel</span>
    <h1>Create New Booking</h1>
    <p>Create a walk-in or phone-in booking on behalf of a customer.</p>
  </div>
  <div class="mtx-head-actions">
    <a href="<?= baseUrl('staff/bookings.php') ?>" class="mtx-btn mtx-btn--ghost"><i class="fas fa-arrow-left"></i> Back to Bookings</a>
  </div>
</header>

<?php if ($flash): ?><div class="alert success"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if ($flashErr): ?><div class="alert error"><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>

<form method="post" class="mtx-grid mtx-grid--main" id="newBookingForm">
  <?= authContextField() ?>

  <div class="mtx-stack">
    <!-- 1. Customer & Motorcycle -->
    <section class="mtx-step">
      <div class="mtx-step-head">
        <span class="mtx-step-num">1</span>
        <div>
          <h3>Customer &amp; Motorcycle</h3>
          <p>Search customers by name, email, or contact number, then pick the motorcycle from the catalog.</p>
        </div>
      </div>
      <div class="mtx-step-body">
        <div class="mtx-form-grid">
          <div class="mtx-field mtx-combo" id="customerCombo">
            <span>Customer <em>*</em></span>
            <input type="hidden" name="customer_id" id="customerIdInput" value="">
            <div class="mtx-combo-input-wrap">
              <i class="fas fa-magnifying-glass"></i>
              <input type="text" id="customerSearch" placeholder="Search name, email, or phone…" autocomplete="off">
            </div>
            <div class="mtx-combo-list" id="customerList" hidden></div>
            <div class="mtx-combo-selected" id="customerSelected" hidden>
              <i class="fas fa-user-check mtx-combo-selected-icon"></i>
              <span class="mtx-combo-selected-copy">
                <strong id="customerSelectedName"></strong>
                <span id="customerSelectedSub"></span>
              </span>
              <button type="button" class="mtx-combo-clear" id="customerClear" title="Clear selection" aria-label="Clear customer"><i class="fas fa-xmark"></i></button>
            </div>
          </div>

          <div class="mtx-field mtx-combo" id="vehicleCombo">
            <span>Motorcycle <em>*</em></span>
            <input type="hidden" name="vehicle_model_id" id="vehicleIdInput" value="">
            <div class="mtx-combo-input-wrap">
              <i class="fas fa-magnifying-glass"></i>
              <input type="text" id="vehicleSearch" placeholder="Search brand, model, type, or cc…" autocomplete="off">
            </div>
            <div class="mtx-combo-list" id="vehicleList" hidden></div>
            <div class="mtx-combo-selected" id="vehicleSelected" hidden>
              <i class="fas fa-motorcycle mtx-combo-selected-icon"></i>
              <span class="mtx-combo-selected-copy">
                <strong id="vehicleSelectedName"></strong>
                <span id="vehicleSelectedSub"></span>
              </span>
              <button type="button" class="mtx-combo-clear" id="vehicleClear" title="Clear selection" aria-label="Clear motorcycle"><i class="fas fa-xmark"></i></button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- 2. Schedule -->
    <section class="mtx-step">
      <div class="mtx-step-head">
        <span class="mtx-step-num">2</span>
        <div>
          <h3>Schedule</h3>
          <p>Shop hours 8:00 AM – 5:00 PM · max <?= BOOKING_MAX_PER_SLOT ?> bookings per slot. Availability updates live.</p>
        </div>
      </div>
      <div class="mtx-step-body">
        <div class="mtx-form-grid" style="margin-bottom:14px;">
          <label class="mtx-field"><span>Date <em>*</em></span>
            <input type="date" name="scheduled_date" id="bookingDate" required min="<?= date('Y-m-d') ?>">
          </label>
        </div>
        <input type="hidden" name="scheduled_time" id="scheduledTimeInput" value="">
        <div class="mtx-slot-grid" id="slotGrid">
          <p class="mtx-slot-note" style="grid-column:1/-1;">Pick a date to see available times.</p>
        </div>
        <p class="mtx-slot-note" id="slotWarning" style="color:#b91c1c;margin:8px 0 0;" hidden>Please choose an available time slot.</p>
      </div>
    </section>

    <!-- 3. Technician -->
    <section class="mtx-step">
      <div class="mtx-step-head">
        <span class="mtx-step-num">3</span>
        <div>
          <h3>Technician</h3>
          <p>Assign now to confirm immediately, or leave for later assignment.</p>
        </div>
      </div>
      <div class="mtx-step-body">
        <label class="mtx-field"><span>Assign Technician (optional)</span>
          <select name="technician_id" data-summary="tech">
            <option value="">— Assign Later (booking stays Pending) —</option>
            <?php foreach ($techs as $t): ?>
              <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
    </section>

    <!-- 4. Services -->
    <section class="mtx-step">
      <div class="mtx-step-head">
        <span class="mtx-step-num">4</span>
        <div>
          <h3>Services <span style="color:var(--accent);">*</span></h3>
          <p>Only services compatible with the selected motorcycle are shown.</p>
        </div>
      </div>
      <div class="mtx-step-body">
        <?php if ($services): ?>
          <div class="mtx-option-grid" id="serviceGrid">
            <?php foreach ($services as $svc): ?>
              <?php $applies = strtolower(trim((string)($svc['applies_to'] ?? 'all'))) ?: 'all'; ?>
              <label class="mtx-option-card" data-applies="<?= htmlspecialchars($applies) ?>">
                <input type="checkbox" name="service_ids[]" value="<?= (int)$svc['id'] ?>"
                       data-service-name="<?= htmlspecialchars($svc['name'], ENT_QUOTES) ?>"
                       data-service-fee="<?= (float)$svc['labor_fee'] ?>">
                <span class="mtx-option-check"><i class="fas fa-check"></i></span>
                <span class="mtx-option-copy">
                  <strong><?= htmlspecialchars($svc['name']) ?></strong>
                  <small>Labor fee</small>
                </span>
                <span class="mtx-option-fee"><?= formatPrice((float)$svc['labor_fee']) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <div class="mtx-empty" id="servicePlaceholder" style="padding:24px 16px;">
            <i class="fas fa-motorcycle"></i>
            <strong>Select a motorcycle first.</strong>
            <span>Compatible services will appear here.</span>
          </div>
          <div class="mtx-empty" id="serviceEmpty" hidden style="padding:24px 16px;">
            <i class="fas fa-tools"></i>
            <strong>No compatible services.</strong>
            <span>No services are configured for this motorcycle type.</span>
          </div>
          <!-- Per-service product choices (mirrors the customer booking flow) -->
          <div id="productPickers" class="mtx-stack" style="gap:12px;margin-top:16px;"></div>
        <?php else: ?>
          <div class="mtx-empty">
            <i class="fas fa-tools"></i>
            <strong>No services available.</strong>
            <span><a href="<?= baseUrl('staff/services.php') ?>" style="color:var(--accent);font-weight:700;">Add services first.</a></span>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <!-- 5. Notes -->
    <section class="mtx-step">
      <div class="mtx-step-head">
        <span class="mtx-step-num">5</span>
        <div>
          <h3>Notes</h3>
          <p>Optional — special instructions or customer requests.</p>
        </div>
      </div>
      <div class="mtx-step-body">
        <label class="mtx-field">
          <textarea name="notes" rows="3" placeholder="Any special instructions or customer notes..."></textarea>
        </label>
      </div>
    </section>
  </div>

  <!-- Booking summary sidebar -->
  <aside class="mtx-stack mtx-sticky">
    <section class="mtx-card">
      <div class="mtx-card-head">
        <div><h2><i class="fas fa-receipt"></i> Booking Summary</h2></div>
      </div>
      <div class="mtx-line-rows" style="margin-bottom:12px;">
        <div class="mtx-line-row"><span><i class="fas fa-user" style="color:#2563eb;"></i>Customer</span><strong id="sumCustomer" style="text-align:right;font-size:.8rem;">—</strong></div>
        <div class="mtx-line-row"><span><i class="fas fa-motorcycle" style="color:#d71920;"></i>Motorcycle</span><strong id="sumVehicle" style="text-align:right;font-size:.8rem;">—</strong></div>
        <div class="mtx-line-row"><span><i class="fas fa-calendar" style="color:#0f766e;"></i>Schedule</span><strong id="sumSchedule" style="text-align:right;font-size:.8rem;">—</strong></div>
        <div class="mtx-line-row"><span><i class="fas fa-user-cog" style="color:#15803d;"></i>Technician</span><strong id="sumTech" style="text-align:right;font-size:.8rem;">Assign later</strong></div>
      </div>
      <h3 style="margin:0 0 8px;font-size:.74rem;font-weight:800;letter-spacing:.07em;text-transform:uppercase;color:var(--muted);">Selected Services</h3>
      <div class="mtx-line-rows" id="sumServices">
        <div class="mtx-line-row"><span class="mtx-cell-sub">No services selected yet.</span></div>
      </div>
      <h3 style="margin:14px 0 8px;font-size:.74rem;font-weight:800;letter-spacing:.07em;text-transform:uppercase;color:var(--muted);">Selected Products</h3>
      <div class="mtx-line-rows" id="sumProducts">
        <div class="mtx-line-row"><span class="mtx-cell-sub">No products selected yet.</span></div>
      </div>
      <div class="mtx-line-rows" style="margin-top:12px;">
        <div class="mtx-line-row"><span>Labor Total</span><strong class="mtx-money" id="sumLabor">PHP 0.00</strong></div>
        <div class="mtx-line-row"><span>Products Total</span><strong class="mtx-money" id="sumProductsTotal">PHP 0.00</strong></div>
      </div>
      <div class="mtx-total-row"><span>Estimated Final Total</span><span id="sumTotal">PHP 0.00</span></div>
      <p class="mtx-cell-sub" style="margin:10px 0 0;">Final cost can still change if the technician records additional parts during service.</p>
      <div class="alert error" id="submitWarning" hidden style="margin-top:12px;font-size:.84rem;"></div>
      <div style="display:grid;gap:8px;margin-top:16px;">
        <button type="submit" class="mtx-btn mtx-btn--primary"><i class="fas fa-check"></i> Create Booking</button>
        <a href="<?= baseUrl('staff/bookings.php') ?>" class="mtx-btn mtx-btn--ghost">Cancel</a>
      </div>
    </section>
  </aside>
</form>

</div><!-- /.mtx-shell -->

<script type="application/json" id="catalogData"><?= json_encode($catalogJson, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?></script>

<?= authContextScriptTag() ?>
<script>
(function () {
  var form = document.getElementById('newBookingForm');
  if (!form) return;

  function php(n) { return 'PHP ' + Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }
  function setText(id, v, fallback) { document.getElementById(id).textContent = v || fallback || '—'; }

  /* ---------- Generic searchable combo ---------- */
  function makeCombo(opts) {
    var input = document.getElementById(opts.search);
    var list = document.getElementById(opts.list);
    var hidden = document.getElementById(opts.hidden);
    var chip = document.getElementById(opts.chip);
    var chipName = document.getElementById(opts.chipName);
    var chipSub = document.getElementById(opts.chipSub);
    var clearBtn = document.getElementById(opts.clear);
    var items = [];
    var activeIdx = -1;

    function close() { list.hidden = true; activeIdx = -1; }

    function render(results) {
      items = results;
      activeIdx = -1;
      if (!results.length) {
        list.innerHTML = '<div class="mtx-combo-empty">' + opts.emptyText + '</div>';
      } else {
        list.innerHTML = results.map(function (r, i) {
          if (r.action) {
            return '<div class="mtx-combo-item" data-idx="' + i + '" style="text-align:center;"><strong style="color:#b2121a;"><i class="fas ' + (r.icon || 'fa-list') + '" style="margin-right:6px;"></i>' + esc(r.label) + '</strong></div>';
          }
          return '<div class="mtx-combo-item" data-idx="' + i + '"><strong>' + esc(r.label) + '</strong><span>' + esc(r.sub) + '</span></div>';
        }).join('');
      }
      list.hidden = false;
    }

    function select(idx) {
      var r = items[idx];
      if (!r) return;
      if (r.action) {
        if (opts.onAction) opts.onAction(r.action);
        input.focus();
        return;
      }
      hidden.value = r.id;
      chipName.textContent = r.label;
      chipSub.textContent = r.sub;
      chip.hidden = false;
      input.value = '';
      close();
      if (opts.onSelect) opts.onSelect(r);
    }

    function markActive() {
      list.querySelectorAll('.mtx-combo-item').forEach(function (el, i) {
        el.classList.toggle('is-active', i === activeIdx);
      });
      var active = list.querySelector('.mtx-combo-item.is-active');
      if (active) active.scrollIntoView({ block: 'nearest' });
    }

    input.addEventListener('input', function () { opts.query(input.value.trim(), render, close); });
    input.addEventListener('focus', function () { opts.query(input.value.trim(), render, close); });
    input.addEventListener('keydown', function (e) {
      if (list.hidden) return;
      if (e.key === 'ArrowDown') { e.preventDefault(); activeIdx = Math.min(items.length - 1, activeIdx + 1); markActive(); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); activeIdx = Math.max(0, activeIdx - 1); markActive(); }
      else if (e.key === 'Enter') { if (activeIdx >= 0) { e.preventDefault(); select(activeIdx); } }
      else if (e.key === 'Escape') { close(); }
    });
    list.addEventListener('mousedown', function (e) {
      var el = e.target.closest('.mtx-combo-item');
      if (el) { e.preventDefault(); select(parseInt(el.getAttribute('data-idx'), 10)); }
    });
    document.addEventListener('click', function (e) {
      if (!list.hidden && !e.target.closest('#' + opts.container)) close();
    });
    clearBtn.addEventListener('click', function () {
      hidden.value = '';
      chip.hidden = true;
      input.value = '';
      if (opts.onClear) opts.onClear();
    });
  }

  /* ---------- Customer combo (remote search) ---------- */
  var customerDebounce = null;
  makeCombo({
    container: 'customerCombo',
    search: 'customerSearch', list: 'customerList', hidden: 'customerIdInput',
    chip: 'customerSelected', chipName: 'customerSelectedName', chipSub: 'customerSelectedSub', clear: 'customerClear',
    emptyText: 'No matching customers found.',
    query: function (term, render, close) {
      clearTimeout(customerDebounce);
      if (!term) { close(); return; }
      customerDebounce = setTimeout(function () {
        // baseUrl() may already carry ?ctx=…, so merge params via the URL API
        var searchUrl = new URL('<?= baseUrl('api/customers-search.php') ?>', window.location.origin);
        searchUrl.searchParams.set('q', term);
        fetch(searchUrl)
          .then(function (r) { return r.json(); })
          .then(function (data) {
            render((data.customers || []).map(function (c) {
              return { id: c.id, label: c.name, sub: c.email + (c.phone ? ' · ' + c.phone : '') };
            }));
          })
          .catch(function () { render([]); });
      }, 220);
    },
    onSelect: refresh,
    onClear: refresh
  });

  /* ---------- Vehicle combo (local catalog search + compact browse) ---------- */
  var catalog = [];
  try { catalog = JSON.parse(document.getElementById('catalogData').textContent || '[]'); } catch (e) { catalog = []; }
  var selectedTypeId = 0;
  var vehicleExpanded = false;
  var VEHICLE_PREVIEW_COUNT = 8;
  var vehicleRerender = null;

  makeCombo({
    container: 'vehicleCombo',
    search: 'vehicleSearch', list: 'vehicleList', hidden: 'vehicleIdInput',
    chip: 'vehicleSelected', chipName: 'vehicleSelectedName', chipSub: 'vehicleSelectedSub', clear: 'vehicleClear',
    emptyText: 'No motorcycles found.',
    query: function (term, render, close) {
      vehicleRerender = function () { this.query(term, render, close); }.bind(this);
      if (!term) {
        // Browse mode: compact preview of the catalog with an expand toggle.
        if (!catalog.length) { render([]); return; }
        if (vehicleExpanded) {
          var all = catalog.slice();
          all.push({ action: 'collapse', icon: 'fa-chevron-up', label: 'Show fewer motorcycles' });
          render(all);
        } else {
          var preview = catalog.slice(0, VEHICLE_PREVIEW_COUNT);
          if (catalog.length > VEHICLE_PREVIEW_COUNT) {
            preview.push({ action: 'expand', icon: 'fa-chevron-down', label: 'View all ' + catalog.length + ' motorcycles' });
          }
          render(preview);
        }
        return;
      }
      var t = term.toLowerCase();
      render(catalog.filter(function (m) {
        return (m.label + ' ' + m.sub).toLowerCase().indexOf(t) !== -1;
      }).slice(0, 30));
    },
    onAction: function (action) {
      vehicleExpanded = action === 'expand';
      if (vehicleRerender) vehicleRerender();
    },
    onSelect: function (r) {
      selectedTypeId = r.typeId;
      vehicleExpanded = false;
      applyCompatibility();
      loadServiceCatalog(r.typeId, r.cc);
      refresh();
    },
    onClear: function () {
      selectedTypeId = 0;
      vehicleExpanded = false;
      serviceCatalog = {};
      applyCompatibility();
      renderProductPickers();
      refresh();
    }
  });

  /* ---------- Per-service product options (same catalog as the customer page) ---------- */
  var serviceCatalog = {}; // serviceId -> { name, products: [...] }

  function loadServiceCatalog(typeId, cc) {
    serviceCatalog = {};
    renderProductPickers();
    var catUrl = new URL('<?= baseUrl('api/staff-booking-catalog.php') ?>', window.location.origin);
    catUrl.searchParams.set('type_id', typeId);
    catUrl.searchParams.set('cc', cc || 0);
    fetch(catUrl)
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.ok) return;
        (data.services || []).forEach(function (s) { serviceCatalog[s.id] = s; });
        renderProductPickers();
        refresh();
      })
      .catch(function () { /* pickers stay empty; server still validates */ });
  }

  function renderProductPickers() {
    var wrap = document.getElementById('productPickers');
    if (!wrap) return;

    // Remember current picks so re-renders keep them
    var current = {};
    wrap.querySelectorAll('input[type="radio"]:checked').forEach(function (r) { current[r.name] = r.value; });

    var html = '';
    form.querySelectorAll('.mtx-option-card:not([hidden]) input[name="service_ids[]"]:checked').forEach(function (cb) {
      var svc = serviceCatalog[parseInt(cb.value, 10)];
      if (!svc || !svc.products || !svc.products.length) return;
      var fieldName = 'service_products[' + svc.id + ']';
      html += '<div class="mtx-step" style="border-radius:12px;">' +
        '<div class="mtx-step-head" style="padding:11px 16px;">' +
          '<div><h3 style="font-size:.86rem;">' + esc(svc.name) + ' — choose a product <em style="color:var(--accent);font-style:normal;">*</em></h3>' +
          '<p>Required for this service.</p></div>' +
        '</div>' +
        '<div class="mtx-step-body" style="padding:12px 16px;"><div class="mtx-line-rows">' +
        svc.products.map(function (p) {
          var checked = current[fieldName] === String(p.id) ? ' checked' : '';
          return '<label class="mtx-line-row" style="cursor:pointer;gap:10px;">' +
            '<span style="display:flex;align-items:center;gap:10px;min-width:0;">' +
              '<input type="radio" name="' + fieldName + '" value="' + p.id + '"' + checked + ' style="accent-color:var(--accent);width:16px;height:16px;flex:0 0 auto;">' +
              '<span style="min-width:0;"><strong style="font-size:.85rem;display:block;">' + esc(p.name) + '</strong>' +
              '<span class="mtx-cell-sub">' + esc(p.brand || '') + (p.brand ? ' · ' : '') + p.stock + ' in stock</span></span>' +
            '</span>' +
            '<strong class="mtx-money">' + php(p.price) + '</strong>' +
          '</label>';
        }).join('') +
        '</div></div></div>';
    });
    wrap.innerHTML = html;
  }

  /* ---------- Service compatibility (from stored applies_to config) ---------- */
  function applyCompatibility() {
    var grid = document.getElementById('serviceGrid');
    var placeholder = document.getElementById('servicePlaceholder');
    var emptyNote = document.getElementById('serviceEmpty');
    if (!grid) return;

    if (!selectedTypeId) {
      grid.hidden = true;
      placeholder.hidden = false;
      emptyNote.hidden = true;
      grid.querySelectorAll('input[type="checkbox"]').forEach(function (cb) { cb.checked = false; });
      return;
    }

    placeholder.hidden = true;
    var visible = 0;
    grid.querySelectorAll('.mtx-option-card').forEach(function (card) {
      var applies = (card.getAttribute('data-applies') || 'all').toLowerCase();
      var ok = applies === 'all' || applies.split(',').map(function (s) { return parseInt(s, 10); }).indexOf(selectedTypeId) !== -1;
      card.hidden = !ok;
      if (!ok) {
        var cb = card.querySelector('input[type="checkbox"]');
        if (cb) cb.checked = false;
      } else {
        visible += 1;
      }
    });
    grid.hidden = visible === 0;
    emptyNote.hidden = visible !== 0;
    renderProductPickers();
  }

  /* ---------- Live time slots (same availability API as the customer page) ---------- */
  var dateInput = document.getElementById('bookingDate');
  var slotGrid = document.getElementById('slotGrid');
  var slotHidden = document.getElementById('scheduledTimeInput');
  var slotWarning = document.getElementById('slotWarning');

  // Native date inputs give ISO (Y-m-d); text fallbacks may give 18/07/2026.
  function normalizeDate(value) {
    value = (value || '').trim();
    if (/^\d{4}-\d{2}-\d{2}$/.test(value)) return value;
    var m = value.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/);
    if (m) {
      return m[3] + '-' + ('0' + m[2]).slice(-2) + '-' + ('0' + m[1]).slice(-2);
    }
    return '';
  }

  function renderSlots(slots) {
    var anyOpen = slots.some(function (s) { return s.remaining > 0; });
    var html = slots.map(function (s) {
      var full = s.remaining <= 0;
      var selected = !full && slotHidden.value === s.value;
      return '<button type="button" class="mtx-slot' + (selected ? ' is-selected' : '') + '"' +
        ' data-slot="' + s.value + '"' + (full ? ' disabled' : '') + '>' +
        '<strong>' + esc(s.label) + '</strong>' +
        '<small>' + (full ? 'Fully Booked' : (s.remaining === 1 ? '1 slot available' : s.remaining + ' slots available')) + '</small>' +
        '</button>';
    }).join('');
    if (!anyOpen) {
      html = '<p class="mtx-slot-note" style="grid-column:1/-1;color:#b91c1c;font-weight:700;">No available time slots for this date — every slot is fully booked. Please pick another date.</p>' + html;
    }
    slotGrid.innerHTML = html;
  }

  function loadSlots() {
    var date = normalizeDate(dateInput.value);
    if (!date) {
      slotGrid.innerHTML = '<p class="mtx-slot-note" style="grid-column:1/-1;">' +
        (dateInput.value ? 'That date could not be understood — use the date picker or the format DD/MM/YYYY.' : 'Pick a date to see available times.') +
        '</p>';
      slotHidden.value = '';
      refresh();
      return;
    }
    slotGrid.innerHTML = '<p class="mtx-slot-note" style="grid-column:1/-1;"><i class="fas fa-spinner fa-spin"></i> Checking availability…</p>';
    // baseUrl() may already carry ?ctx=…, so merge params via the URL API
    var slotsUrl = new URL('<?= baseUrl('api/booking-slots.php') ?>', window.location.origin);
    slotsUrl.searchParams.set('date', date);
    fetch(slotsUrl)
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.ok) {
          slotGrid.innerHTML = '<p class="mtx-slot-note" style="grid-column:1/-1;color:#b91c1c;">' + esc(data.message || 'Unable to load availability.') + '</p>';
          slotHidden.value = '';
          refresh();
          return;
        }
        // Drop the previously selected slot if it filled up in the meantime
        var stillOpen = data.slots.some(function (s) { return s.value === slotHidden.value && s.remaining > 0; });
        if (!stillOpen) slotHidden.value = '';
        renderSlots(data.slots);
        refresh();
      })
      .catch(function () {
        slotGrid.innerHTML = '<p class="mtx-slot-note" style="grid-column:1/-1;color:#b91c1c;">Could not load availability. Try again.</p>';
      });
  }

  dateInput.addEventListener('change', loadSlots);
  slotGrid.addEventListener('click', function (e) {
    var btn = e.target.closest('.mtx-slot');
    if (!btn || btn.disabled) return;
    slotHidden.value = btn.getAttribute('data-slot');
    slotWarning.hidden = true;
    slotGrid.querySelectorAll('.mtx-slot').forEach(function (b) { b.classList.toggle('is-selected', b === btn); });
    refresh();
  });

  form.addEventListener('submit', function (e) {
    var missing = [];
    if (!document.getElementById('customerIdInput').value) missing.push('a customer');
    if (!document.getElementById('vehicleIdInput').value) missing.push('a motorcycle');
    if (!normalizeDate(dateInput.value)) missing.push('a valid date');
    if (!slotHidden.value) missing.push('a time slot');
    var hasService = form.querySelectorAll('.mtx-option-card:not([hidden]) input[name="service_ids[]"]:checked').length > 0;
    if (!hasService) missing.push('at least one service');

    // Every checked service that offers products needs one chosen (customer-page rule)
    form.querySelectorAll('.mtx-option-card:not([hidden]) input[name="service_ids[]"]:checked').forEach(function (cb) {
      var svc = serviceCatalog[parseInt(cb.value, 10)];
      if (svc && svc.products && svc.products.length) {
        var picked = document.querySelector('#productPickers input[name="service_products[' + svc.id + ']"]:checked');
        if (!picked) missing.push('a product for ' + svc.name);
      }
    });

    var warning = document.getElementById('submitWarning');
    if (missing.length) {
      e.preventDefault();
      warning.textContent = 'Please select ' + missing.join(', ').replace(/, ([^,]*)$/, ' and $1') + ' before creating the booking.';
      warning.hidden = false;
      slotWarning.hidden = !!slotHidden.value;
      warning.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }
    warning.hidden = true;
    // Submit the normalized ISO date so the backend always receives Y-m-d.
    dateInput.value = normalizeDate(dateInput.value);
  });

  /* ---------- Booking summary ---------- */
  function refresh() {
    setText('sumCustomer', document.getElementById('customerIdInput').value ? document.getElementById('customerSelectedName').textContent : null);
    setText('sumVehicle', document.getElementById('vehicleIdInput').value ? document.getElementById('vehicleSelectedName').textContent : null);

    var sched = [];
    var isoDate = normalizeDate(dateInput.value);
    if (isoDate) {
      var p = isoDate.split('-');
      var MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
      sched.push(MONTHS[parseInt(p[1], 10) - 1] + ' ' + parseInt(p[2], 10) + ', ' + p[0]);
    }
    if (slotHidden.value) {
      var btn = slotGrid.querySelector('[data-slot="' + slotHidden.value + '"] strong');
      sched.push(btn ? btn.textContent : slotHidden.value);
    }
    setText('sumSchedule', sched.join(' · ') || null);

    var tech = form.querySelector('[data-summary="tech"]');
    setText('sumTech', tech.value ? tech.options[tech.selectedIndex].text : null, 'Assign later');

    var wrap = document.getElementById('sumServices');
    wrap.innerHTML = '';
    var laborTotal = 0, any = false;
    form.querySelectorAll('.mtx-option-card:not([hidden]) input[name="service_ids[]"]:checked').forEach(function (cb) {
      any = true;
      laborTotal += parseFloat(cb.dataset.serviceFee || 0);
      var row = document.createElement('div');
      row.className = 'mtx-line-row';
      var name = document.createElement('span');
      name.textContent = cb.dataset.serviceName;
      var fee = document.createElement('strong');
      fee.className = 'mtx-money';
      fee.textContent = php(parseFloat(cb.dataset.serviceFee || 0));
      row.appendChild(name);
      row.appendChild(fee);
      wrap.appendChild(row);
    });
    if (!any) {
      wrap.innerHTML = '<div class="mtx-line-row"><span class="mtx-cell-sub">No services selected yet.</span></div>';
    }

    // Selected products (one per service, from the pickers)
    var prodWrap = document.getElementById('sumProducts');
    prodWrap.innerHTML = '';
    var productsTotal = 0, anyProduct = false;
    document.querySelectorAll('#productPickers input[type="radio"]:checked').forEach(function (r) {
      var svcId = parseInt((r.name.match(/\[(\d+)\]/) || [])[1] || 0, 10);
      var svc = serviceCatalog[svcId];
      var prod = svc && (svc.products || []).find(function (p) { return String(p.id) === r.value; });
      if (!prod) return;
      anyProduct = true;
      productsTotal += prod.price;
      var row = document.createElement('div');
      row.className = 'mtx-line-row';
      var name = document.createElement('span');
      name.textContent = (svc ? svc.name + ': ' : '') + prod.name;
      name.style.fontSize = '.8rem';
      var fee = document.createElement('strong');
      fee.className = 'mtx-money';
      fee.textContent = php(prod.price);
      row.appendChild(name);
      row.appendChild(fee);
      prodWrap.appendChild(row);
    });
    if (!anyProduct) {
      prodWrap.innerHTML = '<div class="mtx-line-row"><span class="mtx-cell-sub">No products selected yet.</span></div>';
    }

    document.getElementById('sumLabor').textContent = php(laborTotal);
    document.getElementById('sumProductsTotal').textContent = php(productsTotal);
    document.getElementById('sumTotal').textContent = php(laborTotal + productsTotal);
  }

  form.addEventListener('change', function (e) {
    if (e.target && e.target.name === 'service_ids[]') {
      renderProductPickers();
    }
    refresh();
  });
  applyCompatibility();
  refresh();
})();
</script>
</main></div></div></body></html>
