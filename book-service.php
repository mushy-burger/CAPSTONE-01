<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
requireLogin();

ensureMultiServiceBookingSchema();

$user = getCurrentUser();
$vehicles = getCustomerVehicles($user['id']);
$activeTab = $_GET['tab'] ?? $_POST['tab'] ?? 'book';
$activeTab = in_array($activeTab, ['book', 'appointments'], true) ? $activeTab : 'book';
$editableStatuses = ['pending', 'confirmed', 'cancelled'];
$pageAction = $_POST['page_action'] ?? '';
$message = getFlash('booking_success');
$error = getFlash('booking_error');

if (!$vehicles) {
    flashMessage('notice', 'Save your motorcycle first so we can recommend compatible services.');
    redirect(baseUrl('my-vehicle.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($pageAction, ['delete_appointment', 'cancel_appointment'], true)) {
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $booking = fetchOne("SELECT * FROM bookings WHERE id = ? AND user_id = ?", [$bookingId, $user['id']]);

    if (!$booking) {
        flashMessage('booking_error', 'Appointment not found.');
    } elseif ($pageAction === 'cancel_appointment') {
        if ((string)$booking['status'] === 'pending') {
            getDB()->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND user_id = ?")->execute([$bookingId, $user['id']]);
            flashMessage('booking_success', 'Appointment cancelled.');
        } else {
            flashMessage('booking_error', 'Only pending bookings can be self-cancelled. Please contact the shop to cancel a confirmed appointment.');
        }
    } else {
        if (!in_array((string)$booking['status'], $editableStatuses, true)) {
            flashMessage('booking_error', 'This appointment can no longer be deleted.');
        } else {
            $db = getDB();
            $db->beginTransaction();
            try {
                $db->prepare("DELETE FROM booking_products WHERE booking_id = ?")->execute([$bookingId]);
                $db->prepare("DELETE FROM booking_services WHERE booking_id = ?")->execute([$bookingId]);
                $db->prepare("DELETE FROM bookings WHERE id = ? AND user_id = ?")->execute([$bookingId, $user['id']]);
                $db->commit();
                flashMessage('booking_success', 'Appointment deleted.');
            } catch (Throwable $e) {
                $db->rollBack();
                flashMessage('booking_error', $e->getMessage());
            }
        }
    }

    redirect(baseUrl('book-service.php?tab=appointments'));
}

$appointments = fetchAllRows(
    "SELECT b.*, v.plate_number, t.name AS type_name, br.name AS brand_name, m.name AS model_name,
            tech.name AS technician_name
     FROM bookings b
     LEFT JOIN customer_vehicles v ON v.id = b.vehicle_id
     LEFT JOIN motorcycle_types t ON t.id = v.type_id
     LEFT JOIN motorcycle_brands br ON br.id = v.brand_id
     LEFT JOIN motorcycle_models m ON m.id = v.model_id
     LEFT JOIN users tech ON tech.id = b.technician_id
     WHERE b.user_id = ?
     ORDER BY b.created_at DESC, b.id DESC",
    [$user['id']]
);
$appointmentServices = fetchAllRows(
    "SELECT bs.booking_id, bs.service_name, bs.labor_fee
     FROM booking_services bs
     JOIN bookings b ON b.id = bs.booking_id
     WHERE b.user_id = ?
     ORDER BY bs.id ASC",
    [$user['id']]
);
$appointmentProducts = fetchAllRows(
    "SELECT bp.booking_id, bp.service_id, bp.product_name, bp.product_price
     FROM booking_products bp
     JOIN bookings b ON b.id = bp.booking_id
     WHERE b.user_id = ?
     ORDER BY bp.id ASC",
    [$user['id']]
);
$servicesByBooking = [];
foreach ($appointmentServices as $appointmentService) {
    $servicesByBooking[(int)$appointmentService['booking_id']][] = $appointmentService;
}
$productsByBooking = [];
foreach ($appointmentProducts as $appointmentProduct) {
    $productsByBooking[(int)$appointmentProduct['booking_id']][] = $appointmentProduct;
}

$editBookingId = (int)($_GET['edit_booking_id'] ?? $_POST['edit_booking_id'] ?? 0);
$editBooking = null;
$scheduledDateValue = $_POST['scheduled_date'] ?? '';
$scheduledTimeValue = $_POST['scheduled_time'] ?? '';
$notesValue = $_POST['notes'] ?? '';

if ($editBookingId > 0) {
    $editBooking = fetchOne("SELECT * FROM bookings WHERE id = ? AND user_id = ?", [$editBookingId, $user['id']]);
    if ($editBooking && !in_array((string)$editBooking['status'], $editableStatuses, true)) {
        $editBooking = null;
    }
}

$vehicleId = (int)($_POST['vehicle_id'] ?? $_GET['vehicle_id'] ?? $vehicles[0]['id']);
$selectedServiceIds = array_map('intval', (array)($_POST['service_ids'] ?? []));
$selectedProducts = [];
foreach ((array)($_POST['service_products'] ?? []) as $serviceId => $productId) {
    $selectedProducts[(int)$serviceId] = (int)$productId;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $editBooking) {
    $activeTab = 'book';
    $vehicleId = (int)$editBooking['vehicle_id'];
    $scheduledDateValue = (string)$editBooking['scheduled_date'];
    $scheduledTimeValue = (string)($editBooking['scheduled_time'] ?? '');
    $notesValue = (string)($editBooking['notes'] ?? '');
    $selectedServiceIds = array_map(
        static fn(array $row): int => (int)$row['service_id'],
        fetchAllRows("SELECT service_id FROM booking_services WHERE booking_id = ? ORDER BY id ASC", [$editBookingId])
    );
    foreach (fetchAllRows("SELECT service_id, product_id FROM booking_products WHERE booking_id = ?", [$editBookingId]) as $bookingProduct) {
        $selectedProducts[(int)$bookingProduct['service_id']] = (int)$bookingProduct['product_id'];
    }
}

$vehicle = null;
foreach ($vehicles as $candidateVehicle) {
    if ((int)$candidateVehicle['id'] === $vehicleId) {
        $vehicle = $candidateVehicle;
        break;
    }
}
if (!$vehicle) {
    $vehicle = $vehicles[0];
    $vehicleId = (int)$vehicle['id'];
}

$catalog = getBookingServiceCatalog((int)$vehicle['type_id'], (int)$vehicle['cc']);
$allowedServiceIds = array_map(static fn(array $service): int => (int)$service['id'], $catalog);

$selectedServiceIds = array_values(array_intersect($selectedServiceIds, $allowedServiceIds));
$selection = calculateBookingSelection($catalog, $selectedServiceIds, $selectedProducts);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pageAction === 'submit_booking') {
    $date = $scheduledDateValue;
    $time = $scheduledTimeValue;
    $notes = trim($notesValue);

    if (!$selectedServiceIds) {
        $error = 'Select at least one service for this appointment.';
    } elseif (!$date) {
        $error = 'Please choose an appointment date.';
    } elseif ($selection['errors']) {
        $error = $selection['errors'][0];
    } else {
        // Time-slot conflict check (max 3 bookings per slot)
        $maxPerSlot = 3;
        $slotParams = [$date];
        $slotSql = "SELECT COUNT(*) FROM bookings WHERE scheduled_date = ? AND status NOT IN ('cancelled')";
        if ($time) {
            $slotSql .= " AND scheduled_time = ?";
            $slotParams[] = $time;
        }
        if ($editBooking) {
            $slotSql .= " AND id != ?";
            $slotParams[] = (int)$editBooking['id'];
        }
        $slotStmt = getDB()->prepare($slotSql);
        $slotStmt->execute($slotParams);
        $slotCount = (int)$slotStmt->fetchColumn();

        if ($slotCount >= $maxPerSlot) {
            $error = 'This time slot is fully booked (' . $maxPerSlot . ' appointments). Please choose a different date or time.';
        }
        if (!$error) {
        $db = getDB();
        $db->beginTransaction();
        try {
            if ($editBooking) {
                $bookingId = (int)$editBooking['id'];
                $db->prepare(
                    "UPDATE bookings
                     SET vehicle_id = ?, scheduled_date = ?, scheduled_time = ?, notes = ?,
                         labor_total = ?, products_total = ?, total_amount = ?
                     WHERE id = ? AND user_id = ?"
                )->execute([
                    $vehicle['id'],
                    $date,
                    $time ?: null,
                    $notes,
                    $selection['labor_total'],
                    $selection['products_total'],
                    $selection['total_amount'],
                    $bookingId,
                    $user['id'],
                ]);
                $db->prepare("DELETE FROM booking_products WHERE booking_id = ?")->execute([$bookingId]);
                $db->prepare("DELETE FROM booking_services WHERE booking_id = ?")->execute([$bookingId]);
            } else {
                $bookingStmt = $db->prepare(
                    "INSERT INTO bookings
                     (user_id, vehicle_id, scheduled_date, scheduled_time, status, notes, labor_total, products_total, total_amount)
                     VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?)"
                );
                $bookingStmt->execute([
                    $user['id'],
                    $vehicle['id'],
                    $date,
                    $time ?: null,
                    $notes,
                    $selection['labor_total'],
                    $selection['products_total'],
                    $selection['total_amount'],
                ]);
                $bookingId = (int)$db->lastInsertId();
            }

            $serviceStmt = $db->prepare(
                "INSERT INTO booking_services (booking_id, service_id, labor_fee, service_name)
                 VALUES (?, ?, ?, ?)"
            );
            $productStmt = $db->prepare(
                "INSERT INTO booking_products (booking_id, service_id, product_id, product_price, product_name)
                 VALUES (?, ?, ?, ?, ?)"
            );

            foreach ($selection['services'] as $service) {
                $serviceStmt->execute([
                    $bookingId,
                    (int)$service['id'],
                    (float)$service['labor_fee'],
                    $service['name'],
                ]);
            }

            foreach ($selection['products'] as $product) {
                $productStmt->execute([
                    $bookingId,
                    (int)$product['service_id'],
                    (int)$product['product_id'],
                    (float)$product['product_price'],
                    $product['product_name'],
                ]);
            }

            // Notify staff before committing so booking + notification writes stay together.
            if (!$editBooking) {
                $scheduledLabel = date('M j, Y', strtotime($date));
                notifyAllStaff(
                    "New booking request from {$user['name']} scheduled for $scheduledLabel (Booking #$bookingId).",
                    'booking',
                    $bookingId
                );
            }

            $db->commit();

            flashMessage('booking_success', $editBooking ? 'Appointment updated successfully.' : 'Service appointment request saved. Reference #' . $bookingId);
            redirect(baseUrl('book-service.php?tab=appointments'));
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = $e->getMessage();
        }
        } // end if (!$error)
    }
}

