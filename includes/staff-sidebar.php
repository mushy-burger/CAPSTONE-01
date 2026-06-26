<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
requireStaff();
$currentUser = getCurrentUser();
$staffPage = basename($_SERVER['PHP_SELF'], '.php');
$unreadCount = getUnreadNotificationCount((int)$currentUser['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'Staff - MotoTrack') ?></title>
  <link rel="stylesheet" href="<?= baseUrl('assets/css/admin.css?v=' . filemtime(__DIR__ . '/../assets/css/admin.css')) ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="admin-body">

<div class="admin-layout">
  <aside class="admin-sidebar" style="--sidebar-accent:#2563eb;">
    <div class="sidebar-logo">
      <i class="fas fa-motorcycle"></i>
      <span>MotoTrack</span>
    </div>
    <div class="role-badge role-staff">Staff</div>
    <nav class="sidebar-nav">
      <a href="<?= baseUrl('staff/index.php') ?>" class="<?= $staffPage === 'index' ? 'active' : '' ?>">
        <i class="fas fa-tachometer-alt"></i> Dashboard
      </a>
      <a href="<?= baseUrl('staff/bookings.php') ?>" class="<?= $staffPage === 'bookings' ? 'active' : '' ?> nav-notif-wrap">
        <span><i class="fas fa-calendar-check"></i> Bookings</span>
        <?php if ($unreadCount > 0): ?>
          <span class="notif-badge"><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span>
        <?php endif; ?>
      </a>
      <a href="<?= baseUrl('staff/products.php') ?>" class="<?= $staffPage === 'products' ? 'active' : '' ?>">
        <i class="fas fa-box"></i> Products
      </a>
      <a href="<?= baseUrl('staff/services.php') ?>" class="<?= $staffPage === 'services' ? 'active' : '' ?>">
        <i class="fas fa-tools"></i> Services
      </a>
      <a href="<?= baseUrl('staff/vehicles.php') ?>" class="<?= $staffPage === 'vehicles' ? 'active' : '' ?>">
        <i class="fas fa-motorcycle"></i> Vehicle Options
      </a>
    </nav>
  </aside>

  <div class="admin-main">
    <header class="admin-topbar">
      <div>
        <span class="page-title-label"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></span>
      </div>
      <div class="topbar-right">
        <a href="<?= baseUrl('index.php') ?>" class="topbar-icon" title="View Site"><i class="fas fa-external-link-alt"></i></a>
        <?php if ($unreadCount > 0): ?>
          <a href="<?= baseUrl('staff/bookings.php') ?>" class="topbar-icon topbar-notif" title="<?= $unreadCount ?> unread notification<?= $unreadCount !== 1 ? 's' : '' ?>">
            <i class="fas fa-bell"></i>
            <span class="notif-badge-sm"><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span>
          </a>
        <?php else: ?>
          <span class="topbar-icon"><i class="fas fa-bell"></i></span>
        <?php endif; ?>
        <span class="topbar-welcome">Welcome, <?= htmlspecialchars($currentUser['name']) ?>!</span>
        <a href="<?= baseUrl('logout.php') ?>" class="topbar-icon" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
      </div>
    </header>

    <main class="admin-content">
