<?php
$pageTitle = 'Users';
require_once __DIR__ . '/../includes/admin-sidebar.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/TechnicianService.php';

$roleOptions = ['admin', 'staff', 'technician', 'customer'];
$canManageUsers = $currentUser['role'] === 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canManageUsers) {
        flashMessage('users_error', 'Only administrators can update user accounts.');
        redirect(baseUrl('admin/users.php'));
    }

    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);

    if ($userId === (int)$currentUser['id'] && in_array($action, ['update_role', 'toggle_status'], true)) {
        flashMessage('users_error', 'You cannot change your own role or active status.');
        redirect(baseUrl('admin/users.php'));
    }

    if ($action === 'update_role' && $userId > 0) {
        $role = $_POST['role'] ?? '';
        if (in_array($role, $roleOptions, true)) {
            getDB()->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$role, $userId]);
            flashMessage('users_success', 'User role updated.');
        } else {
            flashMessage('users_error', 'Invalid user role.');
        }
        redirect(baseUrl('admin/users.php'));
    }

    if ($action === 'toggle_status' && $userId > 0) {
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        getDB()->prepare("UPDATE users SET is_active = ? WHERE id = ?")->execute([$isActive, $userId]);
        flashMessage('users_success', $isActive ? 'User enabled.' : 'User disabled.');
        redirect(baseUrl('admin/users.php'));
    }

    if ($action === 'create_account' ) {
        $newName  = trim($_POST['new_name'] ?? '');
        $newEmail = strtolower(trim($_POST['new_email'] ?? ''));
        $newRole  = $_POST['new_role'] ?? '';
        $newPass  = $_POST['new_password'] ?? '';
        if (!in_array($newRole, ['staff', 'technician'], true)) {
            flashMessage('users_error', 'Role must be staff or technician.');
        } elseif ($newName === '' || $newEmail === '' || strlen($newPass) < 6) {
            flashMessage('users_error', 'All fields are required. Password min 6 chars.');
        } elseif (fetchOne('SELECT id FROM users WHERE email = ?', [$newEmail])) {
            flashMessage('users_error', 'That email is already registered.');
        } else {
            getDB()->prepare("INSERT INTO users (name, email, password, role, is_active) VALUES (?, ?, ?, ?, 1)")
                ->execute([$newName, $newEmail, password_hash($newPass, PASSWORD_DEFAULT), $newRole]);
            $newUserId = (int)getDB()->lastInsertId();
            if ($newRole === 'technician') {
                techSetQualifiedServices($newUserId, $_POST['tech_service_ids'] ?? []);
            }
            flashMessage('users_success', ucfirst($newRole) . ' account created for ' . $newName . '.');
        }
        redirect(baseUrl('admin/users.php'));
    }

    if ($action === 'save_tech_services' && $userId > 0) {
        $tech = fetchOne("SELECT id FROM users WHERE id = ? AND role = 'technician'", [$userId]);
        if (!$tech) {
            flashMessage('users_error', 'Skills can only be set for technician accounts.');
        } else {
            techSetQualifiedServices($userId, $_POST['tech_service_ids'] ?? []);
            flashMessage('users_success', 'Technician skills updated.');
        }
        redirect(baseUrl('admin/users.php'));
    }

    if ($action === 'reset_password' && $userId > 0) {
        $password = $_POST['password'] ?? '';
        if (strlen($password) < 6) {
            flashMessage('users_error', 'Temporary password must be at least 6 characters.');
        } else {
            getDB()->prepare("UPDATE users SET password = ?, auth_provider = 'local' WHERE id = ?")
                ->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
            flashMessage('users_success', 'User password reset.');
        }
        redirect(baseUrl('admin/users.php'));
    }
}

$flash = getFlash('users_success');
$flashErr = getFlash('users_error');
$roleFilter = $_GET['role'] ?? '';
$roleFilter = in_array($roleFilter, $roleOptions, true) ? $roleFilter : '';
$search = trim($_GET['q'] ?? '');

