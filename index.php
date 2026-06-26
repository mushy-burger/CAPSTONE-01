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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
