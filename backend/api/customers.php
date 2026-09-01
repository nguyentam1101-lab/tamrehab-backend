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
    $stmt = $pdo->query('SELECT * FROM customers ORDER BY id DESC');
    echo json_encode(['success' => true, 'items' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? 'save_customer';

if ($action === 'save_customer') {
    $name = trim((string)($input['name'] ?? ''));
    $phone = trim((string)($input['phone'] ?? ''));
    $zalo = trim((string)($input['zalo'] ?? ''));
    $registeredAt = trim((string)($input['registered_at'] ?? ''));

    $stmt = $pdo->prepare('INSERT INTO customers (name, phone, zalo, registered_at, created_at) VALUES (:name, :phone, :zalo, :registered_at, CURRENT_TIMESTAMP)');
    $stmt->execute([
        ':name' => $name,
        ':phone' => $phone,
        ':zalo' => $zalo,
        ':registered_at' => $registeredAt !== '' ? $registeredAt : null
    ]);
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid action']);
