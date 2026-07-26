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
        $targetUser = fetchOne("SELECT role FROM users WHERE id = ?", [$userId]);
        if (($targetUser['role'] ?? '') === 'customer') {
            // Customer accounts are read-only: their role can never be changed from the admin panel.
            flashMessage('users_error', 'Customer accounts cannot have their role changed.');
        } elseif (in_array($role, $roleOptions, true)) {
            getDB()->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$role, $userId]);
            flashMessage('users_success', 'User role updated.');
        } else {
            flashMessage('users_error', 'Invalid user role.');
        }
        redirect(baseUrl('admin/users.php?view=team'));
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

// View tabs: customers vs internal team (staff, technicians, admins)
$view = $_GET['view'] ?? 'customers';
$view = in_array($view, ['customers', 'team'], true) ? $view : 'customers';
if ($view === 'customers') {
    $roleFilter = '';
}

// Customer-view filters (display-level list filtering)
$statusFilter   = $_GET['status'] ?? '';
$statusFilter   = in_array($statusFilter, ['active', 'disabled'], true) ? $statusFilter : '';
$authFilter     = $_GET['auth'] ?? '';
$authFilter     = in_array($authFilter, ['local', 'google'], true) ? $authFilter : '';
$ordersFilter   = $_GET['orders'] ?? '';
$ordersFilter   = in_array($ordersFilter, ['with', 'without'], true) ? $ordersFilter : '';
$bookingsFilter = $_GET['bookings'] ?? '';
$bookingsFilter = in_array($bookingsFilter, ['with', 'without'], true) ? $bookingsFilter : '';
$joinedFrom     = trim($_GET['joined_from'] ?? '');
$joinedTo       = trim($_GET['joined_to'] ?? '');
$sortOption     = $_GET['sort'] ?? 'newest';
$sortOption     = in_array($sortOption, ['newest', 'oldest', 'name_az', 'name_za'], true) ? $sortOption : 'newest';

$activeFilterCount =
    ($statusFilter !== '' ? 1 : 0) +
    ($authFilter !== '' ? 1 : 0) +
    ($ordersFilter !== '' ? 1 : 0) +
    ($bookingsFilter !== '' ? 1 : 0) +
    ($joinedFrom !== '' ? 1 : 0) +
    ($joinedTo !== '' ? 1 : 0) +
    ($sortOption !== 'newest' ? 1 : 0);

$where = [];
$params = [];
if ($view === 'customers') {
    $where[] = "u.role = 'customer'";

    if ($statusFilter !== '') {
        $where[] = 'u.is_active = ?';
        $params[] = $statusFilter === 'active' ? 1 : 0;
    }
    if ($authFilter !== '') {
        $where[] = 'u.auth_provider = ?';
        $params[] = $authFilter;
    }
    if ($ordersFilter === 'with') {
        $where[] = 'EXISTS (SELECT 1 FROM orders fo WHERE fo.user_id = u.id)';
    } elseif ($ordersFilter === 'without') {
        $where[] = 'NOT EXISTS (SELECT 1 FROM orders fo WHERE fo.user_id = u.id)';
    }
    if ($bookingsFilter === 'with') {
        $where[] = 'EXISTS (SELECT 1 FROM bookings fb WHERE fb.user_id = u.id)';
    } elseif ($bookingsFilter === 'without') {
        $where[] = 'NOT EXISTS (SELECT 1 FROM bookings fb WHERE fb.user_id = u.id)';
    }
    if ($joinedFrom !== '') {
        $where[] = 'DATE(u.created_at) >= ?';
        $params[] = $joinedFrom;
    }
    if ($joinedTo !== '') {
        $where[] = 'DATE(u.created_at) <= ?';
        $params[] = $joinedTo;
    }
} else {
    $where[] = "u.role != 'customer'";
}
if ($roleFilter !== '' && $view === 'team' && $roleFilter !== 'customer') {
    $where[] = 'u.role = ?';
    $params[] = $roleFilter;
}

