<?php

declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Use POST']);
    exit;
}

$orderId = trim((string) ($_POST['order_id'] ?? ''));
if ($orderId === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing order_id']);
    exit;
}

require_once __DIR__ . '/lib/db.php';
$db = getDatabase();

$stmt = $db->prepare("UPDATE orders SET status = 'success', paid_at = CURRENT_TIMESTAMP WHERE order_id = ? AND status = 'pending'");
$stmt->execute([$orderId]);

if ($stmt->rowCount() > 0) {
    echo json_encode(['success' => true, 'message' => 'Order updated to success']);
} else {
    echo json_encode(['success' => false, 'message' => 'Order not found or already processed']);
}
