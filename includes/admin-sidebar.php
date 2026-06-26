<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
requireAdminOnly();
$currentUser = getCurrentUser();
$adminPage = basename($_SERVER['PHP_SELF'], '.php');
$unreadCount = getUnreadNotificationCount((int)$currentUser['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'Admin - MotoTrack') ?></title>
  <link rel="stylesheet" href="<?= baseUrl('assets/css/admin.css?v=' . filemtime(__DIR__ . '/../assets/css/admin.css')) ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body class="admin-body">

<div class="admin-layout">
  <aside class="admin-sidebar" style="--sidebar-accent:#d71920;">
    <div class="sidebar-logo">
      <i class="fas fa-motorcycle"></i>
      <span>MotoTrack</span>
    </div>
    <div class="role-badge role-admin">Admin</div>
    <nav class="sidebar-nav">
      <a href="<?= baseUrl('admin/index.php') ?>" class="<?= $adminPage === 'index' ? 'active' : '' ?>">
        <i class="fas fa-tachometer-alt"></i> Dashboard
      </a>
      <a href="<?= baseUrl('admin/users.php') ?>" class="<?= $adminPage === 'users' ? 'active' : '' ?>">
        <i class="fas fa-users"></i> Users
      </a>
      <a href="<?= baseUrl('admin/orders.php') ?>" class="<?= $adminPage === 'orders' ? 'active' : '' ?>">
        <i class="fas fa-shopping-bag"></i> Orders
      </a>
      <a href="<?= baseUrl('admin/bookings.php') ?>" class="<?= $adminPage === 'bookings' ? 'active' : '' ?>">
        <i class="fas fa-calendar-check"></i> Bookings
      </a>
      <a href="<?= baseUrl('admin/analytics.php') ?>" class="<?= $adminPage === 'analytics' ? 'active' : '' ?>">
        <i class="fas fa-chart-bar"></i> Analytics
      </a>
      <a href="<?= baseUrl('admin/settings.php') ?>" class="<?= $adminPage === 'settings' ? 'active' : '' ?>">
        <i class="fas fa-cog"></i> Settings
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
        <span class="topbar-welcome">Welcome, <?= htmlspecialchars($currentUser['name']) ?>!</span>
        <a href="<?= baseUrl('logout.php') ?>" class="topbar-icon" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
      </div>
    </header>

    <main class="admin-content">
