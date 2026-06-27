<?php
$pageTitle = 'New Booking';
require_once __DIR__ . '/../includes/staff-sidebar.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$flash    = getFlash('nb_success');
$flashErr = getFlash('nb_error');

// Load data for form
$customers  = fetchAllRows("SELECT id, name, email, phone FROM users WHERE role='customer' AND is_active=1 ORDER BY name");
$techs      = fetchAllRows("SELECT id, name FROM users WHERE role='technician' AND is_active=1 ORDER BY name");
$services   = fetchAllRows("SELECT s.id, s.name, s.labor_fee, st.name AS type_name FROM services s JOIN service_types st ON st.id=s.service_type_id WHERE s.is_active=1 ORDER BY st.name, s.name");
$vehicles   = fetchAllRows("SELECT v.id, CONCAT(b.name,' ',m.name,' (',mt.name,') ',v.engine_cc,'cc') AS label FROM customer_vehicles v JOIN motorcycle_models m ON m.id=v.model_id JOIN motorcycle_brands b ON b.id=m.brand_id JOIN motorcycle_types mt ON mt.id=m.type_id WHERE v.is_active=1 ORDER BY label");

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
        "SELECT id, name, labor_fee FROM services WHERE id IN (" . implode(',', $serviceIds) . ")"
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
            createNotification($techId, "You have been assigned to Booking #{$bookingId} by staff.", $bookingId);
        }
        // Notify customer
        if (function_exists('createNotification')) {
            createNotification($customerId, "A service booking (#{$bookingId}) has been created for you by the shop.", $bookingId);
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
?>

<section class="admin-hero">
  <div>
    <span class="eyebrow">Staff</span>
    <h1>Create New Booking</h1>
    <p>Create a walk-in or phone-in booking on behalf of a customer.</p>
  </div>
</section>

<section class="admin-card admin-page-stack" style="max-width:820px;">
  <?php if ($flash): ?><div class="alert success"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
  <?php if ($flashErr): ?><div class="alert error"><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>

  <form method="post" class="admin-form-box" style="background:none;border:none;padding:0;">

    <!-- Customer & Vehicle -->
    <div class="admin-form-box">
      <h3>1. Customer & Vehicle</h3>
      <div class="form-grid-2">
        <label>Customer <span style="color:#d71920;">*</span>
          <select name="customer_id" required>
            <option value="">— Select Customer —</option>
            <?php foreach ($customers as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['email']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Vehicle <span style="color:#d71920;">*</span>
          <select name="vehicle_id" required>
            <option value="">— Select Vehicle —</option>
            <?php foreach ($vehicles as $v): ?>
              <option value="<?= (int)$v['id'] ?>"><?= htmlspecialchars($v['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
    </div>

    <!-- Schedule & Tech -->
    <div class="admin-form-box">
      <h3>2. Schedule & Technician</h3>
      <div class="form-grid-2">
        <label>Date <span style="color:#d71920;">*</span>
          <input type="date" name="scheduled_date" required min="<?= date('Y-m-d') ?>">
        </label>
        <label>Time Slot <span style="color:#d71920;">*</span>
          <select name="scheduled_time" required>
            <option value="">— Pick a Time —</option>
            <?php foreach (['08:00','09:00','10:00','11:00','13:00','14:00','15:00','16:00','17:00'] as $t): ?>
              <option value="<?= $t ?>"><?= date('g:i A', strtotime($t)) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Assign Technician (optional)
          <select name="technician_id">
            <option value="">— Assign Later —</option>
            <?php foreach ($techs as $t): ?>
              <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
    </div>

    <!-- Services -->
    <div class="admin-form-box">
      <h3>3. Services <span style="color:#d71920;">*</span></h3>
      <div class="checkbox-group">
        <?php foreach ($services as $svc): ?>
          <label class="inline-check">
            <input type="checkbox" name="service_ids[]" value="<?= (int)$svc['id'] ?>">
            <?= htmlspecialchars($svc['name']) ?>
            <span style="color:#6b7280;font-size:.82rem;">(<?= formatPrice((float)$svc['labor_fee']) ?>)</span>
          </label>
        <?php endforeach; ?>
      </div>
      <?php if (!$services): ?><p style="color:#6b7280;">No services available. <a href="<?= baseUrl('staff/services.php') ?>">Add services first.</a></p><?php endif; ?>
    </div>

    <!-- Notes -->
    <div class="admin-form-box">
      <h3>4. Notes (optional)</h3>
      <textarea name="notes" rows="3" placeholder="Any special instructions or customer notes..." style="width:100%;border:1px solid var(--line);border-radius:6px;padding:10px;font-size:.9rem;"></textarea>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Create Booking</button>
      <a href="<?= baseUrl('staff/bookings.php') ?>" class="btn btn-outline">Cancel</a>
    </div>
  </form>
</section>

</main></div></div></body></html>
