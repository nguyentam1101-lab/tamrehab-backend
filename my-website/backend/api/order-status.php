<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';
allowCors();

$orderId = trim((string) ($_GET['order_id'] ?? ''));

if ($orderId === '') {
    jsonResponse(['success' => false, 'message' => 'Missing order_id'], 400);
}

$pdo = getDatabase();
$stmt = $pdo->prepare('SELECT order_id, status, amount, content, paid_at FROM orders WHERE order_id = ?');
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    jsonResponse(['success' => false, 'message' => 'Order not found'], 404);
}

jsonResponse([
    'success' => true,
    'order_id' => $order['order_id'],
    'status' => $order['status'],
    'amount' => (float) $order['amount'],
    'content' => $order['content'],
    'paid_at' => $order['paid_at'],
]);
