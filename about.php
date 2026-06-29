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
<section style="background:var(--surface-alt,#f9fafb);border-top:1px solid var(--line);border-bottom:1px solid var(--line);padding:32px 0;">
  <div class="container" style="display:grid;grid-template-columns:repeat(3,1fr);gap:24px;text-align:center;">
    <div>
      <strong style="font-size:2.2rem;font-weight:900;color:#d71920;"><?= number_format($totalCustomers) ?>+</strong>
      <div style="color:var(--muted);font-size:.9rem;margin-top:4px;">Registered Customers</div>
    </div>
    <div>
      <strong style="font-size:2.2rem;font-weight:900;color:#d71920;"><?= number_format($totalBookings) ?>+</strong>
      <div style="color:var(--muted);font-size:.9rem;margin-top:4px;">Completed Services</div>
    </div>
    <div>
      <strong style="font-size:2.2rem;font-weight:900;color:#d71920;"><?= number_format($totalProducts) ?>+</strong>
      <div style="color:var(--muted);font-size:.9rem;margin-top:4px;">Products Available</div>
    </div>
  </div>
</section>

<!-- What We Do -->
<section class="section container about-intro">
  <div class="about-copy">
    <span class="eyebrow">What We Do</span>
    <h2>Clear parts, clear services, clear estimates.</h2>
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
<section style="background:var(--surface-alt,#f9fafb);border-top:1px solid var(--line);padding:60px 0;">
  <div class="container">
    <div class="section-heading" style="margin-bottom:36px;">
      <span class="eyebrow">How It Works</span>
      <h2>From booking to completion in 4 steps.</h2>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:24px;">
      <?php
        $steps = [
          ['icon'=>'fa-calendar-plus',    'color'=>'#2563eb', 'step'=>'1', 'title'=>'Customer Books',   'desc'=>'Choose your motorcycle, select services, pick a date and time online.'],
          ['icon'=>'fa-user-check',       'color'=>'#d97706', 'step'=>'2', 'title'=>'Staff Confirms',   'desc'=>'Staff reviews the request and assigns a certified technician.'],
          ['icon'=>'fa-tools',            'color'=>'#7c3aed', 'step'=>'3', 'title'=>'Tech Services',    'desc'=>'Technician receives the job, starts work, and logs progress notes.'],
          ['icon'=>'fa-flag-checkered',   'color'=>'#15803d', 'step'=>'4', 'title'=>'Job Complete',     'desc'=>'Customer is notified, stock is updated, service record is saved.'],
        ];
        foreach ($steps as $s):
      ?>
        <div style="background:#fff;border-radius:14px;padding:24px;box-shadow:0 1px 4px rgba(0,0,0,.06);text-align:center;border-top:4px solid <?= $s['color'] ?>;">
          <div style="width:48px;height:48px;border-radius:50%;background:<?= $s['color'] ?>;display:grid;place-items:center;margin:0 auto 12px;color:#fff;font-size:1.2rem;">
            <i class="fas <?= $s['icon'] ?>"></i>
          </div>
          <div style="font-size:.75rem;font-weight:900;color:<?= $s['color'] ?>;letter-spacing:.08em;margin-bottom:4px;">STEP <?= $s['step'] ?></div>
          <strong style="display:block;margin-bottom:8px;"><?= $s['title'] ?></strong>
          <p style="font-size:.87rem;color:var(--muted);margin:0;"><?= $s['desc'] ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="section container" style="text-align:center;">
  <span class="eyebrow">Ready to Start?</span>
  <h2 style="margin:8px 0 16px;">Your motorcycle deserves the best care.</h2>
  <p style="color:var(--muted);max-width:480px;margin:0 auto 24px;">Register for free, add your motorcycle profile, and book your first service in minutes.</p>
  <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
    <a class="btn btn-primary" href="<?= baseUrl('register.php') ?>">Create Free Account</a>
    <a class="btn btn-outline" href="<?= baseUrl('contact.php') ?>">Contact the Shop</a>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
