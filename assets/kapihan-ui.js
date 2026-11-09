/* KAPIHAN UI helpers: toast notifications, styled confirm dialog, new-order beep.
   Ginagamit ng admin at kitchen pages. Kulay ng accent: CSS variable --k-accent (nilalagay ng page). */
(function () {
  if (window.__kapihanUi) return;
  window.__kapihanUi = true;

  var css = [
    '#kToastHost{position:fixed;left:50%;transform:translateX(-50%);top:calc(env(safe-area-inset-top,0px) + 16px);z-index:300;width:min(92vw,420px);display:flex;flex-direction:column;gap:10px;pointer-events:none}',
    '.k-toast{pointer-events:auto;display:flex;align-items:flex-start;gap:12px;background:#241812;color:#F4EADA;border:1px solid rgba(203,168,114,.28);border-left:4px solid var(--k-accent,#B25F2C);border-radius:16px;padding:14px 12px 14px 16px;box-shadow:0 18px 40px -12px rgba(36,24,18,.55);opacity:0;transform:translateY(-12px) scale(.98);transition:opacity .28s ease,transform .28s cubic-bezier(.2,.8,.2,1)}',
    '.k-toast.show{opacity:1;transform:none}',
    '.k-toast.warn{border-left-color:#CBA872}.k-toast.error{border-left-color:#e5484d}.k-toast.success{border-left-color:#6aa596}',
    '.k-toast i.k-ico{margin-top:3px;font-size:15px;color:#CBA872;flex-shrink:0}.k-toast.error i.k-ico{color:#ff8a8f}.k-toast.success i.k-ico{color:#8fc9ba}.k-toast.info i.k-ico{color:#e08a55}',
    '.k-toast .k-body{flex:1;min-width:0}.k-toast .k-title{font-weight:700;font-size:14px;line-height:1.3}.k-toast .k-msg{font-size:13px;line-height:1.45;margin-top:3px;color:rgba(244,234,218,.82)}',
    '.k-toast .k-act{margin-top:10px;background:var(--k-accent,#B25F2C);color:#F4EADA;font-weight:700;font-size:12px;padding:7px 14px;border-radius:999px}',
    '.k-toast .k-x{color:rgba(244,234,218,.55);width:26px;height:26px;flex-shrink:0;border-radius:999px;display:grid;place-items:center;font-size:12px}.k-toast .k-x:hover{background:rgba(244,234,218,.12);color:#F4EADA}',
    '#kConfirm{position:fixed;inset:0;z-index:310;display:none;align-items:center;justify-content:center;padding:16px;background:rgba(36,24,18,.55);backdrop-filter:blur(3px)}',
    '#kConfirm.open{display:flex}',
    '#kConfirm .k-card{background:#FBF6EC;color:#241812;border-radius:20px;max-width:420px;width:100%;padding:24px;box-shadow:0 30px 60px -20px rgba(0,0,0,.5);animation:kPop .22s cubic-bezier(.2,.8,.2,1)}',
    '#kConfirm h3{font-family:"Bebas Neue",sans-serif;font-size:26px;letter-spacing:.02em;line-height:1.1}',
    '#kConfirm p{font-size:14px;line-height:1.5;margin-top:8px;color:rgba(36,24,18,.72)}',
    '#kConfirm .k-row{display:flex;justify-content:flex-end;gap:10px;margin-top:22px;flex-wrap:wrap}',
    '#kConfirm button{font-weight:700;font-size:13px;padding:10px 20px;border-radius:999px}',
    '#kConfirm .k-no{background:transparent;border:1px solid rgba(36,24,18,.18)}#kConfirm .k-no:hover{background:rgba(36,24,18,.06)}',
    '#kConfirm .k-yes{background:var(--k-accent,#B25F2C);color:#fff}#kConfirm .k-yes.danger{background:#d6336c}',
    '@keyframes kPop{from{opacity:0;transform:scale(.96) translateY(8px)}to{opacity:1;transform:none}}',
    '@media (prefers-reduced-motion:reduce){.k-toast{transition:none;transform:none}#kConfirm .k-card{animation:none}}'
  ].join('\n');
  var st = document.createElement('style'); st.textContent = css; document.head.appendChild(st);

  var icons = { warn: 'fa-triangle-exclamation', error: 'fa-circle-xmark', success: 'fa-circle-check', info: 'fa-circle-info' };

  window.showToast = function (o) {
    o = o || {};
    var type = icons[o.type] ? o.type : 'info';
    var host = document.getElementById('kToastHost');
    if (!host) { host = document.createElement('div'); host.id = 'kToastHost'; host.setAttribute('aria-live', 'polite'); document.body.appendChild(host); }
    var key = type + '|' + (o.title || '') + '|' + (o.message || '');
    for (var i = 0; i < host.children.length; i++) if (host.children[i].dataset.key === key) return;
    while (host.children.length >= 3) host.firstElementChild.remove();

    var el = document.createElement('div');
    el.className = 'k-toast ' + type; el.dataset.key = key;
    el.setAttribute('role', type === 'error' ? 'alert' : 'status');
    var ic = document.createElement('i'); ic.className = 'fa-solid ' + icons[type] + ' k-ico'; ic.setAttribute('aria-hidden', 'true');
    var body = document.createElement('div'); body.className = 'k-body';
    if (o.title) { var t = document.createElement('p'); t.className = 'k-title'; t.textContent = o.title; body.appendChild(t); }
    if (o.message) { var m = document.createElement('p'); m.className = 'k-msg'; m.textContent = o.message; body.appendChild(m); }
    var timer;
    function dismiss() { clearTimeout(timer); el.classList.remove('show'); setTimeout(function () { el.remove(); }, 300); }
    if (o.actionLabel) {
      var a = document.createElement('button'); a.type = 'button'; a.className = 'k-act'; a.textContent = o.actionLabel;
      a.addEventListener('click', function () { dismiss(); if (typeof o.onAction === 'function') o.onAction(); });
      body.appendChild(a);
    }
    var x = document.createElement('button'); x.type = 'button'; x.className = 'k-x'; x.setAttribute('aria-label', 'Isara');
    x.innerHTML = '<i class="fa-solid fa-xmark"></i>'; x.addEventListener('click', dismiss);
    el.appendChild(ic); el.appendChild(body); el.appendChild(x); host.appendChild(el);
    requestAnimationFrame(function () { el.classList.add('show'); });
    var ms = o.duration || (o.actionLabel ? 8000 : 5000);
    function arm() { timer = setTimeout(dismiss, ms); }
    el.addEventListener('mouseenter', function () { clearTimeout(timer); });
    el.addEventListener('mouseleave', arm); arm();
  };

  /* Styled confirm: await kConfirm({title, message, confirmLabel, cancelLabel, danger}) -> true/false */
  window.kConfirm = function (o) {
    o = o || {};
    return new Promise(function (resolve) {
      var ov = document.getElementById('kConfirm');
      if (!ov) {
        ov = document.createElement('div'); ov.id = 'kConfirm'; ov.setAttribute('role', 'dialog'); ov.setAttribute('aria-modal', 'true');
        ov.innerHTML = '<div class="k-card"><h3></h3><p></p><div class="k-row"><button type="button" class="k-no"></button><button type="button" class="k-yes"></button></div></div>';
        document.body.appendChild(ov);
      }
      var prev = document.activeElement;
      ov.querySelector('h3').textContent = o.title || 'Sigurado ka ba?';
      ov.querySelector('p').textContent = o.message || '';
      var no = ov.querySelector('.k-no'), yes = ov.querySelector('.k-yes');
      no.textContent = o.cancelLabel || 'Cancel'; yes.textContent = o.confirmLabel || 'Confirm';
      yes.className = 'k-yes' + (o.danger ? ' danger' : '');
      function done(v) {
        ov.classList.remove('open'); document.removeEventListener('keydown', key, true);
        no.onclick = yes.onclick = ov.onclick = null; if (prev && prev.focus) prev.focus(); resolve(v);
      }
      function key(e) { if (e.key === 'Escape') { e.preventDefault(); done(false); } }
      no.onclick = function () { done(false); }; yes.onclick = function () { done(true); };
      ov.onclick = function (e) { if (e.target === ov) done(false); };
      document.addEventListener('keydown', key, true);
      ov.classList.add('open'); yes.focus();
    });
  };

  /* <form data-confirm="Mensahe" data-confirm-title="Title" data-confirm-danger="1"> */
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (!f || !f.getAttribute || !f.getAttribute('data-confirm') || f.dataset.confirmed === '1') return;
    e.preventDefault();
    window.kConfirm({
      title: f.getAttribute('data-confirm-title') || 'Sigurado ka ba?', message: f.getAttribute('data-confirm'),
      confirmLabel: f.getAttribute('data-confirm-label') || 'Oo, ituloy', danger: f.getAttribute('data-confirm-danger') === '1'
    }).then(function (ok) { if (ok) { f.dataset.confirmed = '1'; HTMLFormElement.prototype.submit.call(f); } });
  }, true);

  /* Beep para sa bagong order. Kailangan munang may click sa page ang browser bago pumayag mag-play. */
  var ctx = null;
  function unlock() { try { ctx = ctx || new (window.AudioContext || window.webkitAudioContext)(); if (ctx.state === 'suspended') ctx.resume(); } catch (e) {} }
  ['pointerdown', 'keydown'].forEach(function (ev) { document.addEventListener(ev, unlock, { once: false, passive: true }); });
  window.kBeep = function () {
    try {
      unlock(); if (!ctx || ctx.state !== 'running') return;
      [[880, 0], [1175, 0.16]].forEach(function (n) {
        var o = ctx.createOscillator(), g = ctx.createGain();
        o.type = 'sine'; o.frequency.value = n[0]; o.connect(g); g.connect(ctx.destination);
        var t = ctx.currentTime + n[1];
        g.gain.setValueAtTime(0.0001, t); g.gain.exponentialRampToValueAtTime(0.25, t + 0.02); g.gain.exponentialRampToValueAtTime(0.0001, t + 0.28);
        o.start(t); o.stop(t + 0.3);
      });
    } catch (e) {}
  };
})();