$orderBy = match ($sortOption) {
    'oldest'  => 'u.created_at ASC, u.id ASC',
    'name_az' => 'u.name ASC',
    'name_za' => 'u.name DESC',
    default   => 'u.created_at DESC, u.id DESC',
};
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
        COALESCE(bc.booking_count, 0) AS booking_count,
        COALESCE(tj.jobs_done, 0) AS jobs_done
     FROM users u
     LEFT JOIN (SELECT user_id, COUNT(*) AS order_count FROM orders GROUP BY user_id) oc ON oc.user_id = u.id
     LEFT JOIN (SELECT user_id, COUNT(*) AS booking_count FROM bookings GROUP BY user_id) bc ON bc.user_id = u.id
     LEFT JOIN (SELECT technician_id, COUNT(*) AS jobs_done FROM bookings WHERE status = 'completed' AND technician_id IS NOT NULL GROUP BY technician_id) tj ON tj.technician_id = u.id
     " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
     ORDER BY $orderBy
     LIMIT $perPage OFFSET $offset",
    $params
);

$allServices = fetchAllRows("SELECT id, name FROM service_types ORDER BY name");
$techQualifications = techQualificationMap();

$editSkillsId = (int)($_GET['edit_skills'] ?? 0);
$editSkillsUser = $editSkillsId
    ? fetchOne("SELECT id, name FROM users WHERE id = ? AND role = 'technician'", [$editSkillsId])
    : null;

function userInitials(string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $parts = array_values(array_filter($parts, static fn(string $part): bool => $part !== ''));
    if (!$parts) {
        return 'U';
    }

    $first = strtoupper(substr($parts[0], 0, 1));
    $last = count($parts) > 1 ? strtoupper(substr($parts[count($parts) - 1], 0, 1)) : strtoupper(substr($parts[0], 1, 1));
    return $first . $last;
}
?>