$pageTitle = 'Book Service - MotoTrack';
require_once __DIR__ . '/includes/header.php';
?>

<section class="section container form-layout booking-layout">
  <div class="page-tabs booking-page-tabs">
    <a href="<?= baseUrl('book-service.php?tab=book') ?>" class="<?= $activeTab === 'book' ? 'active' : '' ?>">Book Service</a>
    <a href="<?= baseUrl('book-service.php?tab=appointments') ?>" class="<?= $activeTab === 'appointments' ? 'active' : '' ?>">Appointments</a>
  </div>

  <?php if ($activeTab === 'book'): ?>
  <form class="form-panel booking-form" method="post" id="multiServiceBookingForm">
    <?= authContextField() ?>
    <input type="hidden" name="page_action" value="submit_booking">
    <input type="hidden" name="tab" value="book">
    <?php if ($editBooking): ?><input type="hidden" name="edit_booking_id" value="<?= (int)$editBooking['id'] ?>"><?php endif; ?>
    <h2>Appointment details</h2>

    <?php if ($message): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <label>Select motorcycle
      <select name="vehicle_id" onchange="this.form.method='get'; this.form.submit()">
        <?php foreach ($vehicles as $v): ?>
          <option value="<?= (int)$v['id'] ?>" <?= (int)$v['id'] === (int)$vehicle['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($v['brand_name'] . ' ' . $v['model_name'] . ' (' . $v['type_name'] . ')') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>

    <?php if ($catalog): ?>
      <div class="booking-service-picker">
        <div class="booking-block-heading">
          <span class="eyebrow">Services</span>
          <strong>Select all work needed for this appointment</strong>
        </div>

        <div class="service-checkbox-grid">
          <?php foreach ($catalog as $service): ?>
            <?php $isChecked = in_array((int)$service['id'], $selectedServiceIds, true); ?>
            <label class="service-checkbox-card">
              <input
                type="checkbox"
                name="service_ids[]"
                value="<?= (int)$service['id'] ?>"
                <?= $isChecked ? 'checked' : '' ?>
                data-service-toggle
                data-service-id="<?= (int)$service['id'] ?>"
              >
              <span class="service-checkbox-copy">
                <strong><?= htmlspecialchars($service['name']) ?></strong>
                <small><?= htmlspecialchars($service['description'] ?: 'Service available for this motorcycle type.') ?></small>
              </span>
              <span class="service-checkbox-fee"><?= formatPrice((float)$service['labor_fee']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="selected-services-stack" id="selectedServicesStack" <?= $selectedServiceIds ? '' : 'hidden' ?>>
        <div class="booking-block-heading">
          <span class="eyebrow">Selected Services</span>
          <strong>Choose the product for each selected service</strong>
        </div>

        <div class="service-product-sections" id="serviceProductSections"></div>
      </div>

      <label>Date<input type="date" name="scheduled_date" min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($scheduledDateValue) ?>" required></label>
      <label>Time<input type="time" name="scheduled_time" value="<?= htmlspecialchars($scheduledTimeValue) ?>"></label>
      <label>Notes
        <textarea name="notes" rows="4" placeholder="Describe symptoms, preferred parts, or requests"><?= htmlspecialchars($notesValue) ?></textarea>
      </label>
      <div class="booking-form-actions">
        <button class="btn btn-primary" type="submit"><?= $editBooking ? 'Update appointment' : 'Submit booking' ?></button>
        <?php if ($editBooking): ?><a class="btn btn-outline" href="<?= baseUrl('book-service.php?tab=appointments') ?>">Cancel edit</a><?php endif; ?>
      </div>
    <?php else: ?>
      <p>No services are currently configured for <strong><?= htmlspecialchars($vehicle['type_name']) ?></strong> motorcycles.
         Please contact the shop directly.</p>
    <?php endif; ?>
  </form>

  <aside class="summary-box booking-summary" id="bookingSummaryPanel">
    <h2>Estimated Cost</h2>

    <div class="booking-summary-section">
      <span>Vehicle</span>
      <strong><?= htmlspecialchars($vehicle['brand_name'] . ' ' . $vehicle['model_name']) ?></strong>
    </div>

    <div class="booking-summary-group">
      <h3>Selected services</h3>
      <div id="selectedServicesSummary">
        <?php if ($selection['services']): ?>
          <?php foreach ($selection['services'] as $service): ?>
            <div>
              <span><?= htmlspecialchars($service['name']) ?></span>
              <strong><?= formatPrice((float)$service['labor_fee']) ?></strong>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="fine-print" id="emptyServiceSummary">Select services to see the estimate.</p>
        <?php endif; ?>
      </div>
    </div>

    <div class="booking-summary-group">
      <h3>Selected products</h3>
      <div id="selectedProductsSummary">
        <?php if ($selection['products']): ?>
          <?php foreach ($selection['products'] as $product): ?>
            <div>
              <span><?= htmlspecialchars($product['service_name'] . ': ' . $product['product_name']) ?></span>
              <strong><?= formatPrice((float)$product['product_price']) ?></strong>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="fine-print" id="emptyProductSummary">Choose products for each selected service.</p>
        <?php endif; ?>
      </div>
    </div>

    <div><span>Total labor</span><strong id="laborTotalValue"><?= formatPrice((float)$selection['labor_total']) ?></strong></div>
    <div><span>Total products</span><strong id="productsTotalValue"><?= formatPrice((float)$selection['products_total']) ?></strong></div>
    <div><span>Final total</span><strong id="bookingTotalValue"><?= formatPrice((float)$selection['total_amount']) ?></strong></div>
    <p class="fine-print">Final cost can still change if the technician records additional parts during service.</p>
  </aside>
  <?php else: ?>
  <div class="form-panel booking-history-panel">
    <h2>Your appointments</h2>
    <?php if ($message): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="history-list">
      <?php if ($appointments): ?>
        <?php foreach ($appointments as $appointment): ?>
          <?php
            $bookingId   = (int)$appointment['id'];
            $status      = (string)$appointment['status'];
            $canEdit     = in_array($status, $editableStatuses, true);
            $isPending   = $status === 'pending';
            $vehicleLabel = trim(($appointment['brand_name'] ?? '') . ' ' . ($appointment['model_name'] ?? ''));
            $techFirstName = $appointment['technician_name'] ? explode(' ', $appointment['technician_name'])[0] : null;

            // Progress steps
            $steps = ['pending' => 0, 'confirmed' => 1, 'in_progress' => 2, 'completed' => 3, 'cancelled' => -1];
            $currentStep = $steps[$status] ?? 0;
            $stepLabels = ['Pending', 'Confirmed', 'In Progress', 'Completed'];
            $stepIcons  = ['fa-clock', 'fa-check', 'fa-wrench', 'fa-flag-checkered'];

            // Status messages
            $statusMsg = match($status) {
              'pending'     => '⏳ Waiting for staff to confirm your appointment.',
              'confirmed'   => '✅ Confirmed!' . ($techFirstName ? " Assigned to $techFirstName." : '') . ' Please be on time.',
              'in_progress' => '🔧 Your motorcycle is being serviced right now.' . ($techFirstName ? " Technician: $techFirstName." : ''),
              'completed'   => '🎉 Service complete! Thank you for choosing MotoTrack.',
              'cancelled'   => '❌ This appointment has been cancelled.',
              default       => ''
            };
          ?>
          <article class="history-card">
            <div class="history-card-head">
              <div>
                <strong>Appointment #<?= $bookingId ?></strong>
                <span><?= htmlspecialchars(date('M j, Y', strtotime($appointment['scheduled_date']))) ?><?= !empty($appointment['scheduled_time']) ? ' at ' . htmlspecialchars(date('g:i A', strtotime((string)$appointment['scheduled_time']))) : '' ?></span>
              </div>
              <span class="status-pill-lite status-<?= htmlspecialchars($status) ?>"><?= htmlspecialchars(strtoupper(str_replace('_', ' ', $status))) ?></span>
            </div>

            <?php if ($status !== 'cancelled'): ?>
            <!-- Progress tracker -->
            <div class="booking-progress">
              <?php foreach ($stepLabels as $i => $label): ?>
                <div class="bp-step <?= $i < $currentStep ? 'bp-done' : ($i === $currentStep ? 'bp-active' : 'bp-future') ?>">
                  <div class="bp-dot"><i class="fas <?= $stepIcons[$i] ?>"></i></div>
                  <span><?= $label ?></span>
                </div>
                <?php if ($i < count($stepLabels) - 1): ?><div class="bp-line <?= $i < $currentStep ? 'bp-line-done' : '' ?>"></div><?php endif; ?>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ($statusMsg): ?>
            <div class="booking-status-msg booking-status-msg--<?= htmlspecialchars($status) ?>"><?= htmlspecialchars($statusMsg) ?></div>
            <?php endif; ?>

            <div class="history-lines">
              <div><span>Motorcycle</span><strong><?= htmlspecialchars($vehicleLabel) ?></strong></div>
              <div><span>Type</span><strong><?= htmlspecialchars((string)($appointment['type_name'] ?? '-')) ?></strong></div>
              <div><span>Plate</span><strong><?= htmlspecialchars((string)($appointment['plate_number'] ?: '-')) ?></strong></div>
              <?php foreach ($servicesByBooking[$bookingId] ?? [] as $serviceRow): ?>
                <div><span><?= htmlspecialchars($serviceRow['service_name']) ?></span><strong><?= formatPrice((float)$serviceRow['labor_fee']) ?></strong></div>
              <?php endforeach; ?>
              <?php foreach ($productsByBooking[$bookingId] ?? [] as $productRow): ?>
                <div><span><?= htmlspecialchars($productRow['product_name']) ?></span><strong><?= formatPrice((float)$productRow['product_price']) ?></strong></div>
              <?php endforeach; ?>
            </div>

            <div class="history-total">
              <span>Total</span>
              <strong><?= formatPrice((float)$appointment['total_amount']) ?></strong>
            </div>

            <div class="history-actions">
              <?php if ($canEdit && $isPending): ?>
                <a class="btn btn-outline" href="<?= baseUrl('book-service.php?tab=book&edit_booking_id=' . $bookingId) ?>">Edit</a>
              <?php endif; ?>
              <?php if ($isPending): ?>
                <form method="post" onsubmit="return confirm('Cancel appointment #<?= $bookingId ?>?')">
                  <?= authContextField() ?>
                  <input type="hidden" name="tab" value="appointments">
                  <input type="hidden" name="page_action" value="cancel_appointment">
                  <input type="hidden" name="booking_id" value="<?= $bookingId ?>">
                  <button class="btn btn-outline" type="submit">Cancel</button>
                </form>
              <?php elseif (in_array($status, ['confirmed', 'in_progress'], true)): ?>
                <span style="font-size:.82rem;color:var(--muted);">📞 Contact the shop to cancel</span>
              <?php endif; ?>
              <?php if ($canEdit && $isPending): ?>
                <form method="post" onsubmit="return confirm('Delete appointment #<?= $bookingId ?>?')">
                  <?= authContextField() ?>
                  <input type="hidden" name="tab" value="appointments">
                  <input type="hidden" name="page_action" value="delete_appointment">
                  <input type="hidden" name="booking_id" value="<?= $bookingId ?>">
                  <button class="btn btn-outline btn-danger-lite" type="submit">Delete</button>
                </form>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      <?php else: ?>
        <p class="empty-state">No appointments yet.</p>
      <?php endif; ?>
    </div>
  </div>

  <aside class="summary-box booking-summary">
    <h2>Appointments</h2>
    <div><span>Total appointments</span><strong><?= count($appointments) ?></strong></div>
    <div><span>Pending</span><strong><?= count(array_filter($appointments, static fn(array $appointment): bool => (string)$appointment['status'] === 'pending')) ?></strong></div>
    <div><span>Completed</span><strong><?= count(array_filter($appointments, static fn(array $appointment): bool => (string)$appointment['status'] === 'completed')) ?></strong></div>
    <a class="btn btn-primary" href="<?= baseUrl('book-service.php?tab=book') ?>">Book New Appointment</a>
  </aside>
  <?php endif; ?>
</section>

<?php if ($catalog && $activeTab === 'book'): ?>
<script>
(() => {
  const bookingForm = document.getElementById('multiServiceBookingForm');
  if (!bookingForm) return;

  const currency = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    minimumFractionDigits: 2,
  });

  const catalog = <?= json_encode(array_map(static function (array $service): array {
      return [
          'id' => (int)$service['id'],
          'name' => $service['name'],
          'labor_fee' => (float)$service['labor_fee'],
          'category_name' => $service['required_category'] ?? '',
      ];
  }, $catalog), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

  const serviceLookup = new Map(catalog.map((service) => [service.id, service]));
  const serviceToggles = Array.from(bookingForm.querySelectorAll('[data-service-toggle]'));
  const selectedServicesStack = document.getElementById('selectedServicesStack');
  const serviceProductSections = document.getElementById('serviceProductSections');
  const servicesSummary = document.getElementById('selectedServicesSummary');
  const productsSummary = document.getElementById('selectedProductsSummary');
  const laborTotalValue = document.getElementById('laborTotalValue');
  const productsTotalValue = document.getElementById('productsTotalValue');
  const bookingTotalValue = document.getElementById('bookingTotalValue');
  const vehicleId = Number(bookingForm.querySelector('[name="vehicle_id"]')?.value || 0);
  const productEndpoint = <?= json_encode(baseUrl('api/service-products.php'), JSON_UNESCAPED_SLASHES) ?>;
  const initialSelectedProducts = <?= json_encode($selectedProducts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  const productCache = new Map();
  const selectedProducts = new Map(
    Object.entries(initialSelectedProducts).map(([serviceId, productId]) => [Number(serviceId), Number(productId)])
  );

  const formatMoney = (value) => currency.format(Number(value || 0));
  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
  }[char]));

  const renderRows = (container, rows, emptyMessage) => {
    if (!container) return;
    if (!rows.length) {
      container.innerHTML = `<p class="fine-print">${emptyMessage}</p>`;
      return;
    }

    container.innerHTML = rows.map((row) => (
      `<div><span>${row.label}</span><strong>${formatMoney(row.value)}</strong></div>`
    )).join('');
  };

  const selectedServiceIds = () => serviceToggles
    .filter((toggle) => toggle.checked)
    .map((toggle) => Number(toggle.dataset.serviceId || 0))
    .filter(Boolean);

  const getProductImageMarkup = (product) => {
    if (product.image_url) {
      return `<img src="${escapeHtml(product.image_url)}" alt="${escapeHtml(product.name)}">`;
    }
    return '<span><i class="fas fa-box-open"></i></span>';
  };

  const productCardMarkup = (serviceId, product) => {
    const isSelected = selectedProducts.get(serviceId) === Number(product.id);
    const description = product.description || product.category_name || 'Compatible product';
    return `
      <button
        type="button"
        class="booking-product-card${isSelected ? ' is-selected' : ''}"
        data-product-card
        data-service-id="${serviceId}"
        data-product-id="${Number(product.id)}"
      >
        <span class="booking-product-check"><i class="fas fa-check"></i></span>
        <span class="booking-product-image">${getProductImageMarkup(product)}</span>
        <span class="booking-product-copy">
          <strong>${escapeHtml(product.name)}</strong>
          <small>${escapeHtml(product.brand || 'MotoTrack')}</small>
          <em>${escapeHtml(description)}</em>
          <b>${formatMoney(product.price)}</b>
        </span>
        <span class="booking-product-action">${isSelected ? 'Selected' : 'Select Product'}</span>
      </button>
    `;
  };

  const renderProductSection = (service, products, state = 'ready') => {
    if (!serviceProductSections) return;
    const serviceId = Number(service.id);
    let section = serviceProductSections.querySelector(`[data-service-panel="${serviceId}"]`);
    if (!section) {
      section = document.createElement('section');
      section.className = 'selected-service-card';
      section.dataset.servicePanel = String(serviceId);
      serviceProductSections.appendChild(section);
    }

    const title = escapeHtml(service.name);
    const category = escapeHtml(service.category_name || 'Compatible products');
    const labor = formatMoney(service.labor_fee);

    if (state === 'loading') {
      section.innerHTML = `
        <div class="selected-service-header">
          <div><span class="eyebrow">Service</span><h3>${title}</h3></div>
          <strong>${labor} labor</strong>
        </div>
        <div class="product-card-loader">Loading compatible products...</div>
      `;
      return;
    }

    const cards = products.length
      ? products.map((product) => productCardMarkup(serviceId, product)).join('')
      : '<p class="fine-print">No product selection is required for this service.</p>';
    const selectedProductId = selectedProducts.get(serviceId) || 0;

    section.innerHTML = `
      <div class="selected-service-header">
        <div>
          <span class="eyebrow">Service</span>
          <h3>${title}</h3>
        </div>
        <strong>${labor} labor</strong>
      </div>
      <div class="product-picker-heading">
        <strong>${title} products</strong>
        <span>${category}</span>
      </div>
      <div class="booking-product-grid">${cards}</div>
      <input type="hidden" name="service_products[${serviceId}]" value="${selectedProductId}" data-selected-product-input="${serviceId}">
    `;
  };

  const loadProductsForService = async (serviceId) => {
    const service = serviceLookup.get(serviceId);
    if (!service || !serviceProductSections) return;

    if (productCache.has(serviceId)) {
      renderProductSection(service, productCache.get(serviceId));
      updateBookingUi();
      return;
    }

    renderProductSection(service, [], 'loading');

    const url = new URL(productEndpoint, window.location.href);
    url.searchParams.set('service_id', String(serviceId));
    url.searchParams.set('vehicle_id', String(vehicleId));

    try {
      const response = await fetch(url.toString(), { headers: { Accept: 'application/json' } });
      const data = await response.json();
      const products = data.success && Array.isArray(data.products) ? data.products : [];
      productCache.set(serviceId, products);
      renderProductSection(service, products);
    } catch (error) {
      productCache.set(serviceId, []);
      renderProductSection(service, []);
    }

    updateBookingUi();
  };

  const removeProductSection = (serviceId) => {
    selectedProducts.delete(serviceId);
    const panel = serviceProductSections?.querySelector(`[data-service-panel="${serviceId}"]`);
    if (panel) panel.remove();
  };

  const syncProductSections = () => {
    const ids = selectedServiceIds();
    if (selectedServicesStack) selectedServicesStack.hidden = ids.length === 0;

    serviceToggles.forEach((toggle) => {
      const serviceId = Number(toggle.dataset.serviceId || 0);
      if (!toggle.checked) {
        removeProductSection(serviceId);
      }
    });

    ids.forEach((serviceId) => {
      loadProductsForService(serviceId);
    });
  };

  const updateBookingUi = () => {
    let laborTotal = 0;
    let productsTotal = 0;
    const serviceRows = [];
    const productRows = [];

    serviceToggles.forEach((toggle) => {
      const serviceId = Number(toggle.dataset.serviceId || 0);
      const service = serviceLookup.get(serviceId);

      if (!service) return;

      if (!toggle.checked) {
        return;
      }

      laborTotal += Number(service.labor_fee || 0);
      serviceRows.push({
        label: service.name,
        value: Number(service.labor_fee || 0),
      });

      const selectedProductId = Number(selectedProducts.get(serviceId) || 0);
      const products = productCache.get(serviceId) || [];
      const selectedProduct = products.find((product) => Number(product.id) === selectedProductId);

      if (selectedProduct) {
        productsTotal += Number(selectedProduct.price || 0);
        productRows.push({
          label: `${service.name}: ${selectedProduct.name}`,
          value: Number(selectedProduct.price || 0),
        });
      }
    });

    renderRows(servicesSummary, serviceRows, 'Select services to see the estimate.');
    renderRows(productsSummary, productRows, 'Choose products for each selected service.');

    if (laborTotalValue) laborTotalValue.textContent = formatMoney(laborTotal);
    if (productsTotalValue) productsTotalValue.textContent = formatMoney(productsTotal);
    if (bookingTotalValue) bookingTotalValue.textContent = formatMoney(laborTotal + productsTotal);
  };

  serviceToggles.forEach((toggle) => toggle.addEventListener('change', () => {
    syncProductSections();
    updateBookingUi();
  }));

  serviceProductSections?.addEventListener('click', (event) => {
    const card = event.target.closest('[data-product-card]');
    if (!card) return;

    const serviceId = Number(card.dataset.serviceId || 0);
    const productId = Number(card.dataset.productId || 0);
    selectedProducts.set(serviceId, productId);

    const input = serviceProductSections.querySelector(`[data-selected-product-input="${serviceId}"]`);
    if (input) input.value = String(productId);

    const service = serviceLookup.get(serviceId);
    if (service) {
      renderProductSection(service, productCache.get(serviceId) || []);
    }
    updateBookingUi();
  });

  syncProductSections();
  updateBookingUi();
})();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
