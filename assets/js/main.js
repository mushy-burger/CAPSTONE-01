function makeTabContext() {
  if (!window.sessionStorage) return null;
  let ctx = sessionStorage.getItem('mototrack_tab_ctx');
  const current = new URL(window.location.href);
  const urlCtx = current.searchParams.get('ctx');
  if (urlCtx && /^[a-zA-Z0-9_-]{8,64}$/.test(urlCtx)) {
    ctx = urlCtx;
    sessionStorage.setItem('mototrack_tab_ctx', ctx);
    return ctx;
  }

  if (!ctx) {
    const randomPart = Math.random().toString(36).slice(2);
    ctx = `tab_${Date.now().toString(36)}_${randomPart}`;
    sessionStorage.setItem('mototrack_tab_ctx', ctx);
  }
  return ctx;
}

function isSameOriginPhpUrl(url) {
  return url.origin === window.location.origin && url.pathname.endsWith('.php');
}

function withTabContext(value, ctx) {
  if (!ctx) return value;

  const url = new URL(value, window.location.href);
  if (!isSameOriginPhpUrl(url)) return value;

  url.searchParams.set('ctx', ctx);
  return url.toString();
}

function syncTabContextTargets(root = document) {
  const ctx = makeTabContext();
  if (!ctx) return;

  root.querySelectorAll?.('a[href]').forEach((link) => {
    const href = link.getAttribute('href');
    if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:')) return;
    link.href = withTabContext(href, ctx);
  });

  root.querySelectorAll?.('form').forEach((form) => {
    const action = form.getAttribute('action');
    if (action) {
      form.action = withTabContext(action, ctx);
    }

    let input = form.querySelector('input[name="ctx"]');
    if (!input) {
      input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'ctx';
      form.appendChild(input);
    }
    input.value = ctx;
  });
}

function preserveTabContext() {
  const ctx = makeTabContext();
  if (!ctx) return;

  const current = new URL(window.location.href);
  const isPhpPage = current.pathname.endsWith('.php') || current.pathname.endsWith('/');
  if (isPhpPage && !current.searchParams.has('ctx')) {
    current.searchParams.set('ctx', ctx);
    window.location.replace(current.toString());
    return;
  }

  syncTabContextTargets(document);
}

preserveTabContext();

document.addEventListener('DOMContentLoaded', () => syncTabContextTargets(document));
document.addEventListener('click', (event) => {
  const link = event.target.closest?.('a[href]');
  if (link) syncTabContextTargets(document);
}, true);
document.addEventListener('submit', (event) => {
  const form = event.target.closest?.('form');
  if (form) syncTabContextTargets(form.parentElement || document);
}, true);

if (!window.__mototrackFetchContextPatched) {
  window.__mototrackFetchContextPatched = true;
  const originalFetch = window.fetch.bind(window);

  window.fetch = (resource, options = {}) => {
    const ctx = makeTabContext();
    let nextResource = resource;
    const nextOptions = { ...options };
    let isSameOriginRequest = false;

    if (ctx) {
      if (typeof resource === 'string' || resource instanceof URL) {
        const url = new URL(resource.toString(), window.location.href);
        if (url.origin === window.location.origin) {
          isSameOriginRequest = true;
          url.searchParams.set('ctx', ctx);
          nextResource = url.toString();
        }
      } else if (resource instanceof Request) {
        const url = new URL(resource.url, window.location.href);
        if (url.origin === window.location.origin) {
          isSameOriginRequest = true;
          url.searchParams.set('ctx', ctx);
          nextResource = new Request(url.toString(), resource);
        }
      }

      if (isSameOriginRequest) {
        const headers = new Headers(nextOptions.headers || (nextResource instanceof Request ? nextResource.headers : undefined));
        headers.set('X-Auth-Context', ctx);
        nextOptions.headers = headers;
      }
    }

    return originalFetch(nextResource, nextOptions);
  };
}

if (window.MutationObserver) {
  let syncQueued = false;
  const observer = new MutationObserver((mutations) => {
    if (!syncQueued && mutations.some((mutation) => mutation.addedNodes.length > 0)) {
      syncQueued = true;
      window.setTimeout(() => {
        syncQueued = false;
        syncTabContextTargets(document);
      }, 0);
    }
  });
  observer.observe(document.documentElement, { childList: true, subtree: true });
}

