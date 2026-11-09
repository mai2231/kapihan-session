<?php
require_once 'connection/db.php';

$orderId = intval($_GET['order_id'] ?? 0);
$token = (string)($_GET['t'] ?? '');
$order = null;
$items = [];
$error = null;

if ($orderId <= 0) {
    $error = 'Invalid order number.';
} elseif (!can_view_order($pdo, $orderId, $token)) {
    $error = 'Hindi nahanap ang order na ito.';
} else {
    try {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            $error = 'Hindi nahanap ang order na ito.';
        } else {
            $itemStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $itemStmt->execute([$orderId]);
            $items = $itemStmt->fetchAll();
        }
    } catch (Exception $e) {
        error_log('receipt: ' . $e->getMessage());
        $error = 'May problema sa pag-load ng resibo.';
    }
}

$paymentLabels = ['cash' => 'Cash', 'gcash' => 'GCash', 'maya' => 'Maya'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Resibo — Order #<?= $orderId ?></title>
<?php $__fav = asset_url(setting('favicon')); if ($__fav): ?><link rel="icon" href="<?= h($__fav) ?>"><?php endif; ?>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Karla:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  * { box-sizing: border-box; }
  body {
    font-family: 'Karla', sans-serif;
    background: #eee;
    margin: 0;
    padding: 24px;
    display: flex;
    justify-content: center;
  }
  .receipt {
    width: 320px;
    background: #fff;
    padding: 24px 20px;
    border-radius: 4px;
    box-shadow: 0 4px 16px rgba(0,0,0,.12);
  }
  .center { text-align: center; }
  h1 { font-family: 'Bebas Neue', sans-serif; font-size: 28px; letter-spacing: 1px; margin: 0 0 2px; }
  .tagline { font-size: 11px; color: #777; margin-bottom: 14px; }
  .divider { border: none; border-top: 1px dashed #bbb; margin: 14px 0; }
  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  td { padding: 3px 0; vertical-align: top; }
  .qty { color: #777; width: 28px; }
  .item-price { text-align: right; white-space: nowrap; }
  .meta { font-size: 12px; color: #555; margin: 2px 0; }
  .total-row td { font-weight: 700; font-size: 15px; padding-top: 10px; }
  .footer { text-align: center; font-size: 11px; color: #999; margin-top: 16px; }
  .print-btn {
    display: block; width: 100%; margin-top: 18px; padding: 10px;
    background: #241812; color: #F4EADA; border: none; border-radius: 6px;
    font-family: 'Karla', sans-serif; font-weight: 700; font-size: 13px; cursor: pointer;
  }
  @media print {
    body { background: #fff; padding: 0; }
    .receipt { box-shadow: none; width: 100%; }
    .print-btn { display: none; }
  }
</style>
</head>
<body>
  <div class="receipt">
    <?php if ($error): ?>
      <p class="center" style="color:#c0392b; font-weight:700;"><?= htmlspecialchars($error) ?></p>
    <?php else: ?>
      <div class="center">
        <h1>☕ <?= h(strtoupper(setting('store_name'))) ?></h1>
        <?php if (setting('tagline') !== ''): ?><p class="tagline"><?= h(setting('tagline')) ?></p><?php endif; ?>
        <?php if (setting('address') !== ''): ?><p class="tagline"><?= h(setting('address')) ?></p><?php endif; ?>
        <?php if (setting('contact_number') !== ''): ?><p class="tagline"><?= h(setting('contact_number')) ?></p><?php endif; ?>
      </div>
      <hr class="divider">
      <p class="meta">Order #: <strong>#<?= $order['id'] ?></strong></p>
      <p class="meta">Petsa: <?= date('M j, Y — g:i A', strtotime($order['created_at'])) ?></p>
      <p class="meta">Customer: <?= htmlspecialchars($order['customer_name']) ?></p>
      <p class="meta">Bayad: <?= $paymentLabels[strtolower($order['payment_method'] ?? 'cash')] ?? ucfirst($order['payment_method']) ?></p>
      <p class="meta">Uri: <?= h(['dine_in' => 'Dine-In', 'take_out' => 'Take-Out', 'pick_up' => 'Pick-Up'][$order['order_type'] ?? 'pick_up'] ?? 'Pick-Up') ?></p>
      <?php if (!empty($order['notes'])): ?><p class="meta">Note: <?= h($order['notes']) ?></p><?php endif; ?>
      <p class="meta">Payment: <?= (strtolower((string)($order['payment_status'] ?? 'pending')) === 'paid') ? 'Paid' : 'Pending' ?></p>
      <p class="meta">Status: <?= ucfirst($order['status']) ?></p>
      <hr class="divider">
      <table>
        <?php foreach ($items as $it): ?>
        <tr>
          <td class="qty"><?= (int)$it['quantity'] ?>x</td>
          <td><?= htmlspecialchars($it['product_name']) ?></td>
          <td class="item-price"><?= h(cur()) ?><?= number_format($it['price'] * $it['quantity'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="total-row">
          <td colspan="2">TOTAL</td>
          <td class="item-price"><?= h(cur()) ?><?= number_format($order['total_amount'], 2) ?></td>
        </tr>
      </table>
      <hr class="divider">
      <p class="footer">Salamat sa pagbili! Balik ka ulit. 🤎</p>

      <button class="print-btn" onclick="window.print()">I-print ang Resibo</button>
    <?php endif; ?>
  </div>
</body>
</html>
