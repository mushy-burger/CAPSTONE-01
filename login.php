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
$next       = safeLocalPath($_POST['next'] ?? $_GET['next'] ?? null);

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
            'qa'         => baseUrl('qa/index.php'),
        ];
        // Staff-type roles go to their dashboards; customers return to the
        // page they were headed to (if any) instead of the homepage.
        redirect($destinations[$user['role']] ?? ($next ?? baseUrl('index.php')));
    } else {
        $error = 'Invalid email or password.';
    }
}

$pageTitle = 'Login - MotoTrack';
require_once __DIR__ . '/includes/header.php';
?>

<section class="auth-section">
  <div class="auth-layout">
  <?php require __DIR__ . '/includes/auth-aside.php'; ?>
  <form class="auth-card" method="post" data-validate>
    <?= authContextField() ?>
    <?php if ($next !== null): ?><input type="hidden" name="next" value="<?= htmlspecialchars($next, ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
    <span class="eyebrow">Welcome back</span>
    <h1>Login to MotoTrack</h1>
    <?php
      if ($next !== null) {
          $loginReason = 'to continue';
          if (stripos($next, 'cart.php') !== false)            $loginReason = 'to view your cart';
          elseif (stripos($next, 'checkout') !== false)        $loginReason = 'to check out';
          elseif (stripos($next, 'book-service') !== false)    $loginReason = 'to book a service';
          elseif (stripos($next, 'booking-deposit') !== false) $loginReason = 'to pay your booking deposit';
          elseif (stripos($next, 'my-vehicle') !== false)      $loginReason = 'to manage your motorcycles';
          elseif (stripos($next, 'profile') !== false)         $loginReason = 'to open your profile';
          echo '<div class="alert info">Please log in ' . htmlspecialchars($loginReason) . '. You\'ll be taken straight there.</div>';
      }
    ?>
    <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($flashError): ?><div class="alert error"><?= htmlspecialchars($flashError) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php
      // baseUrl() may already append ?ctx=... for the multi-tab auth context,
      // so choose the query separator instead of hard-coding "?" (a second "?"
      // would fold `next` into the ctx value and lose it).
      $googleUrl = baseUrl('google-login.php');
      if ($next !== null) {
          $googleUrl .= (strpos($googleUrl, '?') !== false ? '&' : '?') . 'next=' . rawurlencode($next);
      }
    ?>
    <a class="btn btn-google" href="<?= htmlspecialchars($googleUrl, ENT_QUOTES, 'UTF-8') ?>"><i class="fab fa-google"></i> Continue with Google</a>
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
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
