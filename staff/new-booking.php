<?php
$pageTitle = 'New Booking';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
requireStaff();
$currentUser = getCurrentUser();

// Load data for form
$customers  = fetchAllRows("SELECT id, name, email, phone FROM users WHERE role='customer' AND is_active=1 ORDER BY name");
$techs      = fetchAllRows("SELECT id, name FROM users WHERE role='technician' AND is_active=1 ORDER BY name");
$services   = fetchAllRows("SELECT id, name, labor_fee, applies_to FROM service_types ORDER BY name");
$vehicles   = fetchAllRows(
    "SELECT v.id, CONCAT(b.name, ' ', m.name, ' (', mt.name, ') ', v.cc, 'cc') AS label
     FROM customer_vehicles v
     JOIN motorcycle_models m ON m.id = v.model_id
     JOIN motorcycle_brands b ON b.id = v.brand_id
     JOIN motorcycle_types mt ON mt.id = v.type_id
     ORDER BY label"
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerId  = (int)($_POST['customer_id'] ?? 0);
    $vehicleId   = (int)($_POST['vehicle_id'] ?? 0);
    $techId      = (int)($_POST['technician_id'] ?? 0);
    $serviceIds  = array_map('intval', (array)($_POST['service_ids'] ?? []));
    $schedDate   = trim($_POST['scheduled_date'] ?? '');
    $schedTime   = trim($_POST['scheduled_time'] ?? '');
    $notes       = trim($_POST['notes'] ?? '');

    if (!$customerId || !$vehicleId || empty($serviceIds) || !$schedDate || !$schedTime) {
        flashMessage('nb_error', 'Please fill in all required fields (customer, vehicle, services, date, time).');
        redirect(baseUrl('staff/new-booking.php'));
    }

    // Check slot availability (max 3 per slot)
    $slotCount = (int)(fetchOne(
        "SELECT COUNT(*) AS n FROM bookings WHERE scheduled_date=? AND scheduled_time=? AND status!='cancelled'",
        [$schedDate, $schedTime]
    )['n'] ?? 0);
    if ($slotCount >= 3) {
        flashMessage('nb_error', 'That time slot is fully booked (max 3). Please pick another time.');
        redirect(baseUrl('staff/new-booking.php'));
    }

    // Calculate total
    $selectedServices = fetchAllRows(
        "SELECT id, name, labor_fee FROM service_types WHERE id IN (" . implode(',', array_fill(0, count($serviceIds), '?')) . ")",
        $serviceIds
    );
    $totalAmount = array_sum(array_column($selectedServices, 'labor_fee'));

    try {
        getDB()->beginTransaction();

        $status = $techId > 0 ? 'confirmed' : 'pending';

        getDB()->prepare(
            "INSERT INTO bookings (user_id, vehicle_id, technician_id, scheduled_date, scheduled_time,
                                   status, total_amount, notes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        )->execute([$customerId, $vehicleId, $techId ?: null, $schedDate, $schedTime, $status, $totalAmount, $notes]);

        $bookingId = (int)getDB()->lastInsertId();

        $svcStmt = getDB()->prepare(
            "INSERT INTO booking_services (booking_id, service_id, service_name, labor_fee) VALUES (?, ?, ?, ?)"
        );
        foreach ($selectedServices as $svc) {
            $svcStmt->execute([$bookingId, $svc['id'], $svc['name'], $svc['labor_fee']]);
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
    <!-- 1. Customer & Vehicle -->
    <section class="mtx-step">
      <div class="mtx-step-head">
        <span class="mtx-step-num">1</span>
        <div>
          <h3>Customer &amp; Vehicle</h3>
          <p>Who is this booking for, and which motorcycle?</p>
        </div>
      </div>
      <div class="mtx-step-body">
        <div class="mtx-form-grid">
          <label class="mtx-field"><span>Customer <em>*</em></span>
            <select name="customer_id" required data-summary="customer">
              <option value="">— Select Customer —</option>
              <?php foreach ($customers as $c): ?>
                <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['email']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="mtx-field"><span>Vehicle <em>*</em></span>
            <select name="vehicle_id" required data-summary="vehicle">
              <option value="">— Select Vehicle —</option>
              <?php foreach ($vehicles as $v): ?>
                <option value="<?= (int)$v['id'] ?>"><?= htmlspecialchars($v['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
      </div>
    </section>

    <!-- 2. Schedule -->
    <section class="mtx-step">
      <div class="mtx-step-head">
        <span class="mtx-step-num">2</span>
        <div>
          <h3>Schedule</h3>
          <p>Shop hours 8:00 AM – 5:00 PM, max 3 bookings per slot.</p>
        </div>
      </div>
      <div class="mtx-step-body">
        <div class="mtx-form-grid">
          <label class="mtx-field"><span>Date <em>*</em></span>
            <input type="date" name="scheduled_date" required min="<?= date('Y-m-d') ?>" data-summary="date">
          </label>
          <label class="mtx-field"><span>Time Slot <em>*</em></span>
            <select name="scheduled_time" required data-summary="time">
              <option value="">— Pick a Time —</option>
              <?php foreach (['08:00','09:00','10:00','11:00','13:00','14:00','15:00','16:00','17:00'] as $t): ?>
                <option value="<?= $t ?>"><?= date('g:i A', strtotime($t)) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
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
          <p>Select all work needed for this appointment.</p>
        </div>
      </div>
      <div class="mtx-step-body">
        <?php if ($services): ?>
          <div class="mtx-option-grid">
            <?php foreach ($services as $svc): ?>
              <label class="mtx-option-card">
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
        <div class="mtx-line-row"><span><i class="fas fa-motorcycle" style="color:#d71920;"></i>Vehicle</span><strong id="sumVehicle" style="text-align:right;font-size:.8rem;">—</strong></div>
        <div class="mtx-line-row"><span><i class="fas fa-calendar" style="color:#0f766e;"></i>Schedule</span><strong id="sumSchedule" style="text-align:right;font-size:.8rem;">—</strong></div>
        <div class="mtx-line-row"><span><i class="fas fa-user-cog" style="color:#15803d;"></i>Technician</span><strong id="sumTech" style="text-align:right;font-size:.8rem;">Assign later</strong></div>
      </div>
      <h3 style="margin:0 0 8px;font-size:.74rem;font-weight:800;letter-spacing:.07em;text-transform:uppercase;color:var(--muted);">Selected Services</h3>
      <div class="mtx-line-rows" id="sumServices">
        <div class="mtx-line-row"><span class="mtx-cell-sub">No services selected yet.</span></div>
      </div>
      <div class="mtx-total-row"><span>Estimated Labor Total</span><span id="sumTotal">PHP 0.00</span></div>
      <p class="mtx-cell-sub" style="margin:10px 0 0;">Products/materials can be added by the technician during service.</p>
      <div style="display:grid;gap:8px;margin-top:16px;">
        <button type="submit" class="mtx-btn mtx-btn--primary"><i class="fas fa-check"></i> Create Booking</button>
        <a href="<?= baseUrl('staff/bookings.php') ?>" class="mtx-btn mtx-btn--ghost">Cancel</a>
      </div>
    </section>
  </aside>
</form>

</div><!-- /.mtx-shell -->

<?= authContextScriptTag() ?>
<script>
(function () {
  function php(n) { return 'PHP ' + Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  function optLabel(sel) {
    return sel.value ? sel.options[sel.selectedIndex].text : null;
  }
  function setText(id, v, fallback) { document.getElementById(id).textContent = v || fallback || '—'; }

  var form = document.getElementById('newBookingForm');
  if (!form) return;

  function refresh() {
    var cust = form.querySelector('[data-summary="customer"]');
    var veh  = form.querySelector('[data-summary="vehicle"]');
    var date = form.querySelector('[data-summary="date"]');
    var time = form.querySelector('[data-summary="time"]');
    var tech = form.querySelector('[data-summary="tech"]');

    setText('sumCustomer', optLabel(cust) ? optLabel(cust).replace(/\s*\(.*\)$/, '') : null);
    setText('sumVehicle', optLabel(veh));
    var sched = [];
    if (date.value) sched.push(new Date(date.value + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }));
    if (time.value) sched.push(optLabel(time));
    setText('sumSchedule', sched.join(' · ') || null);
    setText('sumTech', optLabel(tech), 'Assign later');

    var wrap = document.getElementById('sumServices');
    wrap.innerHTML = '';
    var total = 0, any = false;
    form.querySelectorAll('input[name="service_ids[]"]:checked').forEach(function (cb) {
      any = true;
      total += parseFloat(cb.dataset.serviceFee || 0);
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
    document.getElementById('sumTotal').textContent = php(total);
  }

  form.addEventListener('change', refresh);
  refresh();
})();
</script>
</main></div></div></body></html>
