<?php
header('Content-Type: application/json');
require_once '../connection/db.php';
app_session_start();

if (!admin_session_valid($pdo, false)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Kailangan mag-login.']);
    exit();
}

try {
    $stmt = $pdo->query("
        SELECT o.id, o.customer_name, o.status, o.created_at, o.order_type, o.notes,
               GROUP_CONCAT(CONCAT(oi.quantity, 'x|', oi.product_name) SEPARATOR '||') AS items_raw
        FROM orders o
        LEFT JOIN order_items oi ON oi.order_id = o.id
        WHERE LOWER(o.status) IN ('pending','brewing','completed')
        GROUP BY o.id
        ORDER BY o.created_at ASC
    ");
    $rows = $stmt->fetchAll();

    $board = ['pending' => [], 'brewing' => [], 'completed' => []];

    foreach ($rows as $r) {
        $items = [];
        if (!empty($r['items_raw'])) {
            foreach (explode('||', $r['items_raw']) as $part) {
                [$qty, $name] = array_pad(explode('x|', $part, 2), 2, '');
                $items[] = ['qty' => (int) $qty, 'name' => $name];
            }
        }
        $status = strtolower($r['status']);
        if (!isset($board[$status])) continue;

        $board[$status][] = [
            'id' => (int) $r['id'],
            'customer_name' => $r['customer_name'],
            'created_at' => $r['created_at'],
            'order_type' => $r['order_type'] ?? 'pick_up',
            'notes' => $r['notes'] ?? '',
            'items' => $items,
        ];
    }

    // pinaka-bago munang ipakita sa "Ready" column; pinakamatagal na hinihintay muna sa New/Preparing
    $board['completed'] = array_reverse($board['completed']);

    $latest = (int)$pdo->query("SELECT COALESCE(MAX(id), 0) FROM orders")->fetchColumn();
    echo json_encode([
        'success' => true,
        'board' => $board,
        'latest_id' => $latest,
        'pending_count' => count($board['pending']),
        'notify' => ['new_order' => setting_on('notify_new_order'), 'sound' => setting_on('notify_sound')],
    ]);
} catch (Exception $e) {
    error_log('kitchen_data: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'May problema sa pag-load ng orders.']);
}
?>
