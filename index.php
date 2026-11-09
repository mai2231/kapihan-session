<?php
require_once 'connection/db.php';

// Maintenance mode: sarado sa publiko, bukas pa rin sa naka-login na admin
if (maintenance_active($pdo)) {
    http_response_code(503);
    header('Retry-After: 3600');
    $mLogo = asset_url(setting('store_logo'));
?><!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h(setting('store_name')) ?> — Be right back</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Karla:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  body{margin:0;min-height:100vh;display:grid;place-items:center;background:#F4EADA;color:#241812;font-family:'Karla',sans-serif;padding:24px;box-sizing:border-box}
  .card{max-width:440px;width:100%;background:#FBF6EC;border:1px solid rgba(36,24,18,.1);border-radius:28px;padding:40px 28px;text-align:center;box-shadow:0 24px 50px -24px rgba(36,24,18,.35)}
  .logo{width:72px;height:72px;border-radius:50%;background:<?= h(accent()) ?>;display:grid;place-items:center;margin:0 auto 18px;font-size:30px;overflow:hidden}
  .logo img{width:100%;height:100%;object-fit:cover}
  h1{font-family:'Bebas Neue',sans-serif;font-size:42px;letter-spacing:.03em;margin:0;line-height:1}
  p{line-height:1.55;color:rgba(36,24,18,.72);margin:12px 0 0}
  .pill{display:inline-block;margin-top:20px;background:<?= h(accent()) ?>;color:#fff;font-weight:700;font-size:13px;padding:8px 18px;border-radius:999px}
</style></head>
<body><div class="card">
  <div class="logo"><?php if ($mLogo): ?><img src="<?= h($mLogo) ?>" alt=""><?php else: ?>☕<?php endif; ?></div>
  <h1><?= h(strtoupper(setting('store_name'))) ?></h1>
  <p><strong>We’ll be right back.</strong><br>We’re making a few improvements to our ordering system. Thanks for your patience!</p>
  <?php if (setting('contact_number') !== ''): ?><p>Need us now? Call <?= h(setting('contact_number')) ?></p><?php endif; ?>
  <span class="pill">Maintenance in progress</span>
</div></body></html>
<?php
    exit;
}

// Menu: aktibong produkto lang. Kapag nag-error ang database, HINDI ito ituturing na "walang menu".
$productsFromDb = [];
$menuError = false;
try {
    $productsFromDb = $pdo->query("SELECT id, name, category, price, image, description, stock_qty FROM products WHERE is_active = 1 ORDER BY id ASC")->fetchAll();
    foreach ($productsFromDb as &$__p) { $__p['image'] = product_image_url($__p['image'] ?? '', ''); }   // '' = placeholder
    unset($__p);
} catch (Throwable $e) {
    error_log('storefront menu: ' . $e->getMessage());
    $menuError = true;
}
$productsJson = json_encode($productsFromDb, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
if ($productsJson === false) {
    error_log('storefront menu json: ' . json_last_error_msg());
    $productsJson = '[]';
    $menuError = true;
}
$adminSeesEmptyMenu = (!$productsFromDb && !$menuError) ? admin_session_valid($pdo) : false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= h(setting('store_name')) ?> — Everyday Coffee</title>
<?php $__fav = asset_url(setting('favicon')); if ($__fav): ?><link rel="icon" href="<?= h($__fav) ?>"><?php else: ?><link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>☕</text></svg>"><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Karla:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v6.5.1/css/all.css">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
  tailwind.config = {
    theme: {
      extend: {
        colors: { cream: '#c9baa3', card: '#d1c2a4', espresso: '#241812', bark: '#3A2A20', tan: '#CBA872', tandark: '#B4915C', teal: '#48605A', tealdark: '#334640', copper: '<?= h(accent()) ?>', coppdark: '<?= h(accent_dark()) ?>' },
        fontFamily: { display: ['"Bebas Neue"', 'sans-serif'], body: ['Karla', 'sans-serif'] }
      }
    }
  }
</script>
<style>
  :root{ --grain-opacity: .05; }
  html { scroll-behavior: smooth; scroll-padding-top: 112px; }
  body { background: #ceb998; padding-top: env(safe-area-inset-top, 0px); padding-bottom: env(safe-area-inset-bottom, 0px); }
  .grain::before{ content:""; position:absolute; inset:0; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.35'/%3E%3C/svg%3E"); opacity: var(--grain-opacity); pointer-events:none; mix-blend-mode: multiply; }
  .underline-grow{ position:relative; }
  .underline-grow::after{ content:""; position:absolute; left:0; bottom:-4px; height:2px; width:100%; background:currentColor; transform:scaleX(0); transform-origin:left; transition: transform .35s ease; }
  .underline-grow:hover::after{ transform:scaleX(1); }
  .tab-btn[aria-selected="true"]{ background:#241812; color:#F4EADA; }
  .card-lift{ transition: transform .35s cubic-bezier(.2,.8,.2,1), box-shadow .35s ease, opacity .4s ease, border-color .35s ease; will-change: transform; }
  .card-lift:hover{ box-shadow: 0 18px 30px -14px rgba(36,24,18,.35); transform: translateY(-4px); border-color: <?= h(accent()) ?>; }
  .steam-wrap{ position:absolute; z-index: 10; }
  .steam{ position:absolute; bottom:100%; width:6px; border-radius:999px; background: linear-gradient(to top, rgba(244,234,218,0), rgba(244,234,218,.9)); filter: blur(1.5px); animation: rise 3s ease-in-out infinite; opacity:0; }
  .steam.s1{ left:35%; height:40px; animation-delay:0s; }
  .steam.s2{ left:50%; height:50px; animation-delay:.8s; }
  .steam.s3{ left:65%; height:35px; animation-delay:1.5s; }
  @keyframes rise{ 0%{ transform: translateY(0) scaleY(.5) scaleX(1); opacity:0; } 30%{ opacity:.85; } 70%{ opacity:.3; } 100%{ transform: translateY(-55px) scaleY(1.2) scaleX(1.8); opacity:0; } }
  .cup-ring{ box-shadow: 0 0 0 6px #FBF6EC, 0 12px 24px -8px rgba(36,24,18,.35); border: 2px solid #241812; transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.4s ease; }
  .shadow-inner{ box-shadow: inset 0 2px 4px rgba(0,0,0,0.15); }
  .marquee-track{ display:flex; width: max-content; animation: marquee 28s linear infinite; }
  @keyframes marquee{ from{ transform: translateX(0); } to{ transform: translateX(-50%); } }
  .hero-cup-wrap { perspective: 1000px; cursor: pointer; }
  .hero-cup-card { transition: transform 0.4s cubic-bezier(0.2, 0.8, 0.2, 1); transform-style: preserve-3d; }
  .hero-cup-wrap:hover .hero-cup-card { transform: translateY(-8px) scale(1.06) rotateX(10deg) rotateY(-5deg); }
  .cup-float[data-float="1"] { animation: floatBob1 3.5s ease-in-out infinite alternate; }
  .cup-float[data-float="2"] { animation: floatBob2 4.2s ease-in-out infinite alternate; }
  .cup-float[data-float="3"] { animation: floatBob3 3.8s ease-in-out infinite alternate; }
  .cup-float[data-float="4"] { animation: floatBob4 4.5s ease-in-out infinite alternate; }
  @keyframes floatBob1 { 0% { transform: translateY(0px); } 100% { transform: translateY(-16px) rotate(2deg); } }
  @keyframes floatBob2 { 0% { transform: translateY(0px); } 100% { transform: translateY(-12px) rotate(-3deg); } }
  @keyframes floatBob3 { 0% { transform: translateY(0px); } 100% { transform: translateY(-18px) rotate(1.5deg); } }
  @keyframes floatBob4 { 0% { transform: translateY(0px); } 100% { transform: translateY(-14px) rotate(-2deg); } }
  .cup-glow { position: absolute; inset: -10%; border-radius: 50%; background: radial-gradient(circle, rgba(203, 168, 114, 0.4) 0%, rgba(244, 234, 218, 0) 70%); animation: pulseGlow 4s ease-in-out infinite alternate; z-index: -1; }
  @keyframes pulseGlow { 0% { transform: scale(0.85); opacity: 0.4; } 100% { transform: scale(1.15); opacity: 0.8; } }
  .stamp-spin{ animation: spin 12s linear infinite; transform-origin: 50% 50%; }
  @keyframes spin{ to{ transform: rotate(360deg); } }
  [data-reveal]{ opacity: 0; transform: translateY(22px); transition: opacity .7s ease-out, transform .7s ease-out; }
  [data-reveal].is-visible { opacity: 1; transform: translateY(0); }
  .pay-btn[aria-pressed="true"]{ background:#241812; color:#F4EADA; border-color:#241812; }
  /* Payment method buttons with logos */
  .pay-method{ display:flex; flex-direction:column; align-items:center; justify-content:center; gap:7px; min-height:78px; min-width:0; padding:9px 6px; }
  .pay-method .pay-logo{ display:block; height:34px; max-width:100%; border-radius:9px; overflow:hidden; background:#000; flex-shrink:0; transition:box-shadow .2s ease; }
  .pay-method .pay-logo img{ display:block; height:100%; width:auto; max-width:100%; object-fit:contain; }
  .pay-method .pay-icon{ height:34px; display:grid; place-items:center; font-size:21px; }
  .pay-method .pay-label{ font-size:12px; font-weight:600; line-height:1; }
  .pay-method[aria-pressed="true"] .pay-logo{ box-shadow:0 0 0 1.5px rgba(244,234,218,.6); }
  /* QR payment panel */
  .qr-logo{ display:inline-block; height:30px; border-radius:8px; overflow:hidden; background:#000; flex-shrink:0; }
  .qr-logo img{ display:block; height:100%; width:auto; }
  .qr-open{ display:inline-block; background:none; border:0; padding:0; cursor:zoom-in; }
  .qr-img{ display:block; width:176px; height:176px; max-width:100%; object-fit:contain; background:#fff; border-radius:14px; border:1px solid rgba(36,24,18,.1); padding:6px; }
  /* KAPitan Bot launcher + chatbox */
  #aiWidget{ right:24px; bottom:calc(env(safe-area-inset-bottom,0px) + 24px); }
  .ai-fab{ position:relative; display:block; width:56px; height:56px; padding:0; border:0; border-radius:50%; background:transparent; cursor:pointer;
    box-shadow:0 8px 22px -6px rgba(36,24,18,.55), 0 0 0 2px rgba(244,234,218,.95); transition:transform .22s cubic-bezier(.2,.8,.2,1), box-shadow .22s ease; }
  .ai-fab img{ display:block; width:100%; height:100%; border-radius:50%; object-fit:cover; }
  .ai-fab:hover{ transform:scale(1.07); box-shadow:0 14px 30px -8px rgba(36,24,18,.6), 0 0 0 2px rgba(244,234,218,.95); }
  .ai-fab:active{ transform:scale(.97); }
  .ai-fab-label{ position:absolute; right:calc(100% + 12px); top:50%; transform:translateY(-50%); white-space:nowrap; background:#241812; color:#F4EADA; font-size:12px; font-weight:700;
    padding:8px 14px; border-radius:999px; box-shadow:0 8px 20px -8px rgba(36,24,18,.5); pointer-events:none; transition:opacity .2s ease, transform .2s ease; }
  .ai-fab[aria-expanded="true"] .ai-fab-label{ opacity:0; transform:translateY(-50%) translateX(8px); }
  @media (max-width:640px){ .ai-fab-label{ display:none; } }
  .ai-fab:focus-visible{ outline:3px solid <?= h(accent()) ?>; outline-offset:3px; }
  .ai-fab-x{ position:absolute; top:-5px; right:-5px; width:22px; height:22px; border-radius:50%; background:<?= h(accent()) ?>; color:#fff; font-size:11px; display:grid; place-items:center;
    box-shadow:0 0 0 2px #F4EADA; opacity:0; transform:scale(.4); transition:opacity .2s ease, transform .2s cubic-bezier(.2,.8,.2,1); }
  .ai-fab[aria-expanded="true"] .ai-fab-x{ opacity:1; transform:none; }
  .ai-panel{ position:absolute; right:0; bottom:68px; width:min(calc(100vw - 32px), 380px); height:min(72vh, 540px); max-height:calc(100vh - 120px); max-height:calc(100dvh - 120px);
    opacity:0; visibility:hidden; pointer-events:none; transform:translateY(14px) scale(.95); transform-origin:bottom right;
    transition:opacity .22s ease, transform .28s cubic-bezier(.2,.8,.2,1), visibility 0s linear .28s; }
  .ai-panel.open{ opacity:1; visibility:visible; pointer-events:auto; transform:none; transition-delay:0s; }
  .ai-panel *{ min-width:0; }
  @media (max-width:640px){
    #aiWidget{ right:16px; bottom:calc(env(safe-area-inset-bottom,0px) + 16px); }
    .ai-fab{ width:52px; height:52px; }
    .ai-panel{ bottom:64px; }
  }
  @media (prefers-reduced-motion: reduce){ .ai-fab, .ai-fab-x, .ai-panel{ transition:none; } }

  header .nav-row{ transition: height .3s ease, box-shadow .3s ease; }
  header.scrolled .nav-row{ height: 64px; box-shadow: 0 6px 18px -12px rgba(36,24,18,.25); }
  header.scrolled{ }

  .best-seller-badge{
    position:absolute; top:-6px; left:-6px; z-index:5;
    background:<?= h(accent()) ?>; color:#F4EADA; font-size:9px; font-weight:800; letter-spacing:.4px;
    padding:3px 7px; border-radius:999px; box-shadow:0 3px 8px -2px rgba(36,24,18,.4);
    white-space:nowrap; transform: rotate(-6deg);
  }

  #activityBar{ overflow:hidden; max-height:40px; transition: max-height .4s ease, opacity .4s ease; }
  #activityBar.hidden-bar{ max-height:0; opacity:0; }
  .pulse-dot{ display:inline-block; width:7px; height:7px; border-radius:999px; background:#4ADE80; animation: pulseDot 1.6s ease-in-out infinite; }
  @keyframes pulseDot{ 0%,100%{ opacity:1; transform:scale(1); } 50%{ opacity:.4; transform:scale(.8); } }

  .star-row i{ color:#CBA872; }
  .testi-card{ transition: transform .3s ease, box-shadow .3s ease; }
  .testi-card:hover{ transform: translateY(-4px); box-shadow: 0 16px 28px -16px rgba(36,24,18,.3); }

  /* ---- KAPIHAN toast notifications ---- */
  #toastHost{ position:fixed; left:50%; transform:translateX(-50%); top:calc(env(safe-area-inset-top,0px) + 16px); z-index:200; width:min(92vw,420px); display:flex; flex-direction:column; gap:10px; pointer-events:none; }
  .k-toast{ pointer-events:auto; display:flex; align-items:flex-start; gap:12px; background:#241812; color:#F4EADA; border:1px solid rgba(203,168,114,.28); border-left:4px solid <?= h(accent()) ?>; border-radius:16px; padding:14px 12px 14px 16px; box-shadow:0 18px 40px -12px rgba(36,24,18,.55); opacity:0; transform:translateY(-12px) scale(.98); transition:opacity .28s ease, transform .28s cubic-bezier(.2,.8,.2,1); }
  .k-toast.show{ opacity:1; transform:none; }
  .k-toast.warn{ border-left-color:#CBA872; }
  .k-toast.error{ border-left-color:#e5484d; }
  .k-toast.success{ border-left-color:#6aa596; }
  .k-toast-icon{ margin-top:3px; font-size:15px; color:#CBA872; flex-shrink:0; }
  .k-toast.error .k-toast-icon{ color:#ff8a8f; }
  .k-toast.success .k-toast-icon{ color:#8fc9ba; }
  .k-toast.info .k-toast-icon{ color:#e08a55; }
  .k-toast-body{ flex:1; min-width:0; }
  .k-toast-title{ font-weight:700; font-size:14px; line-height:1.3; }
  .k-toast-msg{ font-size:13px; line-height:1.45; margin-top:3px; color:rgba(244,234,218,.82); }
  .k-toast-action{ margin-top:10px; background:<?= h(accent()) ?>; color:#F4EADA; font-weight:700; font-size:12px; padding:7px 14px; border-radius:999px; transition:background .2s ease; }
  .k-toast-action:hover{ background:<?= h(accent_dark()) ?>; }
  .k-toast-close{ color:rgba(244,234,218,.55); width:26px; height:26px; flex-shrink:0; border-radius:999px; display:grid; place-items:center; font-size:12px; transition:background .2s ease, color .2s ease; }
  .k-toast-close:hover{ background:rgba(244,234,218,.12); color:#F4EADA; }
  @media (prefers-reduced-motion: reduce){ .k-toast{ transition:none; transform:none; } }
</style>
</head>
<body class="bg-cream text-espresso font-body antialiased">
<header id="top" class="fixed top-0 inset-x-0 z-50">
  <div class="backdrop-blur-md bg-cream/90 border-b-2 border-espresso/10">
    <div class="nav-row max-w-6xl mx-auto px-5 sm:px-8 h-[76px] flex items-center justify-between">
      <a href="#top" class="flex items-center gap-2.5 group">
        <span class="w-9 h-9 rounded-full bg-espresso text-cream grid place-items-center font-display text-lg tracking-wide group-hover:rotate-[18deg] transition-transform duration-300 overflow-hidden">
          <?php $__logo = asset_url(setting('store_logo')); if ($__logo): ?><img src="<?= h($__logo) ?>" alt="" class="w-full h-full object-cover"><?php else: ?><i class="fa-solid fa-mug-hot text-sm"></i><?php endif; ?>
        </span>
        <span class="font-display text-[26px] leading-none tracking-wide pt-1"><?= h(strtoupper(setting('store_name'))) ?></span>
      </a>
      <nav class="hidden md:flex items-center gap-8 text-[15px] font-medium">
        <a href="#menu" class="underline-grow">Menu</a>
        <a href="#story" class="underline-grow">Ours Story</a>
        <a href="#visit" class="underline-grow">Visit</a>
      </nav>
      <div class="flex items-center gap-3">
        <button id="cartBtn" class="relative w-11 h-11 rounded-full border-2 border-espresso/20 grid place-items-center hover:bg-espresso hover:text-cream transition-colors duration-300">
          <i class="fa-solid fa-cart-shopping text-base"></i>
          <span id="cartCount" class="absolute -top-1.5 -right-1.5 min-w-[19px] h-[19px] px-1 rounded-full bg-copper text-cream text-[11px] font-bold grid place-items-center">0</span>
        </button>
        <a href="#visit" class="hidden sm:inline-flex items-center bg-copper hover:bg-coppdark text-cream text-sm font-semibold px-5 py-2.5 rounded-full border-2 border-copper transition-colors duration-300 gap-2">
          <i class="fa-solid fa-chair text-xs"></i> Find a table
        </a>
      </div>
    </div>
  </div>
  <div id="activityBar" class="bg-espresso text-cream text-xs sm:text-[13px]">
    <div class="max-w-6xl mx-auto px-5 sm:px-8 h-9 flex items-center justify-center gap-2 font-medium tracking-wide">
      <span class="pulse-dot"></span>
      <span id="activityText">Kinukuha ang live status...</span>
    </div>
  </div>
</header>

<section class="relative overflow-hidden pt-[112px]">
  <div class="max-w-6xl mx-auto px-5 sm:px-8 grid lg:grid-cols-[1.05fr_.95fr] gap-10 items-center pt-16 pb-20 lg:pt-24 lg:pb-28">
    <div>
      <p class="text-copper font-semibold tracking-wide text-sm mb-5">Barako beans, brewed the whole day through</p>
      <h1 class="font-display text-[15vw] leading-[0.88] sm:text-[76px] lg:text-[86px] tracking-wide">Coffee for<br>every kind<br>of morning.</h1>
      <p class="mt-6 max-w-md text-[17px] text-espresso/75 leading-relaxed">A small cup shop pulling single-origin beans from Benguet and Batangas — for the 6am rush, the 3pm slump, and every merienda in between.</p>
      <div class="mt-8 flex flex-wrap items-center gap-4">
        <a href="#menu" class="inline-flex items-center gap-2 bg-espresso text-cream font-semibold px-7 py-3.5 rounded-full border-2 border-espresso hover:bg-bark hover:border-bark transition-colors duration-300">See the menu <i class="fa-solid fa-arrow-right text-xs"></i></a>
        <a href="#story" class="inline-flex items-center gap-2 font-semibold px-2 py-3.5 underline-grow border-b-2 border-transparent hover:border-espresso/30 transition-colors duration-300">Our story</a>
      </div>
    </div>
    <div class="relative h-[380px] sm:h-[440px] lg:h-[480px]" id="hero-cups">
      <div class="absolute right-[6%] top-[6%] w-[168px] sm:w-[200px] cup-float hero-cup-wrap" data-float="1">
        <div class="cup-glow"></div>
        <div class="hero-cup-card">
          <div class="relative aspect-square rounded-full cup-ring overflow-hidden" style="background:linear-gradient(135deg,#7a4222,#3c210f)">
            <div class="steam-wrap left-1/2 -translate-x-1/2 top-0"><span class="steam s1"></span><span class="steam s2"></span><span class="steam s3"></span></div>
            <img src="https://images.unsplash.com/photo-1534778101976-62847782c213?q=80&w=600&auto=format&fit=crop" alt="Latte" class="w-full h-full object-cover" onerror="this.style.display=\'none\'" loading="lazy">
          </div>
          <p class="text-center font-display text-lg tracking-wide mt-3">Latte</p>
        </div>
      </div>
      <div class="absolute left-[2%] top-[30%] w-[128px] sm:w-[150px] cup-float hero-cup-wrap" data-float="2">
        <div class="cup-glow"></div>
        <div class="hero-cup-card">
          <div class="relative aspect-square rounded-full cup-ring overflow-hidden" style="background:linear-gradient(135deg,#7a4222,#3c210f)">
            <img src="https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?q=80&w=600&auto=format&fit=crop" alt="Cortado" class="w-full h-full object-cover" onerror="this.style.display=\'none\'" loading="lazy">
          </div>
          <p class="text-center font-display text-base tracking-wide mt-2">Cortado</p>
        </div>
      </div>
      <div class="absolute left-[24%] bottom-0 w-[150px] sm:w-[176px] cup-float hero-cup-wrap" data-float="3">
        <div class="cup-glow"></div>
        <div class="hero-cup-card">
          <div class="relative aspect-square rounded-full cup-ring overflow-hidden" style="background:linear-gradient(135deg,#7a4222,#3c210f)">
            <div class="steam-wrap left-1/2 -translate-x-1/2 top-0"><span class="steam s1"></span><span class="steam s3"></span></div>
            <img src="https://images.unsplash.com/photo-1510591509098-f4fdc6d0ff04?q=80&w=600&auto=format&fit=crop" alt="Espresso" class="w-full h-full object-cover" onerror="this.style.display=\'none\'" loading="lazy">
          </div>
          <p class="text-center font-display text-base tracking-wide mt-2">Espresso</p>
        </div>
      </div>
      <div class="absolute right-[2%] bottom-[4%] w-[104px] sm:w-[124px] cup-float hero-cup-wrap" data-float="4">
        <div class="hero-cup-card">
          <div class="relative aspect-square rounded-full cup-ring overflow-hidden" style="background:linear-gradient(135deg,#7a4222,#3c210f)">
            <img src="https://images.unsplash.com/photo-1572442388796-11668a67e53d?q=80&w=600&auto=format&fit=crop" alt="Cappuccino" class="w-full h-full object-cover" onerror="this.style.display=\'none\'" loading="lazy">
          </div>
        </div>
      </div>
      <div class="absolute left-[36%] top-[14%] w-[76px] h-[76px] hidden sm:block stamp-spin text-copper/80">
        <svg viewBox="0 0 100 100" class="w-full h-full"><defs><path id="stampPath" d="M 50,50 m -38,0 a 38,38 0 1,1 76,0 a 38,38 0 1,1 -76,0"/></defs><text font-size="9.3" letter-spacing="1.5" fill="currentColor"><textPath href="#stampPath">FRESHLY ROASTED • DAILY REFILL • </textPath></text></svg>
      </div>
    </div>
  </div>
  <div class="border-y-2 border-espresso/20 bg-espresso/5 relative">
    <div class="marquee-track text-cream font-display text-xl tracking-wide py-3">
      <div class="flex items-center gap-8 pr-8"><span class="opacity-80">ESPRESSO</span><span class="text-copper/60">•</span><span class="opacity-80">SINGLE ORIGIN</span><span class="text-copper/60">•</span><span class="opacity-80">SLOW BAR</span><span class="text-copper/60">•</span><span class="opacity-80">MERIENDA</span><span class="text-copper/60">•</span><span class="opacity-80">COLD BREW</span><span class="text-copper/60">•</span></div>
      <div class="flex items-center gap-8 pr-8" aria-hidden="true"><span class="opacity-80">ESPRESSO</span><span class="text-copper/60">•</span><span class="opacity-80">SINGLE ORIGIN</span><span class="text-copper/60">•</span><span class="opacity-80">SLOW BAR</span><span class="text-copper/60">•</span><span class="opacity-80">MERIENDA</span><span class="text-copper/60">•</span><span class="opacity-80">COLD BREW</span><span class="text-copper/60">•</span></div>
    </div>
  </div>
</section>

<section id="menu" class="max-w-6xl mx-auto px-5 sm:px-8 pt-20 pb-6">
  <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-6 mb-10" data-reveal>
    <div>
      <h2 class="font-display text-5xl sm:text-6xl tracking-wide">The menu</h2>
      <p class="mt-2 text-espresso/70 max-w-md">Everything's brewed to order — tell the barista how strong you like it.</p>
    </div>
  </div>
  <div id="orderBanner" class="hidden mb-6 flex items-start gap-3 rounded-2xl border-2 border-tan/60 bg-card px-4 py-3 text-sm" role="status">
    <i class="fa-solid fa-circle-info mt-0.5 text-copper"></i><span id="orderBannerText" class="font-semibold"></span>
  </div>
  <div id="tabs" class="flex flex-wrap gap-2 mb-10" data-reveal role="tablist"></div>
  <div id="menuGrid" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5"></div>
</section>

<section class="max-w-6xl mx-auto px-5 sm:px-8 pt-4 pb-16">
  <div class="text-center mb-10" data-reveal>
    <p class="text-copper font-semibold tracking-wide text-sm mb-2">Sabi ng mga suki</p>
    <h2 class="font-display text-4xl sm:text-5xl tracking-wide">Hindi lang kami ang nagsasabi.</h2>
  </div>
  <div class="grid sm:grid-cols-3 gap-5">
    <div class="testi-card bg-card border-2 border-espresso/20 rounded-3xl p-6" data-reveal>
      <div class="star-row mb-3"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i></div>
      <p class="text-espresso/80 text-sm leading-relaxed">"Yung Spanish Latte nila, sobrang sarap — di siya sobrang tamis. Sulit din sa presyo, dito na ako lagi kumukuha ng morning coffee ko."</p>
      <p class="mt-4 font-semibold text-sm">— Jen R.</p>
      <p class="text-xs text-espresso/50">Suki mula 2023</p>
    </div>
    <div class="testi-card bg-card border-2 border-espresso/20 rounded-3xl p-6" data-reveal>
      <div class="star-row mb-3"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star-half-stroke"></i></div>
      <p class="text-espresso/80 text-sm leading-relaxed">"Ang bilis ng serbisyo dahil may QR tracking pa — nalalaman mo agad kung pang-ilan ka. Sobrang convenient lalo na kapag rush hour."</p>
      <p class="mt-4 font-semibold text-sm">— Mark T.</p>
      <p class="text-xs text-espresso/50">Regular sa Taluong branch</p>
    </div>
    <div class="testi-card bg-card border-2 border-espresso/20 rounded-3xl p-6" data-reveal>
      <div class="star-row mb-3"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i></div>
      <p class="text-espresso/80 text-sm leading-relaxed">"Yung Bibingka at Barako combo tuwing umaga, kumpleto na araw ko. Homey talaga yung vibe, parang nasa bahay ka lang."</p>
      <p class="mt-4 font-semibold text-sm">— Aiza D.</p>
      <p class="text-xs text-espresso/50">Suki mula 2024</p>
    </div>
  </div>
</section>

<section id="story" class="relative mt-4 py-20 bg-teal text-cream overflow-hidden">
  <div class="absolute inset-0 grain" style="--grain-opacity:.08"></div>
  <div class="max-w-6xl mx-auto px-5 sm:px-8 grid lg:grid-cols-2 gap-12 items-center relative">
    <div>
      <p class="text-tan font-semibold tracking-wide text-sm mb-4">Since 2026</p>
      <h2 class="font-display text-5xl sm:text-6xl tracking-wide leading-[0.92]">Made for<br>lingering.</h2>
      <p class="mt-6 text-cream/80 leading-relaxed max-w-md">Kapihan started as a two-table stall beside a jeepney terminal. We kept the habit that made it work: no rushing anyone out, and beans roasted close enough to home that you can taste the province in the cup.</p>
    </div>
    <div class="grid grid-cols-2 gap-5">
      <div class="rounded-3xl border-2 border-cream/20 bg-cream/10 p-6" data-reveal>
        <p class="font-display text-5xl tracking-wide">30+</p>
        <p class="mt-1 text-cream/70 text-sm">farmer partners across Benguet &amp; Batangas</p>
      </div>
      <div class="rounded-3xl border-2 border-cream/20 bg-cream/10 p-6 mt-8" data-reveal>
        <p class="font-display text-5xl tracking-wide">6am</p>
        <p class="mt-1 text-cream/70 text-sm">first pull of the day, every day</p>
      </div>
    </div>
  </div>
</section>

<!-- VISIT & 5 BRANCHES SECTION -->
<section id="visit" class="max-w-6xl mx-auto px-5 sm:px-8 py-20">
  <div data-reveal class="mb-8">
    <h2 class="font-display text-5xl sm:text-6xl tracking-wide">Come sit down.</h2>
    <p class="mt-2 text-espresso/70 max-w-sm">5 branches, with room to stay a while.</p>
  </div>

  <div class="grid lg:grid-cols-2 gap-10 items-start">
    <!-- BRANCHES LIST -->
    <div class="space-y-4" data-reveal>
      <div class="bg-card border-2 border-espresso/20 p-5 rounded-3xl flex gap-4 items-center">
        <i class="fa-solid fa-location-dot text-copper text-2xl"></i>
        <div>
          <p class="font-bold text-lg">1. Taluong (Main)</p>
          <p class="text-espresso/70 text-sm">Purok 4, Brgy. Taluong, Polillo, Quezon Province</p>
        </div>
      </div>
      <div class="bg-card border-2 border-espresso/20 p-5 rounded-3xl flex gap-4 items-center">
        <i class="fa-solid fa-location-dot text-copper text-2xl"></i>
        <div>
          <p class="font-bold text-lg">2. Binibitinan</p>
          <p class="text-espresso/70 text-sm">Brgy. Binibitinan, Polillo, Quezon Province</p>
        </div>
      </div>
      <div class="bg-card border-2 border-espresso/20 p-5 rounded-3xl flex gap-4 items-center">
        <i class="fa-solid fa-location-dot text-copper text-2xl"></i>
        <div>
          <p class="font-bold text-lg">3. Salipsip</p>
          <p class="text-espresso/70 text-sm">Brgy. Salipsip, Polillo, Quezon Province</p>
        </div>
      </div>
      <div class="bg-card border-2 border-espresso/20 p-5 rounded-3xl flex gap-4 items-center">
        <i class="fa-solid fa-location-dot text-copper text-2xl"></i>
        <div>
          <p class="font-bold text-lg">4. Anawan / Burdeos</p>
          <p class="text-espresso/70 text-sm">Brgy. Anawan, Burdeos, Quezon Province</p>
        </div>
      </div>
      <div class="bg-card border-2 border-espresso/20 p-5 rounded-3xl flex gap-4 items-center">
        <i class="fa-solid fa-location-dot text-copper text-2xl"></i>
        <div>
          <p class="font-bold text-lg">5. Bayan ng Polillo</p>
          <p class="text-espresso/70 text-sm">Poblacion, Bayan ng Polillo, Quezon Province</p>
        </div>
      </div>
    </div>

    <!-- STOREFRONT PHOTO -->
    <div class="rounded-3xl overflow-hidden relative min-h-[380px] h-full group shadow-xl border-2 border-espresso/20" data-reveal>
      <img src="https://images.unsplash.com/photo-1554118811-1e0d58224f24?q=80&w=1000&auto=format&fit=crop" alt="Kapihan Storefront" class="absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"/>
      <div class="absolute inset-0 bg-gradient-to-t from-espresso/90 via-espresso/40 to-transparent"></div>
      <div class="relative h-full flex items-end p-8 z-10">
        <p class="font-display text-cream text-4xl tracking-wide leading-tight drop-shadow-md">Grab a seat.<br>The pot's always on.</p>
      </div>
    </div>
  </div>
</section>

<footer class="border-t-2 border-espresso/10 bg-espresso/5 py-12 text-center text-espresso/60 text-sm">
  <div class="max-w-6xl mx-auto px-5 space-y-1.5">
    <p class="font-display text-2xl tracking-wide text-espresso mb-1"><?= h(strtoupper(setting('store_name'))) ?></p>
    <?php if (setting('tagline') !== ''): ?><p class="italic text-copper/70 mb-4"><?= h(setting('tagline')) ?></p><?php endif; ?>
    <?php if (setting('store_description') !== ''): ?><p class="max-w-xl mx-auto text-espresso/70 leading-relaxed mb-4 whitespace-pre-line"><?= h(setting('store_description')) ?></p><?php endif; ?>
    <?php if (setting('address') !== ''): ?><p class="mb-2"><i class="fa-solid fa-location-dot mr-1.5 text-copper"></i><?= h(setting('address')) ?></p><?php endif; ?>
    <p class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1 mb-4">
      <?php if (setting('contact_number') !== ''): ?><span><i class="fa-solid fa-phone mr-1.5 text-copper"></i><?= h(setting('contact_number')) ?></span><?php endif; ?>
      <?php if (setting('email') !== ''): ?><a href="mailto:<?= h(setting('email')) ?>" class="hover:text-copper"><i class="fa-solid fa-envelope mr-1.5 text-copper"></i><?= h(setting('email')) ?></a><?php endif; ?>
    </p>
    <p class="mb-4"><i class="fa-regular fa-clock mr-1.5 text-copper"></i>Open daily <?= h(store_open_label()) ?>
      <span class="ml-2 inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11px] font-bold <?= setting('store_status') === 'open' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' ?>">
        <span class="w-1.5 h-1.5 rounded-full <?= setting('store_status') === 'open' ? 'bg-emerald-500' : 'bg-rose-500' ?>"></span><?= setting('store_status') === 'open' ? 'Open' : 'Closed' ?></span></p>
    <p class="pt-4 text-espresso/40 border-t border-espresso/5 mt-6">© <?= date('Y') ?> <?= h(setting('store_name')) ?>. Brewed with care.</p>
  </div>
</footer>

<!-- CART DRAWER MODAL -->
<div id="cartDrawer" class="fixed inset-0 z-50 pointer-events-none transition-opacity duration-300 opacity-0">
  <div id="cartBackdrop" class="absolute inset-0 bg-espresso/40 backdrop-blur-sm"></div>
  <div id="cartPanel" class="absolute right-0 top-0 bottom-0 w-full max-w-md bg-cream shadow-2xl flex flex-col translate-x-full transition-transform duration-300 pointer-events-auto">
    <div class="p-6 border-b border-espresso/10 flex items-center justify-between">
      <h3 class="font-display text-3xl">Your Order</h3>
      <button id="closeCart" class="w-8 h-8 rounded-full border border-espresso/15 flex items-center justify-center">
        <i class="fa-solid fa-xmark text-sm"></i>
      </button>
    </div>
    <div id="cartItems" class="flex-1 overflow-y-auto p-6 space-y-4"></div>
    <div class="p-6 border-t-2 border-espresso/10 bg-card space-y-4">
      <div id="cartFormArea">
        <div>
          <label class="block text-xs font-bold uppercase text-espresso/70 mb-1">Pangalan Mo:</label>
          <input type="text" id="custName" data-gramm="false" data-gramm_editor="false" data-enable-grammarly="false" aria-describedby="nameError" placeholder="hal. Jun Jun" class="w-full bg-cream border border-espresso/15 rounded-xl px-4 py-2.5 text-sm focus:outline-none" />
          <p id="nameError" class="hidden text-xs font-semibold text-rose-600 mt-1.5" role="alert"></p>
        </div>

        <div id="orderTypeWrap" class="mt-4 hidden">
          <label class="block text-xs font-bold uppercase text-espresso/70 mb-2">Uri ng Order:</label>
          <div id="orderTypeOptions" class="grid gap-2"></div>
        </div>

        <div id="notesWrap" class="mt-4 hidden">
          <label for="orderNotes" class="block text-xs font-bold uppercase text-espresso/70 mb-1">Note <span class="font-normal normal-case text-espresso/50">(optional)</span>:</label>
          <textarea id="orderNotes" data-gramm="false" data-gramm_editor="false" data-enable-grammarly="false" rows="2" maxlength="200" placeholder="hal. less sugar, walang yelo" class="w-full bg-cream border border-espresso/15 rounded-xl px-4 py-2.5 text-sm focus:outline-none resize-none"></textarea>
        </div>

        <div class="mt-4">
          <label class="block text-xs font-bold uppercase text-espresso/70 mb-2">Paraan ng Bayad:</label>
          <div id="paymentOptions" class="grid gap-2"></div>
          <div id="payInfo" class="hidden mt-3 rounded-xl bg-cream border border-espresso/10 p-3 text-sm"></div>
        </div>

        <div class="flex justify-between items-center text-lg font-bold mt-4">
          <span>Total</span>
          <span id="cartTotal" class="text-copper"><?= h(cur()) ?>0.00</span>
        </div>
        <p id="minNotice" class="hidden text-xs font-semibold text-rose-600 mt-3"></p>
        <p id="orderRules" class="hidden text-[11px] leading-snug text-espresso/60 mt-3 whitespace-pre-wrap"></p>
        <button id="placeOrderBtn" onclick="submitOrder()" class="w-full bg-espresso text-cream font-semibold py-3.5 rounded-full hover:bg-bark transition-colors mt-4 disabled:opacity-50 disabled:cursor-not-allowed">
          Place Order
        </button>
      </div>

      <!-- CONFIRMATION / QR PANEL (shown after a successful order) -->
      <div id="confirmArea" class="hidden text-center">
        <p class="font-display text-2xl tracking-wide">Salamat, <span id="confirmName"></span>!</p>
        <p class="text-espresso/60 text-sm mt-1">Order <span id="confirmOrderNo" class="font-bold text-copper"></span> — pang-<span id="confirmQueue" class="font-bold"></span> ka sa pila.</p>

        <p id="confirmPrep" class="text-xs font-semibold text-espresso/60 mt-2"></p>
        <div id="confirmPay" class="hidden mt-3 rounded-xl bg-cream border border-espresso/10 p-3 text-sm text-left"></div>

        <div id="qrHolder" class="bg-white rounded-2xl p-4 inline-block mt-4"></div>

        <p class="text-xs text-espresso/50 mt-3">I-scan gamit ang camera ng phone mo (parehong WiFi) para makita ang live status ng order mo, o buksan mismo ang link:</p>
        <a id="trackLink" href="#" target="_blank" class="block text-xs text-copper font-semibold underline mt-1 break-all"></a>

        <button onclick="resetOrderPanel()" class="w-full bg-espresso text-cream font-semibold py-3 rounded-full hover:bg-bark transition-colors mt-5">
          Bagong Order
        </button>
      </div>
    </div>
  </div>
</div>

<!-- KAPitan Bot (lalabas lang kapag naka-enable sa Admin Settings) -->
<div id="aiWidget" class="hidden fixed z-40">
  <div id="aiPanel" class="ai-panel bg-card rounded-3xl border border-espresso/10 shadow-2xl flex flex-col overflow-hidden" role="dialog" aria-label="KAPitan Bot chat" aria-hidden="true">
    <div class="bg-espresso text-cream px-4 py-3.5 flex items-center justify-between gap-3">
      <div class="flex items-center gap-3 min-w-0">
        <img src="assets/img/kapihan-logo.png" alt="" width="40" height="40" class="w-10 h-10 rounded-full flex-shrink-0" style="box-shadow:0 0 0 2px rgba(244,234,218,.85)">
        <div class="leading-tight min-w-0"><p id="botTitle" class="font-display text-xl tracking-wide truncate">KAPitan Bot</p><p class="text-[11px] text-cream/70">Kasama mo sa bawat kape.</p></div>
      </div>
      <button id="aiClose" type="button" class="w-8 h-8 rounded-full hover:bg-white/10 grid place-items-center flex-shrink-0" aria-label="Close chat"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div id="aiMessages" class="flex-1 overflow-y-auto overflow-x-hidden p-4 space-y-3 flex flex-col" aria-live="polite"></div>
    <form id="aiForm" class="px-3 pt-3 pb-2 border-t border-espresso/10 bg-card">
      <div class="flex items-center gap-2">
        <input id="aiInput" data-gramm="false" data-gramm_editor="false" data-enable-grammarly="false" type="text" maxlength="500" autocomplete="off" placeholder="Type your message…" aria-label="Message" class="flex-1 min-w-0 bg-cream border border-espresso/15 rounded-full px-4 py-2.5 text-sm focus:outline-none">
        <button id="aiSend" type="submit" class="h-10 px-4 rounded-full bg-copper text-cream text-sm font-semibold inline-flex items-center gap-2 disabled:opacity-50 flex-shrink-0" aria-label="Send"><span class="hidden min-[380px]:inline">Send</span><i class="fa-solid fa-paper-plane text-xs"></i></button>
      </div>
      <p id="botPrivacy" class="hidden text-[10.5px] leading-snug text-espresso/50 mt-2 px-1"></p>
    </form>
  </div>
  <button id="aiToggle" type="button" class="ai-fab" aria-expanded="false" aria-controls="aiPanel" aria-label="Open KAPitan Bot chat" title="KAPitan Bot">
    <img src="assets/img/kapihan-logo.png" alt="KAPitan Bot" width="56" height="56">
    <span class="ai-fab-label" aria-hidden="true">KAPitan Bot</span>
    <span class="ai-fab-x" aria-hidden="true"><i class="fa-solid fa-xmark"></i></span>
  </button>
</div>

<div id="qrModal" class="hidden fixed inset-0 items-center justify-center p-4" style="z-index:120; background:rgba(36,24,18,.65); backdrop-filter:blur(3px)" role="dialog" aria-modal="true" aria-label="QR code">
  <div class="bg-card rounded-3xl p-5 w-full max-w-sm text-center shadow-2xl">
    <img id="qrBig" src="" alt="QR code" class="w-full max-h-[70vh] object-contain rounded-2xl bg-white border border-espresso/10 p-3">
    <button id="qrClose" type="button" class="mt-4 bg-espresso text-cream font-semibold text-sm px-6 py-2.5 rounded-full hover:bg-bark transition-colors">Close</button>
  </div>
</div>

<div id="toastHost" aria-live="polite" aria-atomic="false"></div>

<script>
let CONFIG = <?= json_encode(public_config(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
let CUR = CONFIG.currency;
let configKey = JSON.stringify(CONFIG);

function normalizeProduct(p){
  return {
    id: String(p.id), name: p.name, cat: p.category || 'coffee', price: parseFloat(p.price),
    img: p.image || '',
    note: p.description || 'Freshly brewed daily.',
    stock: (p.stock_qty === null || p.stock_qty === undefined) ? null : parseInt(p.stock_qty)
  };
}

const dbProducts = <?= $productsJson ?>;
let MENU_ERROR = <?= $menuError ? 'true' : 'false' ?>;
const ADMIN_EMPTY_HINT = <?= $adminSeesEmptyMenu ? 'true' : 'false' ?>;
let PRODUCTS = (dbProducts || []).map(normalizeProduct);

/* ---------- KAPIHAN toast (kapalit ng browser alert) ---------- */
function showToast({ title = '', message = '', type = 'info', actionLabel = '', onAction = null, duration = null } = {}) {
  const host = document.getElementById('toastHost');
  if(!host) return;
  const icons = { warn:'fa-triangle-exclamation', error:'fa-circle-xmark', success:'fa-circle-check', info:'fa-circle-info' };

  // iwas-doble: kung kapareho ang nakabukas na toast, 'wag nang ulitin
  for(const t of host.children){ if(t.dataset.key === type + '|' + title + '|' + message) return; }
  while(host.children.length >= 3) host.firstElementChild.remove();

  const el = document.createElement('div');
  el.className = 'k-toast ' + (icons[type] ? type : 'info');
  el.dataset.key = type + '|' + title + '|' + message;
  el.setAttribute('role', type === 'error' ? 'alert' : 'status');

  const icon = document.createElement('i');
  icon.className = 'fa-solid ' + (icons[type] || icons.info) + ' k-toast-icon';
  icon.setAttribute('aria-hidden', 'true');

  const body = document.createElement('div');
  body.className = 'k-toast-body';
  if(title){ const t = document.createElement('p'); t.className = 'k-toast-title'; t.textContent = title; body.appendChild(t); }
  if(message){ const m = document.createElement('p'); m.className = 'k-toast-msg'; m.textContent = message; body.appendChild(m); }

  let timer = null;
  const dismiss = () => {
    clearTimeout(timer);
    el.classList.remove('show');
    setTimeout(() => el.remove(), 300);
  };

  if(actionLabel){
    const a = document.createElement('button');
    a.type = 'button'; a.className = 'k-toast-action'; a.textContent = actionLabel;
    a.addEventListener('click', () => { dismiss(); if(typeof onAction === 'function') onAction(); });
    body.appendChild(a);
  }

  const x = document.createElement('button');
  x.type = 'button'; x.className = 'k-toast-close'; x.setAttribute('aria-label', 'Isara');
  x.innerHTML = '<i class="fa-solid fa-xmark"></i>';
  x.addEventListener('click', dismiss);

  el.append(icon, body, x);
  host.appendChild(el);
  requestAnimationFrame(() => el.classList.add('show'));

  const ms = duration ?? (actionLabel ? 8000 : 5500);
  const arm = () => { timer = setTimeout(dismiss, ms); };
  el.addEventListener('mouseenter', () => clearTimeout(timer));
  el.addEventListener('mouseleave', arm);
  arm();
}

function applyConfig(c){
  CONFIG = c; CUR = c.currency;
  const changed = renderCheckoutOptions();
  applyAiConfig();
  renderMenu();
  updateCart();
  if(c.maintenance){
    showToast({ title: 'Back soon!', message: 'The shop is under maintenance. Reloading…', type: 'warn' });
    setTimeout(() => window.location.reload(), 1800);
    return;
  }
  if(changed.length){
    showToast({ title: 'Checkout options updated', message: 'Your ' + changed.join(' and ') + ' is no longer available, so we picked another one for you.', type: 'info' });
  }
}

/* ---------- i-sync ang cart sa kasalukuyang menu sa database ---------- */
let syncing = false;
async function syncMenu() {
  if(syncing) return { changed: false };
  syncing = true;
  try {
    const res = await fetch('menu.php', { cache: 'no-store' });
    const data = await res.json();
    if(!data.success || !Array.isArray(data.products)) { if(!PRODUCTS.length) { MENU_ERROR = true; renderMenu(); } return { changed: false, failed: true }; }
    const hadError = MENU_ERROR; MENU_ERROR = false;

    // settings galing sa admin (ordering, payments, AI, maintenance...) — i-apply lang kapag may nagbago
    if(data.config){
      const key = JSON.stringify(data.config);
      if(key !== configKey){ configKey = key; applyConfig(data.config); }
    }

    const before = JSON.stringify(PRODUCTS);
    PRODUCTS = data.products.map(normalizeProduct);
    if(JSON.stringify(PRODUCTS) !== before || hadError) renderMenu();

    const byId = new Map(PRODUCTS.map(p => [p.id, p]));
    const byName = new Map(PRODUCTS.map(p => [p.name.trim().toLowerCase(), p]));
    const removed = [], reduced = [], repriced = [];
    const next = [];

    for(const it of cart){
      // hanapin sa id; kung wala, subukan sa pangalan (kapag nabago lang ang id)
      const p = byId.get(String(it.id)) || byName.get(String(it.name).trim().toLowerCase());
      if(!p){ removed.push(it.name); continue; }
      if(p.stock !== null && p.stock <= 0){ removed.push(p.name + ' (sold out)'); continue; }

      let qty = it.qty;
      if(p.stock !== null && qty > p.stock){ qty = p.stock; reduced.push(p.name + ' (' + p.stock + ' left)'); }
      if(Math.abs(p.price - it.price) > 0.001) repriced.push(p.name);

      const dup = next.find(n => n.id === p.id);
      if(dup) dup.qty = (p.stock !== null) ? Math.min(dup.qty + qty, p.stock) : dup.qty + qty;
      else next.push({ ...p, qty });
    }

    const changed = removed.length || reduced.length || repriced.length;
    cart = next;
    updateCart();

    if(changed){
      const parts = [];
      if(removed.length)  parts.push('One or more items in your cart are no longer available and were removed: ' + removed.join(', ') + '.');
      if(reduced.length)  parts.push('Quantity reduced to match stock: ' + reduced.join(', ') + '.');
      if(repriced.length) parts.push('Price updated: ' + repriced.join(', ') + '.');
      const drawerOpen = !document.getElementById('cartDrawer').classList.contains('pointer-events-none');
      showToast({
        title: 'Please review your cart',
        message: parts.join(' ') + (cart.length ? ' Your total has been recalculated.' : ''),
        type: 'warn',
        actionLabel: drawerOpen ? '' : 'View Cart',
        onAction: () => toggleCart(true)
      });
    }
    return { changed: !!changed };
  } catch(e) {
    if(!PRODUCTS.length){ MENU_ERROR = true; renderMenu(); }
    return { changed: false, failed: true };
  } finally {
    syncing = false;
  }
}

const CATS = [
  { id:'all', label:'All Items', icon:'fa-border-all' },
  { id:'coffee', label:'Hot Coffee', icon:'fa-mug-hot' },
  { id:'cold', label:'Iced Drinks', icon:'fa-snowflake' },
  { id:'special', label:'Non-Coffee', icon:'fa-leaf' },
  { id:'merienda', label:'Merienda', icon:'fa-utensils' }
];

let activeCat = 'all';
let cart = [];
let selectedPayment = '';
let selectedType = '';

const $id = (x) => document.getElementById(x);
const ICONS = { cash: 'fa-money-bill-wave', gcash: 'fa-mobile-screen', maya: 'fa-wallet', dine_in: 'fa-utensils', take_out: 'fa-bag-shopping', pick_up: 'fa-store' };
const PAY_LOGOS = { gcash: 'assets/img/gcash.png', maya: 'assets/img/maya.png' };
function payButtonInner(k){
  const visual = PAY_LOGOS[k]
    ? '<span class="pay-logo"><img src="' + PAY_LOGOS[k] + '" alt="" decoding="async"></span>'
    : '<span class="pay-icon"><i class="fa-solid ' + (ICONS[k] || 'fa-wallet') + '" aria-hidden="true"></i></span>';
  return visual + '<span class="pay-label">' + esc(CONFIG.payments[k].label) + '</span>';
}
const setCols = (el, n) => { el.style.gridTemplateColumns = 'repeat(' + Math.max(1, n) + ', minmax(0, 1fr))'; };

function orderingMessage(){
  return CONFIG.orderingWhy === 'closed'
    ? 'We’re currently closed. You can still browse the menu — see you when we open!'
    : 'Online ordering is temporarily unavailable. You can still browse the menu.';
}

function renderPayInfo(){
  const box = $id('payInfo');
  const p = (CONFIG.payments || {})[selectedPayment];
  if(!p || selectedPayment === 'cash'){ box.classList.add('hidden'); box.innerHTML = ''; return; }
  box.innerHTML = payInfoHtml(p, selectedPayment);
  box.classList.remove('hidden');
}
// "Scan to Pay" — GCash/Maya logo, QR code, account name at instructions mula sa Admin Settings. Walang sirang larawan.
function qrMissingHtml(label){
  return '<p class="qr-missing rounded-xl bg-white border border-espresso/10 px-4 py-5 text-sm font-semibold text-espresso/70">' + esc(label) + ' QR payment is currently unavailable.</p>';
}
function payInfoHtml(p, key){
  const logo = PAY_LOGOS[key] ? '<span class="qr-logo"><img src="' + PAY_LOGOS[key] + '" alt=""></span>' : '';
  const qr = p.qr
    ? '<button type="button" class="qr-open" data-qr="' + esc(p.qr) + '" aria-label="Enlarge the ' + esc(p.label) + ' QR code"><img src="' + esc(p.qr) + '" alt="' + esc(p.label) + ' QR code" class="qr-img"></button>' +
      '<p class="text-[11px] text-espresso/50 mt-1.5">Tap the QR code to enlarge</p>'
    : qrMissingHtml(p.label);
  return '<div class="text-center">' +
    '<div class="flex items-center justify-center gap-2.5 mb-3">' + logo + '<p class="font-display text-xl tracking-wide leading-none pt-0.5">Scan to Pay</p></div>' + qr +
    (p.name ? '<p class="mt-3 text-[10.5px] font-bold uppercase tracking-wide text-espresso/50">Account name</p><p class="font-semibold">' + esc(p.name) + '</p>' : '') +
    (p.number ? '<p class="font-mono text-sm text-espresso/80 mt-0.5">' + esc(p.number) + '</p>' : '') +
    (CONFIG.payInstructions ? '<p class="mt-3 text-xs text-espresso/70 whitespace-pre-wrap text-left">' + esc(CONFIG.payInstructions) + '</p>' : '') +
    '<p class="mt-3 text-[10.5px] text-espresso/45">Never share your PIN, OTP or password with anyone.</p></div>';
}
// QR na hindi ma-load: palitan ng malinaw na mensahe (hindi sirang icon)
document.addEventListener('error', (e) => {
  const t = e.target;
  if(t && t.tagName === 'IMG' && t.classList && t.classList.contains('qr-img')){
    const holder = t.closest('.qr-open') || t;
    const wrap = document.createElement('div');
    wrap.innerHTML = qrMissingHtml((t.alt || '').replace(/ QR code$/, '') || 'QR');
    holder.replaceWith(wrap.firstChild);
  }
}, true);

function renderOrderState(){
  const banner = $id('orderBanner'), btn = $id('placeOrderBtn');
  const open = !!CONFIG.orderingOpen;
  banner.classList.toggle('hidden', open);
  $id('orderBannerText').textContent = open ? '' : orderingMessage();
  btn.disabled = !open;
  btn.textContent = open ? 'Place Order' : 'Ordering unavailable';
  const rules = $id('orderRules');
  rules.textContent = CONFIG.orderRules || '';
  rules.classList.toggle('hidden', !CONFIG.orderRules);
}

// Ino-render ang order type / payment buttons ayon sa Admin Settings. Nagbabalik ng listahan ng pinalitang pinili.
function renderCheckoutOptions(){
  const types = Object.keys(CONFIG.orderTypes || {});
  const pays = Object.keys(CONFIG.payments || {});
  const changed = [];
  if(types.indexOf(selectedType) < 0){ if(selectedType) changed.push('order type'); selectedType = types[0] || ''; }
  if(pays.indexOf(selectedPayment) < 0){ if(selectedPayment) changed.push('payment method'); selectedPayment = pays[0] || ''; }

  const tEl = $id('orderTypeOptions');
  $id('orderTypeWrap').classList.toggle('hidden', types.length <= 1);   // kung isa lang, auto-select na
  tEl.innerHTML = types.map(k => '<button type="button" data-type="' + esc(k) + '" class="pay-btn text-xs font-semibold py-2.5 rounded-xl border border-espresso/15" aria-pressed="' + (k === selectedType) + '"><i class="fa-solid ' + (ICONS[k] || 'fa-bag-shopping') + ' mb-1 block text-sm"></i>' + esc(CONFIG.orderTypes[k]) + '</button>').join('');
  setCols(tEl, types.length);

  const pEl = $id('paymentOptions');
  pEl.innerHTML = pays.map(k => '<button type="button" data-pay="' + esc(k) + '" class="pay-btn pay-method rounded-xl border border-espresso/15" aria-pressed="' + (k === selectedPayment) + '">' + payButtonInner(k) + '</button>').join('');
  setCols(pEl, pays.length);

  $id('notesWrap').classList.toggle('hidden', !CONFIG.allowNotes);
  renderPayInfo();
  renderOrderState();
  return changed;
}

$id('orderTypeOptions').addEventListener('click', (e) => {
  const btn = e.target.closest('[data-type]'); if(!btn) return;
  selectedType = btn.dataset.type;
  $id('orderTypeOptions').querySelectorAll('[data-type]').forEach(b => b.setAttribute('aria-pressed', b === btn));
});
$id('paymentOptions').addEventListener('click', (e) => {
  const btn = e.target.closest('[data-pay]'); if(!btn) return;
  selectedPayment = btn.dataset.pay;
  $id('paymentOptions').querySelectorAll('[data-pay]').forEach(b => b.setAttribute('aria-pressed', b === btn));
  renderPayInfo();
});

const tabsEl = document.getElementById('tabs');
tabsEl.innerHTML = CATS.map(c => `
  <button class="tab-btn flex items-center gap-2 px-4 py-2.5 rounded-full border-2 border-espresso/20 text-sm font-semibold transition-colors" role="tab" data-cat="${c.id}" aria-selected="${c.id==='all'}">
    <i class="fa-solid ${c.icon} text-xs"></i>${c.label}
  </button>
`).join('');

tabsEl.addEventListener('click', (e) => {
  const btn = e.target.closest('.tab-btn');
  if(!btn) return;
  activeCat = btn.dataset.cat;
  [...tabsEl.querySelectorAll('.tab-btn')].forEach(b => b.setAttribute('aria-selected', b === btn));
  renderMenu();
});

const gridEl = document.getElementById('menuGrid');
const PESO_FMT = new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
function peso(n){ return CUR + PESO_FMT.format(Number(n) || 0); }
function esc(v){ return String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

const BESTSELLER_KEYWORDS = ['latte', 'cappuccino', 'mocha', 'barako'];
function isBestSeller(name){
  const n = name.toLowerCase();
  return BESTSELLER_KEYWORDS.some(k => n.includes(k));
}

function renderMenu(){
  const items = PRODUCTS.filter(p => activeCat === 'all' || p.cat === activeCat);
  if(items.length === 0){
    if(PRODUCTS.length === 0 && MENU_ERROR){
      // may error sa pag-load — hindi ito "walang menu"
      gridEl.innerHTML = '<div class="col-span-full text-center py-12"><p class="font-semibold text-espresso/80">Unable to load the menu. Please try again.</p>' +
        '<button type="button" id="menuRetry" class="mt-4 bg-espresso text-cream font-semibold text-sm px-6 py-2.5 rounded-full hover:bg-bark transition-colors"><i class="fa-solid fa-rotate-right mr-2"></i>Try again</button></div>';
      return;
    }
    let msg = 'Wala pang item sa kategoryang ito.';
    if(PRODUCTS.length === 0) msg = ADMIN_EMPTY_HINT ? 'Your menu is empty. Add products in Admin → Product Management.' : 'Inihahanda pa ang menu — balik ka mamaya!';
    gridEl.innerHTML = '<p class="col-span-full text-center text-espresso/60 py-12">' + msg + '</p>';
    return;
  }
  gridEl.innerHTML = items.map(p => {
    const outOfStock = p.stock !== null && p.stock <= 0;
    const lowStock = p.stock !== null && p.stock > 0 && p.stock <= 5;
    return `
    <div class="menu-card card-lift bg-card rounded-3xl p-5 flex items-center gap-4 border-2 border-espresso/20 shadow-sm ${outOfStock ? 'opacity-60' : ''}" data-id="${esc(p.id)}">
      <div class="relative w-20 h-20 rounded-full cup-ring overflow-hidden flex-shrink-0 shadow-inner" style="background:linear-gradient(135deg,#7a4222,#3c210f)">
        ${!outOfStock && isBestSeller(p.name) ? '<span class="best-seller-badge"><i class="fa-solid fa-fire"></i> Paborito</span>' : ''}
        ${outOfStock ? '<span class="best-seller-badge" style="background:#6b7280">Ubos</span>' : ''}
        ${p.img ? `<img src="${esc(p.img)}" alt="${esc(p.name)}" class="w-full h-full object-cover" onerror="this.style.display='none'" loading="lazy">` : ''}
      </div>
      <div class="flex-1 min-w-0">
        <div class="flex items-baseline justify-between gap-2">
          <p class="font-display text-xl tracking-wide leading-none">${esc(p.name)}</p>
          <p class="font-semibold text-copper whitespace-nowrap">${peso(p.price)}</p>
        </div>
        <p class="text-espresso/60 text-[13px] mt-1.5 leading-snug">${esc(p.note)}</p>
        ${outOfStock ? '<p class="text-rose-600 text-[11px] font-bold mt-1">Out of stock</p>' : ''}
        ${lowStock ? `<p class="text-amber-600 text-[11px] font-bold mt-1">${p.stock} na lang!</p>` : ''}
      </div>
      <button onclick="addToCart('${esc(p.id)}')" ${outOfStock ? 'disabled' : ''} class="add-btn w-9 h-9 rounded-full ${outOfStock ? 'bg-gray-300 cursor-not-allowed' : 'bg-espresso hover:bg-copper'} text-cream grid place-items-center flex-shrink-0 transition-colors duration-300">
        <i class="fa-solid fa-plus text-xs"></i>
      </button>
    </div>
  `;
  }).join('');
}

function addToCart(id) {
  if(!CONFIG.orderingOpen){
    showToast({ title: 'Ordering is unavailable', message: orderingMessage(), type: 'warn' });
    return;
  }
  const prod = PRODUCTS.find(p => String(p.id) === String(id));
  if(!prod){
    // luma na ang menu sa screen — i-refresh nang tahimik
    showToast({ title: 'Menu updated', message: 'The menu has changed, so we refreshed it for you.', type: 'info' });
    syncMenu();
    return;
  }
  if(prod.stock !== null && prod.stock <= 0) return;

  const existing = cart.find(i => String(i.id) === String(id));
  const currentQty = existing ? existing.qty : 0;
  if(prod.stock !== null && currentQty + 1 > prod.stock){
    showToast({ title: 'Limited stock', message: 'Only ' + prod.stock + ' left of ' + prod.name + '.', type: 'warn' });
    return;
  }

  if(existing) existing.qty++;
  else cart.push({ ...prod, qty: 1 });
  updateCart();
}

function updateCart() {
  const count = cart.reduce((acc, i) => acc + i.qty, 0);
  const total = cart.reduce((acc, i) => acc + (i.price * i.qty), 0);
  document.getElementById('cartCount').textContent = count;
  document.getElementById('cartTotal').textContent = peso(total);
  const minEl = document.getElementById('minNotice');
  if(minEl){
    const min = Number(CONFIG.minOrder) || 0;
    const short = cart.length > 0 && min > 0 && total + 0.00001 < min;
    minEl.textContent = short ? 'Minimum order is ' + peso(min) + ' — add ' + peso(min - total) + ' more.' : '';
    minEl.classList.toggle('hidden', !short);
  }

  document.getElementById('cartItems').innerHTML = cart.length === 0 ? '<p class="text-center text-espresso/50 my-10">Cart is empty.</p>' :
    cart.map(i => `
      <div class="flex items-center justify-between border-b border-espresso/10 pb-3">
        <div><p class="font-bold text-sm">${esc(i.name)}</p><p class="text-xs text-copper">${peso(i.price)}</p></div>
        <div class="flex items-center gap-2">
          <button onclick="changeQty('${esc(i.id)}', -1)" class="w-6 h-6 rounded-full border border-espresso/20 flex items-center justify-center text-xs">-</button>
          <span class="text-sm font-semibold">${i.qty}</span>
          <button onclick="changeQty('${esc(i.id)}', 1)" class="w-6 h-6 rounded-full border border-espresso/20 flex items-center justify-center text-xs">+</button>
        </div>
      </div>
    `).join('');
}

function changeQty(id, delta) {
  const item = cart.find(i => String(i.id) === String(id));
  if(item) {
    if(delta > 0 && item.stock !== null && item.qty + delta > item.stock){
      showToast({ title: 'Limited stock', message: 'Only ' + item.stock + ' left of ' + item.name + '.', type: 'warn' });
      return;
    }
    item.qty += delta;
    if(item.qty <= 0) cart = cart.filter(i => String(i.id) !== String(id));
  }
  updateCart();
}

function showNameError(msg){
  const el = $id('custName'), p = $id('nameError');
  p.textContent = msg; p.classList.remove('hidden');
  el.classList.add('ring-2', 'ring-rose-300', 'border-rose-400');
}
function clearNameError(){
  const el = $id('custName'), p = $id('nameError');
  p.textContent = ''; p.classList.add('hidden');
  el.classList.remove('ring-2', 'ring-rose-300', 'border-rose-400');
}
$id('custName').addEventListener('input', clearNameError);

let submitting = false;
async function submitOrder() {
  if(submitting) return;
  if(!CONFIG.orderingOpen){
    showToast({ title: 'Ordering is unavailable', message: orderingMessage(), type: 'warn' });
    return;
  }
  const nameEl = document.getElementById('custName');
  const name = nameEl.value.trim();

  if(!cart.length){
    showToast({ title: 'Your cart is empty', message: 'Add an item to your cart before placing an order.', type: 'warn' });
    return;
  }
  if(!name){
    showNameError('Please enter your name before placing the order.');
    showToast({ title: 'Name required', message: 'Please enter your name before placing the order.', type: 'warn' });
    nameEl.focus();
    return;
  }
  if(!selectedType || !selectedPayment){
    showToast({ title: 'Almost there', message: 'Please choose an order type and a payment method.', type: 'warn' });
    return;
  }

  submitting = true;
  try {
    // I-sync muna sa menu at settings. Kung may nabago, ipakita muna sa customer bago ituloy.
    const sync = await syncMenu();
    if(sync.changed) return;
    if(!CONFIG.orderingOpen){ showToast({ title: 'Ordering is unavailable', message: orderingMessage(), type: 'warn' }); return; }

    const total = cart.reduce((a, i) => a + i.price * i.qty, 0);
    const min = Number(CONFIG.minOrder) || 0;
    if(min > 0 && total + 0.00001 < min){
      showToast({ title: 'Minimum order', message: 'The minimum order is ' + peso(min) + '. Add ' + peso(min - total) + ' more to continue.', type: 'warn' });
      return;
    }

    const res = await fetch('save_order.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        customer_name: name,
        items: cart.map(i => ({ id: i.id, qty: i.qty })),
        payment_method: selectedPayment,
        order_type: selectedType,
        notes: CONFIG.allowNotes ? document.getElementById('orderNotes').value.trim() : ''
      })
    });
    let result = null;
    try { result = await res.json(); } catch(e) {}

    if(result && result.success) {
      showConfirmation(name, result.order_id, result.queue_pos, result.token, result);
      cart = [];
      nameEl.value = '';
      document.getElementById('orderNotes').value = '';
      updateCart();
    } else if(result && result.code === 'maintenance') {
      showToast({ title: 'Back soon!', message: result.message, type: 'warn' });
      setTimeout(() => window.location.reload(), 2000);
    } else if(result && ['ordering_off', 'payment_unavailable', 'order_type_unavailable'].indexOf(result.code) >= 0) {
      await syncMenu();   // kukunin ang pinakabagong settings
      showToast({ title: 'Checkout was updated', message: result.message, type: 'warn' });
    } else if(result && (result.code === 'unavailable' || result.code === 'stock')) {
      // pangalanan muna ang mga apektadong item (bago ma-sync at maalis sa cart)
      const bad = (result.items || []).map(id => (cart.find(i => String(i.id) === String(id)) || {}).name).filter(Boolean);
      const again = await syncMenu();   // aalisin/aayusin ang cart at magpapakita ng sarili nitong abiso
      if(!again.changed){
        showToast({ title: 'Please review your cart', message: (bad.length ? bad.join(', ') + ' — ' : '') + (result.message || 'One or more items in your cart are no longer available.'), type: 'error' });
      }
    } else if(result && result.code === 'min_order') {
      showToast({ title: 'Minimum order', message: result.message, type: 'warn' });
    } else {
      showToast({ title: 'Something went wrong', message: (result && result.message) || 'Please try again.', type: 'error' });
    }
  } catch(e) {
    showToast({ title: 'No connection', message: 'Your order could not be sent. Please check your internet connection and try again.', type: 'error' });
  } finally {
    submitting = false;
  }
}

function showConfirmation(name, orderId, queuePos, token, info) {
  try { localStorage.setItem('kapihan_last_order', JSON.stringify({ id: orderId, token: token || '', t: Date.now() })); } catch(e) {}
  document.getElementById('cartFormArea').classList.add('hidden');
  document.getElementById('confirmArea').classList.remove('hidden');

  document.getElementById('confirmName').textContent = name;
  document.getElementById('confirmOrderNo').textContent = '#' + orderId;
  document.getElementById('confirmQueue').textContent = queuePos;
  const prep = info && info.prep_time ? Number(info.prep_time) : (CONFIG.prepTime || 0);
  document.getElementById('confirmPrep').textContent = prep ? 'Estimated preparation: about ' + prep + ' minutes' : '';
  const payBox = document.getElementById('confirmPay');
  const pm = info && info.payment_method ? (CONFIG.payments || {})[info.payment_method] : null;
  if(pm && info.payment_method !== 'cash'){ payBox.innerHTML = payInfoHtml(pm, info.payment_method) + '<p class="mt-3 text-center text-xs font-semibold text-espresso/70">Payment reference: <span class="font-mono">#ORD-' + esc(orderId) + '</span><br><span class="font-normal text-espresso/55">Your payment stays pending until our staff verifies it.</span></p>'; payBox.classList.remove('hidden'); }
  else { payBox.innerHTML = ''; payBox.classList.add('hidden'); }

  const trackUrl = window.location.origin + window.location.pathname.replace(/[^\/]*$/, '') + 'track.php?order_id=' + orderId + '&t=' + encodeURIComponent(token || '');
  const linkEl = document.getElementById('trackLink');
  linkEl.href = trackUrl;
  linkEl.textContent = trackUrl;

  const qrHolder = document.getElementById('qrHolder');
  qrHolder.innerHTML = '';
  if(typeof QRCode !== 'undefined') {
    new QRCode(qrHolder, { text: trackUrl, width: 160, height: 160, colorDark: '#241812', colorLight: '#ffffff' });
  } else {
    qrHolder.innerHTML = '<p class="text-xs text-espresso/50 p-4">(QR unavailable — gamitin na lang ang link sa ibaba)</p>';
  }
}

function resetOrderPanel() {
  document.getElementById('confirmPay').classList.add('hidden');
  document.getElementById('confirmArea').classList.add('hidden');
  document.getElementById('cartFormArea').classList.remove('hidden');
  toggleCart(false);
}

const cartDrawer = document.getElementById('cartDrawer');
const cartPanel = document.getElementById('cartPanel');
function toggleCart(open) {
  cartDrawer.classList.toggle('pointer-events-none', !open);
  cartDrawer.classList.toggle('opacity-0', !open);
  cartPanel.classList.toggle('translate-x-full', !open);
}

document.getElementById('cartBtn').addEventListener('click', () => toggleCart(true));
document.getElementById('closeCart').addEventListener('click', () => toggleCart(false));
document.getElementById('cartBackdrop').addEventListener('click', () => toggleCart(false));

const heroCups = document.getElementById('hero-cups');
if(heroCups) {
  heroCups.addEventListener('mousemove', (e) => {
    const rect = heroCups.getBoundingClientRect();
    const x = (e.clientX - rect.left) / rect.width - 0.5;
    const y = (e.clientY - rect.top) / rect.height - 0.5;
    document.querySelectorAll('.hero-cup-wrap').forEach((cup, idx) => {
      const factor = (idx + 1) * 12;
      cup.style.transform = `translate(${x * factor}px, ${y * factor}px)`;
    });
  });
  heroCups.addEventListener('mouseleave', () => {
    document.querySelectorAll('.hero-cup-wrap').forEach((cup) => {
      cup.style.transform = 'translate(0px, 0px)';
    });
  });
}

const observer = new IntersectionObserver((entries) => {
  entries.forEach(entry => { if(entry.isIntersecting) entry.target.classList.add('is-visible'); });
}, { threshold: 0.15 });
document.querySelectorAll('[data-reveal]').forEach(el => observer.observe(el));

renderMenu();
renderCheckoutOptions();
gridEl.addEventListener('click', (e) => { if(e.target.closest('#menuRetry')) reloadMenu(true); });
function setQrModal(src){
  const m = $id('qrModal');
  if(src){ $id('qrBig').src = src; m.classList.remove('hidden'); m.classList.add('flex'); $id('qrClose').focus(); }
  else { m.classList.add('hidden'); m.classList.remove('flex'); $id('qrBig').removeAttribute('src'); }
}
document.addEventListener('click', (e) => {
  const open = e.target.closest('.qr-open'); if(open){ setQrModal(open.dataset.qr); return; }
  if(e.target.id === 'qrModal' || e.target.closest('#qrClose')) setQrModal('');
});
document.addEventListener('keydown', (e) => { if(e.key === 'Escape') setQrModal(''); });
async function reloadMenu(manual){
  const btn = $id('menuRetry'); if(btn){ btn.disabled = true; btn.classList.add('opacity-60'); }
  const r = await syncMenu();
  if(r.failed){
    MENU_ERROR = PRODUCTS.length === 0;
    renderMenu();
    if(manual) showToast({ title: 'Menu unavailable', message: 'Unable to load the menu. Please try again.', type: 'error' });
  }
}
if(MENU_ERROR){ [2500, 6000, 12000].forEach(ms => setTimeout(() => { if(MENU_ERROR) reloadMenu(false); }, ms)); }

/* ---------- KAPitan Bot chat ---------- */
const aiState = { busy: false, welcomeText: '', historyLoaded: false };
function aiBubble(role, text){
  const el = document.createElement('div');
  el.className = role === 'user'
    ? 'self-end max-w-[85%] bg-espresso text-cream rounded-2xl rounded-br-md px-3.5 py-2.5 text-sm whitespace-pre-wrap break-words'
    : 'self-start max-w-[85%] bg-cream border border-espresso/10 rounded-2xl rounded-bl-md px-3.5 py-2.5 text-sm whitespace-pre-wrap break-words';
  el.textContent = text;
  const box = $id('aiMessages'); box.appendChild(el); box.scrollTop = box.scrollHeight;
  return el;
}
function aiBubbleLink(text, label, href){
  const el = aiBubble('assistant', text);
  const a = document.createElement('a');
  a.href = href; a.target = '_blank'; a.rel = 'noopener';
  a.className = 'mt-2 inline-flex items-center gap-2 bg-espresso text-cream text-xs font-semibold rounded-full px-4 py-2 hover:bg-bark transition-colors';
  a.textContent = label;
  el.appendChild(document.createElement('br')); el.appendChild(a);
  return el;
}
function setAiOpen(open){
  const panel = $id('aiPanel'), btn = $id('aiToggle');
  panel.classList.toggle('open', open);
  panel.setAttribute('aria-hidden', open ? 'false' : 'true');
  btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  btn.setAttribute('aria-label', (open ? 'Close ' : 'Open ') + botName() + ' chat');
  if(open){ loadBotHistory(); setTimeout(() => $id('aiInput').focus({ preventScroll: true }), 220); }
}
const isAiOpen = () => $id('aiPanel').classList.contains('open');
const botName = () => (CONFIG.bot && CONFIG.bot.name) || 'KAPitan Bot';

// Apat na quick actions. Ang View Menu ay scroll lang; ang tatlo ay dumadaan sa KAPitan Bot (nase-save sa conversation).
const AI_QUICK = [
  { id: 'menu',      label: 'View Menu',           icon: 'fa-mug-hot' },
  { id: 'recommend', label: 'Recommend Something', icon: 'fa-wand-magic-sparkles', text: 'Recommend something for me' },
  { id: 'order',     label: 'Check My Order',      icon: 'fa-receipt',             text: 'Check my order' },
  { id: 'payment',   label: 'Payment Methods',     icon: 'fa-wallet',              text: 'Payment methods' }
];
function renderQuick(){
  const box = $id('aiMessages');
  let wrap = $id('aiQuick');
  if(!wrap){
    wrap = document.createElement('div');
    wrap.id = 'aiQuick';
    wrap.className = 'flex flex-col items-start gap-2 pt-1';
    AI_QUICK.forEach(a => {
      const b = document.createElement('button');
      b.type = 'button'; b.dataset.ai = a.id;
      b.className = 'text-sm font-semibold border border-espresso/20 bg-cream text-espresso rounded-full px-4 py-2 hover:bg-espresso hover:text-cream transition-colors';
      const i = document.createElement('i'); i.className = 'fa-solid ' + a.icon + ' mr-2 text-copper'; i.setAttribute('aria-hidden', 'true');
      b.appendChild(i); b.appendChild(document.createTextNode(a.label));
      wrap.appendChild(b);
    });
  }
  box.appendChild(wrap);
  box.scrollTop = box.scrollHeight;
}
function lastOrder(){
  try {
    const o = JSON.parse(localStorage.getItem('kapihan_last_order') || 'null');
    if(o && o.id && Date.now() - Number(o.t || 0) < 24 * 3600 * 1000) return o;
  } catch(e) {}
  return null;
}
function aiAction(id){
  if(id === 'menu'){
    setAiOpen(false);
    const m = $id('menu');
    if(m && m.scrollIntoView) m.scrollIntoView({ behavior: (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) ? 'auto' : 'smooth', block: 'start' });
    return;
  }
  const a = AI_QUICK.find(x => x.id === id);
  if(a && a.text) sendAi(a.text);
}

// Ang usapan ay nakatali sa browser session na ito — walang ibang customer ang makakakita nito
async function loadBotHistory(){
  if(aiState.historyLoaded) return;
  aiState.historyLoaded = true;
  try {
    const res = await fetch('kapitan_chat.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'history' }) });
    const data = await res.json();
    if(data && data.success && Array.isArray(data.messages) && data.messages.length){
      const quick = $id('aiQuick'); if(quick) quick.remove();
      data.messages.forEach(m => aiBubble(m.sender === 'customer' ? 'user' : 'assistant', m.message));
    }
  } catch(e) { /* hindi kritikal */ }
}

function applyAiConfig(){
  const bot = CONFIG.bot || {};
  const on = !!bot.enabled;
  $id('aiWidget').classList.toggle('hidden', !on);
  if(!on){ setAiOpen(false); return; }
  $id('botTitle').textContent = botName();
  $id('aiToggle').setAttribute('aria-label', 'Open ' + botName() + ' chat');
  $id('aiToggle').title = botName();
  const priv = $id('botPrivacy');
  priv.textContent = bot.privacy || '';
  priv.classList.toggle('hidden', !bot.privacy);
  const box = $id('aiMessages');
  if(!box.firstChild){ aiBubble('assistant', bot.welcome); aiState.welcomeText = bot.welcome; renderQuick(); }
  else if(!aiState.historyLoaded && aiState.welcomeText !== bot.welcome){ box.firstChild.textContent = bot.welcome; aiState.welcomeText = bot.welcome; }
}

async function sendAi(text){
  text = text.trim();
  if(!text || aiState.busy) return;
  aiState.busy = true; $id('aiSend').disabled = true;
  const quick = $id('aiQuick'); if(quick) quick.remove();
  aiBubble('user', text);
  const typing = aiBubble('assistant', '…');
  try {
    const o = lastOrder();
    const res = await fetch('kapitan_chat.php', { method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'send', message: text, order: o ? { id: o.id, token: o.token } : null }) });
    let data = null; try { data = await res.json(); } catch(e) {}
    if(data && data.success){
      typing.textContent = data.reply;
      if(data.link && data.link.url){
        const a = document.createElement('a');
        a.href = data.link.url; a.target = '_blank'; a.rel = 'noopener';
        a.className = 'mt-2 inline-flex items-center gap-2 bg-espresso text-cream text-xs font-semibold rounded-full px-4 py-2 hover:bg-bark transition-colors';
        a.textContent = data.link.label;
        typing.appendChild(document.createElement('br')); typing.appendChild(a);
      }
    } else {
      typing.textContent = (data && data.message) || "Sorry, I can't answer right now. Please try again in a moment.";
      if(res.status === 503) syncMenu();   // baka na-disable ng admin o nasa maintenance
    }
  } catch(e) {
    typing.textContent = "Sorry, I couldn't connect. Please check your internet and try again.";
  } finally {
    aiState.busy = false; $id('aiSend').disabled = false;
    const box = $id('aiMessages'); box.scrollTop = box.scrollHeight;
  }
}
$id('aiToggle').addEventListener('click', () => setAiOpen(!isAiOpen()));
$id('aiClose').addEventListener('click', () => setAiOpen(false));
$id('aiForm').addEventListener('submit', (e) => { e.preventDefault(); const i = $id('aiInput'); const v = i.value; i.value = ''; sendAi(v); });
$id('aiMessages').addEventListener('click', (e) => { const b = e.target.closest('[data-ai]'); if(b) aiAction(b.dataset.ai); });
document.addEventListener('keydown', (e) => { if(e.key === 'Escape' && isAiOpen()) { setAiOpen(false); $id('aiToggle').focus(); } });
applyAiConfig();


/* panatilihing sync ang cart sa menu: kapag binuksan ang cart, bumalik sa tab, at kada minuto */
document.getElementById('cartBtn').addEventListener('click', () => { syncMenu(); });
document.addEventListener('visibilitychange', () => { if(!document.hidden) syncMenu(); });
setInterval(() => { if(!document.hidden) syncMenu(); }, 60000);

/* navbar shrinks + gains a shadow once the page has scrolled a bit */
const headerEl = document.getElementById('top');
window.addEventListener('scroll', () => {
  headerEl.classList.toggle('scrolled', window.scrollY > 40);
}, { passive: true });

/* live activity ticker — real data mula sa orders table, hindi peke */
async function pollActivity(){
  const bar = document.getElementById('activityBar');
  const textEl = document.getElementById('activityText');
  try {
    const res = await fetch('activity.php', { cache: 'no-store' });
    const data = await res.json();
    if(!data.success){ bar.classList.add('hidden-bar'); return; }

    if(data.in_queue > 0){
      textEl.textContent = `${data.in_queue} pumipila ngayon` + (data.served_today > 0 ? ` • ${data.served_today} cups served today` : '');
    } else if(data.served_today > 0){
      textEl.textContent = `${data.served_today} cups served today`;
    } else {
      textEl.textContent = 'Bukas na kami — dumaan ka!';
    }
    bar.classList.remove('hidden-bar');
  } catch(e){
    bar.classList.add('hidden-bar');
  }
}
pollActivity();
setInterval(pollActivity, 20000);
</script>
</body>
</html>