<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
requireTechnician();
$currentUser = getCurrentUser();
$techPage = basename($_SERVER['PHP_SELF'], '.php');
$unreadCount = getUnreadNotificationCount((int)$currentUser['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'Technician - MotoTrack') ?></title>
  <link rel="stylesheet" href="<?= baseUrl('assets/css/admin.css?v=' . filemtime(__DIR__ . '/../assets/css/admin.css')) ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="admin-body">

<div class="admin-layout">
  <aside class="admin-sidebar" style="--sidebar-accent:#15803d;">
    <div class="sidebar-logo">
      <i class="fas fa-motorcycle"></i>
      <span>MotoTrack</span>
    </div>
    <div class="role-badge role-tech">Technician</div>
    <nav class="sidebar-nav">
      <a href="<?= baseUrl('tech/index.php') ?>" class="<?= $techPage === 'index' ? 'active' : '' ?> nav-notif-wrap">
        <span><i class="fas fa-wrench"></i> Work Queue</span>
        <?php if ($unreadCount > 0): ?>
          <span class="notif-badge"><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span>
        <?php endif; ?>
      </a>
    </nav>
  </aside>

  <div class="admin-main">
    <header class="admin-topbar">
      <div>
        <span class="page-title-label"><?= htmlspecialchars($pageTitle ?? 'Work Queue') ?></span>
      </div>
      <div class="topbar-right">
        <?php if ($unreadCount > 0): ?>
          <a href="<?= baseUrl('tech/index.php') ?>" class="topbar-icon topbar-notif" title="<?= $unreadCount ?> new assignment<?= $unreadCount !== 1 ? 's' : '' ?>">
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
