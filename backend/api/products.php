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
    $stmt = $pdo->query('SELECT * FROM products ORDER BY id DESC');
    echo json_encode(['success' => true, 'items' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? 'save_product';

if ($action === 'save_product') {
    $id = (int)($input['id'] ?? 0);
    $name = trim((string)($input['name'] ?? ''));
    $productType = trim((string)($input['product_type'] ?? 'service'));
    $price = (float)($input['price'] ?? 0);
    $description = trim((string)($input['description'] ?? ''));
    $stockQuantity = $productType === 'physical' ? max(0, (int)($input['stock_quantity'] ?? 0)) : null;

    $sql = $id > 0
        ? 'UPDATE products SET name = :name, product_type = :product_type, price = :price, description = :description, stock_quantity = :stock_quantity WHERE id = :id'
        : 'INSERT INTO products (name, product_type, price, description, stock_quantity, created_at) VALUES (:name, :product_type, :price, :description, :stock_quantity, CURRENT_TIMESTAMP)';
    $stmt = $pdo->prepare($sql);
    $params = [
        ':name' => $name,
        ':product_type' => $productType,
        ':price' => $price,
        ':description' => $description,
        ':stock_quantity' => $stockQuantity
    ];
    if ($id > 0) {
        $params[':id'] = $id;
    }
    $stmt->execute($params);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'delete_product') {
    $stmt = $pdo->prepare('DELETE FROM products WHERE id = :id');
    $stmt->execute([':id' => (int)($input['id'] ?? 0)]);
    echo json_encode(['success' => $stmt->rowCount() > 0]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid action']);