<section class="usrx-page">
  <div class="usrx-hero">
    <div class="usrx-hero-copy">
      <div class="usrx-hero-icon"><i class="fas fa-users-gear"></i></div>
      <div>
        <h1>Users</h1>
        <p>Manage customer, staff, and technician accounts, roles, access, and skills.</p>
      </div>
    </div>
    <form method="get" class="usrx-toolbar" style="flex-wrap:wrap;">
      <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
      <label class="usrx-filter usrx-filter--search">
        <i class="fas fa-magnifying-glass"></i>
        <input type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search users">
      </label>
      <?php if ($view === 'team'): ?>
        <label class="usrx-filter">
          <i class="fas fa-user-shield"></i>
          <select name="role">
            <option value="">All team roles</option>
            <?php foreach (['admin', 'staff', 'technician'] as $role): ?>
              <option value="<?= htmlspecialchars($role) ?>" <?= $roleFilter === $role ? 'selected' : '' ?>>
                <?= htmlspecialchars(ucfirst($role)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <button type="submit" class="usrx-btn usrx-btn--dark"><i class="fas fa-sliders"></i> Filter</button>
        <?php if ($search || $roleFilter): ?><a href="<?= baseUrl('admin/users.php?view=team') ?>" class="usrx-btn usrx-btn--ghost"><i class="fas fa-rotate-left"></i> Reset</a><?php endif; ?>
      <?php else: ?>
        <label class="usrx-filter" title="Account status">
          <i class="fas fa-toggle-on"></i>
          <select name="status">
            <option value="">Any status</option>
            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="disabled" <?= $statusFilter === 'disabled' ? 'selected' : '' ?>>Disabled</option>
          </select>
        </label>
        <label class="usrx-filter" title="Sign-in method">
          <i class="fas fa-key"></i>
          <select name="auth">
            <option value="">Any sign-in</option>
            <option value="local" <?= $authFilter === 'local' ? 'selected' : '' ?>>Password</option>
            <option value="google" <?= $authFilter === 'google' ? 'selected' : '' ?>>Google</option>
          </select>
        </label>
        <label class="usrx-filter" title="Shop orders">
          <i class="fas fa-bag-shopping"></i>
          <select name="orders">
            <option value="">Orders: any</option>
            <option value="with" <?= $ordersFilter === 'with' ? 'selected' : '' ?>>Has orders</option>
            <option value="without" <?= $ordersFilter === 'without' ? 'selected' : '' ?>>No orders</option>
          </select>
        </label>
        <label class="usrx-filter" title="Service bookings">
          <i class="fas fa-clipboard-list"></i>
          <select name="bookings">
            <option value="">Bookings: any</option>
            <option value="with" <?= $bookingsFilter === 'with' ? 'selected' : '' ?>>Has bookings</option>
            <option value="without" <?= $bookingsFilter === 'without' ? 'selected' : '' ?>>No bookings</option>
          </select>
        </label>
        <label class="usrx-filter" title="Joined from">
          <i class="fas fa-calendar-day"></i>
          <input type="date" name="joined_from" value="<?= htmlspecialchars($joinedFrom) ?>">
        </label>
        <label class="usrx-filter" title="Joined until">
          <i class="fas fa-calendar-check"></i>
          <input type="date" name="joined_to" value="<?= htmlspecialchars($joinedTo) ?>">
        </label>
        <label class="usrx-filter" title="Sort by">
          <i class="fas fa-arrow-down-wide-short"></i>
          <select name="sort">
            <option value="newest" <?= $sortOption === 'newest' ? 'selected' : '' ?>>Newest first</option>
            <option value="oldest" <?= $sortOption === 'oldest' ? 'selected' : '' ?>>Oldest first</option>
            <option value="name_az" <?= $sortOption === 'name_az' ? 'selected' : '' ?>>Name A–Z</option>
            <option value="name_za" <?= $sortOption === 'name_za' ? 'selected' : '' ?>>Name Z–A</option>
          </select>
        </label>
        <button type="submit" class="usrx-btn usrx-btn--dark">
          <i class="fas fa-sliders"></i> Apply Filters
          <?php if ($activeFilterCount > 0): ?>
            <span style="margin-left:6px;min-width:20px;height:20px;display:inline-grid;place-items:center;border-radius:999px;background:var(--accent);color:#fff;font-size:.7rem;font-weight:900;"><?= $activeFilterCount ?></span>
          <?php endif; ?>
        </button>
        <?php if ($search || $activeFilterCount > 0): ?>
          <a href="<?= baseUrl('admin/users.php?view=customers') ?>" class="usrx-btn usrx-btn--ghost"><i class="fas fa-rotate-left"></i> Clear Filters</a>
        <?php endif; ?>
      <?php endif; ?>
    </form>
  </div>

  <!-- Customers / Team tabs -->
  <div class="mtx-seg" role="tablist" aria-label="User views" style="background:#fff;border:1px solid var(--line);">
    <a href="<?= baseUrl('admin/users.php?view=customers') ?>" class="<?= $view === 'customers' ? 'active' : '' ?>" role="tab"><i class="fas fa-users" style="margin-right:7px;"></i>Customers</a>
    <a href="<?= baseUrl('admin/users.php?view=team') ?>" class="<?= $view === 'team' ? 'active' : '' ?>" role="tab"><i class="fas fa-user-gear" style="margin-right:7px;"></i>Staff &amp; Team</a>
  </div>

  <?php if ($view === 'team'): ?>
  <section class="usrx-card usrx-create-card">
    <div class="usrx-card-head">
      <div>
        <span class="usrx-kicker">Account creation</span>
        <h2>Create Staff or Technician Account</h2>
        <p>Add internal team members without affecting customer accounts.</p>
      </div>
      <div class="usrx-card-mark"><i class="fas fa-user-plus"></i></div>
    </div>
    <form method="post" class="usrx-create-form">
      <?= authContextField() ?>
      <input type="hidden" name="action" value="create_account">
      <label class="usrx-field">
        <span>Full Name</span>
        <input type="text" name="new_name" placeholder="Juan Dela Cruz" required>
      </label>
      <label class="usrx-field">
        <span>Email Address</span>
        <input type="email" name="new_email" placeholder="name@mototrack.com" required>
      </label>
      <label class="usrx-field">
        <span>Password</span>
        <input type="password" name="new_password" placeholder="Minimum 6 characters" required minlength="6">
      </label>
      <label class="usrx-field usrx-field--role">
        <span>Role</span>
        <select name="new_role" id="createAccountRole" required>
          <option value="">Select role</option>
          <option value="staff">Staff</option>
          <option value="technician">Technician</option>
        </select>
      </label>
      <button type="submit" class="usrx-btn usrx-btn--primary usrx-create-submit">
        <i class="fas fa-plus"></i> Create Account
      </button>

      <div id="techServicesSection" class="usrx-skill-panel" hidden>
        <div class="usrx-skill-panel-head">
          <strong><i class="fas fa-screwdriver-wrench"></i> Qualified Services</strong>
          <span>Used by auto-assignment when this technician receives jobs.</span>
        </div>
        <?php if ($allServices): ?>
          <div class="usrx-skill-grid">
            <?php foreach ($allServices as $svc): ?>
              <label class="usrx-skill-chip">
                <input type="checkbox" name="tech_service_ids[]" value="<?= (int)$svc['id'] ?>">
                <span><i class="fas fa-check"></i><?= htmlspecialchars($svc['name']) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="usrx-empty-inline">No services exist yet. Add services first in Settings &gt; Compatible Services.</div>
        <?php endif; ?>
      </div>
    </form>
  </section>
  <?php endif; ?>

  <?php if ($editSkillsUser): ?>
  <section class="usrx-card usrx-skills-card">
    <div class="usrx-card-head">
      <div>
        <span class="usrx-kicker">Technician skills</span>
        <h2>Edit Skills &mdash; <?= htmlspecialchars($editSkillsUser['name']) ?></h2>
        <p>Select the services this technician can perform.</p>
      </div>
      <div class="usrx-card-mark"><i class="fas fa-screwdriver-wrench"></i></div>
    </div>
    <form method="post" class="usrx-skills-form">
      <?= authContextField() ?>
      <input type="hidden" name="action" value="save_tech_services">
      <input type="hidden" name="user_id" value="<?= (int)$editSkillsUser['id'] ?>">
      <?php $currentSkills = $techQualifications[(int)$editSkillsUser['id']] ?? []; ?>
      <?php if ($allServices): ?>
        <div class="usrx-skill-grid">
          <?php foreach ($allServices as $svc): ?>
            <label class="usrx-skill-chip">
              <input type="checkbox" name="tech_service_ids[]" value="<?= (int)$svc['id'] ?>"
                     <?= isset($currentSkills[(int)$svc['id']]) ? 'checked' : '' ?>>
              <span><i class="fas fa-check"></i><?= htmlspecialchars($svc['name']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <div class="usrx-panel-actions">
          <button type="submit" class="usrx-btn usrx-btn--primary"><i class="fas fa-floppy-disk"></i> Save Skills</button>
          <a href="<?= baseUrl('admin/users.php') ?>" class="usrx-btn usrx-btn--ghost"><i class="fas fa-xmark"></i> Cancel</a>
        </div>
      <?php else: ?>
        <div class="usrx-empty-inline">No services exist yet. Add services first in Settings &gt; Compatible Services.</div>
      <?php endif; ?>
    </form>
  </section>
  <?php endif; ?>

  <?php if (!$canManageUsers): ?>
    <div class="alert error">Only administrators can change roles, reset passwords, or disable accounts.</div>
  <?php endif; ?>
  <?php if ($flash): ?><div class="alert success"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
  <?php if ($flashErr): ?><div class="alert error"><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>

  <section class="usrx-card usrx-list-card">
    <div class="usrx-card-head">
      <div>
        <span class="usrx-kicker"><?= $view === 'customers' ? 'Customer directory' : 'Internal team' ?></span>
        <h2><?= $view === 'customers' ? 'Customers' : 'Staff, Technicians & Admins' ?></h2>
        <p>
          <?= $totalCount ?> account<?= $totalCount === 1 ? '' : 's' ?><?= $roleFilter ? ' filtered by ' . htmlspecialchars($roleFilter) : '' ?><?= $activeFilterCount > 0 ? ' · ' . $activeFilterCount . ' filter' . ($activeFilterCount === 1 ? '' : 's') . ' active' : '' ?><?= $search !== '' ? ' · searching "' . htmlspecialchars($search) . '"' : '' ?>
        </p>
      </div>
    </div>

    <?php if ($users): ?>
      <div class="usrx-table-wrap">
        <table class="usrx-table">
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
              <?php
                $isSelf = (int)$user['id'] === (int)$currentUser['id'];
                $active = (int)($user['is_active'] ?? 1) === 1;
                $roleColors = ['admin' => '#d71920', 'staff' => '#7c3aed', 'technician' => '#2563eb', 'customer' => '#15803d'];
                $roleColor = $roleColors[$user['role']] ?? '#6b7280';
              ?>
              <tr>
                <td>
                  <div class="usrx-user-cell">
                    <div class="usrx-avatar"><?= htmlspecialchars(userInitials($user['name'])) ?></div>
                    <div>
                      <strong><?= htmlspecialchars($user['name']) ?></strong>
                      <span><?= htmlspecialchars($user['email']) ?></span>
                      <?php if ($user['phone']): ?><small><i class="fas fa-phone"></i> <?= htmlspecialchars($user['phone']) ?></small><?php endif; ?>
                    </div>
                  </div>
                </td>
                <td>
                  <?php if ($canManageUsers && !$isSelf && $user['role'] !== 'customer'): ?>
                    <form method="post" class="usrx-role-form">
                      <?= authContextField() ?>
                      <input type="hidden" name="action" value="update_role">
                      <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                      <select name="role" data-original-role="<?= htmlspecialchars($user['role']) ?>">
                        <?php foreach ($roleOptions as $role): ?>
                          <option value="<?= htmlspecialchars($role) ?>" <?= $user['role'] === $role ? 'selected' : '' ?>>
                            <?= htmlspecialchars(ucfirst($role)) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                      <button type="submit" class="usrx-btn usrx-btn--tiny usrx-btn--ghost usrx-role-save">Save</button>
                    </form>
                  <?php else: ?>
                    <span class="usrx-role-badge" style="--role-color: <?= $roleColor ?>;"><?= htmlspecialchars(ucfirst($user['role'])) ?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="usrx-status-stack">
                    <span class="usrx-status-badge <?= $active ? 'is-active' : 'is-disabled' ?>">
                      <i class="fas fa-circle"></i><?= $active ? 'Active' : 'Disabled' ?>
                    </span>
                    <?php if ($user['role'] === 'technician'): ?>
                      <?php $isReady = ($user['availability_status'] ?? 'off_duty') === 'ready'; ?>
                      <span class="usrx-status-badge <?= $isReady ? 'is-ready' : 'is-off' ?>">
                        <i class="fas fa-location-dot"></i><?= $isReady ? 'Ready / On Site' : 'Off Duty' ?>
                      </span>
                    <?php endif; ?>
                  </div>
                  <?php if ($canManageUsers && !$isSelf): ?>
                    <form method="post" class="usrx-toggle-form">
                      <?= authContextField() ?>
                      <input type="hidden" name="action" value="toggle_status">
                      <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                      <label class="usrx-switch">
                        <input type="checkbox" name="is_active" value="1" <?= $active ? 'checked' : '' ?> onchange="this.form.submit()">
                        <span></span>
                        <em>Enabled</em>
                      </label>
                    </form>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="usrx-activity">
                    <?php if ($user['role'] === 'customer'): ?>
                      <span><i class="fas fa-bag-shopping"></i><?= (int)$user['order_count'] ?> order<?= (int)$user['order_count'] === 1 ? '' : 's' ?></span>
                      <span><i class="fas fa-clipboard-list"></i><?= (int)$user['booking_count'] ?> service request<?= (int)$user['booking_count'] === 1 ? '' : 's' ?></span>
                    <?php elseif ($user['role'] === 'technician'): ?>
                      <span><i class="fas fa-flag-checkered"></i><?= (int)$user['jobs_done'] ?> completed job<?= (int)$user['jobs_done'] === 1 ? '' : 's' ?></span>
                      <span><i class="fas fa-screwdriver-wrench"></i><?= count($techQualifications[(int)$user['id']] ?? []) ?> qualified service<?= count($techQualifications[(int)$user['id']] ?? []) === 1 ? '' : 's' ?></span>
                    <?php else: ?>
                      <span><i class="fas fa-id-badge"></i>Internal account</span>
                    <?php endif; ?>
                  </div>
                </td>
                <td><span class="usrx-date"><?= htmlspecialchars(date('M j, Y', strtotime($user['created_at']))) ?></span></td>
                <td>
                  <?php if ($canManageUsers): ?>
                    <div class="usrx-manage">
                      <form method="post" class="usrx-reset-form">
                        <?= authContextField() ?>
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                        <input type="password" name="password" placeholder="New password" minlength="6" required>
                        <button type="submit" class="usrx-icon-btn" title="Reset password" aria-label="Reset password"><i class="fas fa-key"></i></button>
                      </form>
                      <?php if ($user['role'] === 'technician'): ?>
                        <?php $skillCount = count($techQualifications[(int)$user['id']] ?? []); ?>
                        <a href="<?= baseUrl('admin/users.php?view=team&edit_skills=' . (int)$user['id']) ?>" class="usrx-skill-link">
                          <i class="fas fa-screwdriver-wrench"></i> Manage Skills <strong><?= $skillCount ?></strong>
                        </a>
                      <?php endif; ?>
                    </div>
                  <?php else: ?>
                    <span class="subtext">Read only</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if ($totalPages > 1): ?>
        <div class="usrx-pagination">
          <?php
            $q = http_build_query(array_filter([
                'view' => $view,
                'q' => $search,
                'role' => $roleFilter,
                'status' => $statusFilter,
                'auth' => $authFilter,
                'orders' => $ordersFilter,
                'bookings' => $bookingsFilter,
                'joined_from' => $joinedFrom,
                'joined_to' => $joinedTo,
                'sort' => $sortOption !== 'newest' ? $sortOption : '',
            ], static fn($value): bool => $value !== ''));
            $base = baseUrl('admin/users.php') . ($q ? "?$q&" : '?');
          ?>
          <a href="<?= $base ?>page=1" class="usrx-btn usrx-btn--ghost"><i class="fas fa-angles-left"></i></a>
          <a href="<?= $base ?>page=<?= max(1,$page-1) ?>" class="usrx-btn usrx-btn--ghost"><i class="fas fa-angle-left"></i> Prev</a>
          <span>Page <?= $page ?> of <?= $totalPages ?> (<?= $totalCount ?> users)</span>
          <a href="<?= $base ?>page=<?= min($totalPages,$page+1) ?>" class="usrx-btn usrx-btn--ghost">Next <i class="fas fa-angle-right"></i></a>
          <a href="<?= $base ?>page=<?= $totalPages ?>" class="usrx-btn usrx-btn--ghost"><i class="fas fa-angles-right"></i></a>
        </div>
      <?php else: ?>
        <div class="usrx-total">
          <?= $totalCount ?> user<?= $totalCount!==1?'s':'' ?> total
        </div>
      <?php endif; ?>
    <?php else: ?>
      <div class="usrx-empty">
        <div class="usrx-empty-icon"><i class="fas fa-user-magnifying-glass"></i></div>
        <h3>No users found</h3>
        <p>No accounts match the current search and filters.</p>
        <?php if ($search || $roleFilter || $activeFilterCount > 0): ?>
          <a href="<?= baseUrl('admin/users.php?view=' . $view) ?>" class="usrx-btn usrx-btn--ghost"><i class="fas fa-rotate-left"></i> Clear Filters</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </section>
</section>

<?= authContextScriptTag() ?>
<script>
(function () {
  var roleSelect = document.getElementById('createAccountRole');
  var techSection = document.getElementById('techServicesSection');
  if (roleSelect && techSection) {
    function sync() {
      techSection.hidden = roleSelect.value !== 'technician';
    }
    roleSelect.addEventListener('change', sync);
    sync();
  }

  document.querySelectorAll('.usrx-role-form').forEach(function (form) {
    form.classList.add('is-enhanced');
    var select = form.querySelector('select[name="role"]');
    if (!select) return;
    function updateDirtyState() {
      form.classList.toggle('is-dirty', select.value !== select.dataset.originalRole);
    }
    select.addEventListener('change', updateDirtyState);
    updateDirtyState();
  });
})();
</script>
</main></div></div></body></html>
