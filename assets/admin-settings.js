/* KAPIHAN Admin — Settings Center behavior (navigation, dirty tracking, AJAX save, uploads, confirmations) */
(function () {
  'use strict';
  var root = document.querySelector('[data-settings-root]');
  if (!root) return;

  var panels = root.querySelectorAll('.sx-panel');
  var navBtns = root.querySelectorAll('[data-sx-nav]');
  var select = document.getElementById('sxSelect');
  var forms = root.querySelectorAll('.settings-form');
  var csrfEl = document.querySelector('input[name="csrf"]');
  var MAX_BYTES = 2 * 1024 * 1024;
  var TYPES = ['image/jpeg', 'image/png', 'image/webp'];

  /* ---------- navigation ---------- */
  function showSection(name, updateUrl) {
    var found = false;
    panels.forEach(function (p) { var on = p.dataset.panel === name; if (on) found = true; p.classList.toggle('hidden', !on); });
    if (!found) return showSection('store', updateUrl);
    navBtns.forEach(function (b) {
      var on = b.dataset.sxNav === name;
      b.classList.toggle('bg-copper', on); b.classList.toggle('text-white', on);
      b.classList.toggle('text-espresso', !on); b.classList.toggle('hover:bg-gray-100', !on);
      if (on) b.setAttribute('aria-current', 'page'); else b.removeAttribute('aria-current');
    });
    if (select) select.value = name;
    if (updateUrl !== false) {
      try { var u = new URL(window.location.href); u.searchParams.set('section', name); history.replaceState(null, '', u); } catch (e) {}
    }
  }
  navBtns.forEach(function (b) { b.addEventListener('click', function () { showSection(b.dataset.sxNav); }); });
  if (select) select.addEventListener('change', function () { showSection(select.value); });
  showSection(window.__settingsSection || 'store', false);

  /* ---------- helpers ---------- */
  function formState(form) {
    var o = {}, fd = new FormData(form);
    fd.forEach(function (v, k) {
      if (k === 'csrf' || k === 'action' || k === 'section') return;
      o[k] = (typeof v === 'string') ? v : (v && v.name ? 'file:' + v.name + ':' + v.size : '');
    });
    return o;
  }
  function stateKey(o) { return JSON.stringify(o); }
  function clearErrors(scope) {
    scope.querySelectorAll('.sx-err').forEach(function (e) { e.textContent = ''; e.classList.add('hidden'); });
    scope.querySelectorAll('.ring-rose-300').forEach(function (e) { e.classList.remove('ring-2', 'ring-rose-300', 'border-rose-400'); });
  }
  function showErrors(form, errors) {
    var first = null;
    Object.keys(errors || {}).forEach(function (name) {
      var p = form.querySelector('.sx-err[data-err="' + name + '"]');
      if (p) { p.textContent = errors[name]; p.classList.remove('hidden'); }
      var input = form.elements[name];
      if (input && input.classList) { input.classList.add('ring-2', 'ring-rose-300', 'border-rose-400'); if (!first) first = input; }
      else if (p && !first) first = p;
    });
    if (first && first.scrollIntoView) { first.scrollIntoView({ block: 'center', behavior: 'smooth' }); if (first.focus && first.tagName) { try { first.focus({ preventScroll: true }); } catch (e) {} } }
  }
  function setLoading(form, on) {
    var save = form.querySelector('.sx-save'), spin = form.querySelector('.sx-spin');
    if (!save) return;
    if (spin) spin.classList.toggle('hidden', !on);
    form.classList.toggle('opacity-70', on); form.style.pointerEvents = on ? 'none' : '';
    if (on) save.disabled = true;
  }

  /* ---------- guards: styled confirmations for important changes ---------- */
  var guards = {
    ordering: function (f, init) {
      if (init.online_ordering === '1' && !f.elements.online_ordering.checked)
        return { title: 'Turn off online ordering?', message: 'Customers will see that ordering is temporarily unavailable and cannot place orders until you turn it back on.', confirmLabel: 'Turn off', danger: true };
    },
    store: function (f, init) {
      if (init.store_status !== 'closed' && f.elements.store_status.value === 'closed')
        return { title: 'Mark the store as closed?', message: 'The storefront stays visible, but customers cannot place orders while the store is closed.', confirmLabel: 'Mark as closed', danger: true };
    },
    system: function (f, init) {
      if (init.maintenance_mode !== '1' && f.elements.maintenance_mode.checked)
        return { title: 'Turn on maintenance mode?', message: 'The public storefront will show a maintenance page. You can still use the admin panel while signed in.', confirmLabel: 'Turn on', danger: true };
    },
    bot: function (f, init) {
      if (init.bot_enabled === '1' && !f.elements.bot_enabled.checked)
        return { title: 'Hide KAPitan Bot?', message: 'The chat button will disappear from the storefront until you enable it again.', confirmLabel: 'Hide chatbot' };
    }
  };

  /* ---------- apply server data after a successful save ---------- */
  var IMG_KEYS = { store_logo_file: 'store_logo_url', favicon_file: 'favicon_url', gcash_qr_file: 'gcash_qr_url', maya_qr_file: 'maya_qr_url' };
  function setPreview(box, url) {
    var img = box.querySelector('.sx-preview'), ph = box.querySelector('.sx-placeholder'), rm = box.querySelector('.sx-remove-wrap'), file = box.querySelector('.sx-file'), rmChk = box.querySelector('.sx-remove');
    if (url) { img.src = url + (url.indexOf('?') < 0 ? '?v=' + Date.now() : ''); img.classList.remove('hidden'); ph.classList.add('hidden'); rm.classList.remove('hidden'); rm.classList.add('inline-flex'); }
    else { img.removeAttribute('src'); img.classList.add('hidden'); ph.classList.remove('hidden'); rm.classList.add('hidden'); rm.classList.remove('inline-flex'); }
    box.dataset.origSrc = url || '';
    if (file) file.value = ''; if (rmChk) rmChk.checked = false;
  }
  function applyData(form, data) {
    data = data || {};
    Object.keys(IMG_KEYS).forEach(function (field) {
      if (!(IMG_KEYS[field] in data)) return;
      root.querySelectorAll('.sx-image[data-file="' + field + '"]').forEach(function (box) { setPreview(box, data[IMG_KEYS[field]]); });
    });
    if ('store_logo_url' in data) {
      document.querySelectorAll('.js-store-logo').forEach(function (el) {
        if (data.store_logo_url) { el.innerHTML = ''; var i = document.createElement('img'); i.src = data.store_logo_url; i.alt = ''; i.className = 'w-full h-full object-cover'; el.appendChild(i); }
        else { el.textContent = '☕'; }
      });
    }
    if (data.store_name) document.querySelectorAll('.js-store-name').forEach(function (el) { el.textContent = data.store_name.toUpperCase(); });
    if (data.clear) form.reset();
  }

  /* ---------- per-form wiring ---------- */
  forms.forEach(function (form) {
    var sec = form.dataset.section;
    var save = form.querySelector('.sx-save'), reset = form.querySelector('.sx-reset');
    form._init = formState(form); form._snap = stateKey(form._init);

    function refresh() {
      var dirty = stateKey(formState(form)) !== form._snap;
      if (save) save.disabled = !dirty; if (reset) reset.disabled = !dirty;
    }
    form.addEventListener('input', refresh);
    form.addEventListener('change', refresh);
    form.addEventListener('reset', function () {
      setTimeout(function () {
        clearErrors(form);
        form.querySelectorAll('.sx-image').forEach(function (box) { setPreview(box, box.dataset.origSrc || ''); });
        syncMethods(); syncAccent(); refresh();
      }, 0);
    });

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      clearErrors(form);
      if (!form.checkValidity()) {
        var bad = {};
        Array.prototype.forEach.call(form.elements, function (el) {
          if (el.name && el.willValidate && !el.checkValidity()) bad[el.name] = el.validity.valueMissing ? 'This field is required.' : (el.validationMessage || 'Please check this field.');
        });
        showErrors(form, bad);
        showToast({ title: 'Please complete all required fields.', type: 'warn' });
        return;
      }
      var guard = guards[sec] ? guards[sec](form, form._init) : null;
      (guard ? kConfirm(guard) : Promise.resolve(true)).then(function (ok) { if (ok) send(form, sec); });
    });
  });

  function send(form, sec) {
    setLoading(form, true);
    fetch(form.getAttribute('action'), { method: 'POST', body: new FormData(form), headers: { 'X-Requested-With': 'kapihan', 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.text().then(function (t) { try { return JSON.parse(t); } catch (e) { return null; } }); })
      .then(function (res) {
        setLoading(form, false);
        if (!res) { showToast({ title: 'Unable to save settings', message: 'Please try again.', type: 'error' }); form.querySelector('.sx-save').disabled = false; return; }
        if (res.success) {
          applyData(form, res.data);
          form._init = formState(form); form._snap = stateKey(form._init);
          form.querySelector('.sx-save').disabled = true; var rs = form.querySelector('.sx-reset'); if (rs) rs.disabled = true;
          showToast({ title: 'Saved', message: res.message, type: 'success' });
          if (res.data && res.data.reload) { setTimeout(function () { window.location.reload(); }, 900); return; }
          if (res.data && res.data.accent && res.data.accent !== document.documentElement.dataset.accent) {
            setTimeout(function () { window.location.reload(); }, 1100);   // para ma-apply ang bagong accent sa buong admin
          }
        } else {
          showErrors(form, res.errors);
          showToast({ title: res.message && res.message.indexOf('required') > -1 ? 'Please complete all required fields.' : 'Couldn’t save', message: res.message && res.message.indexOf('required') > -1 ? '' : res.message || 'Unable to save settings. Please try again.', type: res.errors && Object.keys(res.errors).length ? 'warn' : 'error' });
          form.querySelector('.sx-save').disabled = false;
        }
      })
      .catch(function () {
        setLoading(form, false); form.querySelector('.sx-save').disabled = false;
        showToast({ title: 'Unable to save settings', message: 'Check your connection and try again.', type: 'error' });
      });
  }

  /* ---------- action buttons (test AI connection, logout other sessions) ---------- */
  root.querySelectorAll('[data-post-section]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var sec = btn.dataset.postSection;
      var go = function () {
        var fd = new FormData();
        fd.append('csrf', csrfEl ? csrfEl.value : ''); fd.append('action', 'save_settings'); fd.append('section', sec);
        if (btn.dataset.postId) fd.append('id', btn.dataset.postId);
        var label = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Working…';
        fetch('admin.php?tab=settings', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'kapihan', 'Accept': 'application/json' }, credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (res) {
            showToast({ title: res.success ? 'Done' : 'Couldn’t complete that', message: res.message, type: res.success ? 'success' : 'error' });
            if (res.success && res.data && res.data.reload) setTimeout(function () { window.location.reload(); }, 900);
          })
          .catch(function () { showToast({ title: 'Something went wrong', message: 'Please try again.', type: 'error' }); })
          .finally(function () { btn.disabled = false; btn.innerHTML = label; });
      };
      if (btn.dataset.confirmTitle) kConfirm({ title: btn.dataset.confirmTitle, message: btn.dataset.confirmText, confirmLabel: btn.dataset.confirmLabel, danger: btn.dataset.confirmDanger === '1' }).then(function (ok) { if (ok) go(); });
      else go();
    });
  });

  /* ---------- image inputs: preview + client-side validation ---------- */
  root.querySelectorAll('.sx-image').forEach(function (box) {
    var file = box.querySelector('.sx-file'), err = box.querySelector('.sx-err'), rmChk = box.querySelector('.sx-remove');
    file.addEventListener('change', function () {
      err.textContent = ''; err.classList.add('hidden');
      var f = file.files[0];
      if (!f) { setPreviewTemp(box, box.dataset.origSrc); return; }
      if (TYPES.indexOf(f.type) < 0) { err.textContent = 'JPG, JPEG, PNG or WEBP only.'; err.classList.remove('hidden'); file.value = ''; return; }
      if (f.size > MAX_BYTES) { err.textContent = 'That image is over 2MB. Choose a smaller one.'; err.classList.remove('hidden'); file.value = ''; return; }
      if (rmChk) rmChk.checked = false;
      setPreviewTemp(box, URL.createObjectURL(f));
    });
    if (rmChk) rmChk.addEventListener('change', function () { if (rmChk.checked) { file.value = ''; setPreviewTemp(box, ''); } else setPreviewTemp(box, box.dataset.origSrc); });
  });
  function setPreviewTemp(box, url) {
    var img = box.querySelector('.sx-preview'), ph = box.querySelector('.sx-placeholder');
    if (url) { img.src = url; img.classList.remove('hidden'); ph.classList.add('hidden'); }
    else { img.removeAttribute('src'); img.classList.add('hidden'); ph.classList.remove('hidden'); }
  }

  /* ---------- payments: dim fields of disabled methods (values are still kept) ---------- */
  function syncMethods() {
    root.querySelectorAll('[data-method-toggle]').forEach(function (cb) {
      var box = document.getElementById('method_' + cb.dataset.methodToggle);
      if (box) box.classList.toggle('opacity-50', !cb.checked);
    });
  }
  root.querySelectorAll('[data-method-toggle]').forEach(function (cb) { cb.addEventListener('change', syncMethods); });
  syncMethods();

  /* ---------- appearance: swatches + color picker + preview ---------- */
  var accentText = document.getElementById('f_accent_color'), accentPicker = document.getElementById('accentPicker'), accentPrev = document.getElementById('accentPreview');
  function validHex(v) { return /^#[0-9a-fA-F]{6}$/.test(v); }
  function syncAccent() {
    if (!accentText) return;
    var v = accentText.value.trim();
    if (validHex(v)) { if (accentPicker) accentPicker.value = v.toLowerCase(); if (accentPrev) accentPrev.style.background = v; }
  }
  if (accentText) {
    accentText.addEventListener('input', syncAccent);
    if (accentPicker) accentPicker.addEventListener('input', function () { accentText.value = accentPicker.value.toUpperCase(); syncAccent(); accentText.dispatchEvent(new Event('input', { bubbles: true })); });
    root.querySelectorAll('.sx-swatch').forEach(function (sw) {
      sw.addEventListener('click', function () { accentText.value = sw.dataset.color; syncAccent(); accentText.dispatchEvent(new Event('input', { bubbles: true })); });
    });
  }

  /* ---------- flash message galing sa non-JS save ---------- */
  if (window.__settingsFlash) {
    showToast({ title: window.__settingsFlash.type === 'success' ? 'Saved' : 'Couldn’t save', message: window.__settingsFlash.message, type: window.__settingsFlash.type === 'success' ? 'success' : 'error' });
  }
})();
