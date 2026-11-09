<?php
require_once 'connection/db.php';
$orderId = intval($_GET['order_id'] ?? 0);
$token = (string)($_GET['t'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= h(setting('store_name')) ?> — Track Your Order</title>
<?php $__fav = asset_url(setting('favicon')); if ($__fav): ?><link rel="icon" href="<?= h($__fav) ?>"><?php endif; ?>
<style>:root{ --k-accent: <?= h(accent()) ?>; }</style>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Karla:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: {
      extend: {
        colors: { cream: '#F4EADA', card: '#FBF6EC', espresso: '#241812', bark: '#3A2A20', tan: '#CBA872', teal: '#48605A', copper: '<?= h(accent()) ?>', coppdark: '<?= h(accent_dark()) ?>' },
        fontFamily: { display: ['"Bebas Neue"', 'sans-serif'], body: ['Karla', 'sans-serif'] }
      }
    }
  }
</script>
<style>
  body { background:#F4EADA; }
  .pulse-ring{ animation: pulse 2s ease-in-out infinite; }
  @keyframes pulse{ 0%,100%{ transform:scale(1); opacity:1;} 50%{ transform:scale(1.05); opacity:.85;} }
  .status-step{ opacity:.35; }
  .status-step.active{ opacity:1; }
  .status-step.done .step-dot{ background:#48605A; }
</style>
</head>
<body class="font-body text-espresso min-h-screen flex items-center justify-center p-5">

<?php if ($orderId <= 0): ?>
  <div class="text-center">
    <p class="font-display text-3xl">Walang order na nakita.</p>
    <p class="text-espresso/60 mt-2">I-scan ulit ang QR code mula sa resibo mo.</p>
  </div>
<?php else: ?>

  <div class="w-full max-w-sm bg-card rounded-3xl border border-espresso/10 shadow-lg p-7 text-center">
    <p class="text-copper font-semibold text-sm tracking-wide"><?= h(strtoupper(setting('store_name'))) ?></p>
    <p class="font-display text-2xl tracking-wide mt-1">Order #<?= $orderId ?></p>

    <div id="loadingState" class="py-10 text-espresso/50">Loading...</div>

    <div id="orderState" class="hidden">
      <div class="mt-6 mb-4">
        <div id="queueRing" class="pulse-ring w-28 h-28 rounded-full bg-espresso text-cream mx-auto grid place-items-center">
          <div>
            <p id="queueNumber" class="font-display text-4xl leading-none">–</p>
            <p class="text-[10px] uppercase tracking-wide text-cream/70 mt-1">sa pila</p>
          </div>
        </div>
      </div>

      <div class="flex items-center justify-between text-xs font-semibold text-espresso/60 mt-6 mb-2 px-2">
        <div class="status-step" data-step="pending"><span class="step-dot inline-block w-2.5 h-2.5 rounded-full bg-espresso/25 mb-1"></span><p>Pending</p></div>
        <div class="status-step" data-step="brewing"><span class="step-dot inline-block w-2.5 h-2.5 rounded-full bg-espresso/25 mb-1"></span><p>Brewing</p></div>
        <div class="status-step" data-step="completed"><span class="step-dot inline-block w-2.5 h-2.5 rounded-full bg-espresso/25 mb-1"></span><p>Ready</p></div>
      </div>

      <div class="text-left bg-cream rounded-2xl p-4 mt-5 text-sm border border-espresso/10">
        <p class="flex justify-between"><span class="text-espresso/60">Pangalan</span> <span id="custName" class="font-semibold"></span></p>
        <p class="flex justify-between mt-1"><span class="text-espresso/60">Items</span> <span id="itemsSummary" class="font-semibold text-right max-w-[60%]"></span></p>
        <p class="flex justify-between mt-1"><span class="text-espresso/60">Uri</span> <span id="orderType" class="font-semibold"></span></p>
        <p id="notesRow" class="hidden flex justify-between mt-1"><span class="text-espresso/60">Note</span> <span id="orderNotes" class="font-semibold text-right max-w-[60%] break-words"></span></p>
        <p class="flex justify-between mt-1"><span class="text-espresso/60">Bayad</span> <span id="paymentMethod" class="font-semibold"></span></p>
        <p class="flex justify-between mt-1 pt-1 border-t border-espresso/10"><span class="text-espresso/60">Total</span> <span id="totalAmount" class="font-bold text-copper"></span></p>
      </div>

      <p id="prepInfo" class="hidden text-xs font-semibold text-espresso/60 mt-4"></p>

      <p id="notifyHint" class="text-[11px] text-espresso/45 mt-4 leading-snug">Panatilihing bukas ang tab na ito para makatanggap ng notification pag handa na ang order mo.</p>
      <button id="notifyBtn" class="mt-3 text-xs font-semibold text-copper underline-offset-2 underline">I-enable ang notifications</button>
    </div>

    <div id="errorState" class="hidden py-10 text-rose-600 text-sm"></div>
  </div>

<script src="assets/kapihan-ui.js"></script>
<script>
const CUR = <?= json_encode(cur()) ?>;
const orderId = <?= json_encode($orderId) ?>;
const orderToken = <?= json_encode($token) ?>;
let lastStatus = null;
let notifyEnabled = false;
let notifySound = false;
let notifPermissionAsked = false;

const STATUS_LABELS = { pending: 'Pending', brewing: 'Brewing', completed: 'Handa na!', cancelled: 'Cancelled' };
const PAYMENT_LABELS = { cash: 'Cash', gcash: 'GCash', maya: 'Maya' };
const TYPE_LABELS = { dine_in: 'Dine-In', take_out: 'Take-Out', pick_up: 'Pick-Up' };

function requestNotifyPermission(){
  if(!('Notification' in window)) return;
  Notification.requestPermission().then(() => {
    document.getElementById('notifyHint').textContent = 'Naka-enable na ang notifications. Panatilihing bukas ang tab na ito.';
  });
}
document.getElementById('notifyBtn').addEventListener('click', requestNotifyPermission);

function fireNotification(status){
  if(!('Notification' in window) || Notification.permission !== 'granted') return;
  if(status === 'brewing'){
    new Notification('Kapihan', { body: 'Ginagawa na ang order mo! ☕' });
  } else if(status === 'completed'){
    new Notification('Kapihan', { body: 'Handa na ang order mo! Kunin na sa counter. 🎉' });
  }
}

function render(data){
  document.getElementById('loadingState').classList.add('hidden');
  document.getElementById('orderState').classList.remove('hidden');

  document.getElementById('queueNumber').textContent = data.status === 'completed' ? '✓' : data.queue_pos;
  document.getElementById('custName').textContent = data.customer_name;
  document.getElementById('itemsSummary').textContent = data.items_summary || '—';
  const payLabel = PAYMENT_LABELS[data.payment_method] || data.payment_method;
  const payState = data.payment_status === 'paid' ? 'Paid' : (data.payment_method === 'cash' ? 'Pay at the counter' : 'Awaiting verification');
  document.getElementById('paymentMethod').textContent = payLabel + ' · ' + payState;
  document.getElementById('totalAmount').textContent = CUR + new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(data.total_amount) || 0);
  document.getElementById('orderType').textContent = TYPE_LABELS[data.order_type] || 'Pick-Up';
  const notesRow = document.getElementById('notesRow');
  if(data.notes){ document.getElementById('orderNotes').textContent = data.notes; notesRow.classList.remove('hidden'); notesRow.classList.add('flex'); }
  const prep = document.getElementById('prepInfo');
  if(data.status === 'pending' || data.status === 'brewing'){ prep.textContent = 'Estimated preparation: about ' + data.prep_time + ' minutes'; prep.classList.remove('hidden'); }
  else { prep.classList.add('hidden'); }
  // sumusunod sa Notification settings ng admin
  notifyEnabled = !!(data.notify && data.notify.status);
  notifySound = !!(data.notify && data.notify.sound);
  document.getElementById('notifyBtn').classList.toggle('hidden', !notifyEnabled);
  document.getElementById('notifyHint').classList.toggle('hidden', !notifyEnabled);

  document.querySelectorAll('.status-step').forEach(el => {
    const step = el.dataset.step;
    const order = ['pending','brewing','completed'];
    const curIdx = order.indexOf(data.status);
    const stepIdx = order.indexOf(step);
    el.classList.toggle('active', stepIdx === curIdx);
    el.classList.toggle('done', stepIdx <= curIdx && curIdx !== -1);
  });

  const ring = document.getElementById('queueRing');
  if(data.status === 'completed'){
    ring.classList.remove('pulse-ring');
    ring.classList.remove('bg-espresso');
    ring.classList.add('bg-teal');
  }

  if(lastStatus !== null && lastStatus !== data.status && notifyEnabled){
    fireNotification(data.status);
    if(data.status === 'brewing') showToast({ title: 'Ginagawa na ang order mo! ☕', type: 'info' });
    if(data.status === 'completed') showToast({ title: 'Handa na ang order mo!', message: 'Kunin na sa counter.', type: 'success' });
    if(notifySound) kBeep();
  }
  lastStatus = data.status;
}

async function poll(){
  try{
    const res = await fetch('order_status.php?order_id=' + orderId + '&t=' + encodeURIComponent(orderToken), { cache: 'no-store' });
    const data = await res.json();
    if(data.success){
      render(data);
    } else {
      document.getElementById('loadingState').classList.add('hidden');
      document.getElementById('errorState').classList.remove('hidden');
      document.getElementById('errorState').textContent = data.message || 'Order not found.';
    }
  } catch(e){
    // network hiccup — quiet retry on next interval
  }
}

poll();
setInterval(poll, 6000);
</script>

<?php endif; ?>
</body>
</html>
