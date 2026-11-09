<?php

require_once '../connection/db.php';
app_session_start();

$resetKey = (string)($GLOBALS['KAPIHAN_CFG']['admin_reset_key'] ?? '');
$enabled = strlen($resetKey) >= 12;
$error = '';
$done = false;

if ($enabled && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // limitasyon: 5 maling subok bawat 15 minuto bawat browser session
    $now = time();
    $fails = array_values(array_filter($_SESSION['reset_fails'] ?? [], fn($t) => $now - $t < 900));
    if (count($fails) >= 5) {
        $error = 'Too many failed attempts. Please wait 15 minutes and try again.';
    } else {
        $key = (string)($_POST['reset_key'] ?? '');
        $username = trim((string)($_POST['username'] ?? ''));
        $new = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE username = ?");
        $stmt->execute([$username]);
        $uid = $stmt->fetchColumn();

        if (!hash_equals($resetKey, $key) || !$uid) {
            usleep(700000);
            $fails[] = $now;
            $error = 'The reset key or username is incorrect.';
        } elseif (strlen($new) < 8) {
            $error = 'Use at least 8 characters for the new password.';
        } elseif ($new !== $confirm) {
            $error = 'The two passwords do not match.';
        } else {
            $pdo->prepare("UPDATE admin_users SET password_hash = ?, session_epoch = session_epoch + 1 WHERE id = ?")
                ->execute([password_hash($new, PASSWORD_BCRYPT), $uid]);
            $_SESSION['reset_fails'] = [];
            $done = true;
        }
    }
    $_SESSION['reset_fails'] = $done ? [] : $fails;
}
$fav = asset_url(setting('favicon'), '../');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reset password — <?= h(setting('store_name')) ?> Admin</title>
<?php if ($fav): ?><link rel="icon" href="<?= h($fav) ?>"><?php endif; ?>
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = { theme: { extend: {
    colors: { cream: '#F4EADA', espresso: '#241812', copper: '<?= h(accent()) ?>', coppdark: '<?= h(accent_dark()) ?>' },
    fontFamily: { display: ['"Bebas Neue"', 'sans-serif'], body: ['Karla', 'sans-serif'] } } } };
</script>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Karla:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-espresso min-h-screen grid place-items-center p-4 font-body">
  <div class="w-full max-w-sm bg-cream rounded-3xl p-8">
    <p class="font-display text-3xl tracking-wide"><?= h(strtoupper(setting('store_name'))) ?></p>
    <p class="text-sm text-espresso/60 mb-6">Reset admin password</p>

    <?php if (!$enabled): ?>
      <p class="text-sm text-espresso/80 leading-relaxed">Password reset is turned off. To use it, the store owner needs to set an <strong>admin reset key</strong> on the server:</p>
      <ol class="text-sm text-espresso/80 list-decimal pl-5 mt-3 space-y-1.5">
        <li>Open <code class="bg-white px-1 rounded">config.php</code> and add <code class="bg-white px-1 rounded">'admin_reset_key' =&gt; 'a-long-secret-phrase'</code> (at least 12 characters), or set the <code class="bg-white px-1 rounded">KAPIHAN_ADMIN_RESET_KEY</code> environment variable.</li>
        <li>Reload this page and enter that key below.</li>
        <li>Remove the key again after you have signed in.</li>
      </ol>
    <?php elseif ($done): ?>
      <p class="text-sm font-semibold text-emerald-700 mb-4">Your password was updated. All other sessions were signed out.</p>
      <p class="text-xs text-espresso/60 mb-5">For safety, remove the reset key from the server configuration now.</p>
      <a href="admin_login.php" class="block text-center w-full bg-espresso text-cream font-semibold py-3 rounded-full hover:bg-black transition-colors">Back to login</a>
    <?php else: ?>
      <form method="POST" autocomplete="off">
        <?= csrf_field() ?>
        <label class="block text-xs font-bold uppercase text-espresso/60 mb-1">Reset key</label>
        <input type="password" name="reset_key" required class="w-full bg-white border border-espresso/15 rounded-xl px-4 py-2.5 text-sm mb-1 focus:outline-none focus:border-copper">
        <p class="text-[11px] text-espresso/50 mb-4">The key set in your server configuration.</p>

        <label class="block text-xs font-bold uppercase text-espresso/60 mb-1">Username</label>
        <input type="text" name="username" required value="<?= h($_POST['username'] ?? 'admin') ?>" class="w-full bg-white border border-espresso/15 rounded-xl px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-copper">

        <label class="block text-xs font-bold uppercase text-espresso/60 mb-1">New password</label>
        <input type="password" name="new_password" required minlength="8" autocomplete="new-password" class="w-full bg-white border border-espresso/15 rounded-xl px-4 py-2.5 text-sm mb-1 focus:outline-none focus:border-copper">
        <p class="text-[11px] text-espresso/50 mb-4">At least 8 characters.</p>

        <label class="block text-xs font-bold uppercase text-espresso/60 mb-1">Confirm new password</label>
        <input type="password" name="confirm_password" required minlength="8" autocomplete="new-password" class="w-full bg-white border border-espresso/15 rounded-xl px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-copper">

        <?php if ($error): ?><p class="text-sm font-semibold text-rose-600 mb-4" role="alert"><?= h($error) ?></p><?php endif; ?>

        <button class="w-full bg-espresso text-cream font-semibold py-3 rounded-full hover:bg-black transition-colors">Reset password</button>
      </form>
    <?php endif; ?>

    <?php if (!$done): ?><p class="text-xs mt-5 text-center"><a href="admin_login.php" class="font-semibold text-espresso/60 hover:text-copper transition-colors">← Back to login</a></p><?php endif; ?>
  </div>
</body>
</html>
