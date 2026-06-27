<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';

$sent  = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? 'General Inquiry');
    $message = trim($_POST['message'] ?? '');

    if ($name === '' || $email === '' || $message === '') {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Save message to contact_messages table (create if not exists)
        try {
            getDB()->exec("CREATE TABLE IF NOT EXISTS contact_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                email VARCHAR(180) NOT NULL,
                subject VARCHAR(200) NOT NULL DEFAULT 'General Inquiry',
                message TEXT NOT NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            getDB()->prepare(
                "INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)"
            )->execute([$name, $email, $subject, $message]);
            $sent = true;
        } catch (Throwable $e) {
            $error = 'Could not save your message. Please try again.';
        }
    }
}

$pageTitle = 'Contact - MotoTrack';
require_once __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
  <div class="container">
    <span class="eyebrow">Contact Us</span>
    <h1>Need help with parts or service?</h1>
    <p>Send us a message and our team will get back to you shortly.</p>
  </div>
</section>

<section class="section container form-layout">
  <form class="form-panel" method="post" id="contactForm">
    <?= authContextField() ?>
    <h2>Send a Message</h2>

    <?php if ($sent): ?>
      <div class="alert success">
        ✅ Message sent! We'll get back to you at <strong><?= htmlspecialchars($email) ?></strong> soon.
      </div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!$sent): ?>
      <label>Your Name <input type="text" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? ($currentUser['name'] ?? '')) ?>"></label>
      <label>Email Address <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? ($currentUser['email'] ?? '')) ?>"></label>
      <label>Subject
        <select name="subject">
          <option value="General Inquiry">General Inquiry</option>
          <option value="Parts Availability">Parts Availability</option>
          <option value="Service Booking Help">Service Booking Help</option>
          <option value="Order Issue">Order Issue</option>
          <option value="Feedback">Feedback</option>
        </select>
      </label>
      <label>Message <textarea name="message" rows="6" required placeholder="Describe your concern or question..."><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea></label>
      <button class="btn btn-primary" type="submit">Send Message</button>
    <?php else: ?>
      <a class="btn btn-outline" href="<?= baseUrl('contact.php') ?>">Send Another Message</a>
      <a class="btn btn-primary" href="<?= baseUrl('index.php') ?>">Back to Home</a>
    <?php endif; ?>
  </form>

  <aside class="summary-box">
    <h2>Shop Details</h2>
    <div><span><i class="fas fa-phone" style="color:#d71920;width:18px;"></i> Phone</span><strong>0900 500 1234</strong></div>
    <div><span><i class="fas fa-envelope" style="color:#d71920;width:18px;"></i> Email</span><strong>company@mototrack.com</strong></div>
    <div><span><i class="fas fa-map-marker-alt" style="color:#d71920;width:18px;"></i> Location</span><strong>Bambang City</strong></div>
    <div><span><i class="fas fa-clock" style="color:#d71920;width:18px;"></i> Hours</span><strong>Mon – Sun, 8 AM – 7 PM</strong></div>
    <hr style="border:none;border-top:1px solid var(--line);margin:14px 0;">
    <p style="font-size:.85rem;color:var(--muted);">You can also book a service appointment directly through our booking system.</p>
    <a class="btn btn-primary" href="<?= baseUrl('book-service.php') ?>" style="width:100%;text-align:center;margin-top:6px;">Book a Service</a>
  </aside>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
