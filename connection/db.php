<?php

const KAPIHAN_SCHEMA_VERSION = 7;
const KAPIHAN_VERSION = '1.4.0';

$cfg = [
    'db_host' => 'localhost',
    'db_port' => 3306,
    'db_name' => 'kapihan',
    'db_user' => 'root',
    'db_pass' => '',
    'auto_create_db' => true,   // default para sa XAMPP; patayin sa live hosting
    'admin_initial_password' => 'admin123',
    'timezone' => 'Asia/Manila',
    'admin_idle_minutes' => 480, // awtomatikong mag-logout ang admin kapag walang aktibidad nang ganito katagal
    'admin_reset_key' => '',     // para sa Forgot Password (admin/reset_password.php). Iwanang blangko = naka-off.
    'debug' => false,
];
$cfgFile = __DIR__ . '/../config.php';
if (is_file($cfgFile)) {
    $loaded = require $cfgFile;
    if (is_array($loaded)) {
        foreach ($loaded as $k => $v) { if (is_string($v)) $loaded[$k] = trim($v); }
        $cfg = array_merge($cfg, $loaded);
    }
}
$envMap = [
    'KAPIHAN_DB_HOST' => 'db_host', 'KAPIHAN_DB_PORT' => 'db_port', 'KAPIHAN_DB_NAME' => 'db_name',
    'KAPIHAN_DB_USER' => 'db_user', 'KAPIHAN_DB_PASS' => 'db_pass',
    'KAPIHAN_ADMIN_PASSWORD' => 'admin_initial_password',
    'KAPIHAN_TIMEZONE' => 'timezone', 'KAPIHAN_ADMIN_RESET_KEY' => 'admin_reset_key',
];
foreach ($envMap as $env => $key) {
    $v = getenv($env);
    if ($v !== false && trim($v) !== '') $cfg[$key] = trim($v);   // trim: iwas stray CR/espasyo mula sa .env (Windows)
}
$GLOBALS['KAPIHAN_CFG'] = $cfg;

// Huwag ipakita sa customer ang raw PHP errors, SQL o file paths — sa log lang (maliban kung debug)
if (empty($cfg['debug'])) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

// Oras: Pilipinas bilang default (ang Docker/hosting ay madalas naka-UTC kaya mali ang oras ng orders)
if (!in_array($cfg['timezone'], timezone_identifiers_list(), true)) $cfg['timezone'] = 'Asia/Manila';
date_default_timezone_set($cfg['timezone']);

// ---------- helpers na magagamit ng lahat ng page ----------

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function app_session_start(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'secure' => $https, 'samesite' => 'Lax']);
    ini_set('session.use_strict_mode', '1');
    session_start();
}

function csrf_token(): string {
    app_session_start();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}
function csrf_check(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        app_session_start();
        if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
            http_response_code(403);
            if (wants_json()) {
                header('Content-Type: application/json; charset=utf-8');
                exit(json_encode(['success' => false, 'message' => 'Nag-expire ang session ng page. I-refresh at subukan ulit.']));
            }
            exit('Expired o invalid ang form token. Bumalik sa nakaraang page, i-refresh, at subukan ulit.');
        }
    }
}

// Puwedeng tingnan ang order kung admin, o kung tama ang token na nasa link/QR ng customer.
function can_view_order(PDO $pdo, int $orderId, string $token): bool {
    app_session_start();
    if (!empty($_SESSION['admin_id'])) return true;
    if ($token === '') return false;
    $s = $pdo->prepare("SELECT public_token FROM orders WHERE id = ?");
    $s->execute([$orderId]);
    $real = $s->fetchColumn();
    return is_string($real) && $real !== '' && hash_equals($real, $token);
}

// ---------- connection ----------

$dsnBase = "mysql:host={$cfg['db_host']};port={$cfg['db_port']};charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
$db = $cfg['db_name'];

