<?php
$pageTitle = 'MotoTrack - Motorcycle Parts and Service';
require_once __DIR__ . '/includes/header.php';

$heroEyebrow = getSiteSetting('hero_eyebrow', 'Parts, accessories, and maintenance');
$heroHeading = getSiteSetting('hero_heading',  'Keep your motorcycle ready for every ride.');
$heroSubtext = getSiteSetting('hero_subtext',  'Shop reliable products, save your motorcycle profile, and book compatible services with instant cost estimates.');
$heroBackgroundImage = getSiteSetting('hero_background_image', '');
$heroBgPath = $heroBackgroundImage && file_exists(__DIR__ . '/uploads/' . $heroBackgroundImage) ? $heroBackgroundImage : '';
$heroBackground = $heroBgPath ? baseUrl('uploads/' . rawurlencode($heroBgPath) . '?v=' . filemtime(__DIR__ . '/uploads/' . $heroBgPath)) : '';
?>

<section class="hero hero-home"<?= $heroBackground ? ' style="--hero-bg:url(\'' . htmlspecialchars($heroBackground, ENT_QUOTES, 'UTF-8') . '\')"' : '' ?>>
  <div class="hero-home-shell">
    <div class="hero-copy hero-home-copy">
      <span class="eyebrow"><?= htmlspecialchars($heroEyebrow) ?></span>
      <h1 class="hero-home-title"><?= htmlspecialchars($heroHeading) ?></h1>
      <p><?= htmlspecialchars($heroSubtext) ?></p>
      <div class="hero-actions hero-home-actions">
        <a href="<?= baseUrl('book-service.php') ?>" class="btn btn-primary">Book a Service</a>
        <a href="<?= baseUrl('shop.php') ?>" class="btn btn-outline">Browse Products</a>
      </div>
    </div>

    <div class="hero-home-benefits">
      <div>
        <i class="fas fa-motorcycle" aria-hidden="true"></i>
        <span>
          <strong>Motorcycle Profiles</strong>
          <small>Save your ride details</small>
        </span>
      </div>
      <div>
        <i class="fas fa-puzzle-piece" aria-hidden="true"></i>
        <span>
          <strong>Compatible Options</strong>
          <small>Match parts and services</small>
        </span>
      </div>
      <div>
        <i class="fas fa-calculator" aria-hidden="true"></i>
        <span>
          <strong>Service Estimates</strong>
          <small>Review costs before booking</small>
        </span>
      </div>
      <div>
        <i class="fas fa-clipboard-check" aria-hidden="true"></i>
        <span>
          <strong>Activity Tracking</strong>
          <small>Follow bookings and orders</small>
        </span>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
