<?php
$pageTitle = 'Users';
require_once __DIR__ . '/../includes/admin-sidebar.php';
require_once __DIR__ . '/../includes/db.php';

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

$users = fetchAllRows(
    "SELECT
        u.*,
        COALESCE(oc.order_count, 0) AS order_count,
        COALESCE(bc.booking_count, 0) AS booking_count
     FROM users u
     LEFT JOIN (
       SELECT user_id, COUNT(*) AS order_count
       FROM orders
       GROUP BY user_id
     ) oc ON oc.user_id = u.id
     LEFT JOIN (
       SELECT user_id, COUNT(*) AS booking_count
       FROM bookings
       GROUP BY user_id
     ) bc ON bc.user_id = u.id
     " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
     ORDER BY u.created_at DESC, u.id DESC",
    $params
);
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
                    <input type="text" name="password" placeholder="Temp password" minlength="6" required>
                    <button type="submit" class="btn btn-outline">Reset</button>
                  </form>
                <?php else: ?>
                  <span class="subtext">Read only</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p class="empty-note">No users found.</p>
  <?php endif; ?>
</section>

</main></div></div></body></html>
