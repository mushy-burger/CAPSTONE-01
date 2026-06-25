<?php
$pageTitle = 'Contact - MotoTrack';
require_once __DIR__ . '/includes/header.php';
$sent = $_SERVER['REQUEST_METHOD'] === 'POST';
?>

<section class="page-hero">
  <div class="container">
    <span class="eyebrow">Contact Us</span>
    <h1>Need help with parts or service?</h1>
    <p>Send a message or visit the shop during business hours.</p>
  </div>
</section>

<section class="section container form-layout">
  <form class="form-panel" method="post">
    <?= authContextField() ?>
    <h2>Message MotoTrack</h2>
    <?php if ($sent): ?><div class="alert success">Message received. The shop team will contact you soon.</div><?php endif; ?>
    <label>Name<input type="text" name="name" required></label>
    <label>Email<input type="email" name="email" required></label>
    <label>Message<textarea name="message" rows="5" required></textarea></label>
    <button class="btn btn-primary" type="submit">Send message</button>
  </form>
  <aside class="summary-box">
    <h2>Shop Details</h2>
    <div><span>Phone</span><strong>0900 500 1234</strong></div>
    <div><span>Email</span><strong>company@mototrack.com</strong></div>
    <div><span>Location</span><strong>Bambang City</strong></div>
    <div><span>Hours</span><strong>Mon - Sun, 8 am - 7 pm</strong></div>
  </aside>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
