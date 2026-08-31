<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../db.php';
$pdo = tamrehab_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query('SELECT o.*, p.name AS product_name FROM orders o LEFT JOIN products p ON p.id = o.product_id ORDER BY o.id DESC');
    echo json_encode(['success' => true, 'items' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
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
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid action']);
