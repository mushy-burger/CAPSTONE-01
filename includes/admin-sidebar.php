<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
requireAdminOnly();
$currentUser = getCurrentUser();
$_isQA = $currentUser['role'] === 'qa';
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
    <div class="role-badge role-admin">
      <?php if ($_isQA): ?><i class="fas fa-vial" aria-hidden="true"></i> QA / Admin View<?php else: ?>Admin<?php endif; ?>
    </div>
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
      <a href="<?= baseUrl('admin/ratings.php') ?>" class="<?= $adminPage === 'ratings' ? 'active' : '' ?>">
        <i class="fas fa-star"></i> Ratings
      </a>
      <a href="<?= baseUrl('admin/purchase-orders.php') ?>" class="<?= $adminPage === 'purchase-orders' ? 'active' : '' ?>">
        <i class="fas fa-file-invoice"></i> Purchase Orders
      </a>
      <a href="<?= baseUrl('admin/suppliers.php') ?>" class="<?= $adminPage === 'suppliers' ? 'active' : '' ?>">
        <i class="fas fa-truck"></i> Suppliers
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
        <div class="notif-dropdown-wrap" id="notifWrap">
          <button class="topbar-icon topbar-notif" id="notifBtn" type="button" title="Notifications">
            <i class="fas fa-bell"></i>
            <?php if ($unreadCount > 0): ?><span class="notif-badge-sm"><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span><?php endif; ?>
          </button>
          <div class="notif-dropdown" id="notifDropdown" hidden>
            <div class="notif-dropdown-head"><strong>Notifications</strong><?php if ($unreadCount > 0): ?><a href="<?= baseUrl('api/notifications.php?mark_read=1') ?>" class="notif-mark-all">Mark all read</a><?php endif; ?></div>
            <div class="notif-list" id="notifList"><div class="notif-item"><span>Loading...</span></div></div>
          </div>
        </div>
        <span class="topbar-welcome">Welcome, <?= htmlspecialchars($currentUser['name']) ?>!</span>
        <a href="<?= baseUrl('logout.php') ?>" class="topbar-icon" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
      </div>
    </header>

    <main class="admin-content">
<?php require_once __DIR__ . '/qa-banner.php'; ?>

<script>
(function(){
  var btn = document.getElementById('notifBtn');
  var drop = document.getElementById('notifDropdown');
  var list = document.getElementById('notifList');
  if (!btn || !drop) return;
  var loaded = false;
  var esc = function(value){ return String(value || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;'); };
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
          if (!notifs.length) { list.innerHTML = '<div class="notif-empty">No notifications yet.</div>'; return; }
          list.innerHTML = notifs.map(function(n){
            var cls = n.is_read == 0 ? 'notif-item unread' : 'notif-item';
            var t = n.created_at ? new Date(n.created_at).toLocaleString('en-PH',{month:'short',day:'numeric',hour:'2-digit',minute:'2-digit'}) : '';
            var inner = '<span class="notif-msg">'+esc(n.message)+'</span><span class="notif-time">'+esc(t)+'</span>';
            return n.action_url ? '<a class="'+cls+'" href="'+esc(n.action_url)+'">'+inner+'</a>' : '<div class="'+cls+'">'+inner+'</div>';
          }).join('');
        }).catch(function(){ list.innerHTML = '<div class="notif-empty">Could not load notifications.</div>'; });
    }
  });
  document.addEventListener('click', function(){ drop.hidden = true; });
  drop.addEventListener('click', function(e){ e.stopPropagation(); });
  var markAll = drop.querySelector('.notif-mark-all');
  if (markAll) markAll.addEventListener('click', function(e){
    e.preventDefault();
    fetch(markAll.href).then(function(r){ return r.json(); }).then(function(){
      document.querySelectorAll('.notif-badge, .notif-badge-sm').forEach(function(el){ el.remove(); });
      list.querySelectorAll('.notif-item.unread').forEach(function(el){ el.classList.remove('unread'); });
      markAll.remove();
    });
  });
})();
</script>
