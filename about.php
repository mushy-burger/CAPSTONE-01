<?php
$pageTitle = 'About MotoTrack - Motorcycle Parts & Service';
require_once __DIR__ . '/includes/header.php';
?>

<section class="page-hero page-hero--feature">
  <div class="container">
    <div class="page-hero-text">
      <h1>Motorcycle care built around real shop work.</h1>
      <p>MotoTrack connects inventory, booking, service materials, and customer motorcycle profiles in one modern system.</p>
    </div>
    <?php
      // Reuse the existing homepage hero asset as the About composition counterweight.
      $aboutHeroFile = __DIR__ . '/uploads/hero_background_image.png';
      $aboutHeroImg  = file_exists($aboutHeroFile)
          ? baseUrl('uploads/hero_background_image.png') . '?v=' . filemtime($aboutHeroFile)
          : '';
    ?>
    <?php if ($aboutHeroImg): ?>
      <div class="page-hero-motif page-hero-motif--image" aria-hidden="true"
           style="--hero-motif-img:url('<?= htmlspecialchars($aboutHeroImg, ENT_QUOTES, 'UTF-8') ?>')"></div>
    <?php else: ?>
      <div class="page-hero-motif" aria-hidden="true"><i class="fas fa-motorcycle"></i></div>
    <?php endif; ?>
  </div>
</section>

<!-- What We Do -->
<section class="section container about-intro">
  <div class="about-copy">
    <h2>Clear parts, clear services, clear estimates.</h2>
    <p>Customers can browse available parts and accessories, then book maintenance matched to their motorcycle type. Staff confirm requests, assign technicians, and track inventory aligned with sales and service usage.</p>
    <p>Technicians receive their job queue digitally and can add notes directly to each job as they work.</p>
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
    <div><i class="fas fa-bell"></i><strong>Booking Notifications</strong><span>Staff and technicians receive alerts for new bookings and assignments.</span></div>
    <div><i class="fas fa-chart-bar"></i><strong>Business Analytics</strong><span>Admins track revenue, top products, service volume, and inventory risk.</span></div>
  </div>
</section>

<!-- How It Works -->
<section class="container about-band">
  <div class="section-heading">
    <h2>From booking to completion in 4 steps.</h2>
    <p>A connected workflow keeps the motorcycle, selected work, materials, and appointment together.</p>
  </div>
  <div class="about-steps">
    <?php
      $steps = [
        ['icon'=>'fa-calendar-plus',  'step'=>'1', 'title'=>'Customer Books', 'desc'=>'Choose a motorcycle, select services, then pick a date and time.'],
        ['icon'=>'fa-user-check',     'step'=>'2', 'title'=>'Staff Confirms', 'desc'=>'Staff reviews the request and assigns an available technician.'],
        ['icon'=>'fa-tools',          'step'=>'3', 'title'=>'Technician Works', 'desc'=>'The technician receives the job and records service progress.'],
        ['icon'=>'fa-flag-checkered', 'step'=>'4', 'title'=>'Work Is Recorded', 'desc'=>'The appointment, service record, and related stock activity stay connected.'],
      ];
      foreach ($steps as $s):
    ?>
      <div class="about-step">
        <div class="about-step-icon"><i class="fas <?= $s['icon'] ?>"></i></div>
        <div class="about-step-num">STEP <?= $s['step'] ?></div>
        <strong><?= $s['title'] ?></strong>
        <p><?= $s['desc'] ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- CTA (auth-aware) -->
<section class="section container about-cta-section">
  <div class="about-cta">
    <?php if (!$currentUser): ?>
      <h2>Keep your motorcycle details and service activity together.</h2>
      <p>Create an account, add your motorcycle profile, and use it when booking compatible services.</p>
      <div class="about-cta-actions">
        <a class="btn btn-primary" href="<?= baseUrl('register.php') ?>">Create Account</a>
        <a class="btn btn-outline" href="<?= baseUrl('contact.php') ?>">Contact the Shop</a>
      </div>
    <?php elseif (in_array($currentUser['role'], ['admin', 'staff'], true)): ?>
      <h2>Keep the shop running smoothly.</h2>
      <p>Jump back into the panel to manage bookings, inventory, and reports.</p>
      <div class="about-cta-actions">
        <a class="btn btn-primary" href="<?= baseUrl($currentUser['role'] === 'admin' ? 'admin/index.php' : 'staff/index.php') ?>">Open <?= $currentUser['role'] === 'admin' ? 'Admin' : 'Staff' ?> Panel</a>
        <a class="btn btn-outline" href="<?= baseUrl('shop.php') ?>">Browse the Shop</a>
      </div>
    <?php elseif ($currentUser['role'] === 'technician'): ?>
      <h2>Your work queue is waiting.</h2>
      <p>Check your assigned jobs and today's schedule.</p>
      <div class="about-cta-actions">
        <a class="btn btn-primary" href="<?= baseUrl('tech/index.php') ?>">Open Work Queue</a>
      </div>
    <?php else: ?>
      <h2>Plan the next job for your motorcycle.</h2>
      <p>Book your next service or browse parts matched to your motorcycle profile.</p>
      <div class="about-cta-actions">
        <a class="btn btn-primary" href="<?= baseUrl('book-service.php') ?>">Book a Service</a>
        <a class="btn btn-outline" href="<?= baseUrl('my-vehicle.php') ?>">My Vehicle</a>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
