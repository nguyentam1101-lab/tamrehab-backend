<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = [];
}

$rawContent = '';
foreach (['content', 'description', 'message', 'reference', 'code', 'orderCode'] as $key) {
    if (isset($data[$key]) && trim((string)$data[$key]) !== '') {
        $rawContent = trim((string)$data[$key]);
        break;
    }
}

if ($rawContent === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing content', 'payload' => $data]);
    exit;
}

$orderId = null;

if (preg_match('/TAM[-\s]?(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})[-\s]?(\d{3})/i', $rawContent, $matches)) {
    $orderId = 'TAM-' . $matches[1] . $matches[2] . $matches[3] . $matches[4] . $matches[5] . $matches[6] . '-' . $matches[7];
} elseif (preg_match('/TAM(\d{13,})/i', $rawContent, $matches)) {
    $digits = $matches[1];
    if (strlen($digits) >= 16) {
        $orderId = 'TAM-' . substr($digits, 0, 13) . '-' . substr($digits, 13, 3);
    }
}

if ($orderId === null) {
    error_log("Webhook: Could not extract order_id from content: {$rawContent}");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Could not extract order_id', 'raw_content' => $rawContent]);
    exit;
}

$dbFile = __DIR__ . '/brain.db';
$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$amount = (float)($data['amount'] ?? $data['transferAmount'] ?? $data['total'] ?? 0);

$stmt = $pdo->prepare('UPDATE orders SET status = :status, paid_at = CURRENT_TIMESTAMP, amount = COALESCE(NULLIF(:amount, 0), amount) WHERE order_id = :order_id AND status = "pending"');
$stmt->execute([
    ':status' => 'success',
    ':amount' => $amount,
    ':order_id' => $orderId
]);

$updated = $stmt->rowCount();
if ($updated > 0) {
    error_log("Webhook: Order {$orderId} updated to success");
    echo json_encode(['success' => true, 'message' => 'Order updated to success', 'order_id' => $orderId]);
} else {
    error_log("Webhook: No pending order found for {$orderId}");
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'No pending order matched', 'order_id' => $orderId]);
}
