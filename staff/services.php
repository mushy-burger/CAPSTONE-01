<?php
$pageTitle = 'Compatible Services';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
requireStaff();
$currentUser = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    flashMessage('svc_error', 'Services are managed by Admin Settings.');
    redirect(baseUrl('staff/services.php'));
}

$flashErr = getFlash('svc_error');

$motorcycleTypes = fetchAllRows("SELECT * FROM motorcycle_types ORDER BY name");
$services = fetchAllRows(
    "SELECT
        st.*,
        COALESCE(c.name, st.required_category) AS required_category_name,
        (
            SELECT COUNT(*)
            FROM products p
            WHERE p.category_id = st.required_category_id
              AND p.status != 'out_of_stock'
              AND p.stock > 0
        ) AS available_product_count
     FROM service_types st
     LEFT JOIN categories c ON c.id = st.required_category_id
     ORDER BY st.name"
);

function appliesLabel(string $val, array $types): string {
    if (strtolower(trim($val)) === 'all' || trim($val) === '') {
        return 'All types';
    }

    $ids = array_map('intval', preg_split('/\s*,\s*/', $val) ?: []);
    $typeMap = array_column($types, 'name', 'id');
    $names = array_filter(array_map(fn($id) => $typeMap[$id] ?? null, $ids));

    return $names ? implode(', ', $names) : $val;
}

function serviceAvailability(array $service): array {
    $requiredCategory = trim((string)($service['required_category_name'] ?? ''));
    $requiredCategoryId = (int)($service['required_category_id'] ?? 0);

    if ($requiredCategory === '' && $requiredCategoryId <= 0) {
        return ['Available', '#15803d'];
    }

    if ((int)($service['available_product_count'] ?? 0) > 0) {
        return ['Available', '#15803d'];
    }

    return ['Needs Products', '#b45309'];
}

require_once __DIR__ . '/../includes/staff-sidebar.php';
?>

<?php if ($flashErr): ?><div class="alert error"><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>

<section class="admin-card admin-page-stack">
  <div class="admin-page-head">
    <div>
      <h1>Compatible Services</h1>
      <p>View the Admin-managed service catalog used for bookings, estimates, and technician assignment.</p>
    </div>
  </div>

  <?php if ($services): ?>
    <div class="admin-table-wrap">
      <table class="admin-data-table staff-service-catalog">
        <thead>
          <tr>
            <th>Service Name</th>
            <th>Description</th>
            <th>Labor Fee</th>
            <th>Required Product Category</th>
            <th>Compatible Motorcycle Types</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($services as $svc): ?>
            <?php [$statusLabel, $statusColor] = serviceAvailability($svc); ?>
            <tr>
              <td><strong><?= htmlspecialchars($svc['name']) ?></strong></td>
              <td><?= htmlspecialchars(trim((string)($svc['description'] ?? '')) !== '' ? $svc['description'] : '—') ?></td>
              <td><?= formatPrice((float)$svc['labor_fee']) ?></td>
              <td><?= htmlspecialchars(trim((string)($svc['required_category_name'] ?? '')) !== '' ? $svc['required_category_name'] : 'None') ?></td>
              <td><span class="settings-tag"><?= htmlspecialchars(appliesLabel((string)$svc['applies_to'], $motorcycleTypes)) ?></span></td>
              <td><span class="status-pill" style="--status-color: <?= $statusColor ?>;"><?= htmlspecialchars($statusLabel) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p class="empty-note">No compatible services have been configured by Admin yet.</p>
  <?php endif; ?>
</section>

<?= authContextScriptTag() ?>
</main></div></div></body></html>
