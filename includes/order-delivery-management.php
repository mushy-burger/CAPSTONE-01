<?php
/** @var string $orderDeliveryPage */
?>
<div class="mtx-modal order-delivery-modal" id="orderDeliveryModal" role="dialog" aria-modal="true" aria-labelledby="orderDeliveryTitle" hidden>
  <div class="mtx-modal__backdrop" data-order-delivery-close aria-hidden="true"></div>
  <section class="mtx-modal__dialog order-delivery-modal__dialog" role="document">
    <button type="button" class="mtx-modal__close" data-order-delivery-close aria-label="Close delivery tracking"><i class="fas fa-xmark" aria-hidden="true"></i></button>
    <div class="order-delivery-modal__icon" aria-hidden="true"><i class="fas fa-truck"></i></div>
    <h2 class="mtx-modal__title" id="orderDeliveryTitle">Delivery Tracking</h2>
    <p class="order-delivery-modal__message">Manage Lalamove tracking for Order <strong id="orderDeliveryNumber"></strong>.</p>
    <div class="order-delivery-modal__status"><span>Delivery status</span><strong id="orderDeliveryStatus"></strong></div>
    <form method="post" id="orderDeliveryTrackingForm" class="order-delivery-modal__form">
      <?= authContextField() ?>
      <input type="hidden" name="action" value="save_tracking">
      <input type="hidden" name="order_id" id="orderDeliveryOrderId">
      <label class="order-delivery-modal__field" for="orderDeliveryTrackingUrl">
        <span>Lalamove Tracking Link</span>
        <input type="url" name="tracking_url" id="orderDeliveryTrackingUrl" inputmode="url" placeholder="Paste tracking link here" required>
      </label>
      <p class="order-delivery-modal__hint">Saving a valid link automatically changes this order to Delivering.</p>
      <div class="order-delivery-modal__links">
        <a class="mtx-btn mtx-btn--ghost" id="orderDeliveryOpenLink" href="#" target="_blank" rel="noopener noreferrer" hidden><i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i> Open Tracking</a>
        <button type="submit" class="mtx-btn mtx-btn--primary" id="orderDeliverySave"><i class="fas fa-floppy-disk" aria-hidden="true"></i> Save Tracking Link</button>
      </div>
    </form>
    <div class="order-delivery-modal__footer">
      <button type="button" class="mtx-btn mtx-btn--ghost" data-order-delivery-close>Close</button>
    </div>
  </section>
</div>

<div class="mtx-modal order-delivered-modal" id="orderDeliveredModal" role="dialog" aria-modal="true" aria-labelledby="orderDeliveredTitle" hidden>
  <div class="mtx-modal__backdrop" data-order-delivered-close aria-hidden="true"></div>
  <section class="mtx-modal__dialog order-delivered-modal__dialog" role="document">
    <button type="button" class="mtx-modal__close" data-order-delivered-close aria-label="Close delivered order confirmation"><i class="fas fa-xmark" aria-hidden="true"></i></button>
    <div class="order-delivered-modal__icon" aria-hidden="true"><i class="fas fa-circle-check"></i></div>
    <h2 class="mtx-modal__title" id="orderDeliveredTitle">Mark Order as Delivered?</h2>
    <p class="order-delivered-modal__message">Confirm that <strong id="orderDeliveredNumber"></strong> has been successfully delivered to the customer.</p>
    <p class="order-delivered-modal__support">This marks delivery complete and notifies the customer.</p>
    <form method="post" id="orderDeliveredForm" class="order-delivered-modal__form">
      <?= authContextField() ?>
      <input type="hidden" name="action" value="mark_delivered">
      <input type="hidden" name="order_id" id="orderDeliveredOrderId">
      <div class="order-delivered-modal__actions">
        <button type="button" class="mtx-btn mtx-btn--ghost" data-order-delivered-close>Cancel</button>
        <button type="submit" class="mtx-btn order-delivered-modal__confirm" id="orderDeliveredConfirm"><i class="fas fa-circle-check" aria-hidden="true"></i> Mark as Delivered</button>
      </div>
    </form>
  </section>
</div>

