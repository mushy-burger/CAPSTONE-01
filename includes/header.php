<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
$cartCount = getCartCount();
$currentUser = getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'MotoTrack') ?></title>
  <link rel="stylesheet" href="<?= baseUrl('assets/css/style.css?v=' . filemtime(__DIR__ . '/../assets/css/style.css')) ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="customer-site page-<?= htmlspecialchars($currentPage, ENT_QUOTES, 'UTF-8') ?>">

<a class="skip-link" href="#mainContent">Skip to content</a>

<header class="site-header">
  <div class="header-inner">
    <a href="<?= baseUrl('index.php') ?>" class="logo" aria-label="MotoTrack home">
      <span class="logo-icon"><i class="fas fa-motorcycle"></i></span>
      <span class="logo-copy">
        <span class="logo-text">MotoTrack</span>
        <span class="logo-kicker">Built For The Ride</span>
      </span>
    </a>

    <nav class="main-nav" aria-label="Main navigation">
      <a href="<?= baseUrl('index.php') ?>" class="<?= $currentPage === 'index' ? 'active' : '' ?>"<?= $currentPage === 'index' ? ' aria-current="page"' : '' ?>>Home</a>
      <a href="<?= baseUrl('about.php') ?>" class="<?= $currentPage === 'about' ? 'active' : '' ?>"<?= $currentPage === 'about' ? ' aria-current="page"' : '' ?>>About</a>
      <a href="<?= baseUrl('shop.php') ?>" class="<?= $currentPage === 'shop' ? 'active' : '' ?>"<?= $currentPage === 'shop' ? ' aria-current="page"' : '' ?>>Shop</a>
      <a href="<?= baseUrl('book-service.php') ?>" class="<?= $currentPage === 'book-service' ? 'active' : '' ?>"<?= $currentPage === 'book-service' ? ' aria-current="page"' : '' ?>>Book Service</a>
    </nav>

    <div class="header-actions">
      <a href="<?= baseUrl('shop.php') ?>" class="header-icon header-quick" aria-label="Search products" title="Search products"><i class="fas fa-search" aria-hidden="true"></i></a>
      <a href="<?= baseUrl('cart.php') ?>" class="header-icon cart-icon header-quick" aria-label="View cart" title="Cart">
        <i class="fas fa-shopping-cart" aria-hidden="true"></i>
        <?php if ($cartCount > 0): ?>
          <span class="cart-badge"><?= $cartCount ?></span>
        <?php endif; ?>
      </a>

      <?php if ($currentUser): ?>
        <a href="<?= baseUrl('my-vehicle.php') ?>" class="header-icon wide" aria-label="My Vehicle" title="My Vehicle">
          <i class="fas fa-motorcycle" aria-hidden="true"></i><span>My Vehicle</span>
        </a>
        <a href="<?= baseUrl('profile.php') ?>" class="header-icon <?= $currentPage === 'profile' ? 'active' : '' ?>" aria-label="My Profile" title="My Profile">
          <i class="fas fa-user" aria-hidden="true"></i>
        </a>
        <?php if (in_array($currentUser['role'], ['admin','staff'], true)): ?>
          <a href="<?= baseUrl('admin/index.php') ?>" class="btn btn-small">Admin</a>
        <?php endif; ?>
        <a href="<?= baseUrl('logout.php') ?>" class="header-icon" aria-label="Logout" title="Logout"><i class="fas fa-sign-out-alt" aria-hidden="true"></i></a>
      <?php else: ?>
        <a href="<?= baseUrl('login.php') ?>" class="btn btn-small">Login</a>
        <a href="<?= baseUrl('my-vehicle.php') ?>" class="header-icon wide" aria-label="My Vehicle" title="My Vehicle">
          <i class="fas fa-motorcycle" aria-hidden="true"></i><span>My Vehicle</span>
        </a>
      <?php endif; ?>
    </div>

    <button class="mobile-menu-toggle" id="menuToggle" type="button" aria-label="Open menu" aria-controls="mobileNav" aria-expanded="false">
      <i class="fas fa-bars" aria-hidden="true"></i>
    </button>
  </div>

  <nav class="mobile-nav" id="mobileNav" aria-label="Mobile navigation">
    <a href="<?= baseUrl('index.php') ?>" class="<?= $currentPage === 'index' ? 'active' : '' ?>"<?= $currentPage === 'index' ? ' aria-current="page"' : '' ?>>Home</a>
    <a href="<?= baseUrl('about.php') ?>" class="<?= $currentPage === 'about' ? 'active' : '' ?>"<?= $currentPage === 'about' ? ' aria-current="page"' : '' ?>>About</a>
    <a href="<?= baseUrl('shop.php') ?>" class="<?= $currentPage === 'shop' ? 'active' : '' ?>"<?= $currentPage === 'shop' ? ' aria-current="page"' : '' ?>>Shop</a>
    <a href="<?= baseUrl('book-service.php') ?>" class="<?= $currentPage === 'book-service' ? 'active' : '' ?>"<?= $currentPage === 'book-service' ? ' aria-current="page"' : '' ?>>Book Service</a>
    <?php if ($currentUser): ?>
      <a href="<?= baseUrl('my-vehicle.php') ?>">My Vehicle</a>
      <a href="<?= baseUrl('profile.php') ?>">My Profile</a>
      <a href="<?= baseUrl('logout.php') ?>">Logout</a>
    <?php else: ?>
      <a href="<?= baseUrl('login.php') ?>">Login</a>
    <?php endif; ?>
  </nav>
</header>
<main id="mainContent">
