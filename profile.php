<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
requireLogin();

$sessionUser = getCurrentUser();
$userId = (int)$sessionUser['id'];

/** Keep the logged-in session copy in sync after profile edits (mirrors loginUser()). */
function profileSyncSession(string $name, string $email): void {
    $key = currentAuthContext();
    if (isset($_SESSION['auth_contexts'][$key])) {
        $_SESSION['auth_contexts'][$key]['user_name'] = $name;
        $_SESSION['auth_contexts'][$key]['user_email'] = $email;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $account = fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
    $isGoogleAccount = ($account['auth_provider'] ?? 'local') === 'google';

    if ($action === 'update_profile' && $account) {
        $name  = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        // Google accounts keep their Google email — ignore any submitted value
        $email = $isGoogleAccount ? $account['email'] : strtolower(trim($_POST['email'] ?? ''));

        if ($name === '') {
            flashMessage('profile_error', 'Name is required.');
        } elseif (mb_strlen($name) > 100) {
            flashMessage('profile_error', 'Name must be 100 characters or fewer.');
        } elseif (!$isGoogleAccount && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flashMessage('profile_error', 'Please enter a valid email address.');
        } elseif (!$isGoogleAccount && fetchOne("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $userId])) {
            flashMessage('profile_error', 'That email is already used by another account.');
        } elseif ($phone !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
            flashMessage('profile_error', 'Please enter a valid contact number (7-20 digits).');
        } else {
            getDB()->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?")
                ->execute([$name, $email, $phone !== '' ? $phone : null, $userId]);
            profileSyncSession($name, $email);
            flashMessage('profile_success', 'Profile updated successfully.');
        }
        redirect(baseUrl('profile.php'));
    }

    if ($action === 'change_password' && $account) {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($isGoogleAccount) {
            flashMessage('profile_error', 'Google accounts do not use a password on this site.');
        } elseif ($current === '' || $new === '' || $confirm === '') {
            flashMessage('profile_error', 'All password fields are required.');
        } elseif (!password_verify($current, $account['password'])) {
            flashMessage('profile_error', 'Your current password is incorrect.');
        } elseif (strlen($new) < 6) {
            flashMessage('profile_error', 'New password must be at least 6 characters.');
        } elseif ($new !== $confirm) {
            flashMessage('profile_error', 'New password and confirmation do not match.');
        } elseif (password_verify($new, $account['password'])) {
            flashMessage('profile_error', 'New password must be different from your current password.');
        } else {
            getDB()->prepare("UPDATE users SET password = ? WHERE id = ?")
                ->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
            flashMessage('profile_success', 'Password changed successfully.');
        }
        redirect(baseUrl('profile.php'));
    }
}

$account = fetchOne("SELECT id, name, email, phone, auth_provider, created_at FROM users WHERE id = ?", [$userId]);
if (!$account) {
    redirect(baseUrl('logout.php'));
}
$isGoogleAccount = ($account['auth_provider'] ?? 'local') === 'google';

$flash = getFlash('profile_success');
$flashErr = getFlash('profile_error');

$pageTitle = 'My Profile - MotoTrack';
require_once __DIR__ . '/includes/header.php';
?>

<section class="container page-hero">
  <h1>My Profile</h1>
  <p>Manage your account details and keep your login secure.</p>
</section>

<section class="container profile-section">
  <?php if ($flash): ?><div class="alert success"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
  <?php if ($flashErr): ?><div class="alert error"><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>

  <div class="profile-grid <?= $isGoogleAccount ? 'profile-grid--single' : '' ?>">
    <!-- Account information -->
    <div class="form-panel profile-panel">
      <div class="profile-panel-head">
        <span class="profile-panel-icon"><i class="fas fa-user"></i></span>
        <div>
          <h2>Account Information</h2>
          <p class="fine-print">Member since <?= htmlspecialchars(date('F Y', strtotime($account['created_at']))) ?></p>
        </div>
      </div>

      <form method="post">
        <?= authContextField() ?>
        <input type="hidden" name="action" value="update_profile">

        <label>Full name
          <input type="text" name="name" required maxlength="100" value="<?= htmlspecialchars($account['name']) ?>">
        </label>

        <label>Email address
          <input type="email" name="email" <?= $isGoogleAccount ? 'disabled' : 'required' ?> value="<?= htmlspecialchars($account['email']) ?>">
          <?php if ($isGoogleAccount): ?>
            <span class="profile-field-note">
              <i class="fab fa-google"></i> This email is managed by your Google account and cannot be changed.
            </span>
          <?php endif; ?>
        </label>

        <label>Contact number
          <input type="tel" name="phone" value="<?= htmlspecialchars($account['phone'] ?? '') ?>" placeholder="e.g. 09171234567" pattern="[0-9+\-\s()]{7,20}">
        </label>

        <button type="submit" class="btn btn-primary">Save changes</button>
      </form>
    </div>

    <?php if (!$isGoogleAccount): ?>
    <!-- Change password -->
    <div class="form-panel profile-panel">
      <div class="profile-panel-head">
        <span class="profile-panel-icon"><i class="fas fa-lock"></i></span>
        <div>
          <h2>Change Password</h2>
          <p class="fine-print">Use at least 6 characters. You'll stay logged in after changing it.</p>
        </div>
      </div>

      <form method="post">
        <?= authContextField() ?>
        <input type="hidden" name="action" value="change_password">

        <label>Current password
          <input type="password" name="current_password" required autocomplete="current-password">
        </label>

        <label>New password
          <input type="password" name="new_password" required minlength="6" autocomplete="new-password">
        </label>

        <label>Confirm new password
          <input type="password" name="confirm_password" required minlength="6" autocomplete="new-password">
        </label>

        <button type="submit" class="btn btn-primary">Update password</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
