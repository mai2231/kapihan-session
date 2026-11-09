<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'connection/db.php';

// Sariling exception para hindi maipakita sa customer ang teknikal na error (PDOException ay RuntimeException din)
class OrderError extends Exception {
    public string $errCode;
    public array $ids;
    public function __construct(string $message, string $errCode = 'error', array $ids = []) {
        parent::__construct($message);
        $this->errCode = $errCode;
        $this->ids = $ids;
    }
}

function fail(string $msg, int $http = 200, array $extra = []) {
    http_response_code($http);
    echo json_encode(['success' => false, 'message' => $msg] + $extra);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Invalid request.', 405);

if (maintenance_active($pdo)) fail('The shop is under maintenance. Please check back soon.', 503, ['code' => 'maintenance']);
[$canOrder, $why] = ordering_state();
if (!$canOrder) {
    fail($why === 'closed' ? 'We are currently closed. Please order again when we open.' : 'Online ordering is temporarily unavailable.', 200, ['code' => 'ordering_off']);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) fail('Something went wrong. Please try again.');

$name = trim((string)($input['customer_name'] ?? ''));
$name = mb_substr($name, 0, 60);
if ($name === '') fail('Please enter your name before placing the order.', 200, ['code' => 'name']);

// simpleng proteksyon laban sa spam: isang order kada 3 segundo bawat browser
app_session_start();
if (isset($_SESSION['last_order_at']) && time() - $_SESSION['last_order_at'] < 3) {
    fail('Please wait a moment and try again.', 429);
}

$payment = strtolower(trim((string)($input['payment_method'] ?? '')));
if (!array_key_exists($payment, enabled_payments())) {
    fail('That payment method is not available. Please choose another.', 200, ['code' => 'payment_unavailable']);
}

$orderType = strtolower(trim((string)($input['order_type'] ?? '')));
if (!array_key_exists($orderType, enabled_order_types())) {
    fail('That order type is not available. Please choose another.', 200, ['code' => 'order_type_unavailable']);
}

$notes = '';
if (setting_on('allow_notes')) {
    $notes = trim((string)($input['notes'] ?? ''));
    $notes = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $notes) ?? '';
    $notes = mb_substr($notes, 0, 200);
}

// Isama ang magkakaparehong item at i-clamp ang quantity. ID at qty lang ang pinagkakatiwalaan sa client.
$wanted = [];
foreach ((array)($input['items'] ?? []) as $it) {
    $id  = (string)($it['id'] ?? '');
    $qty = (int)($it['qty'] ?? 0);
    if ($id === '' || $qty < 1) continue;
    $wanted[$id] = ($wanted[$id] ?? 0) + min($qty, 50);
}
if (!$wanted) fail('Your cart is empty. Add an item before placing an order.', 200, ['code' => 'empty']);
if (count($wanted) > 40) fail('There are too many different items in one order.');

try {
    $pdo->beginTransaction();

    $sel    = $pdo->prepare("SELECT id, name, price, stock_qty, is_active FROM products WHERE id = ? FOR UPDATE");
    $deduct = $pdo->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE id = ?");

    // 1) Kunin at i-lock ang lahat ng item; ipunin ang mga wala na sa menu o kulang ang stock
    $found = [];
    $missing = [];
    $short = [];
    $shortNames = [];
    foreach ($wanted as $id => $qty) {
        $sel->execute([(string)$id]);
        $prod = $sel->fetch();
        if (!$prod || !(int)$prod['is_active']) { $missing[] = (string)$id; continue; }   // wala na, o hindi na aktibo
        if ($prod['stock_qty'] !== null && (int)$prod['stock_qty'] < $qty) { $short[] = (string)$id; $shortNames[] = $prod['name'] . ' (' . max(0, (int)$prod['stock_qty']) . ' left)'; }
        $found[(string)$id] = [$prod, $qty];
    }
    if ($missing) {
        throw new OrderError('One or more items in your cart are no longer available. Please review your cart.', 'unavailable', $missing);
    }
    if ($short) {
        throw new OrderError('Not enough stock for: ' . implode(', ', $shortNames) . '. Please adjust the quantity.', 'stock', $short);
    }

    // 2) Kuwentahin ang total at ibawas ang stock — presyo mula sa database lang
    $lines = [];
    $total = 0.0;
    foreach ($found as [$prod, $qty]) {
        if ($prod['stock_qty'] !== null) $deduct->execute([$qty, $prod['id']]);
        $price = (float)$prod['price'];
        $total += $price * $qty;
        $lines[] = [$prod['name'], $price, $qty];
    }

    $minOrder = (float)setting('min_order_amount');
    if ($minOrder > 0 && $total + 0.00001 < $minOrder) {
        throw new OrderError('The minimum order is ' . peso($minOrder) . '. Add a little more to your cart.', 'min_order');
    }

    $token = bin2hex(random_bytes(8));
    $pdo->prepare("INSERT INTO orders (customer_name, total_amount, payment_method, order_type, notes, public_token) VALUES (?, ?, ?, ?, ?, ?)")
        ->execute([$name, round($total, 2), $payment, $orderType, $notes !== '' ? $notes : null, $token]);
    $orderId = (int)$pdo->lastInsertId();

    $itemStmt = $pdo->prepare("INSERT INTO order_items (order_id, product_name, price, quantity) VALUES (?, ?, ?, ?)");
    foreach ($lines as [$pname, $price, $qty]) {
        $itemStmt->execute([$orderId, $pname, $price, $qty]);
    }

    $pdo->commit();
    $_SESSION['last_order_at'] = time();

    $q = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE status IN ('pending','brewing') AND id <= ?");
    $q->execute([$orderId]);

    echo json_encode([
        'success'        => true,
        'order_id'       => $orderId,
        'token'          => $token,
        'queue_pos'      => (int)$q->fetchColumn(),
        'total'          => round($total, 2),
        'payment_method' => $payment,
        'order_type'     => $orderType,
        'prep_time'      => (int)setting('prep_time'),
    ]);
} catch (OrderError $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fail($e->getMessage(), 200, ['code' => $e->errCode, 'items' => $e->ids]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('save_order: ' . $e->getMessage());
    fail('Something went wrong. Please try again.', 500);
}
