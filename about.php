<?php
$pageTitle = 'About MotoTrack - Motorcycle Parts & Service';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db.php';

// Live stats
$totalCustomers  = (int)(fetchOne("SELECT COUNT(*) AS n FROM users WHERE role='customer'")['n'] ?? 0);
$totalBookings   = (int)(fetchOne("SELECT COUNT(*) AS n FROM bookings WHERE status='completed'")['n'] ?? 0);
$totalProducts   = (int)(fetchOne("SELECT COUNT(*) AS n FROM products WHERE status != 'out_of_stock'")['n'] ?? 0);
?>

<section class="page-hero">
  <div class="container">
    <span class="eyebrow">About MotoTrack</span>
    <h1>Motorcycle care built around real shop work.</h1>
    <p>MotoTrack connects inventory, booking, service materials, and customer motorcycle profiles in one modern system.</p>
  </div>
</section>

<!-- Stats Bar -->
<section class="container about-stats" aria-label="MotoTrack in numbers">
  <div class="about-stat">
    <strong><?= number_format($totalCustomers) ?>+</strong>
    <span>Registered Customers</span>
  </div>
  <div class="about-stat">
    <strong><?= number_format($totalBookings) ?>+</strong>
    <span>Completed Services</span>
  </div>
  <div class="about-stat">
    <strong><?= number_format($totalProducts) ?>+</strong>
    <span>Products Available</span>
  </div>
</section>

<!-- What We Do -->
<section class="section container about-intro">
  <div class="about-copy">
    <span class="eyebrow">What We Do</span>
    <h2 style="color:#fff;margin:14px 0 0;">Clear parts, clear services, clear estimates.</h2>
    <p>Customers can browse available parts and accessories, then book maintenance matched to their motorcycle type. Staff confirm requests, assign certified technicians, and track inventory aligned with sales and service usage.</p>
    <p>Technicians receive their job queue digitally — no paperwork — and can add notes directly to each job as they work.</p>
    <div class="about-actions" style="display:flex;gap:10px;flex-wrap:wrap;margin-top:18px;">
      <a class="btn btn-primary" href="<?= baseUrl('book-service.php') ?>">Book a Service</a>
      <a class="btn btn-outline" href="<?= baseUrl('shop.php') ?>">Browse Parts</a>
    </div>
  </div>
  <div class="feature-list about-features">
    <div><i class="fas fa-motorcycle"></i><strong>Motorcycle Profile</strong><span>Type, brand, model, and CC drive service suggestions and pricing.</span></div>
    <div><i class="fas fa-boxes-stacked"></i><strong>Inventory-Aware Shop</strong><span>Products show live stock and auto-deduct after checkout or service.</span></div>
    <div><i class="fas fa-wrench"></i><strong>Service Estimates</strong><span>Labor plus material rules calculate expected cost before you book.</span></div>
    <div><i class="fas fa-user-cog"></i><strong>Technician Assignment</strong><span>Staff assign specific technicians to each job for accountability.</span></div>
    <div><i class="fas fa-bell"></i><strong>Real-Time Notifications</strong><span>Staff and technicians get instant alerts on new bookings and assignments.</span></div>
    <div><i class="fas fa-chart-bar"></i><strong>Business Analytics</strong><span>Admins track revenue, top products, service volume, and inventory risk.</span></div>
  </div>
</section>

<!-- How It Works -->
<section class="container about-band">
  <div class="section-heading" style="margin-bottom:28px;">
    <span class="eyebrow">How It Works</span>
    <h2>From booking to completion in 4 steps.</h2>
  </div>
  <div class="about-steps">
    <?php
      $steps = [
        ['icon'=>'fa-calendar-plus',  'color'=>'#4f8df9', 'step'=>'1', 'title'=>'Customer Books', 'desc'=>'Choose your motorcycle, select services, pick a date and time online.'],
        ['icon'=>'fa-user-check',     'color'=>'#e8b93c', 'step'=>'2', 'title'=>'Staff Confirms', 'desc'=>'Staff reviews the request and assigns a certified technician.'],
        ['icon'=>'fa-tools',          'color'=>'#b18cff', 'step'=>'3', 'title'=>'Tech Services',  'desc'=>'Technician receives the job, starts work, and logs progress notes.'],
        ['icon'=>'fa-flag-checkered', 'color'=>'#2fbf71', 'step'=>'4', 'title'=>'Job Complete',   'desc'=>'Customer is notified, stock is updated, service record is saved.'],
      ];
      foreach ($steps as $s):
    ?>
      <div class="about-step" style="--step-color:<?= $s['color'] ?>;">
        <div class="about-step-icon"><i class="fas <?= $s['icon'] ?>"></i></div>
        <div class="about-step-num">STEP <?= $s['step'] ?></div>
        <strong><?= $s['title'] ?></strong>
        <p><?= $s['desc'] ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- CTA (auth-aware) -->
<section class="section container" style="padding-top:24px;">
  <div class="about-cta">
    <?php if (!$currentUser): ?>
      <span class="eyebrow">Ready to Start?</span>
      <h2>Your motorcycle deserves the best care.</h2>
      <p>Register for free, add your motorcycle profile, and book your first service in minutes.</p>
      <div class="about-cta-actions">
        <a class="btn btn-primary" href="<?= baseUrl('register.php') ?>">Create Free Account</a>
        <a class="btn btn-outline" href="<?= baseUrl('contact.php') ?>">Contact the Shop</a>
      </div>
    <?php elseif (in_array($currentUser['role'], ['admin', 'staff'], true)): ?>
      <span class="eyebrow">Welcome back, <?= htmlspecialchars(explode(' ', $currentUser['name'])[0]) ?></span>
      <h2>Keep the shop running smoothly.</h2>
      <p>Jump back into the panel to manage bookings, inventory, and reports.</p>
      <div class="about-cta-actions">
        <a class="btn btn-primary" href="<?= baseUrl($currentUser['role'] === 'admin' ? 'admin/index.php' : 'staff/index.php') ?>">Open <?= $currentUser['role'] === 'admin' ? 'Admin' : 'Staff' ?> Panel</a>
        <a class="btn btn-outline" href="<?= baseUrl('shop.php') ?>">Browse the Shop</a>
      </div>
    <?php elseif ($currentUser['role'] === 'technician'): ?>
      <span class="eyebrow">Welcome back, <?= htmlspecialchars(explode(' ', $currentUser['name'])[0]) ?></span>
      <h2>Your work queue is waiting.</h2>
      <p>Check your assigned jobs and today's schedule.</p>
      <div class="about-cta-actions">
        <a class="btn btn-primary" href="<?= baseUrl('tech/index.php') ?>">Open Work Queue</a>
      </div>
    <?php else: ?>
      <span class="eyebrow">Welcome back, <?= htmlspecialchars(explode(' ', $currentUser['name'])[0]) ?></span>
      <h2>Your motorcycle deserves the best care.</h2>
      <p>Book your next service or browse parts matched to your motorcycle profile.</p>
      <div class="about-cta-actions">
        <a class="btn btn-primary" href="<?= baseUrl('book-service.php') ?>">Book a Service</a>
        <a class="btn btn-outline" href="<?= baseUrl('my-vehicle.php') ?>">My Vehicle</a>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