$where = [];
$params = [];
if ($roleFilter !== '') {
    $where[] = 'u.role = ?';
    $params[] = $roleFilter;
}
if ($search !== '') {
    $where[] = '(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$perPage    = 20;
$page       = max(1, (int)($_GET['page'] ?? 1));

$totalCount = (int)(fetchOne(
    "SELECT COUNT(*) AS n FROM users u " . ($where ? 'WHERE ' . implode(' AND ', $where) : ''),
    $params
)['n'] ?? 0);

$totalPages = max(1, (int)ceil($totalCount / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$users = fetchAllRows(
    "SELECT
        u.*,
        COALESCE(oc.order_count, 0) AS order_count,
        COALESCE(bc.booking_count, 0) AS booking_count
     FROM users u
     LEFT JOIN (SELECT user_id, COUNT(*) AS order_count FROM orders GROUP BY user_id) oc ON oc.user_id = u.id
     LEFT JOIN (SELECT user_id, COUNT(*) AS booking_count FROM bookings GROUP BY user_id) bc ON bc.user_id = u.id
     " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
     ORDER BY u.created_at DESC, u.id DESC
     LIMIT $perPage OFFSET $offset",
    $params
);

$allServices = fetchAllRows("SELECT id, name FROM service_types ORDER BY name");
$techQualifications = techQualificationMap();

$editSkillsId = (int)($_GET['edit_skills'] ?? 0);
$editSkillsUser = $editSkillsId
    ? fetchOne("SELECT id, name FROM users WHERE id = ? AND role = 'technician'", [$editSkillsId])
    : null;
?>

<section class="admin-card admin-page-stack">
  <div class="admin-page-head">
    <div>
      <h1>Users</h1>
      <p>Review customer and staff accounts, roles, access status, and activity.</p>
    </div>
    <form method="get" class="admin-inline-form">
      <input type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search users">
      <select name="role">
        <option value="">All roles</option>
        <?php foreach ($roleOptions as $role): ?>
          <option value="<?= htmlspecialchars($role) ?>" <?= $roleFilter === $role ? 'selected' : '' ?>>
            <?= htmlspecialchars(ucfirst($role)) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-outline">Filter</button>
      <?php if ($search || $roleFilter): ?><a href="<?= baseUrl('admin/users.php') ?>" class="btn btn-outline">Reset</a><?php endif; ?>
    </form>
  </div>

  <!-- Create Staff / Tech Account -->
  <div style="background:#f8fafc;border:1px solid var(--line);border-radius:8px;padding:18px 20px;margin:0 0 18px;">
    <h2 style="margin:0 0 12px;font-size:.95rem;">➕ Create Staff or Technician Account</h2>
    <form method="post" class="admin-inline-form" style="flex-wrap:wrap;gap:8px;">
      <?= authContextField() ?>
      <input type="hidden" name="action" value="create_account">
      <input type="text"     name="new_name"     placeholder="Full name"      required style="min-width:140px;flex:1;">
      <input type="email"    name="new_email"    placeholder="Email address"  required style="min-width:180px;flex:1;">
      <input type="password" name="new_password" placeholder="Password (min 6)" required minlength="6" style="min-width:150px;flex:1;">
      <select name="new_role" id="createAccountRole" required style="min-width:140px;">
        <option value="">— Select Role —</option>
        <option value="staff">Staff</option>
        <option value="technician">Technician</option>
      </select>
      <button type="submit" class="btn btn-primary">Create Account</button>

      <div id="techServicesSection" hidden style="flex-basis:100%;margin-top:10px;border-top:1px dashed var(--line);padding-top:12px;">
        <strong style="display:block;font-size:.85rem;margin-bottom:8px;">
          <i class="fas fa-tools" style="color:var(--accent);"></i> Qualified Services
          <span class="subtext" style="font-weight:400;">— which services can this technician perform? (used by auto-assignment)</span>
        </strong>
        <?php if ($allServices): ?>
          <div style="display:flex;flex-wrap:wrap;gap:8px 18px;">
            <?php foreach ($allServices as $svc): ?>
              <label style="display:inline-flex;align-items:center;gap:7px;font-size:.86rem;font-weight:600;">
                <input type="checkbox" name="tech_service_ids[]" value="<?= (int)$svc['id'] ?>" style="width:16px;height:16px;min-height:0;accent-color:var(--accent);">
                <?= htmlspecialchars($svc['name']) ?>
              </label>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <span class="subtext">No services exist yet — add services first in Settings &rarr; Compatible Services.</span>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <?php if ($editSkillsUser): ?>
  <!-- Edit an existing technician's qualified services -->
  <div style="background:#fff7f7;border:1px solid var(--accent);border-radius:8px;padding:18px 20px;margin:0 0 18px;">
    <h2 style="margin:0 0 12px;font-size:.95rem;">
      <i class="fas fa-tools" style="color:var(--accent);"></i>
      Edit Skills — <?= htmlspecialchars($editSkillsUser['name']) ?>
    </h2>
    <form method="post">
      <?= authContextField() ?>
      <input type="hidden" name="action" value="save_tech_services">
      <input type="hidden" name="user_id" value="<?= (int)$editSkillsUser['id'] ?>">
      <?php $currentSkills = $techQualifications[(int)$editSkillsUser['id']] ?? []; ?>
      <?php if ($allServices): ?>
        <div style="display:flex;flex-wrap:wrap;gap:8px 18px;margin-bottom:12px;">
          <?php foreach ($allServices as $svc): ?>
            <label style="display:inline-flex;align-items:center;gap:7px;font-size:.86rem;font-weight:600;">
              <input type="checkbox" name="tech_service_ids[]" value="<?= (int)$svc['id'] ?>"
                     <?= isset($currentSkills[(int)$svc['id']]) ? 'checked' : '' ?>
                     style="width:16px;height:16px;min-height:0;accent-color:var(--accent);">
              <?= htmlspecialchars($svc['name']) ?>
            </label>
          <?php endforeach; ?>
        </div>
        <button type="submit" class="btn btn-primary" style="font-size:.85rem;">Save Skills</button>
        <a href="<?= baseUrl('admin/users.php') ?>" class="btn btn-outline" style="font-size:.85rem;">Cancel</a>
      <?php else: ?>
        <span class="subtext">No services exist yet — add services first in Settings &rarr; Compatible Services.</span>
      <?php endif; ?>
    </form>
  </div>
  <?php endif; ?>

  <?php if (!$canManageUsers): ?>
    <div class="alert error">Only administrators can change roles, reset passwords, or disable accounts.</div>
  <?php endif; ?>
  <?php if ($flash): ?><div class="alert success"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
  <?php if ($flashErr): ?><div class="alert error"><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>

  <?php if ($users): ?>
    <div class="admin-table-wrap">
      <table class="admin-data-table">
        <thead>
          <tr>
            <th>User</th>
            <th>Role</th>
            <th>Status</th>
            <th>Activity</th>
            <th>Joined</th>
            <th>Manage</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $user): ?>
            <?php $isSelf = (int)$user['id'] === (int)$currentUser['id']; ?>
            <tr>
              <td>
                <strong><?= htmlspecialchars($user['name']) ?></strong>
                <div class="subtext"><?= htmlspecialchars($user['email']) ?></div>
                <?php if ($user['phone']): ?><div class="subtext"><?= htmlspecialchars($user['phone']) ?></div><?php endif; ?>
              </td>
              <td>
                <?php if ($canManageUsers && !$isSelf): ?>
                  <form method="post" class="admin-row-form">
                    <?= authContextField() ?>
                    <input type="hidden" name="action" value="update_role">
                    <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                    <select name="role">
                      <?php foreach ($roleOptions as $role): ?>
                        <option value="<?= htmlspecialchars($role) ?>" <?= $user['role'] === $role ? 'selected' : '' ?>>
                          <?= htmlspecialchars(ucfirst($role)) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-outline">Save</button>
                  </form>
                <?php else: ?>
                  <?= htmlspecialchars(ucfirst($user['role'])) ?>
                <?php endif; ?>
              </td>
              <td>
                <?php $active = (int)($user['is_active'] ?? 1) === 1; ?>
                <span class="status-pill" style="--status-color: <?= $active ? '#15803d' : '#b91c1c' ?>;">
                  <?= $active ? 'Active' : 'Disabled' ?>
                </span>
                <?php if ($user['role'] === 'technician'): ?>
                  <?php $isReady = ($user['availability_status'] ?? 'off_duty') === 'ready'; ?>
                  <span class="status-pill" style="--status-color: <?= $isReady ? '#2563eb' : '#6b7280' ?>;margin-top:4px;">
                    <?= $isReady ? 'Ready / On Site' : 'Off Duty' ?>
                  </span>
                <?php endif; ?>
                <?php if ($canManageUsers && !$isSelf): ?>
                  <form method="post" class="admin-toggle-form">
                    <?= authContextField() ?>
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                    <label>
                      <input type="checkbox" name="is_active" value="1" <?= $active ? 'checked' : '' ?> onchange="this.form.submit()">
                      Enabled
                    </label>
                  </form>
                <?php endif; ?>
              </td>
              <td>
                <div class="stacked-lines">
                  <span><?= (int)$user['order_count'] ?> order<?= (int)$user['order_count'] === 1 ? '' : 's' ?></span>
                  <span><?= (int)$user['booking_count'] ?> service request<?= (int)$user['booking_count'] === 1 ? '' : 's' ?></span>
                </div>
              </td>
              <td><?= htmlspecialchars(date('M j, Y', strtotime($user['created_at']))) ?></td>
              <td>
                <?php if ($canManageUsers): ?>
                  <form method="post" class="admin-row-form">
                    <?= authContextField() ?>
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                    <input type="password" name="password" placeholder="New password" minlength="6" required>
                  <button type="submit" class="btn btn-outline">Reset</button>
                  </form>
                  <?php if ($user['role'] === 'technician'): ?>
                    <?php $skillCount = count($techQualifications[(int)$user['id']] ?? []); ?>
                    <a href="<?= baseUrl('admin/users.php?edit_skills=' . (int)$user['id']) ?>" class="btn btn-outline" style="font-size:.8rem;margin-top:6px;display:inline-block;">
                      <i class="fas fa-tools"></i> Skills (<?= $skillCount ?>)
                    </a>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="subtext">Read only</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
      <div style="display:flex;gap:8px;align-items:center;padding:14px 24px;border-top:1px solid var(--line);flex-wrap:wrap;">
        <?php
          $q = http_build_query(array_filter(['q'=>$search,'role'=>$roleFilter]));
          $base = baseUrl('admin/users.php') . ($q ? "?$q&" : '?');
        ?>
        <a href="<?= $base ?>page=1" class="btn btn-outline" style="font-size:.82rem;">«</a>
        <a href="<?= $base ?>page=<?= max(1,$page-1) ?>" class="btn btn-outline" style="font-size:.82rem;">‹ Prev</a>
        <span style="font-size:.85rem;color:var(--muted);">Page <?= $page ?> of <?= $totalPages ?> (<?= $totalCount ?> users)</span>
        <a href="<?= $base ?>page=<?= min($totalPages,$page+1) ?>" class="btn btn-outline" style="font-size:.82rem;">Next ›</a>
        <a href="<?= $base ?>page=<?= $totalPages ?>" class="btn btn-outline" style="font-size:.82rem;">»</a>
      </div>
    <?php else: ?>
      <div style="padding:10px 24px;border-top:1px solid var(--line);font-size:.83rem;color:var(--muted);">
        <?= $totalCount ?> user<?= $totalCount!==1?'s':'' ?> total
      </div>
    <?php endif; ?>
  <?php else: ?>
    <p class="empty-note">No users found.</p>
  <?php endif; ?>
</section>

<?= authContextScriptTag() ?>
<script>
(function () {
  var roleSelect = document.getElementById('createAccountRole');
  var techSection = document.getElementById('techServicesSection');
  if (!roleSelect || !techSection) return;
  function sync() {
    techSection.hidden = roleSelect.value !== 'technician';
  }
  roleSelect.addEventListener('change', sync);
  sync();
})();
</script>
</main></div></div></body></html>
