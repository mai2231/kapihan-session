<?php
require_once '../connection/db.php';
app_session_start();

// kung naka-login na, diretso sa dashboard
if (admin_session_valid($pdo)) {
    header('Location: admin.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    
    $_SESSION['login_attempts'] = $_SESSION['login_attempts'] ?? 0;
    $_SESSION['login_window_start'] = $_SESSION['login_window_start'] ?? time();
    if (time() - $_SESSION['login_window_start'] > 600) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_window_start'] = time();
    }

    if ($_SESSION['login_attempts'] >= 8) {
        $error = 'Too many failed attempts. Please wait a few minutes and try again.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['admin_epoch'] = (int)$user['session_epoch'];
            $_SESSION['login_at'] = time();
            // itala ang nakaraang login para sa Security panel
            $pdo->prepare("UPDATE admin_users SET prev_login_at = last_login_at, last_login_at = NOW() WHERE id = ?")
                ->execute([$user['id']]);
            unset($_SESSION['login_attempts'], $_SESSION['login_window_start']);
            header('Location: admin.php');
            exit();
        } else {
            $_SESSION['login_attempts']++;
            $error = 'Incorrect username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h(setting('store_name')) ?> Admin — Login</title>
<?php $__fav = asset_url(setting('favicon'), '../'); if ($__fav): ?><link rel="icon" href="<?= h($__fav) ?>"><?php endif; ?>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Karla:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: { extend: {
      colors: { cream: '#F4EADA', espresso: '#241812', copper: '<?= h(accent()) ?>', coppdark: '<?= h(accent_dark()) ?>' },
      fontFamily: { display: ['"Bebas Neue"', 'sans-serif'], body: ['Karla', 'sans-serif'] }
    }}
  }
</script>
</head>
<body class="bg-espresso font-body min-h-screen flex items-center justify-center p-5">
  <form method="POST" class="w-full max-w-sm bg-cream rounded-3xl p-8">
    <p class="font-display text-3xl tracking-wide"><?= h(strtoupper(setting('store_name'))) ?></p>
    <p class="text-sm text-espresso/60 mb-6">Admin Panel</p>

    <label class="block text-xs font-bold uppercase text-espresso/60 mb-1">Username</label>
    <input type="text" name="username" autofocus required autocomplete="username"
           class="w-full bg-white border border-espresso/15 rounded-xl px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-copper">

    <label class="block text-xs font-bold uppercase text-espresso/60 mb-1">Password</label>
    <input type="password" name="password" required autocomplete="current-password"
           class="w-full bg-white border border-espresso/15 rounded-xl px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-copper">

    <?php if ($error): ?>
      <p class="text-sm font-semibold text-rose-600 mb-4"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <button class="w-full bg-espresso text-cream font-semibold py-3 rounded-full hover:bg-black transition-colors">
      Login
    </button>

    <p class="text-xs mt-5 text-center">
      <a href="reset_password.php" class="font-semibold text-espresso/60 hover:text-copper transition-colors">Forgot your password?</a>
    </p>
  </form>
</body>
</html>