<script>
(function () {
  var trackingModal = document.getElementById('orderDeliveryModal');
  var deliveredModal = document.getElementById('orderDeliveredModal');
  var trackingForm = document.getElementById('orderDeliveryTrackingForm');
  var deliveredForm = document.getElementById('orderDeliveredForm');
  if (!trackingModal || !deliveredModal || !trackingForm || !deliveredForm) return;

  var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  var closeTimers = new WeakMap();
  var lastTriggers = new WeakMap();
  function closeDuration() { return reducedMotion.matches ? 120 : 170; }
  function focusable(modal) {
    return Array.prototype.slice.call(modal.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'));
  }
  function closeModal(modal, restoreFocus, afterClose) {
    if (modal.hidden) return;
    window.clearTimeout(closeTimers.get(modal));
    modal.classList.remove('is-opening', 'is-open');
    modal.classList.add('is-closing');
    closeTimers.set(modal, window.setTimeout(function () {
      modal.classList.remove('is-closing');
      modal.hidden = true;
      if (typeof afterClose === 'function') afterClose();
      if (restoreFocus !== false) {
        var trigger = lastTriggers.get(modal);
        if (trigger && document.contains(trigger)) trigger.focus({ preventScroll: true });
      }
    }, closeDuration()));
  }
  function openModal(modal, trigger) {
    lastTriggers.set(modal, trigger || document.activeElement);
    window.clearTimeout(closeTimers.get(modal));
    modal.hidden = false;
    modal.classList.remove('is-closing', 'is-open');
    modal.classList.add('is-opening');
    window.requestAnimationFrame(function () {
      modal.classList.remove('is-opening');
      modal.classList.add('is-open');
      var elements = focusable(modal);
      if (elements.length) elements[0].focus({ preventScroll: true });
    });
  }
  document.addEventListener('keydown', function (event) {
    var modal = !deliveredModal.hidden ? deliveredModal : (!trackingModal.hidden ? trackingModal : null);
    if (!modal) return;
    if (event.key === 'Escape') {
      event.preventDefault();
      closeModal(modal);
      return;
    }
    if (event.key !== 'Tab') return;
    var elements = focusable(modal);
    if (!elements.length) return;
    var first = elements[0];
    var last = elements[elements.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });
  trackingModal.querySelectorAll('[data-order-delivery-close]').forEach(function (control) {
    control.addEventListener('click', function () { closeModal(trackingModal); });
  });
  deliveredModal.querySelectorAll('[data-order-delivered-close]').forEach(function (control) {
    control.addEventListener('click', function () { closeModal(deliveredModal); });
  });

  var statusLabel = document.getElementById('orderDeliveryStatus');
  var orderNumber = document.getElementById('orderDeliveryNumber');
  var trackingUrl = document.getElementById('orderDeliveryTrackingUrl');
  var trackingOrderId = document.getElementById('orderDeliveryOrderId');
  var openTracking = document.getElementById('orderDeliveryOpenLink');
  document.querySelectorAll('[data-order-delivery-open]').forEach(function (trigger) {
    trigger.addEventListener('click', function () {
      var id = trigger.getAttribute('data-order-id') || '';
      var status = trigger.getAttribute('data-delivery-status') || 'pending';
      var url = trigger.getAttribute('data-tracking-url') || '';
      orderNumber.textContent = '#' + id;
      trackingOrderId.value = id;
      trackingUrl.value = url;
      statusLabel.textContent = status.charAt(0).toUpperCase() + status.slice(1);
      statusLabel.className = 'is-' + status;
      openTracking.hidden = url === '';
      openTracking.href = url || '#';
      openModal(trackingModal, trigger);
    });
  });
  function openDeliveredConfirmation(id, label, trigger) {
    document.getElementById('orderDeliveredNumber').textContent = label || ('#' + id);
    document.getElementById('orderDeliveredOrderId').value = id;
    openModal(deliveredModal, trigger);
  }
  document.querySelectorAll('[data-order-delivered-open]').forEach(function (trigger) {
    trigger.addEventListener('click', function () {
      openDeliveredConfirmation(trigger.getAttribute('data-order-id') || '', '#' + (trigger.getAttribute('data-order-id') || ''), trigger);
    });
  });
  trackingForm.addEventListener('submit', function (event) {
    if (trackingForm.dataset.submitting === 'true') {
      event.preventDefault();
      return;
    }
    trackingForm.dataset.submitting = 'true';
    var button = document.getElementById('orderDeliverySave');
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    button.innerHTML = '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Saving...';
  });
  deliveredForm.addEventListener('submit', function (event) {
    if (deliveredForm.dataset.submitting === 'true') {
      event.preventDefault();
      return;
    }
    deliveredForm.dataset.submitting = 'true';
    var button = document.getElementById('orderDeliveredConfirm');
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    button.innerHTML = '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Marking...';
  });
}());
</script>
