<?php
// Kasalukuyang menu — ginagamit ng storefront para i-sync ang cart sa database.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once 'connection/db.php';

try {
    $rows = $pdo->query("SELECT id, name, category, price, image, description, stock_qty FROM products WHERE is_active = 1 ORDER BY id ASC")->fetchAll();
    foreach ($rows as &$r) { $r['image'] = product_image_url($r['image'] ?? '', ''); }   // '' = placeholder
    unset($r);
    echo json_encode(['success' => true, 'products' => $rows, 'config' => public_config()], JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    error_log('menu: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load the menu. Please try again.']);
}