function clearPageTextSelection() {
  const active = document.activeElement;
  const isEditable = active && (
    active.tagName === 'INPUT'
    || active.tagName === 'TEXTAREA'
    || active.isContentEditable
  );

  if (isEditable) return;

  const selection = window.getSelection ? window.getSelection() : null;
  if (selection && !selection.isCollapsed) {
    selection.removeAllRanges();
  }
}

window.addEventListener('pageshow', clearPageTextSelection);
document.addEventListener('DOMContentLoaded', clearPageTextSelection);
document.addEventListener('mouseup', () => window.setTimeout(clearPageTextSelection, 0));
document.addEventListener('keyup', (event) => {
  if (event.key === 'Escape' || event.ctrlKey || event.metaKey || event.shiftKey) {
    window.setTimeout(clearPageTextSelection, 0);
  }
});
document.addEventListener('selectionchange', () => {
  window.setTimeout(clearPageTextSelection, 0);
});

const menuToggle = document.getElementById('menuToggle');
const mobileNav = document.getElementById('mobileNav');

if (menuToggle && mobileNav) {
  menuToggle.addEventListener('click', () => mobileNav.classList.toggle('open'));
}

const cartCheckoutForm = document.getElementById('cartCheckoutForm');

if (cartCheckoutForm) {
  const currency = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    minimumFractionDigits: 2,
  });
  const rows = Array.from(document.querySelectorAll('[data-cart-row]'));
  const subtotalTarget = document.querySelector('[data-cart-selected-subtotal]');
  const totalTarget = document.querySelector('[data-cart-selected-total]');
  const checkoutBtn = document.querySelector('[data-cart-checkout-btn]');
  const message = document.querySelector('[data-cart-selection-message]');

  const clampQuantity = (input) => {
    const min = Number(input.min || 1);
    const max = Number(input.max || 9999);
    const value = Math.max(min, Math.min(Number(input.value || min), max));
    input.value = String(value);
    return value;
  };

  const updateCartTotals = () => {
    let selectedTotal = 0;
    let selectedCount = 0;

    rows.forEach((row) => {
      const checkbox = row.querySelector('.cart-select-checkbox');
      const qtyInput = row.querySelector('[data-cart-qty]');
      const lineTarget = row.querySelector('[data-cart-line-subtotal]');
      const price = Number(row.dataset.price || 0);
      const qty = qtyInput ? clampQuantity(qtyInput) : 1;
      const lineTotal = price * qty;

      if (lineTarget) lineTarget.textContent = currency.format(lineTotal);
      if (checkbox?.checked) {
        selectedCount += 1;
        selectedTotal += lineTotal;
      }
    });

    if (subtotalTarget) subtotalTarget.textContent = currency.format(selectedTotal);
    if (totalTarget) totalTarget.textContent = currency.format(selectedTotal);
    if (checkoutBtn) checkoutBtn.disabled = selectedCount === 0;
    if (message) message.textContent = selectedCount === 0 ? 'Select at least one item to checkout.' : `${selectedCount} item${selectedCount === 1 ? '' : 's'} selected.`;
  };

  rows.forEach((row) => {
    const qtyInput = row.querySelector('[data-cart-qty]');
    const checkbox = row.querySelector('.cart-select-checkbox');
    row.querySelector('[data-cart-qty-minus]')?.addEventListener('click', () => {
      if (!qtyInput) return;
      qtyInput.value = String(Math.max(Number(qtyInput.min || 1), Number(qtyInput.value || 1) - 1));
      updateCartTotals();
    });
    row.querySelector('[data-cart-qty-plus]')?.addEventListener('click', () => {
      if (!qtyInput) return;
      qtyInput.value = String(Math.min(Number(qtyInput.max || 9999), Number(qtyInput.value || 1) + 1));
      updateCartTotals();
    });
    qtyInput?.addEventListener('input', updateCartTotals);
    checkbox?.addEventListener('change', updateCartTotals);
  });

  cartCheckoutForm.addEventListener('submit', (event) => {
    const hasSelection = rows.some((row) => row.querySelector('.cart-select-checkbox')?.checked);
    if (!hasSelection) {
      event.preventDefault();
      if (message) message.textContent = 'Select at least one item to checkout.';
    }
  });

  updateCartTotals();
}

