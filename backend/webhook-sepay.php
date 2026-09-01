<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/db.php';
$pdo = tamrehab_db();

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = [];
}

$orderId = null;
foreach (['order_id', 'reference', 'content', 'code', 'orderCode'] as $key) {
    if (isset($data[$key]) && trim((string)$data[$key]) !== '') {
        $orderId = trim((string)$data[$key]);
        break;
    }
}

if ($orderId === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing order identifier']);
    exit;
}

$amount = (float)($data['amount'] ?? 0);
$transferContent = trim((string)($data['content'] ?? ''));

$stmt = $pdo->prepare('UPDATE orders SET status = :status, paid_at = CURRENT_TIMESTAMP, amount = COALESCE(NULLIF(:amount, 0), amount), content = COALESCE(NULLIF(:content, ""), content) WHERE order_id = :order_id AND status = "pending"');
$stmt->execute([
    ':status' => 'success',
    ':amount' => $amount,
    ':content' => $transferContent,
    ':order_id' => $orderId
]);

$updated = $stmt->rowCount();
if ($updated > 0) {
    echo json_encode(['success' => true, 'message' => 'Order updated to success']);
} else {
    echo json_encode(['success' => false, 'message' => 'No pending order matched']);
}
