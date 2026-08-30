<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';
allowCors();
$pdo = getDatabase();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query('SELECT * FROM customers ORDER BY id DESC');
    jsonResponse(['success' => true, 'items' => $stmt->fetchAll()]);
}

$input = requestJson();
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
    jsonResponse(['success' => true]);
}

jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