document.querySelectorAll('.password-toggle').forEach((button) => {
  button.addEventListener('click', () => {
    const field = button.closest('.password-field');
    const input = field ? field.querySelector('input') : null;
    const icon = button.querySelector('i');
    if (!input) return;
    const shouldShow = input.type === 'password';
    input.type = shouldShow ? 'text' : 'password';
    button.setAttribute('aria-label', shouldShow ? 'Hide password' : 'Show password');
    if (icon) {
      icon.classList.toggle('fa-eye', !shouldShow);
      icon.classList.toggle('fa-eye-slash', shouldShow);
    }
  });
});

const typeSelect = document.getElementById('typeSelect');
const brandSelect = document.getElementById('brandSelect');
const modelSelect = document.getElementById('modelSelect');
const ccDisplay = document.getElementById('ccDisplay');

function filterVehicleModels() {
  if (!typeSelect || !brandSelect || !modelSelect) return;
  const typeName = typeSelect.value.trim().toLowerCase();
  const brandName = brandSelect.value.trim().toLowerCase();
  Array.from(modelSelect.options).forEach((option) => {
    if (!option.value) return;
    const optionType = (option.dataset.type || '').trim().toLowerCase();
    const optionBrand = (option.dataset.brand || '').trim().toLowerCase();
    const matchesType = !typeName || optionType === typeName;
    const matchesBrand = !brandName || optionBrand === brandName;
    option.hidden = !(matchesType && matchesBrand);
  });
  const selected = modelSelect.selectedOptions[0];
  if (selected && selected.hidden) modelSelect.value = '';
  if (ccDisplay && (!modelSelect.value || !modelSelect.selectedOptions[0]?.dataset.cc)) ccDisplay.textContent = '-';
}

if (typeSelect && brandSelect && modelSelect) {
  typeSelect.addEventListener('change', filterVehicleModels);
  brandSelect.addEventListener('change', filterVehicleModels);
  modelSelect.addEventListener('change', () => {
    const selected = modelSelect.selectedOptions[0];
    if (selected && selected.dataset.cc && ccDisplay) ccDisplay.textContent = `${selected.dataset.cc}cc`;
  });
  filterVehicleModels();
  if (modelSelect.value && modelSelect.selectedOptions[0]?.dataset.cc && ccDisplay) {
    ccDisplay.textContent = `${modelSelect.selectedOptions[0].dataset.cc}cc`;
  }
}

const vehicleManager = document.getElementById('vehicleManager');

