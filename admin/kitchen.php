<?php
require_once '../connection/db.php';
app_session_start();
if (!admin_session_valid($pdo)) {
    header('Location: admin_login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h(setting('store_name')) ?> — Kitchen Display</title>
<?php $__fav = asset_url(setting('favicon'), '../'); if ($__fav): ?><link rel="icon" href="<?= h($__fav) ?>"><?php endif; ?>
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v6.5.1/css/all.css">
<style>:root{ --k-accent: <?= h(accent()) ?>; }</style>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Karla:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: { extend: {
      colors: { cream: '#F4EADA', espresso: '#241812', copper: '<?= h(accent()) ?>' },
      fontFamily: { display: ['"Bebas Neue"', 'sans-serif'], body: ['Karla', 'sans-serif'] }
    }}
  }
</script>
<style>
  body{ font-family:'Karla',sans-serif; }
  .card-pop{ animation: pop .25s ease; }
  @keyframes pop{ from{ transform:scale(.94); opacity:.4; } to{ transform:scale(1); opacity:1; } }
  .order-card{ cursor:pointer; transition: transform .15s ease, box-shadow .15s ease; }
  .order-card:hover{ transform: translateY(-2px); box-shadow: 0 10px 20px -10px rgba(0,0,0,.25); }
  .order-card:active{ transform: scale(.98); }
  .col-scroll{ max-height: calc(100vh - 150px); overflow-y: auto; }
</style>
</head>
<body class="bg-gray-200 min-h-screen">

  <header class="bg-espresso text-cream px-6 py-4 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <span class="w-9 h-9 rounded-full bg-copper grid place-items-center font-display text-lg">K</span>
      <div>
        <p class="font-display text-2xl leading-none tracking-wide"><?= h(strtoupper(setting('store_name'))) ?></p>
        <p class="text-[11px] text-cream/60 uppercase tracking-wide">Kitchen Display</p>
      </div>
    </div>
    <div class="flex items-center gap-4">
      <span id="clock" class="text-sm font-semibold text-cream/80"></span>
      <a href="admin.php?tab=orders" class="text-xs font-semibold bg-white/10 hover:bg-white/20 px-4 py-2 rounded-full transition-colors">← Back to Admin</a>
    </div>
  </header>

  <main class="p-6 grid grid-cols-1 md:grid-cols-3 gap-5">

    <section class="bg-rose-50 rounded-2xl p-4">
      <h2 class="font-display text-2xl tracking-wide text-rose-700 mb-3 flex items-center justify-between">
        New Orders <span id="countPending" class="text-sm bg-rose-200 text-rose-800 rounded-full px-2.5 py-0.5 font-body font-bold">0</span>
      </h2>
      <div id="colPending" class="col-scroll space-y-3"></div>
    </section>

    <section class="bg-white rounded-2xl p-4 border border-gray-200">
      <h2 class="font-display text-2xl tracking-wide text-gray-700 mb-3 flex items-center justify-between">
        Preparing <span id="countBrewing" class="text-sm bg-gray-200 text-gray-700 rounded-full px-2.5 py-0.5 font-body font-bold">0</span>
      </h2>
      <div id="colBrewing" class="col-scroll space-y-3"></div>
    </section>

    <section class="bg-blue-50 rounded-2xl p-4">
      <h2 class="font-display text-2xl tracking-wide text-blue-700 mb-3 flex items-center justify-between">
        Ready <span id="countCompleted" class="text-sm bg-blue-200 text-blue-800 rounded-full px-2.5 py-0.5 font-body font-bold">0</span>
      </h2>
      <div id="colCompleted" class="col-scroll space-y-3"></div>
    </section>

  </main>

<script src="../assets/kapihan-ui.js"></script>
<script>
function tick(){
  document.getElementById('clock').textContent = new Date().toLocaleString('en-PH', { hour: '2-digit', minute: '2-digit', second: '2-digit', month:'short', day:'numeric' });
}
tick();
setInterval(tick, 1000);

function esc(v){ return String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

function fmtTime(iso){
  const d = new Date(iso.replace(' ', 'T'));
  return d.toLocaleString('en-PH', { month: 'numeric', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

const TYPE_LABELS = { dine_in: 'Dine-In', take_out: 'Take-Out', pick_up: 'Pick-Up' };

function cardHtml(order, colorClass){
  const itemsHtml = order.items.map(i => `<li class="text-sm">${esc(i.name)} (${Number(i.qty)}x)</li>`).join('');
  return `
    <div class="order-card card-pop bg-white rounded-xl p-4 shadow-sm border border-gray-100" data-id="${order.id}" onclick="advanceOrder(${order.id})">
      <div class="flex items-center justify-between mb-1">
        <p class="font-bold ${colorClass} text-sm">#ORD-${order.id}</p>
        <p class="text-[11px] text-gray-400">${fmtTime(order.created_at)}</p>
      </div>
      <p class="mb-1.5"><span class="text-[10px] font-bold uppercase tracking-wide bg-espresso/10 text-espresso px-2 py-0.5 rounded-full">${esc(TYPE_LABELS[order.order_type] || 'Pick-Up')}</span></p>
      <ul class="list-disc list-inside space-y-0.5">${itemsHtml}</ul>
      ${order.notes ? `<p class="mt-2 text-xs italic text-gray-500">“${esc(order.notes)}”</p>` : ''}
    </div>
  `;
}

let lastSignature = '';
let lastSeenId = null;

async function loadBoard(){
  try {
    const res = await fetch('kitchen_data.php', { cache: 'no-store' });
    if(res.status === 401){ window.location.href = 'admin_login.php'; return; }
    const data = await res.json();
    if(!data.success) return;

    // alerto para sa bagong order (sumusunod sa Notification settings ng admin)
    if(lastSeenId === null){
      lastSeenId = data.latest_id;
    } else if(data.latest_id > lastSeenId){
      const fresh = data.board.pending.filter(o => o.id > lastSeenId);
      lastSeenId = data.latest_id;
      if(data.notify && data.notify.new_order && fresh.length){
        showToast({ title: fresh.length > 1 ? fresh.length + ' bagong orders!' : 'Bagong order!',
                    message: fresh.map(o => '#ORD-' + o.id + ' — ' + o.customer_name).join(', '), type: 'success' });
        if(data.notify.sound) kBeep();
      }
    }

    const sig = JSON.stringify(data.board);
    if(sig === lastSignature) return; // walang pagbabago, 'wag na i-redraw (para hindi kumurap)
    lastSignature = sig;

    document.getElementById('colPending').innerHTML = data.board.pending.map(o => cardHtml(o, 'text-rose-600')).join('') || emptyMsg();
    document.getElementById('colBrewing').innerHTML = data.board.brewing.map(o => cardHtml(o, 'text-gray-600')).join('') || emptyMsg();
    document.getElementById('colCompleted').innerHTML = data.board.completed.map(o => cardHtml(o, 'text-blue-600')).join('') || emptyMsg();

    document.getElementById('countPending').textContent = data.board.pending.length;
    document.getElementById('countBrewing').textContent = data.board.brewing.length;
    document.getElementById('countCompleted').textContent = data.board.completed.length;
  } catch(e) { /* quiet retry sa susunod na poll */ }
}

function emptyMsg(){
  return '<p class="text-sm text-gray-400 text-center py-6">Wala sa ngayon.</p>';
}

async function advanceOrder(id){
  try {
    const res = await fetch('kitchen_advance.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'kapihan' },
      body: JSON.stringify({ order_id: id })
    });
    const data = await res.json();
    if(data.success){
      lastSignature = ''; // sapilitang i-redraw
      loadBoard();
    }
  } catch(e) {}
}

loadBoard();
setInterval(loadBoard, 4000);
</script>
</body>
</html>
