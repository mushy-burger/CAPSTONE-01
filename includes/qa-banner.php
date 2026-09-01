<?php
// Only render the banner when a QA user is viewing
require_once __DIR__ . '/auth.php';
$_qa_user = getCurrentUser();
if (!$_qa_user || $_qa_user['role'] !== 'qa') return;
?>
<div id="qa-mode-bar">
  <span class="qa-badge-pill"><i class="fas fa-vial"></i> QA MODE</span>
  <span class="qa-bar-label">
    You are viewing as <strong>QA Tester</strong> — read-only observer.
  </span>
  <div class="qa-bar-links">
    <a href="<?= baseUrl('qa/index.php') ?>"><i class="fas fa-th-large"></i> QA Hub</a>
    <a href="<?= baseUrl('admin/index.php') ?>"><i class="fas fa-user-shield"></i> Admin</a>
    <a href="<?= baseUrl('staff/index.php') ?>"><i class="fas fa-user-tie"></i> Staff</a>
    <a href="<?= baseUrl('tech/index.php') ?>"><i class="fas fa-wrench"></i> Tech</a>
    <a href="<?= baseUrl('index.php') ?>"><i class="fas fa-globe"></i> Public</a>
    <a href="<?= baseUrl('logout.php') ?>" class="qa-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
  </div>
</div>

<style>
#qa-mode-bar {
  position: fixed;
  bottom: 0; left: 0; right: 0;
  z-index: 99999;
  background: linear-gradient(90deg, #1a0533 0%, #3b0764 50%, #1a0533 100%);
  border-top: 2px solid #a855f7;
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 8px 20px;
  font-family: 'Inter', system-ui, sans-serif;
  font-size: 0.82rem;
  box-shadow: 0 -4px 24px rgba(168,85,247,.35);
  flex-wrap: wrap;
}
.qa-badge-pill {
  background: #a855f7;
  color: #fff;
  font-weight: 700;
  font-size: 0.75rem;
  letter-spacing: .08em;
  padding: 3px 10px;
  border-radius: 99px;
  white-space: nowrap;
  flex-shrink: 0;
}
.qa-bar-label {
  color: rgba(255,255,255,.75);
  flex: 1;
  min-width: 180px;
}
.qa-bar-label strong { color: #e9d5ff; }
.qa-bar-links {
  display: flex;
  gap: 6px;
  align-items: center;
  flex-wrap: wrap;
}
.qa-bar-links a {
  color: #e9d5ff;
  text-decoration: none;
  padding: 4px 10px;
  border-radius: 6px;
  border: 1px solid rgba(168,85,247,.4);
  font-size: 0.78rem;
  transition: background .15s, border-color .15s;
  display: flex;
  align-items: center;
  gap: 5px;
  white-space: nowrap;
}
.qa-bar-links a:hover { background: rgba(168,85,247,.3); border-color: #a855f7; }
.qa-bar-links a.qa-logout { border-color: rgba(239,68,68,.4); color: #fca5a5; }
.qa-bar-links a.qa-logout:hover { background: rgba(239,68,68,.2); border-color: #ef4444; }

/* push page content up so the bar doesn't cover it */
body { padding-bottom: 52px !important; }
</style>
