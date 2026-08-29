<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$dbFile = __DIR__ . '/../brain.db';
$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query('SELECT * FROM products ORDER BY id DESC');
    echo json_encode(['success' => true, 'items' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? 'save_product';

if ($action === 'save_product') {
    $name = trim((string)($input['name'] ?? ''));
    $productType = trim((string)($input['product_type'] ?? 'service'));
    $price = (float)($input['price'] ?? 0);
    $description = trim((string)($input['description'] ?? ''));
    $stockQuantity = (int)($input['stock_quantity'] ?? 0);

    $stmt = $pdo->prepare('INSERT INTO products (name, product_type, price, description, stock_quantity, created_at) VALUES (:name, :product_type, :price, :description, :stock_quantity, CURRENT_TIMESTAMP)');
    $stmt->execute([
        ':name' => $name,
        ':product_type' => $productType,
        ':price' => $price,
        ':description' => $description,
        ':stock_quantity' => $stockQuantity
    ]);
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid action']);
