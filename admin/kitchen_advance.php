<?php
header('Content-Type: application/json');
require_once '../connection/db.php';
app_session_start();

if (!admin_session_valid($pdo)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Kailangan mag-login.']);
    exit();
}

// CSRF: ang custom header ay hindi mapapadala ng ibang website nang hindi dumadaan sa CORS preflight
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'kapihan' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$orderId = intval($input['order_id'] ?? 0);

$next = ['pending' => 'brewing', 'brewing' => 'completed'];

if ($orderId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order id.']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Hindi nahanap ang order.']);
        exit();
    }

    $current = strtolower($order['status']);
    if (!isset($next[$current])) {
        echo json_encode(['success' => false, 'message' => 'Wala nang susunod na stage ang order na ito.']);
        exit();
    }

    $newStatus = $next[$current];
    $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$newStatus, $orderId]);

    echo json_encode(['success' => true, 'new_status' => $newStatus]);
} catch (Exception $e) {
    error_log('kitchen_advance: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'May problema. Subukan ulit.']);
}
?>
