<?php
$pageTitle = 'About - MotoTrack';
require_once __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
  <div class="container">
    <span class="eyebrow">About</span>
    <h1>Motorcycle care built around real shop work.</h1>
    <p>MotoTrack connects inventory, booking, service materials, and customer motorcycle profiles in one web-based system.</p>
  </div>
</section>

<section class="section container two-column">
  <div>
    <span class="eyebrow">Quality over quantity</span>
    <h2>Clear parts, clear services, clear estimates.</h2>
    <p>Customers can browse available parts and accessories, then book maintenance that matches their motorcycle type. Staff can confirm requests and keep inventory aligned with sales and service usage.</p>
    <a class="btn btn-primary" href="<?= baseUrl('book-service.php') ?>">Book a service</a>
  </div>
  <div class="feature-list">
    <div><i class="fas fa-motorcycle"></i><strong>Motorcycle profile</strong><span>Type, brand, model, and cc drive service suggestions.</span></div>
    <div><i class="fas fa-boxes-stacked"></i><strong>Inventory-aware shop</strong><span>Products show stock and deduct after checkout.</span></div>
    <div><i class="fas fa-wrench"></i><strong>Service estimates</strong><span>Labor plus material rules calculate expected cost.</span></div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
