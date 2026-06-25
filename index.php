<?php
$pageTitle = 'MotoTrack - Motorcycle Parts and Service';
require_once __DIR__ . '/includes/header.php';

$heroEyebrow = getSiteSetting('hero_eyebrow', 'Parts, accessories, and maintenance');
$heroHeading = getSiteSetting('hero_heading',  'Keep your motorcycle ready for every ride.');
$heroSubtext = getSiteSetting('hero_subtext',  'Shop reliable products, save your motorcycle profile, and book compatible services with instant cost estimates.');
$heroImage   = getSiteSetting('hero_image', '');
$heroImgPath = $heroImage && file_exists(__DIR__ . '/uploads/' . $heroImage) ? $heroImage : '';
?>

<section class="hero">
  <div class="hero-copy">
    <span class="eyebrow"><?= htmlspecialchars($heroEyebrow) ?></span>
    <h1><?= htmlspecialchars($heroHeading) ?></h1>
    <p><?= htmlspecialchars($heroSubtext) ?></p>
    <div class="hero-actions">
      <a href="<?= baseUrl('shop.php') ?>" class="btn btn-primary">Shop now</a>
      <a href="<?= baseUrl('book-service.php') ?>" class="btn btn-outline">Book service</a>
    </div>
  </div>
  <div class="hero-panel">
    <div class="bike-visual">
      <?php if ($heroImgPath): ?>
        <img src="<?= baseUrl('uploads/' . rawurlencode($heroImgPath)) ?>" alt="Hero">
      <?php else: ?>
        <i class="fas fa-motorcycle"></i>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
