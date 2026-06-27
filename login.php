<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';

if (isLoggedIn()) {
    redirect(baseUrl('index.php'));
}

$error      = '';
$success    = getFlash('auth_success');
$flashError = getFlash('auth_error');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember_me']);
    $user     = fetchOne("SELECT * FROM users WHERE email = ?", [$email]);

    if ($user && (int)($user['is_active'] ?? 1) !== 1) {
        $error = 'This account has been disabled. Please contact the shop.';
    } elseif ($user && password_verify($password, $user['password'])) {
        loginUser($user);
        if ($remember) {
            $params = session_get_cookie_params();
            setcookie(session_name(), session_id(), [
                'expires'  => time() + (86400 * 30),
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        $destinations = [
            'admin'      => baseUrl('admin/index.php'),
            'staff'      => baseUrl('staff/index.php'),
            'technician' => baseUrl('tech/index.php'),
        ];
        redirect($destinations[$user['role']] ?? baseUrl('index.php'));
    } else {
        $error = 'Invalid email or password.';
    }
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
    <label class="remember-me-label" style="display:flex;align-items:center;gap:8px;font-size:.88rem;font-weight:600;cursor:pointer;margin-top:4px;">
      <input type="checkbox" name="remember_me" value="1"> Remember me for 30 days
    </label>
    <p><a href="<?= baseUrl('forgot-password.php') ?>">Forgot password?</a></p>
    <p>New customer? <a href="<?= baseUrl('register.php') ?>">Create an account</a></p>
  </form>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
