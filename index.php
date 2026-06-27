<?php
$pageTitle = 'MotoTrack - Motorcycle Parts and Service';
require_once __DIR__ . '/includes/header.php';

$heroEyebrow = getSiteSetting('hero_eyebrow', 'Parts, accessories, and maintenance');
$heroHeading = getSiteSetting('hero_heading',  'Keep your motorcycle ready for every ride.');
$heroSubtext = getSiteSetting('hero_subtext',  'Shop reliable products, save your motorcycle profile, and book compatible services with instant cost estimates.');
$heroBackgroundImage = getSiteSetting('hero_background_image', '');
$heroBgPath = $heroBackgroundImage && file_exists(__DIR__ . '/uploads/' . $heroBackgroundImage) ? $heroBackgroundImage : '';
$heroBackground = $heroBgPath ? baseUrl('uploads/' . rawurlencode($heroBgPath) . '?v=' . filemtime(__DIR__ . '/uploads/' . $heroBgPath)) : '';
$headlineWords = preg_split('/\s+/', trim($heroHeading)) ?: [];
$heroHeadingFirst = implode(' ', array_slice($headlineWords, 0, max(1, (int)ceil(count($headlineWords) / 2))));
$heroHeadingSecond = implode(' ', array_slice($headlineWords, max(1, (int)ceil(count($headlineWords) / 2))));
?>

<section class="hero hero-home"<?= $heroBackground ? ' style="--hero-bg:url(\'' . htmlspecialchars($heroBackground, ENT_QUOTES, 'UTF-8') . '\')"' : '' ?>>
  <div class="hero-home-shell">
    <div class="hero-copy hero-home-copy">
      <span class="eyebrow"><?= htmlspecialchars($heroEyebrow) ?></span>
      <h1 class="hero-home-title">
        <span><?= htmlspecialchars($heroHeadingFirst) ?></span>
        <?php if ($heroHeadingSecond !== ''): ?><span class="accent-line"><?= htmlspecialchars($heroHeadingSecond) ?></span><?php endif; ?>
      </h1>
      <p><?= htmlspecialchars($heroSubtext) ?></p>
      <div class="hero-actions hero-home-actions">
        <a href="<?= baseUrl('book-service.php') ?>" class="btn btn-primary">Our Services</a>
        <a href="<?= baseUrl('shop.php') ?>" class="btn btn-outline">Browse Products</a>
      </div>
    </div>

    <div class="hero-home-benefits">
      <div>
        <i class="fas fa-shield-alt"></i>
        <span>
          <strong>100% Genuine Parts</strong>
          <small>Trusted and reliable</small>
        </span>
      </div>
      <div>
        <i class="fas fa-tags"></i>
        <span>
          <strong>Best Price Guarantee</strong>
          <small>Quality at the best price</small>
        </span>
      </div>
      <div>
        <i class="fas fa-tools"></i>
        <span>
          <strong>Expert Service</strong>
          <small>Certified mechanics</small>
        </span>
      </div>
      <div>
        <i class="fas fa-clock"></i>
        <span>
          <strong>Quick & Easy Booking</strong>
          <small>Get back on the road faster</small>
        </span>
      </div>
    </div>
  </div>
</section>

<?php if ($currentUser && $currentUser['role'] === 'customer'):
    require_once __DIR__ . '/includes/db.php';
    $latestBooking = fetchOne(
        "SELECT b.id, b.status, b.scheduled_date, b.scheduled_time, b.technician_id,
                u.name AS tech_name
         FROM bookings b
         LEFT JOIN users u ON u.id = b.technician_id
         WHERE b.user_id = ?
         ORDER BY b.created_at DESC LIMIT 1",
        [$currentUser['id']]
    );
    $latestOrder = fetchOne(
        "SELECT id, total, status, payment_status, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 1",
        [$currentUser['id']]
    );
    $statusSteps = ['pending'=>0,'confirmed'=>1,'in_progress'=>2,'completed'=>3];
    $currentStep = $statusSteps[$latestBooking['status'] ?? ''] ?? 0;
    $stepLabels  = ['Pending','Confirmed','In Progress','Done'];
    $stepIcons   = ['fa-hourglass-start','fa-user-check','fa-tools','fa-flag-checkered'];
