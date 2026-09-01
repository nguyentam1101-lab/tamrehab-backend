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
    $orderId = trim((string)($_GET['order_id'] ?? ''));
    if ($orderId !== '') {
        $stmt = $pdo->prepare('SELECT o.*, p.name AS product_name FROM orders o LEFT JOIN products p ON p.id = o.product_id WHERE o.order_id = :order_id LIMIT 1');
        $stmt->execute([':order_id' => $orderId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'item' => $row ?: null]);
        exit;
    }
    $stmt = $pdo->query('SELECT o.*, p.name AS product_name FROM orders o LEFT JOIN products p ON p.id = o.product_id ORDER BY o.id DESC');
    echo json_encode(['success' => true, 'items' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? 'save_order';

if ($action === 'save_order') {
    $id = (int)($input['id'] ?? 0);
    $orderId = trim((string)($input['order_id'] ?? ''));
    $productId = (int)($input['product_id'] ?? 0);
    $quantity = max(1, (int)($input['quantity'] ?? 1));
    $amount = (float)($input['amount'] ?? 0);
    $content = trim((string)($input['content'] ?? ''));
    $status = trim((string)($input['status'] ?? 'pending'));

    if ($id > 0) {
        $stmt = $pdo->prepare('UPDATE orders SET order_id = :order_id, product_id = :product_id, quantity = :quantity, amount = :amount, content = :content, status = :status WHERE id = :id');
        $params = [
            ':order_id' => $orderId, ':product_id' => $productId ?: null, ':quantity' => $quantity,
            ':amount' => $amount, ':content' => $content, ':status' => $status, ':id' => $id
        ];
    } else {
        $stmt = $pdo->prepare('INSERT INTO orders (order_id, product_id, quantity, amount, content, status, created_at) VALUES (:order_id, :product_id, :quantity, :amount, :content, :status, CURRENT_TIMESTAMP)');
        $params = [
        ':order_id' => $orderId,
        ':product_id' => $productId ?: null,
        ':quantity' => $quantity,
        ':amount' => $amount,
        ':content' => $content,
        ':status' => $status
        ];
    }
    $pdo->beginTransaction();
    try {
        $stmt->execute($params);
        if ($id === 0 && $productId > 0) {
            $stock = $pdo->prepare('SELECT product_type, stock_quantity FROM products WHERE id = :id');
            $stock->execute([':id' => $productId]);
            $product = $stock->fetch();
            if ($product && $product['product_type'] === 'physical') {
                $update = $pdo->prepare('UPDATE products SET stock_quantity = stock_quantity - :quantity WHERE id = :id AND stock_quantity >= :quantity');
                $update->execute([':quantity' => $quantity, ':id' => $productId]);
                if ($update->rowCount() !== 1) throw new RuntimeException('Không đủ tồn kho sản phẩm vật lý');
            }
        }
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $error->getMessage()]);
        exit;
    }
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'set_success') {
    $stmt = $pdo->prepare('UPDATE orders SET status = "success", paid_at = COALESCE(paid_at, CURRENT_TIMESTAMP) WHERE id = :id AND status != "success"');
    $stmt->execute([':id' => (int)($input['id'] ?? 0)]);
    echo json_encode(['success' => $stmt->rowCount() > 0]);
    exit;
}

if ($action === 'delete_order') {
    $stmt = $pdo->prepare('DELETE FROM orders WHERE id = :id');
    $stmt->execute([':id' => (int)($input['id'] ?? 0)]);
    echo json_encode(['success' => $stmt->rowCount() > 0]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid action']);
