<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';

if (isLoggedIn()) {
    redirect(baseUrl('index.php'));
}

$ctx = currentAuthContext();
$email = strtolower(trim($_POST['email'] ?? $_GET['email'] ?? ($_SESSION['reset_email'][$ctx] ?? '')));
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = trim($_POST['otp'] ?? '');
    $reset = fetchOne(
        "SELECT * FROM password_resets
         WHERE email = ? AND used_at IS NULL AND expires_at >= NOW()
         ORDER BY id DESC LIMIT 1",
        [$email]
    );

    if ($reset && password_verify($otp, $reset['otp_hash'])) {
        getDB()->prepare("UPDATE password_resets SET verified_at = NOW() WHERE id = ?")->execute([$reset['id']]);
        $_SESSION['password_reset_id'][$ctx] = (int)$reset['id'];
        $_SESSION['reset_email'][$ctx] = $email;
        redirect(baseUrl('reset-password.php'));
    }

    $error = 'Invalid or expired OTP code.';
}

$pageTitle = 'Verify OTP - MotoTrack';
require_once __DIR__ . '/includes/header.php';
?>

<section class="auth-section">
  <form class="auth-card" method="post">
    <?= authContextField() ?>
    <span class="eyebrow">Check your email</span>
    <h1>Enter OTP</h1>
    <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <label>Email<input type="email" name="email" value="<?= htmlspecialchars($email) ?>" required></label>
    <label>OTP code<input type="text" name="otp" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" required></label>
    <button class="btn btn-primary" type="submit">Verify code</button>
    <p><a href="<?= baseUrl('forgot-password.php') ?>">Send a new code</a></p>
  </form>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
