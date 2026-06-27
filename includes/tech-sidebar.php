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
      <a href="<?= baseUrl('tech/history.php') ?>" class="<?= $techPage === 'history' ? 'active' : '' ?>">
        <i class="fas fa-history"></i> Job History
      </a>
    </nav>
    <div style="padding:16px 20px;border-top:1px solid rgba(255,255,255,.1);margin-top:auto;">
      <a href="<?= baseUrl('forgot-password.php') ?>" style="font-size:.78rem;color:rgba(255,255,255,.5);text-decoration:none;">
        <i class="fas fa-key"></i> Change / Reset Password
      </a>
    </div>

  <div class="admin-main">
    <header class="admin-topbar">
      <div>
        <span class="page-title-label"><?= htmlspecialchars($pageTitle ?? 'Work Queue') ?></span>
      </div>
      <div class="topbar-right">
        <!-- Notification Bell with Dropdown -->
        <div class="notif-dropdown-wrap" id="notifWrap">
          <button class="topbar-icon topbar-notif" id="notifBtn" type="button" title="Notifications">
            <i class="fas fa-bell"></i>
            <?php if ($unreadCount > 0): ?>
              <span class="notif-badge-sm"><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span>
            <?php endif; ?>
          </button>
          <div class="notif-dropdown" id="notifDropdown" hidden>
            <div class="notif-dropdown-head">
              <strong>Notifications</strong>
              <?php if ($unreadCount > 0): ?>
                <a href="<?= baseUrl('api/notifications.php?mark_read=1') ?>" class="notif-mark-all">Mark all read</a>
              <?php endif; ?>
            </div>
            <div class="notif-list" id="notifList"><div class="notif-item"><span>Loading...</span></div></div>
          </div>
        </div>
        <span class="topbar-welcome">Welcome, <?= htmlspecialchars($currentUser['name']) ?>!</span>
        <a href="<?= baseUrl('logout.php') ?>" class="topbar-icon" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
      </div>
    </header>

    <main class="admin-content">

<script>
(function(){
  var btn = document.getElementById('notifBtn');
  var drop = document.getElementById('notifDropdown');
  var list = document.getElementById('notifList');
  if (!btn || !drop) return;
  var loaded = false;
  btn.addEventListener('click', function(e){
    e.stopPropagation();
    var hidden = drop.hidden;
    drop.hidden = !hidden;
    if (hidden && !loaded) {
      loaded = true;
      fetch('<?= baseUrl('api/notifications.php') ?>')
        .then(function(r){ return r.json(); })
        .then(function(data){
          var notifs = data.notifications || [];
          if (!notifs.length) {
            list.innerHTML = '<div class="notif-empty">No notifications yet.</div>';
            return;
          }
          list.innerHTML = notifs.map(function(n){
            var cls = n.is_read == 0 ? 'notif-item unread' : 'notif-item';
            var t = n.created_at ? new Date(n.created_at).toLocaleString('en-PH',{month:'short',day:'numeric',hour:'2-digit',minute:'2-digit'}) : '';
            return '<div class="'+cls+'"><span class="notif-msg">'+n.message.replace(/</g,'&lt;')+'</span><span class="notif-time">'+t+'</span></div>';
          }).join('');
        }).catch(function(){ list.innerHTML = '<div class="notif-empty">Could not load notifications.</div>'; });
    }
  });
  document.addEventListener('click', function(){ drop.hidden = true; });
  drop.addEventListener('click', function(e){ e.stopPropagation(); });
})();
</script>
