<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';
allowCors();
$pdo = getDatabase();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query('SELECT * FROM products ORDER BY id DESC');
    jsonResponse(['success' => true, 'items' => $stmt->fetchAll()]);
}

$input = requestJson();
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
    jsonResponse(['success' => true]);
}

jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
