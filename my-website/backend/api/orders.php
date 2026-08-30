<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';
allowCors();
$pdo = getDatabase();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query('SELECT o.*, p.name AS product_name FROM orders o LEFT JOIN products p ON p.id = o.product_id ORDER BY o.id DESC');
    jsonResponse(['success' => true, 'items' => $stmt->fetchAll()]);
}

$input = requestJson();
$action = $input['action'] ?? 'save_order';

if ($action === 'save_order') {
    $orderId = trim((string)($input['order_id'] ?? ''));
    $productId = (int)($input['product_id'] ?? 0);
    $quantity = max(1, (int)($input['quantity'] ?? 1));
    $amount = (float)($input['amount'] ?? 0);
    $content = trim((string)($input['content'] ?? ''));
    $status = trim((string)($input['status'] ?? 'pending'));

    $stmt = $pdo->prepare('INSERT INTO orders (order_id, product_id, quantity, amount, content, status, created_at) VALUES (:order_id, :product_id, :quantity, :amount, :content, :status, CURRENT_TIMESTAMP)');
    $stmt->execute([
        ':order_id' => $orderId,
        ':product_id' => $productId,
        ':quantity' => $quantity,
        ':amount' => $amount,
        ':content' => $content,
        ':status' => $status
    ]);
    jsonResponse(['success' => true]);
}

jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