?>
<section style="background:#f8fafc;border-top:1px solid #e5e7eb;padding:36px 0;">
  <div class="container" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;">
    <!-- Latest Booking Widget -->
    <?php if ($latestBooking): ?>
    <div style="background:#fff;border-radius:14px;padding:22px;border:1px solid #e5e7eb;box-shadow:0 1px 4px rgba(0,0,0,.05);">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
        <strong style="font-size:.95rem;">Latest Booking</strong>
        <span style="font-size:.75rem;font-weight:900;padding:3px 10px;border-radius:20px;
          background:<?= ['pending'=>'#f3f4f6','confirmed'=>'#eff6ff','in_progress'=>'#fffbeb','completed'=>'#f0fdf4','cancelled'=>'#fef2f2'][$latestBooking['status']] ?? '#f3f4f6' ?>;
          color:<?= ['pending'=>'#6b7280','confirmed'=>'#1d4ed8','in_progress'=>'#b45309','completed'=>'#15803d','cancelled'=>'#b91c1c'][$latestBooking['status']] ?? '#6b7280' ?>;">
          <?= strtoupper(str_replace('_',' ',$latestBooking['status'])) ?>
        </span>
      </div>
      <div style="display:flex;align-items:center;gap:0;margin-bottom:12px;">
        <?php foreach ($stepLabels as $si => $slabel): ?>
          <div style="display:flex;flex-direction:column;align-items:center;gap:4px;flex:0 0 auto;font-size:.65rem;font-weight:700;color:<?= $si<=$currentStep?($si<$currentStep?'#15803d':'#d71920'):'#9ca3af' ?>;">
            <div style="width:28px;height:28px;border-radius:50%;background:<?= $si<$currentStep?'#15803d':($si===$currentStep?'#d71920':'#e5e7eb') ?>;display:grid;place-items:center;color:<?= $si<=$currentStep?'#fff':'#9ca3af' ?>;font-size:.7rem;">
              <i class="fas <?= $stepIcons[$si] ?>"></i>
            </div>
            <?= $slabel ?>
          </div>
          <?php if ($si < 3): ?>
            <div style="flex:1;height:2px;background:<?= $si<$currentStep?'#15803d':'#e5e7eb' ?>;margin-bottom:14px;min-width:12px;"></div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
      <?php if ($latestBooking['scheduled_date']): ?>
        <p style="font-size:.82rem;color:#6b7280;margin:0 0 4px;">
          📅 <?= htmlspecialchars(date('M j, Y', strtotime($latestBooking['scheduled_date']))) ?>
          <?= $latestBooking['scheduled_time'] ? ' at '.htmlspecialchars(date('g:i A', strtotime($latestBooking['scheduled_time']))) : '' ?>
        </p>
      <?php endif; ?>
      <?php if ($latestBooking['tech_name'] && in_array($latestBooking['status'],['confirmed','in_progress','completed'])): ?>
        <p style="font-size:.82rem;color:#6b7280;margin:0 0 8px;">🔧 Tech: <strong><?= htmlspecialchars(explode(' ',$latestBooking['tech_name'])[0]) ?></strong></p>
      <?php endif; ?>
      <a href="<?= baseUrl('book-service.php?tab=appointments') ?>" class="btn btn-outline" style="font-size:.82rem;width:100%;text-align:center;margin-top:6px;">View All Appointments</a>
    </div>
    <?php endif; ?>

    <!-- Latest Order Widget -->
    <?php if ($latestOrder): ?>
    <div style="background:#fff;border-radius:14px;padding:22px;border:1px solid #e5e7eb;box-shadow:0 1px 4px rgba(0,0,0,.05);">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
        <strong style="font-size:.95rem;">Latest Order #<?= (int)$latestOrder['id'] ?></strong>
        <span style="font-size:.75rem;font-weight:900;padding:3px 10px;border-radius:20px;background:<?= $latestOrder['payment_status']==='paid'?'#f0fdf4':'#fffbeb' ?>;color:<?= $latestOrder['payment_status']==='paid'?'#15803d':'#b45309' ?>;">
          <?= strtoupper($latestOrder['payment_status'] ?? 'PENDING') ?>
        </span>
      </div>
      <p style="font-size:.88rem;color:#6b7280;margin:0 0 6px;">🗓 <?= htmlspecialchars(date('M j, Y', strtotime($latestOrder['created_at']))) ?></p>
      <p style="font-size:1.1rem;font-weight:900;color:#d71920;margin:0 0 14px;"><?= formatPrice((float)$latestOrder['total']) ?></p>
      <a href="<?= baseUrl('cart.php?tab=orders') ?>" class="btn btn-outline" style="font-size:.82rem;width:100%;text-align:center;">View All Orders</a>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div style="background:#fff;border-radius:14px;padding:22px;border:1px solid #e5e7eb;box-shadow:0 1px 4px rgba(0,0,0,.05);">
      <strong style="font-size:.95rem;display:block;margin-bottom:14px;">Quick Actions</strong>
      <div style="display:flex;flex-direction:column;gap:10px;">
        <a href="<?= baseUrl('book-service.php') ?>" class="btn btn-primary" style="text-align:center;">📅 Book a Service</a>
        <a href="<?= baseUrl('shop.php') ?>" class="btn btn-outline" style="text-align:center;">🛍 Browse Parts</a>
        <a href="<?= baseUrl('my-vehicle.php') ?>" class="btn btn-outline" style="text-align:center;">🏍 My Motorcycle</a>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