if (vehicleManager) {
  const editModal = document.getElementById('motorcycleEditModal');
  const editForm = document.getElementById('motorcycleEditForm');
  const editMotorcycleId = document.getElementById('editMotorcycleId');
  const editMotorcycleType = document.getElementById('editMotorcycleType');
  const editMotorcycleBrand = document.getElementById('editMotorcycleBrand');
  const editMotorcycleModel = document.getElementById('editMotorcycleModel');
  const editMotorcycleCc = document.getElementById('editMotorcycleCc');
  const wizardModal = document.getElementById('motorcycleWizardModal');
  const wizardSteps = wizardModal ? Array.from(wizardModal.querySelectorAll('[data-step]')) : [];
  const openWizardBtn = document.getElementById('openMotorcycleWizard');
  const closeModalButtons = wizardModal ? wizardModal.querySelectorAll('[data-close-modal]') : [];
  const nextStepButtons = wizardModal ? wizardModal.querySelectorAll('[data-next-step]') : [];
  const prevStepButtons = wizardModal ? wizardModal.querySelectorAll('[data-prev-step]') : [];
  const wizardTypeInput = document.getElementById('wizardTypeInput');
  const wizardBrandInput = document.getElementById('wizardBrandInput');
  const wizardModelInput = document.getElementById('wizardModelInput');
  const wizardSearchStatus = document.getElementById('wizardSearchStatus');
  const searchMotorcycleSpecBtn = document.getElementById('searchMotorcycleSpecBtn');
  const resultTypeValue = document.getElementById('resultTypeValue');
  const resultBrandValue = document.getElementById('resultBrandValue');
  const resultModelValue = document.getElementById('resultModelValue');
  const resultCcValue = document.getElementById('resultCcValue');
  const manualCcInput = document.getElementById('manualCcInput');
  const wizardResultMessage = document.getElementById('wizardResultMessage');
  const wizardEditBtn = document.getElementById('wizardEditBtn');
  const wizardSaveForm = document.getElementById('wizardSaveForm');
  const candidatePanel = document.getElementById('candidatePanel');
  const candidateList = document.getElementById('candidateList');
  const candidateHiddenInputs = document.getElementById('candidateHiddenInputs');
  const saveTypeInput = document.getElementById('saveTypeInput');
  const quickForm = document.querySelector('.vehicle-quick-form');
  const editButtons = document.querySelectorAll('.js-edit-motorcycle');
  const baseUrl = vehicleManager.dataset.baseUrl || '';
  const selectAllMotorcycles = document.getElementById('selectAllMotorcycles');
  const motorcycleSelectBoxes = Array.from(document.querySelectorAll('.js-motorcycle-select'));
  const bulkMotorcycleDeleteBtn = document.getElementById('bulkMotorcycleDeleteBtn');

  let currentStep = 1;
  let activeCandidates = [];
  let activeCcRange = null;
  let activeResult = null;

  const getCtx = () => {
    if (!window.sessionStorage) return '';
    return sessionStorage.getItem('mototrack_tab_ctx') || '';
  };

  const showStep = (step) => {
    currentStep = step;
    wizardSteps.forEach((item) => {
      item.classList.toggle('is-active', item.dataset.step === String(step));
    });
  };

  const resetWizardState = () => {
    activeCandidates = [];
    activeCcRange = null;
    activeResult = null;
    if (wizardTypeInput) wizardTypeInput.value = '';
    if (wizardBrandInput) wizardBrandInput.value = '';
    if (wizardModelInput) wizardModelInput.value = '';
    if (wizardSearchStatus) wizardSearchStatus.textContent = '';
    if (wizardResultMessage) wizardResultMessage.textContent = 'Review the engine cc before saving.';
    if (resultTypeValue) resultTypeValue.textContent = '-';
    if (resultBrandValue) resultBrandValue.textContent = '-';
    if (resultModelValue) resultModelValue.textContent = '-';
    if (resultCcValue) resultCcValue.textContent = '-';
    if (manualCcInput) {
      manualCcInput.value = '';
      manualCcInput.hidden = true;
    }
    if (saveTypeInput) saveTypeInput.value = '';
    if (candidateList) candidateList.innerHTML = '';
    if (candidateHiddenInputs) candidateHiddenInputs.innerHTML = '';
    if (candidatePanel) candidatePanel.hidden = true;
  };

  const openModal = () => {
    if (!wizardModal) return;
    wizardModal.classList.add('is-open');
    wizardModal.setAttribute('aria-hidden', 'false');
    resetWizardState();
    showStep(1);
    setTimeout(() => wizardTypeInput?.focus(), 20);
  };

  const closeModal = () => {
    if (!wizardModal) return;
    wizardModal.classList.remove('is-open');
    wizardModal.setAttribute('aria-hidden', 'true');
  };

  const openEditModal = (button) => {
    if (!editModal) return;
    if (editMotorcycleId) editMotorcycleId.value = button.dataset.id || '';
    if (editMotorcycleType) editMotorcycleType.value = button.dataset.type || '';
    if (editMotorcycleBrand) editMotorcycleBrand.value = button.dataset.brand || '';
    if (editMotorcycleModel) editMotorcycleModel.value = button.dataset.model || '';
    if (editMotorcycleCc) editMotorcycleCc.value = button.dataset.cc || '';
    editModal.classList.add('is-open');
    editModal.setAttribute('aria-hidden', 'false');
    setTimeout(() => editMotorcycleType?.focus(), 20);
  };

  const closeEditModal = () => {
    if (!editModal) return;
    editModal.classList.remove('is-open');
    editModal.setAttribute('aria-hidden', 'true');
  };

  const validateStep = (step) => {
    if (step === 1 && wizardTypeInput && !wizardTypeInput.value.trim()) {
      wizardSearchStatus.textContent = 'Motorcycle type is required.';
      wizardTypeInput.focus();
      return false;
    }
    if (step === 2 && wizardBrandInput && !wizardBrandInput.value.trim()) {
      wizardSearchStatus.textContent = 'Brand is required.';
      wizardBrandInput.focus();
      return false;
    }
    if (step === 3 && wizardModelInput && !wizardModelInput.value.trim()) {
      wizardSearchStatus.textContent = 'Model is required.';
      wizardModelInput.focus();
      return false;
    }
    wizardSearchStatus.textContent = '';
    return true;
  };

  const renderCandidateList = () => {
    if (!candidatePanel || !candidateList) return;
    candidateList.innerHTML = '';
    if (!activeCandidates.length) {
      candidatePanel.hidden = true;
      return;
    }
    candidatePanel.hidden = false;
    activeCandidates.forEach((candidate, index) => {
      const row = document.createElement('label');
      row.className = 'vehicle-candidate-item';
      const checkbox = document.createElement('input');
      checkbox.type = 'checkbox';
      checkbox.checked = true;
      checkbox.dataset.candidateIndex = String(index);
      const wrap = document.createElement('span');
      const title = document.createElement('strong');
      title.textContent = candidate.model || 'Unknown model';
      const meta = document.createElement('small');
      meta.textContent = `${candidate.brand || ''} ${candidate.cc || ''}${candidate.year ? ` - ${candidate.year}` : ''}`.trim();
      wrap.appendChild(title);
      wrap.appendChild(meta);
      row.appendChild(checkbox);
      row.appendChild(wrap);
      candidateList.appendChild(row);
    });
  };

  const parseCc = (value) => {
    const match = String(value || '').match(/([0-9]{2,4})/);
    return match ? parseInt(match[1], 10) : 0;
  };

  const applySearchResult = (result, found = true) => {
    activeResult = result || null;
    activeCandidates = Array.isArray(result.candidates) ? result.candidates : [];
    activeCandidates = activeCandidates.slice().sort((left, right) => parseCc(left.cc) - parseCc(right.cc));
    activeCcRange = Array.isArray(result.cc_range) && result.cc_range.length === 2 ? result.cc_range : null;
    const hasCandidates = activeCandidates.length > 0;
    const shouldRequireManual = !found && !hasCandidates;

    if (resultTypeValue) resultTypeValue.textContent = result.type || '-';
    if (resultBrandValue) resultBrandValue.textContent = result.brand || '-';
    if (resultModelValue) resultModelValue.textContent = result.model || '-';
    if (resultCcValue) {
      resultCcValue.textContent = activeCcRange
        ? `${activeCcRange[0]}cc to ${activeCcRange[1]}cc`
        : (result.cc || 'Not found');
    }
    if (saveTypeInput) saveTypeInput.value = result.type || '';

    if (shouldRequireManual && manualCcInput) {
      manualCcInput.hidden = false;
      manualCcInput.focus();
      if (resultCcValue) resultCcValue.textContent = 'Manual input required';
    } else if (manualCcInput) {
      manualCcInput.hidden = true;
      manualCcInput.value = result.cc || '';
    }

    renderCandidateList();
    showStep('result');
  };

  openWizardBtn?.addEventListener('click', openModal);
  closeModalButtons.forEach((button) => button.addEventListener('click', closeModal));
  nextStepButtons.forEach((button) => {
    button.addEventListener('click', () => {
      if (!validateStep(currentStep)) return;
      showStep(currentStep + 1);
    });
  });
  prevStepButtons.forEach((button) => {
    button.addEventListener('click', () => {
      if (currentStep === 'result') showStep(3);
      else showStep(Math.max(1, Number(currentStep) - 1));
      wizardSearchStatus.textContent = '';
    });
  });

  wizardEditBtn?.addEventListener('click', () => {
    showStep(3);
    wizardSearchStatus.textContent = '';
    wizardModelInput?.focus();
  });

  manualCcInput?.addEventListener('input', () => {
    if (saveTypeInput) saveTypeInput.dataset.manualCc = manualCcInput.value.trim();
  });

  wizardSaveForm?.addEventListener('submit', (event) => {
    const selectedCandidates = candidateList
      ? Array.from(candidateList.querySelectorAll('input[type="checkbox"]'))
          .map((checkbox, index) => ({ checkbox, candidate: activeCandidates[index] }))
          .filter((entry) => entry.checkbox.checked && entry.candidate)
          .map((entry) => entry.candidate)
      : [];
    const finalCc = manualCcInput && !manualCcInput.hidden
      ? manualCcInput.value.trim()
      : String(activeResult?.cc || '').trim();
    const finalType = saveTypeInput?.value.trim() || '';
    const finalBrand = resultBrandValue?.textContent.trim() || '';
    const finalModel = resultModelValue?.textContent.trim() || '';

    if (!finalCc && selectedCandidates.length === 0) {
      event.preventDefault();
      wizardSearchStatus.textContent = 'Engine cc is required before saving.';
      manualCcInput?.focus();
      return;
    }

    if (candidateHiddenInputs) candidateHiddenInputs.innerHTML = '';

    if (selectedCandidates.length > 0 && candidateHiddenInputs) {
      selectedCandidates.forEach((candidate, index) => {
        const prefix = `selected_candidates[${index}]`;
        [
          ['type', finalType],
          ['brand', candidate.brand || finalBrand],
          ['model', candidate.model || ''],
          ['cc', candidate.cc || ''],
        ].forEach(([field, value]) => {
          const input = document.createElement('input');
          input.type = 'hidden';
          input.name = `${prefix}[${field}]`;
          input.value = value;
          candidateHiddenInputs.appendChild(input);
        });
      });
    } else if (candidateHiddenInputs) {
      [
        ['type', finalType],
        ['brand', finalBrand],
        ['model', finalModel],
        ['cc', finalCc],
      ].forEach(([field, value]) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = field;
        input.value = value;
        candidateHiddenInputs.appendChild(input);
      });
    }
  });

  searchMotorcycleSpecBtn?.addEventListener('click', async () => {
    if (!validateStep(3)) return;

    const type = wizardTypeInput?.value.trim() || '';
    const brand = wizardBrandInput?.value.trim() || '';
    const model = wizardModelInput?.value.trim() || '';
    const params = new URLSearchParams({ type, brand, model });
    const ctx = getCtx();
    if (ctx) params.set('ctx', ctx);

    wizardSearchStatus.textContent = 'Searching motorcycle specification...';
    searchMotorcycleSpecBtn.disabled = true;

    try {
      const response = await fetch(`${baseUrl}api/admin/motorcycle/search.php?${params.toString()}`);
      const data = await response.json();
      const result = data.motorcycle || { type, brand, model, cc: '' };
      if (data.candidates) result.candidates = data.candidates;
      if (data.cc_range) result.cc_range = data.cc_range;
      if (data.success) {
        wizardResultMessage.textContent = 'Motorcycle information found. Review before saving.';
        applySearchResult(result, true);
      } else {
        wizardResultMessage.textContent = data.message || 'No motorcycle specification found.';
        applySearchResult(result, false);
      }
    } catch (error) {
      wizardResultMessage.textContent = 'Search request failed. Check API connection or admin session.';
      applySearchResult({ type, brand, model, cc: '' }, false);
    } finally {
      wizardSearchStatus.textContent = '';
      searchMotorcycleSpecBtn.disabled = false;
    }
  });

  editButtons.forEach((button) => {
    button.addEventListener('click', () => {
      openEditModal(button);
    });
  });

  const syncBulkMotorcycleDelete = () => {
    const selectedCount = motorcycleSelectBoxes.filter((checkbox) => checkbox.checked).length;
    if (bulkMotorcycleDeleteBtn) {
      bulkMotorcycleDeleteBtn.disabled = selectedCount === 0;
      bulkMotorcycleDeleteBtn.textContent = selectedCount > 0
        ? `Delete Selected (${selectedCount})`
        : 'Delete Selected';
    }
    if (selectAllMotorcycles) {
      selectAllMotorcycles.checked = selectedCount > 0 && selectedCount === motorcycleSelectBoxes.length;
      selectAllMotorcycles.indeterminate = selectedCount > 0 && selectedCount < motorcycleSelectBoxes.length;
    }
  };

  selectAllMotorcycles?.addEventListener('change', () => {
    motorcycleSelectBoxes.forEach((checkbox) => {
      checkbox.checked = selectAllMotorcycles.checked;
    });
    syncBulkMotorcycleDelete();
  });

  motorcycleSelectBoxes.forEach((checkbox) => {
    checkbox.addEventListener('change', syncBulkMotorcycleDelete);
  });

  syncBulkMotorcycleDelete();

  editModal?.querySelectorAll('[data-close-edit-modal]').forEach((button) => {
    button.addEventListener('click', closeEditModal);
  });

  editForm?.addEventListener('submit', () => {
    closeEditModal();
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && wizardModal?.classList.contains('is-open')) {
      closeModal();
    }
  });
}
