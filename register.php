<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';

if (isLoggedIn()) {
    redirect(baseUrl('index.php'));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || $email === '' || strlen($password) < 6) {
        $error = 'Please complete all fields. Password must be at least 6 characters.';
    } elseif (fetchOne("SELECT id FROM users WHERE email = ?", [$email])) {
        $error = 'That email is already registered.';
    } else {
        $stmt = getDB()->prepare("INSERT INTO users (name, email, password, phone, role) VALUES (?, ?, ?, ?, 'customer')");
        $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $phone]);
        $user = fetchOne("SELECT * FROM users WHERE id = ?", [(int)getDB()->lastInsertId()]);
        loginUser($user);
        redirect(baseUrl('my-vehicle.php'));
    }
}

$pageTitle = 'Register - MotoTrack';
require_once __DIR__ . '/includes/header.php';
?>

<section class="auth-section">
  <form class="auth-card" method="post">
    <?= authContextField() ?>
    <span class="eyebrow">Customer access</span>
    <h1>Create your account</h1>
    <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <a class="btn btn-google" href="<?= baseUrl('google-login.php') ?>"><i class="fab fa-google"></i> Continue with Google</a>
    <div class="auth-divider"><span>or</span></div>
    <label>Name<input type="text" name="name" required></label>
    <label>Email<input type="email" name="email" required></label>
    <label>Phone<input type="text" name="phone"></label>
    <label>Password
      <span class="password-field">
        <input type="password" name="password" minlength="6" required>
        <button type="button" class="password-toggle" aria-label="Show password"><i class="fas fa-eye"></i></button>
      </span>
    </label>
    <button class="btn btn-primary" type="submit">Register</button>
    <p>Already have an account? <a href="<?= baseUrl('login.php') ?>">Login</a></p>
  </form>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
