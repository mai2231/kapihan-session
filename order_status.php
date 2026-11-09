<?php
header('Content-Type: application/json');
require_once 'connection/db.php';

$orderId = intval($_GET['order_id'] ?? 0);
$token = (string)($_GET['t'] ?? '');
if ($orderId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order id.']);
    exit;
}
if (!can_view_order($pdo, $orderId, $token)) {
    // parehong sagot sa "wala" at "bawal" para hindi mahulaan ang mga order number
    echo json_encode(['success' => false, 'message' => 'Order not found.']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT o.*,
               GROUP_CONCAT(CONCAT(oi.quantity, 'x ', oi.product_name) SEPARATOR ', ') AS items_summary
        FROM orders o
        LEFT JOIN order_items oi ON oi.order_id = o.id
        WHERE o.id = ?
        GROUP BY o.id
    ");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found.']);
        exit;
    }

    $queueStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE status IN ('pending','brewing') AND id <= ?");
    $queueStmt->execute([$orderId]);
    $queuePos = (int) $queueStmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'order_id' => (int) $order['id'],
        'customer_name' => $order['customer_name'],
        'status' => strtolower($order['status']),
        'payment_method' => $order['payment_method'] ?? 'cash',
        'total_amount' => (float) $order['total_amount'],
        'items_summary' => $order['items_summary'] ?? '',
        'queue_pos' => $queuePos,
        'created_at' => $order['created_at'],
        'order_type' => $order['order_type'] ?? 'pick_up',
        'payment_status' => $order['payment_status'] ?? 'pending',
        'notes' => $order['notes'] ?? '',
        'prep_time' => (int) setting('prep_time'),
        'notify' => ['status' => setting_on('notify_status'), 'sound' => setting_on('notify_sound')],
        'currency' => cur(),
    ]);
} catch (Exception $e) {
    error_log('order_status: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'May problema. Subukan ulit.']);
}
?>
