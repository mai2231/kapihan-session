<?php
header('Content-Type: application/json');
require_once 'connection/db.php';

try {
    $inQueue = (int) $pdo->query(
        "SELECT COUNT(*) FROM orders WHERE status IN ('pending','brewing')"
    )->fetchColumn();

    $servedToday = (int) $pdo->query(
        "SELECT COUNT(*) FROM orders WHERE status = 'completed' AND DATE(created_at) = CURDATE()"
    )->fetchColumn();

    echo json_encode(['success' => true, 'in_queue' => $inQueue, 'served_today' => $servedToday]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'in_queue' => 0, 'served_today' => 0]);
}
?>