try {
    try {
        $pdo = new PDO($dsnBase . ";dbname=$db", $cfg['db_user'], $cfg['db_pass'], $options);
    } catch (PDOException $e) {
        // 1049 = unknown database. Gagawa lang kung pinayagan sa config at may karapatan ang user.
        if ((int)$e->getCode() === 1049 && !empty($cfg['auto_create_db'])) {
            $tmp = new PDO($dsnBase, $cfg['db_user'], $cfg['db_pass'], $options);
            $tmp->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo = new PDO($dsnBase . ";dbname=$db", $cfg['db_user'], $cfg['db_pass'], $options);
        } else {
            throw $e;
        }
    }
    try {
        $pdo->exec("SET time_zone = '" . (new DateTime('now', new DateTimeZone($cfg['timezone'])))->format('P') . "'");
    } catch (Throwable $e) { /* hindi kritikal */ }
} catch (PDOException $e) {
    error_log('Kapihan DB connect: ' . $e->getMessage());
    http_response_code(503);
    exit(!empty($cfg['debug'])
        ? 'DB error: ' . h($e->getMessage())
        : 'We are having trouble connecting right now. Please try again in a moment.');
}

// ---------- schema + auto-migration (tatakbo lang kapag luma ang version) ----------

function colInfo(PDO $pdo, string $table, string $column) {
    $s = $pdo->prepare("SELECT DATA_TYPE, COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $s->execute([$table, $column]);
    return $s->fetch() ?: null;
}
function ensureColumn(PDO $pdo, string $table, string $column, string $ddl) {
    if (!colInfo($pdo, $table, $column)) $pdo->exec("ALTER TABLE `$table` ADD COLUMN $ddl");
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS schema_info (version INT NOT NULL) ENGINE=InnoDB");
    $curVer = $pdo->query("SELECT MAX(version) FROM schema_info")->fetchColumn();
    $curVer = $curVer === null ? 0 : (int)$curVer;

    if ($curVer < KAPIHAN_SCHEMA_VERSION) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS products (
            id VARCHAR(50) PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            category VARCHAR(50) NOT NULL DEFAULT 'coffee',
            price DECIMAL(10,2) NOT NULL,
            image VARCHAR(500),
            description TEXT,
            stock_qty INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_name VARCHAR(255) NOT NULL DEFAULT '',
            total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            payment_method VARCHAR(30) NOT NULL DEFAULT 'cash',
            public_token VARCHAR(32) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS order_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            product_name VARCHAR(255) NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            quantity INT NOT NULL,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // --- migration para sa lumang database ---
        ensureColumn($pdo, 'products', 'created_at', "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        ensureColumn($pdo, 'products', 'stock_qty',  "stock_qty INT DEFAULT NULL");
        ensureColumn($pdo, 'orders', 'customer_name',  "customer_name VARCHAR(255) NOT NULL DEFAULT ''");
        ensureColumn($pdo, 'orders', 'total_amount',   "total_amount DECIMAL(10,2) NOT NULL DEFAULT 0");
        ensureColumn($pdo, 'orders', 'status',         "status VARCHAR(20) NOT NULL DEFAULT 'pending'");
        ensureColumn($pdo, 'orders', 'payment_method', "payment_method VARCHAR(30) NOT NULL DEFAULT 'cash'");
        ensureColumn($pdo, 'orders', 'created_at',     "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        ensureColumn($pdo, 'orders', 'public_token',   "public_token VARCHAR(32) DEFAULT NULL");

        $st = colInfo($pdo, 'orders', 'status');
        if ($st && strtolower($st['DATA_TYPE']) === 'enum') {
            $pdo->exec("ALTER TABLE orders MODIFY status VARCHAR(20) NOT NULL DEFAULT 'pending'");
        }
        $uid = colInfo($pdo, 'orders', 'user_id');
        if ($uid && strtoupper($uid['IS_NULLABLE']) === 'NO') {
            $pdo->exec("ALTER TABLE orders MODIFY user_id {$uid['COLUMN_TYPE']} NULL DEFAULT NULL");
        }
        ensureColumn($pdo, 'order_items', 'product_name', "product_name VARCHAR(255) NOT NULL DEFAULT ''");
        ensureColumn($pdo, 'order_items', 'price',        "price DECIMAL(10,2) NOT NULL DEFAULT 0");
        ensureColumn($pdo, 'order_items', 'quantity',     "quantity INT NOT NULL DEFAULT 1");

        // --- v4: settings, order type/notes, session tools ---
        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
            setting_key VARCHAR(64) NOT NULL PRIMARY KEY,
            setting_value TEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        ensureColumn($pdo, 'orders', 'order_type', "order_type VARCHAR(20) NOT NULL DEFAULT 'pick_up'");
        ensureColumn($pdo, 'orders', 'notes', "notes VARCHAR(300) DEFAULT NULL");
        ensureColumn($pdo, 'admin_users', 'last_login_at', "last_login_at DATETIME DEFAULT NULL");
        ensureColumn($pdo, 'admin_users', 'prev_login_at', "prev_login_at DATETIME DEFAULT NULL");
        ensureColumn($pdo, 'admin_users', 'session_epoch', "session_epoch INT NOT NULL DEFAULT 0");

        // --- v5: KAPitan Bot (sariling chatbot ng KAPIHAN) ---
        $pdo->exec("CREATE TABLE IF NOT EXISTS kapitan_conversations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(64) NOT NULL,
            customer_name VARCHAR(100) DEFAULT NULL,
            status VARCHAR(12) NOT NULL DEFAULT 'open',
            context TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_session (session_id),
            INDEX idx_updated (updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS kapitan_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            conversation_id INT NOT NULL,
            sender VARCHAR(10) NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_conversation (conversation_id, id),
            FOREIGN KEY (conversation_id) REFERENCES kapitan_conversations(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS kapitan_faqs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            question VARCHAR(200) NOT NULL,
            keywords VARCHAR(200) DEFAULT NULL,
            answer TEXT NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // alisin ang mga lumang external-chatbot settings
        $pdo->exec("DELETE FROM settings WHERE setting_key LIKE 'ai\\_%'");

        // --- v6: product visibility, PHP-only currency ---
        ensureColumn($pdo, 'products', 'is_active', "is_active TINYINT(1) NOT NULL DEFAULT 1");
        $pdo->exec("DELETE FROM settings WHERE setting_key = 'currency'");

        // --- v7: payment status (pending hanggang ma-verify ng admin) ---
        $hadPaymentStatus = colInfo($pdo, 'orders', 'payment_status');
        ensureColumn($pdo, 'orders', 'payment_status', "payment_status VARCHAR(12) NOT NULL DEFAULT 'pending'");
        ensureColumn($pdo, 'orders', 'payment_verified_at', "payment_verified_at DATETIME DEFAULT NULL");
        if (!$hadPaymentStatus) {
            // Para sa mga lumang order lang: ang natapos na cash order ay nabayaran na sa counter. Ang GCash/Maya ay mananatiling pending.
            $pdo->exec("UPDATE orders SET payment_status = 'paid', payment_verified_at = NOW() WHERE status = 'completed' AND payment_method = 'cash'");
        }

        // Default admin (unang beses lang)
        if ((int)$pdo->query("SELECT COUNT(*) FROM admin_users")->fetchColumn() === 0) {
            $pdo->prepare("INSERT INTO admin_users (username, password_hash) VALUES (?, ?)")
                ->execute(['admin', password_hash((string)$cfg['admin_initial_password'], PASSWORD_BCRYPT)]);
        }

        $pdo->exec("DELETE FROM schema_info");
        $pdo->prepare("INSERT INTO schema_info (version) VALUES (?)")->execute([KAPIHAN_SCHEMA_VERSION]);
    }
} catch (PDOException $e) {
    error_log('Kapihan migration: ' . $e->getMessage());
    if (!empty($cfg['debug'])) { http_response_code(500); exit('Migration error: ' . h($e->getMessage())); }
}


// ============================================================
//  SETTINGS — key/value sa `settings` table, may default values
// ============================================================

function setting_defaults(): array {
    return [
        // Store
        'store_name' => 'KAPIHAN', 'tagline' => 'Kape. Kwento. Koneksyon.', 'address' => 'Manila, Philippines',
        'store_description' => '', 'contact_number' => '', 'email' => '', 'opening_time' => '07:00', 'closing_time' => '20:00',
        'store_status' => 'open', 'store_logo' => '',
        // Ordering
        'online_ordering' => '1', 'dine_in' => '1', 'take_out' => '1', 'pick_up' => '1',
        'min_order_amount' => '0', 'prep_time' => '15', 'allow_notes' => '1', 'order_rules' => '',
        // Payments
        'pay_cash' => '1', 'pay_gcash' => '1', 'pay_maya' => '1',
        'gcash_name' => '', 'gcash_number' => '', 'gcash_qr' => '',
        'maya_name' => '', 'maya_number' => '', 'maya_qr' => '', 'pay_instructions' => '',
        // Notifications
        'notify_new_order' => '1', 'notify_status' => '1', 'notify_sound' => '1',
        'notify_low_stock' => '1', 'low_stock_threshold' => '5',
        // KAPitan Bot
        'bot_enabled' => '1', 'bot_name' => 'KAPitan Bot',
        'bot_welcome' => "Hi! I'm KAPitan Bot ☕\nWelcome to KAPIHAN! How can I help you today?",
        'bot_tone' => 'friendly', 'bot_language' => 'taglish', 'bot_knowledge' => '',
        'bot_privacy_enabled' => '1',
        'bot_privacy_text' => 'Messages with KAPitan Bot may be stored by KAPIHAN to improve customer support and service.',
        'bot_retention_days' => '90',
        // Appearance
        'favicon' => '', 'accent_color' => '#B25F2C',
        // System
        'maintenance_mode' => '0',
    ];
}

function settings_all(bool $refresh = false): array {
    static $cache = null;
    if ($cache === null || $refresh) {
        global $pdo;
        $cache = setting_defaults();
        try {
            foreach ($pdo->query("SELECT setting_key, setting_value FROM settings") as $r) {
                if (array_key_exists($r['setting_key'], $cache)) $cache[$r['setting_key']] = (string)$r['setting_value'];
            }
        } catch (Throwable $e) {
            error_log('settings load: ' . $e->getMessage());
        }
    }
    return $cache;
}
function setting(string $key): string { return settings_all()[$key] ?? ''; }
function setting_on(string $key): bool { return setting($key) === '1'; }

function settings_save(PDO $pdo, array $values): void {
    $allowed = setting_defaults();
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                           ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $pdo->beginTransaction();
    try {
        foreach ($values as $k => $v) {
            if (!array_key_exists($k, $allowed)) continue;   // walang kilalang key = hindi sine-save
            $stmt->execute([$k, (string)$v]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    settings_all(true);
}

// Philippines-only: laging Philippine Peso (₱). Walang pagpipilian ng ibang currency.
const KAPIHAN_CURRENCY = '₱';
function cur(): string { return KAPIHAN_CURRENCY; }
function peso(float $n): string { return KAPIHAN_CURRENCY . number_format($n, 2); }

function accent(): string {
    $c = setting('accent_color');
    return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? strtoupper($c) : '#B25F2C';
}
function color_luminance(string $hex): float {
    $rgb = [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
    foreach ($rgb as &$v) { $v /= 255; $v = $v <= 0.03928 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4); }
    return 0.2126 * $rgb[0] + 0.7152 * $rgb[1] + 0.0722 * $rgb[2];
}
function accent_dark(): string {
    $c = accent();
    $r = (int)round(hexdec(substr($c, 1, 2)) * 0.8);
    $g = (int)round(hexdec(substr($c, 3, 2)) * 0.8);
    $b = (int)round(hexdec(substr($c, 5, 2)) * 0.8);
    return sprintf('#%02X%02X%02X', $r, $g, $b);
}

// Path ng na-upload na file para sa <img>/<link>. $prefix = '' (root pages) o '../' (admin pages)
function asset_url(string $rel, string $prefix = ''): string {
    if ($rel === '' || strpos($rel, 'uploads/settings/') !== 0 || basename($rel) === '' || !preg_match('#^uploads/settings/[A-Za-z0-9._-]+$#', $rel)) return '';
    return is_file(dirname(__DIR__) . '/' . $rel) ? $prefix . $rel : '';
}

// Larawan ng produkto: http(s) URL, o lokal na upload na umiiral. Kapag wala/sira: '' (placeholder ang ipapakita ng page).
// $prefix = '' para sa pages sa root, '../' para sa admin pages.
function product_image_url(?string $path, string $prefix = ''): string {
    $path = trim((string)$path);
    if ($path === '') return '';
    if (preg_match('#^https?://#i', $path)) return $path;
    if (preg_match('#^uploads/products/[A-Za-z0-9._-]+$#', $path) && is_file(dirname(__DIR__) . '/' . $path)) return $prefix . $path;
    return '';
}
// Tanggap lang ang http(s) URL galing sa form (hindi tatanggap ng kung anong path mula sa browser)
function product_clean_url($v): ?string {
    $v = trim((string)$v);
    if ($v === '' || strlen($v) > 500 || !preg_match('#^https?://#i', $v)) return null;
    return filter_var($v, FILTER_VALIDATE_URL) ? $v : null;
}
// Ang kasalukuyang placeholder (kayumangging bilog)
function product_placeholder(): string {
    return "data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22%3E%3Ccircle cx=%2250%22 cy=%2250%22 r=%2250%22 fill=%22%237a4222%22/%3E%3C/svg%3E";
}

function store_open_label(): string {
    $f = function ($t) { $d = DateTime::createFromFormat('H:i', $t); return $d ? $d->format('g:i A') : $t; };
    return $f(setting('opening_time')) . ' – ' . $f(setting('closing_time'));
}

function enabled_order_types(): array {
    $all = ['dine_in' => 'Dine-In', 'take_out' => 'Take-Out', 'pick_up' => 'Pick-Up'];
    return array_filter($all, fn($label, $key) => setting_on($key), ARRAY_FILTER_USE_BOTH);
}
function enabled_payments(): array {
    $all = ['cash' => 'Cash', 'gcash' => 'GCash', 'maya' => 'Maya'];
    return array_filter($all, fn($label, $key) => setting_on('pay_' . $key), ARRAY_FILTER_USE_BOTH);
}

// ---------- admin session (may "logout other sessions" sa pamamagitan ng epoch) ----------

function admin_session_valid(PDO $pdo, bool $touch = true): bool {
    app_session_start();
    if (empty($_SESSION['admin_id'])) return false;
    // Idle timeout. Ang background polling (kitchen_data.php) ay $touch = false para hindi nito mapahaba ang session.
    $idle = max(5, (int)($GLOBALS['KAPIHAN_CFG']['admin_idle_minutes'] ?? 480)) * 60;
    if (isset($_SESSION['last_seen']) && time() - (int)$_SESSION['last_seen'] > $idle) {
        unset($_SESSION['admin_id'], $_SESSION['admin_username'], $_SESSION['admin_epoch'], $_SESSION['last_seen']);
        return false;
    }
    $s = $pdo->prepare("SELECT session_epoch FROM admin_users WHERE id = ?");
    $s->execute([$_SESSION['admin_id']]);
    $row = $s->fetch();
    $ok = $row && (int)$row['session_epoch'] === (int)($_SESSION['admin_epoch'] ?? 0);
    if ($ok && ($touch || !isset($_SESSION['last_seen']))) $_SESSION['last_seen'] = time();
    return (bool)$ok;
}

// Maintenance: sarado ang storefront para sa publiko, bukas pa rin para sa naka-login na admin
function maintenance_active(PDO $pdo): bool {
    return setting_on('maintenance_mode') && !admin_session_valid($pdo);
}

// Puwede bang tumanggap ng order ngayon? [true, ''] o [false, 'closed'|'online_off']
function ordering_state(): array {
    if (setting('store_status') !== 'open') return [false, 'closed'];
    if (!setting_on('online_ordering')) return [false, 'online_off'];
    return [true, ''];
}

// ---------- ligtas na image upload (logo, favicon, QR) ----------

function save_uploaded_image(string $field, string $subdir = 'settings', int $maxBytes = 2097152): ?string {
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return null;
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('The image could not be uploaded. Please try again.');
    if ($file['size'] > $maxBytes) throw new RuntimeException('That image is too large (maximum ' . round($maxBytes / 1048576) . 'MB).');

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = function_exists('finfo_open') ? finfo_file(finfo_open(FILEINFO_MIME_TYPE), $file['tmp_name']) : mime_content_type($file['tmp_name']);
    if (!isset($allowed[$mime]) || @getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException('Only JPG, JPEG, PNG or WEBP images are allowed.');
    }
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        throw new RuntimeException('Only JPG, JPEG, PNG or WEBP images are allowed.');
    }

    $dir = dirname(__DIR__) . '/uploads/' . $subdir;
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) throw new RuntimeException('The upload folder is not available.');
    $deny = $dir . '/.htaccess';
    if (!is_file($deny)) @file_put_contents($deny, "<FilesMatch \"\\.(php|phtml|php[0-9])$\">\n  Require all denied\n</FilesMatch>\n");

    $name = bin2hex(random_bytes(10)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) throw new RuntimeException('The image could not be saved. Please try again.');
    return 'uploads/' . $subdir . '/' . $name;
}

function delete_uploaded_image(string $rel): void {
    // Lokal na upload lang ng KAPIHAN ang buburahin (hindi kailanman ang external URL o ibang path)
    if (!preg_match('#^uploads/(settings|products)/[A-Za-z0-9._-]+$#', $rel)) return;
    $path = dirname(__DIR__) . '/' . $rel;
    if (is_file($path)) @unlink($path);
}

// csrf_check na sumasagot ng JSON para sa fetch/AJAX requests
function wants_json(): bool {
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'kapihan'
        || stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
}

// Settings na puwedeng malaman ng customer (storefront + menu.php). Walang sikreto dito.
function public_config(): array {
    global $pdo;
    [$open, $why] = ordering_state();
    $pay = [];
    foreach (enabled_payments() as $k => $label) {
        $pay[$k] = [
            'label'  => $label,
            'name'   => $k === 'cash' ? '' : setting($k . '_name'),
            'number' => $k === 'cash' ? '' : setting($k . '_number'),
            'qr'     => $k === 'cash' ? '' : asset_url(setting($k . '_qr')),
        ];
    }
    return [
        'currency'      => cur(),
        'orderingOpen'  => $open,
        'orderingWhy'   => $why,
        'orderTypes'    => (object)enabled_order_types(),
        'payments'      => (object)$pay,
        'minOrder'      => (float)setting('min_order_amount'),
        'allowNotes'    => setting_on('allow_notes'),
        'prepTime'      => (int)setting('prep_time'),
        'orderRules'    => setting('order_rules'),
        'payInstructions' => setting('pay_instructions'),
        'bot'           => [
            'enabled' => setting_on('bot_enabled'),
            'name'    => setting('bot_name') !== '' ? setting('bot_name') : 'KAPitan Bot',
            'welcome' => setting('bot_welcome'),
            'privacy' => setting_on('bot_privacy_enabled') ? setting('bot_privacy_text') : '',
        ],
        'maintenance'   => maintenance_active($pdo),
    ];
}




function bot_tones(): array { return ['friendly' => 'Friendly', 'helpful' => 'Helpful', 'professional' => 'Professional']; }
function bot_languages(): array { return ['english' => 'English', 'tagalog' => 'Tagalog', 'taglish' => 'Taglish']; }

// Buburahin ang luma at idle na usapan ayon sa retention setting. Tatawagin paminsan-minsan.
function kapitan_purge(PDO $pdo): void {
    try {
        $pdo->exec("UPDATE kapitan_conversations SET status = 'closed' WHERE status = 'open' AND updated_at < (NOW() - INTERVAL 6 HOUR)");
        $days = (int)setting('bot_retention_days');
        if ($days > 0) {
            $pdo->prepare("DELETE FROM kapitan_conversations WHERE updated_at < (NOW() - INTERVAL ? DAY)")->execute([$days]);
        }
    } catch (Throwable $e) {
        error_log('kapitan_purge: ' . $e->getMessage());
    }
}


function products_missing_images(PDO $pdo): array {
    $missing = [];
    try {
        foreach ($pdo->query("SELECT name, image FROM products WHERE image LIKE 'uploads/products/%'") as $r) {
            if (product_image_url($r['image'], '') === '') $missing[] = (string)$r['name'];
        }
    } catch (Throwable $e) {
        error_log('products_missing_images: ' . $e->getMessage());
    }
    return $missing;
}
