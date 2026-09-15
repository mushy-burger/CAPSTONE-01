<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireQA();
$currentUser = getCurrentUser();
$pageTitle = 'QA Hub — MotoTrack';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <link rel="stylesheet" href="<?= baseUrl('assets/css/admin.css?v=' . filemtime(__DIR__ . '/../assets/css/admin.css')) ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root { --qa-purple: #a855f7; --qa-dark: #1a0533; }

    body { background: #0f0520; min-height: 100vh; font-family: 'Inter', system-ui, sans-serif; margin: 0; padding: 0; }

    .qa-hub-wrap {
      max-width: 960px;
      margin: 0 auto;
      padding: 60px 24px 100px;
    }

    /* Header */
    .qa-hub-header {
      display: flex;
      align-items: center;
      gap: 20px;
      margin-bottom: 48px;
    }
    .qa-hub-icon {
      width: 64px; height: 64px;
      background: linear-gradient(135deg, #7c3aed, #a855f7);
      border-radius: 18px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.8rem; color: #fff;
      box-shadow: 0 8px 32px rgba(168,85,247,.4);
      flex-shrink: 0;
    }
    .qa-hub-header h1 {
      font-size: 1.75rem;
      font-weight: 800;
      color: #fff;
      margin: 0 0 4px;
      letter-spacing: -.02em;
    }
    .qa-hub-header p {
      margin: 0;
      color: rgba(255,255,255,.5);
      font-size: .9rem;
    }
    .qa-hub-header .qa-pill {
      display: inline-flex; align-items: center; gap: 6px;
      background: rgba(168,85,247,.15);
      border: 1px solid rgba(168,85,247,.4);
      color: #c084fc;
      font-size: .72rem; font-weight: 700; letter-spacing: .08em;
      padding: 3px 10px; border-radius: 99px; margin-bottom: 6px;
    }

    /* Section titles */
    .qa-section-title {
      font-size: .72rem;
      font-weight: 700;
      letter-spacing: .12em;
      text-transform: uppercase;
      color: rgba(255,255,255,.35);
      margin: 0 0 14px;
      padding-left: 4px;
    }

    /* Panel grid */
    .qa-panel-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 16px;
      margin-bottom: 40px;
    }
    .qa-panel-card {
      background: rgba(255,255,255,.04);
      border: 1px solid rgba(255,255,255,.08);
      border-radius: 16px;
      padding: 24px 20px;
      text-decoration: none;
      transition: transform .2s, background .2s, border-color .2s, box-shadow .2s;
      display: flex;
      flex-direction: column;
      gap: 12px;
      position: relative;
      overflow: hidden;
    }
    .qa-panel-card::before {
      content: '';
      position: absolute;
      inset: 0;
      background: var(--card-glow, transparent);
      opacity: 0;
      transition: opacity .3s;
      border-radius: inherit;
    }
    .qa-panel-card:hover { transform: translateY(-4px); box-shadow: 0 12px 40px rgba(0,0,0,.4); }
    .qa-panel-card:hover::before { opacity: 1; }
    .qa-panel-card:hover { border-color: var(--card-accent, rgba(255,255,255,.2)); }

    .qa-card-icon {
      width: 44px; height: 44px;
      border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.1rem;
      color: #fff;
    }
    .qa-card-label {
      font-size: .78rem;
      font-weight: 600;
      letter-spacing: .06em;
      text-transform: uppercase;
      color: rgba(255,255,255,.45);
    }
    .qa-card-name {
      font-size: 1.05rem;
      font-weight: 700;
      color: #fff;
      margin: 0;
    }
    .qa-card-desc {
      font-size: .8rem;
      color: rgba(255,255,255,.45);
      line-height: 1.5;
    }
    .qa-card-arrow {
      margin-top: auto;
      color: rgba(255,255,255,.25);
      font-size: .8rem;
      transition: color .2s, transform .2s;
    }
    .qa-panel-card:hover .qa-card-arrow { color: var(--card-accent-text, rgba(255,255,255,.6)); transform: translateX(4px); }

    /* Color themes */
    .qa-card--admin  { --card-glow: linear-gradient(135deg,rgba(215,25,32,.08),transparent); --card-accent: rgba(215,25,32,.5); --card-accent-text: #fca5a5; }
    .qa-card--admin  .qa-card-icon { background: linear-gradient(135deg,#991b1b,#d71920); }

    .qa-card--staff  { --card-glow: linear-gradient(135deg,rgba(37,99,235,.08),transparent); --card-accent: rgba(37,99,235,.5); --card-accent-text: #93c5fd; }
    .qa-card--staff  .qa-card-icon { background: linear-gradient(135deg,#1d4ed8,#2563eb); }

    .qa-card--tech   { --card-glow: linear-gradient(135deg,rgba(21,128,61,.08),transparent); --card-accent: rgba(21,128,61,.5); --card-accent-text: #86efac; }
    .qa-card--tech   .qa-card-icon { background: linear-gradient(135deg,#166534,#16a34a); }

    .qa-card--public { --card-glow: linear-gradient(135deg,rgba(245,158,11,.08),transparent); --card-accent: rgba(245,158,11,.5); --card-accent-text: #fde68a; }
    .qa-card--public .qa-card-icon { background: linear-gradient(135deg,#b45309,#d97706); }

    .qa-card--customer { --card-glow: linear-gradient(135deg,rgba(8,145,178,.08),transparent); --card-accent: rgba(8,145,178,.5); --card-accent-text: #a5f3fc; }
    .qa-card--customer .qa-card-icon { background: linear-gradient(135deg,#0e7490,#0891b2); }

    /* Quick actions */
    .qa-actions-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
      gap: 10px;
      margin-bottom: 40px;
    }
    .qa-action-btn {
      background: rgba(255,255,255,.04);
      border: 1px solid rgba(255,255,255,.08);
      border-radius: 10px;
      padding: 12px 16px;
      color: rgba(255,255,255,.7);
      text-decoration: none;
      font-size: .82rem;
      display: flex; align-items: center; gap: 10px;
      transition: background .15s, border-color .15s, color .15s;
    }
    .qa-action-btn i { color: rgba(255,255,255,.35); font-size: .9rem; width: 16px; text-align: center; }
    .qa-action-btn:hover { background: rgba(168,85,247,.1); border-color: rgba(168,85,247,.3); color: #e9d5ff; }
    .qa-action-btn:hover i { color: #c084fc; }

    /* Logout */
    .qa-logout-row {
      display: flex;
      justify-content: flex-end;
      padding-top: 16px;
    }
    .qa-logout-btn {
      display: inline-flex; align-items: center; gap: 8px;
      background: rgba(239,68,68,.1);
      border: 1px solid rgba(239,68,68,.25);
      color: #fca5a5;
      padding: 10px 20px;
      border-radius: 10px;
      font-size: .85rem; font-weight: 600;
      text-decoration: none;
      transition: background .15s;
    }
    .qa-logout-btn:hover { background: rgba(239,68,68,.2); }
  </style>
</head>
<body>

<div class="qa-hub-wrap">

  <!-- Header -->
  <div class="qa-hub-header">
    <div class="qa-hub-icon"><i class="fas fa-vial"></i></div>
    <div>
      <div class="qa-pill"><i class="fas fa-circle" style="font-size:.5rem"></i> QA MODE ACTIVE</div>
      <h1>QA Tester Hub</h1>
      <p>Hello, <strong style="color:#e9d5ff"><?= htmlspecialchars($currentUser['name']) ?></strong> — browse any panel below as a read-only observer.</p>
    </div>
  </div>

  <!-- Panels -->
  <p class="qa-section-title"><i class="fas fa-door-open"></i> System Panels</p>
  <div class="qa-panel-grid">

    <a href="<?= baseUrl('admin/index.php') ?>" class="qa-panel-card qa-card--admin">
      <div class="qa-card-icon"><i class="fas fa-user-shield"></i></div>
      <div>
        <div class="qa-card-label">Panel</div>
        <div class="qa-card-name">Admin</div>
      </div>
      <div class="qa-card-desc">Dashboard, users, bookings, orders, analytics, settings.</div>
      <div class="qa-card-arrow"><i class="fas fa-arrow-right"></i> Enter</div>
    </a>

    <a href="<?= baseUrl('staff/index.php') ?>" class="qa-panel-card qa-card--staff">
      <div class="qa-card-icon"><i class="fas fa-user-tie"></i></div>
      <div>
        <div class="qa-card-label">Panel</div>
        <div class="qa-card-name">Staff</div>
      </div>
      <div class="qa-card-desc">Booking management, POS, products, services, vehicles.</div>
      <div class="qa-card-arrow"><i class="fas fa-arrow-right"></i> Enter</div>
    </a>

    <a href="<?= baseUrl('tech/index.php') ?>" class="qa-panel-card qa-card--tech">
      <div class="qa-card-icon"><i class="fas fa-wrench"></i></div>
      <div>
        <div class="qa-card-label">Panel</div>
        <div class="qa-card-name">Technician</div>
      </div>
      <div class="qa-card-desc">Work queue and job history from a technician's view.</div>
      <div class="qa-card-arrow"><i class="fas fa-arrow-right"></i> Enter</div>
    </a>

    <a href="<?= baseUrl('index.php') ?>" class="qa-panel-card qa-card--public">
      <div class="qa-card-icon"><i class="fas fa-globe"></i></div>
      <div>
        <div class="qa-card-label">Experience</div>
        <div class="qa-card-name">Public / Guest</div>
      </div>
      <div class="qa-card-desc">Homepage, shop, about, contact as a visitor.</div>
      <div class="qa-card-arrow"><i class="fas fa-arrow-right"></i> Enter</div>
    </a>

    <a href="<?= baseUrl('book-service.php') ?>" class="qa-panel-card qa-card--customer">
      <div class="qa-card-icon"><i class="fas fa-calendar-check"></i></div>
      <div>
        <div class="qa-card-label">Experience</div>
        <div class="qa-card-name">Customer</div>
      </div>
      <div class="qa-card-desc">Booking flow, shop, cart, profile as a logged-in customer.</div>
      <div class="qa-card-arrow"><i class="fas fa-arrow-right"></i> Enter</div>
    </a>

  </div>

  <!-- Quick links -->
  <p class="qa-section-title"><i class="fas fa-bolt"></i> Quick Links</p>
  <div class="qa-actions-grid">
    <a href="<?= baseUrl('admin/bookings.php') ?>" class="qa-action-btn"><i class="fas fa-calendar-alt"></i> Admin Bookings</a>
    <a href="<?= baseUrl('admin/users.php') ?>" class="qa-action-btn"><i class="fas fa-users"></i> User Management</a>
    <a href="<?= baseUrl('admin/analytics.php') ?>" class="qa-action-btn"><i class="fas fa-chart-bar"></i> Analytics</a>
    <a href="<?= baseUrl('admin/orders.php') ?>" class="qa-action-btn"><i class="fas fa-shopping-bag"></i> Orders</a>
    <a href="<?= baseUrl('admin/ratings.php') ?>" class="qa-action-btn"><i class="fas fa-star"></i> Ratings</a>
    <a href="<?= baseUrl('admin/settings.php') ?>" class="qa-action-btn"><i class="fas fa-cog"></i> Settings</a>
    <a href="<?= baseUrl('staff/bookings.php') ?>" class="qa-action-btn"><i class="fas fa-clipboard-list"></i> Staff Bookings</a>
    <a href="<?= baseUrl('staff/pos.php') ?>" class="qa-action-btn"><i class="fas fa-cash-register"></i> POS</a>
    <a href="<?= baseUrl('tech/index.php') ?>" class="qa-action-btn"><i class="fas fa-tools"></i> Work Queue</a>
    <a href="<?= baseUrl('shop.php') ?>" class="qa-action-btn"><i class="fas fa-store"></i> Shop</a>
    <a href="<?= baseUrl('profile.php') ?>" class="qa-action-btn"><i class="fas fa-user"></i> My Profile</a>
    <a href="<?= baseUrl('book-service.php') ?>" class="qa-action-btn"><i class="fas fa-motorcycle"></i> Book Service</a>
  </div>

  <!-- Logout -->
  <div class="qa-logout-row">
    <a href="<?= baseUrl('logout.php') ?>" class="qa-logout-btn"><i class="fas fa-sign-out-alt"></i> Logout QA Session</a>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/qa-banner.php'; ?>

</body>
</html>
