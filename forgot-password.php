<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/mail.php';

if (isLoggedIn()) {
    redirect(baseUrl('index.php'));
}

$message = '';
$devOtp = '';
$flashError = getFlash('auth_error');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $user = fetchOne("SELECT * FROM users WHERE email = ?", [$email]);

    if ($user) {
        $otp = (string)random_int(100000, 999999);
        $stmt = getDB()->prepare(
            "INSERT INTO password_resets (user_id, email, otp_hash, expires_at)
             VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))"
        );
        $stmt->execute([$user['id'], $email, password_hash($otp, PASSWORD_DEFAULT)]);

        $_SESSION['reset_email'][currentAuthContext()] = $email;
        $sent = sendOtpEmail($email, $otp);
        $message = 'If that email exists, we sent a 6-digit OTP code.';
        if (!$sent) {
            $devOtp = $otp;
        }
    } else {
        $message = 'If that email exists, we sent a 6-digit OTP code.';
    }
}

$pageTitle = 'Forgot Password - MotoTrack';
require_once __DIR__ . '/includes/header.php';
?>

<section class="auth-section">
  <form class="auth-card" method="post">
    <?= authContextField() ?>
    <span class="eyebrow">Password help</span>
    <h1>Forgot password</h1>
    <?php if ($flashError): ?><div class="alert error"><?= htmlspecialchars($flashError) ?></div><?php endif; ?>
    <?php if ($message): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($devOtp): ?><div class="alert error">Local mail is not configured. Test OTP: <?= htmlspecialchars($devOtp) ?></div><?php endif; ?>
    <label>Email<input type="email" name="email" required></label>
    <button class="btn btn-primary" type="submit">Send OTP</button>
    <p><a href="<?= baseUrl('verify-otp.php') ?>">I already have a code</a></p>
    <p><a href="<?= baseUrl('login.php') ?>">Back to login</a></p>
  </form>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
