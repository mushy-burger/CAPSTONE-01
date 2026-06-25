<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';

if (isLoggedIn()) {
    redirect(baseUrl('index.php'));
}

$ctx = currentAuthContext();
$resetId = (int)($_SESSION['password_reset_id'][$ctx] ?? 0);
$reset = $resetId ? fetchOne(
    "SELECT * FROM password_resets
     WHERE id = ? AND verified_at IS NOT NULL AND used_at IS NULL AND expires_at >= NOW()",
    [$resetId]
) : null;

if (!$reset) {
    flashMessage('auth_error', 'Please verify your OTP before changing your password.');
    redirect(baseUrl('forgot-password.php'));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        getDB()->beginTransaction();
        try {
            getDB()->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([password_hash($password, PASSWORD_DEFAULT), $reset['user_id']]);
            getDB()->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?")->execute([$reset['id']]);
            unset($_SESSION['password_reset_id'][$ctx], $_SESSION['reset_email'][$ctx]);
            getDB()->commit();
            flashMessage('auth_success', 'Password changed. You can now log in.');
            redirect(baseUrl('login.php'));
        } catch (Throwable $e) {
            getDB()->rollBack();
            $error = 'Password reset failed. Please try again.';
        }
    }
}

$pageTitle = 'Reset Password - MotoTrack';
require_once __DIR__ . '/includes/header.php';
?>

<section class="auth-section">
  <form class="auth-card" method="post">
    <?= authContextField() ?>
    <span class="eyebrow">New password</span>
    <h1>Reset password</h1>
    <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <label>New password
      <span class="password-field">
        <input type="password" name="password" minlength="6" required>
        <button type="button" class="password-toggle" aria-label="Show password"><i class="fas fa-eye"></i></button>
      </span>
    </label>
    <label>Confirm password
      <span class="password-field">
        <input type="password" name="confirm_password" minlength="6" required>
        <button type="button" class="password-toggle" aria-label="Show password"><i class="fas fa-eye"></i></button>
      </span>
    </label>
    <button class="btn btn-primary" type="submit">Change password</button>
  </form>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
