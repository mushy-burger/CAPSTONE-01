<?php
/**
 * Live Manila date/time chip, shared by the Admin, Staff, and Technician dashboards.
 *
 * Renders a `.mtx-page-head-meta` chip that self-updates every second in the
 * Asia/Manila timezone, and keeps any `[data-greeting]` element in sync
 * (Good Morning / Afternoon / Evening). Include once per page, inside the
 * `.mtx-page-head` header. Display-only — no backend involvement.
 */
$mtxManilaNow = new DateTime('now', new DateTimeZone('Asia/Manila'));
?>
<div class="mtx-page-head-meta mtx-live-clock">
  <i class="fas fa-calendar"></i> <span data-clock-date><?= $mtxManilaNow->format('l, F j, Y') ?></span>
  <i class="fas fa-clock"></i> <strong data-clock-time><?= $mtxManilaNow->format('g:i:s A') ?></strong>
</div>
<script>
(function () {
  if (window.__mtxClockStarted) return;
  window.__mtxClockStarted = true;

  var TZ = 'Asia/Manila';
  var dateFmt = new Intl.DateTimeFormat('en-US', { timeZone: TZ, weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
  var timeFmt = new Intl.DateTimeFormat('en-US', { timeZone: TZ, hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true });
  var hourFmt = new Intl.DateTimeFormat('en-US', { timeZone: TZ, hour: 'numeric', hour12: false });

  function tick() {
    var now = new Date();
    var dateStr = dateFmt.format(now);
    var timeStr = timeFmt.format(now);
    var hour = parseInt(hourFmt.format(now), 10) % 24;
    var greeting = hour < 12 ? 'Good Morning' : (hour < 18 ? 'Good Afternoon' : 'Good Evening');

    document.querySelectorAll('[data-clock-date]').forEach(function (el) { el.textContent = dateStr; });
    document.querySelectorAll('[data-clock-time]').forEach(function (el) { el.textContent = timeStr; });
    document.querySelectorAll('[data-greeting]').forEach(function (el) { el.textContent = greeting; });
  }

  tick();
  setInterval(tick, 1000);
})();
</script>
