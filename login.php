<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';

if (isLoggedIn()) {
    redirect(baseUrl('index.php'));
}

$error = '';
$success = getFlash('auth_success');
$flashError = getFlash('auth_error');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $user = fetchOne("SELECT * FROM users WHERE email = ?", [$email]);

    if ($user && password_verify($password, $user['password'])) {
        loginUser($user);
        redirect(in_array($user['role'], ['admin', 'staff'], true) ? baseUrl('admin/index.php') : baseUrl('index.php'));
    }
    $error = 'Invalid email or password.';
}

$pageTitle = 'Login - MotoTrack';
require_once __DIR__ . '/includes/header.php';
?>

<section class="auth-section">
  <form class="auth-card" method="post">
    <?= authContextField() ?>
    <span class="eyebrow">Welcome back</span>
    <h1>Login to MotoTrack</h1>
    <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($flashError): ?><div class="alert error"><?= htmlspecialchars($flashError) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <a class="btn btn-google" href="<?= baseUrl('google-login.php') ?>"><i class="fab fa-google"></i> Continue with Google</a>
    <div class="auth-divider"><span>or</span></div>
    <label>Email<input type="email" name="email" required></label>
    <label>Password
      <span class="password-field">
        <input type="password" name="password" required>
        <button type="button" class="password-toggle" aria-label="Show password"><i class="fas fa-eye"></i></button>
      </span>
    </label>
    <button class="btn btn-primary" type="submit">Login</button>
    <p><a href="<?= baseUrl('forgot-password.php') ?>">Forgot password?</a></p>
    <p>New customer? <a href="<?= baseUrl('register.php') ?>">Create an account</a></p>
  </form>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
