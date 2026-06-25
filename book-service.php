<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
requireLogin();

ensureMultiServiceBookingSchema();

$user = getCurrentUser();
$vehicles = getCustomerVehicles($user['id']);

if (!$vehicles) {
    flashMessage('notice', 'Save your motorcycle first so we can recommend compatible services.');
    redirect(baseUrl('my-vehicle.php'));
}

$vehicleId = (int)($_POST['vehicle_id'] ?? $_GET['vehicle_id'] ?? $vehicles[0]['id']);
$vehicle = null;
foreach ($vehicles as $candidateVehicle) {
    if ((int)$candidateVehicle['id'] === $vehicleId) {
        $vehicle = $candidateVehicle;
        break;
    }
}
if (!$vehicle) {
    $vehicle = $vehicles[0];
}

$catalog = getBookingServiceCatalog((int)$vehicle['type_id'], (int)$vehicle['cc']);
$allowedServiceIds = array_map(static fn(array $service): int => (int)$service['id'], $catalog);

$selectedServiceIds = array_map('intval', (array)($_POST['service_ids'] ?? []));
$selectedServiceIds = array_values(array_intersect($selectedServiceIds, $allowedServiceIds));
$selectedProducts = [];
foreach ((array)($_POST['service_products'] ?? []) as $serviceId => $productId) {
    $selectedProducts[(int)$serviceId] = (int)$productId;
}

$selection = calculateBookingSelection($catalog, $selectedServiceIds, $selectedProducts);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['scheduled_date'] ?? '';
    $time = $_POST['scheduled_time'] ?? '';
    $notes = trim($_POST['notes'] ?? '');

    if (!$selectedServiceIds) {
        $error = 'Select at least one service for this appointment.';
    } elseif (!$date) {
        $error = 'Please choose an appointment date.';
    } elseif ($selection['errors']) {
        $error = $selection['errors'][0];
    } else {
        $db = getDB();
        $db->beginTransaction();
        try {
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

            $db->commit();
            $message = 'Service appointment request saved. Reference #' . $bookingId;
            $selectedServiceIds = [];
            $selectedProducts = [];
            $selection = calculateBookingSelection($catalog, $selectedServiceIds, $selectedProducts);
        } catch (Throwable $e) {
            $db->rollBack();
            $error = $e->getMessage();
        }
    }
}

$pageTitle = 'Book Service - MotoTrack';
require_once __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
  <div class="container">
    <span class="eyebrow">Service Appointment</span>
    <h1>Book multiple services in one visit</h1>
    <p><?= htmlspecialchars($vehicle['brand_name'] . ' ' . $vehicle['model_name']) ?>,
       <?= (int)$vehicle['cc'] ?>cc <?= htmlspecialchars($vehicle['type_name']) ?></p>
  </div>
</section>

<section class="section container form-layout booking-layout">
  <form class="form-panel booking-form" method="post" id="multiServiceBookingForm">
    <?= authContextField() ?>
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

      <label>Date<input type="date" name="scheduled_date" min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($_POST['scheduled_date'] ?? '') ?>" required></label>
      <label>Time<input type="time" name="scheduled_time" value="<?= htmlspecialchars($_POST['scheduled_time'] ?? '') ?>"></label>
      <label>Notes
        <textarea name="notes" rows="4" placeholder="Describe symptoms, preferred parts, or requests"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
      </label>
      <button class="btn btn-primary" type="submit">Submit booking</button>
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
</section>

<?php if ($catalog): ?>
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
