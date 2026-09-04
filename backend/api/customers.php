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
require_once __DIR__ . '/../mailer.php';
$pdo = tamrehab_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query('SELECT * FROM customers ORDER BY id DESC');
    echo json_encode(['success' => true, 'items' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? 'save_customer';

if ($action === 'save_customer') {
    $id = (int)($input['id'] ?? 0);
    $name = trim((string)($input['name'] ?? ''));
    $phone = trim((string)($input['phone'] ?? ''));
    $email = trim((string)($input['email'] ?? ''));
    $zalo = trim((string)($input['zalo'] ?? ''));
    $registeredAt = trim((string)($input['registered_at'] ?? ''));

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email không hợp lệ']);
        exit;
    }

    $sql = $id > 0
        ? 'UPDATE customers SET name = :name, phone = :phone, email = :email, zalo = :zalo, registered_at = :registered_at WHERE id = :id'
        : 'INSERT INTO customers (name, phone, email, zalo, registered_at, created_at) VALUES (:name, :phone, :email, :zalo, :registered_at, CURRENT_TIMESTAMP)';
    $stmt = $pdo->prepare($sql);
    $params = [
        ':name' => $name,
        ':phone' => $phone,
        ':email' => $email !== '' ? $email : null,
        ':zalo' => $zalo,
        ':registered_at' => $registeredAt !== '' ? $registeredAt : null
    ];
    if ($id > 0) $params[':id'] = $id;
    $stmt->execute($params);

    $emailResult = null;
    if ($id === 0 && $email !== '') {
        try {
            $isTest = str_contains(strtolower($email), '+test') || !empty($input['test']);
            if ($isTest) {
                $r1 = tamrehab_send_resend_email($email, tamrehab_sequence_email('welcome', ['name' => $name])['subject'], tamrehab_sequence_email('welcome', ['name' => $name])['html']);
                $r2 = tamrehab_send_resend_email($email, tamrehab_sequence_email('nurture', ['name' => $name])['subject'], tamrehab_sequence_email('nurture', ['name' => $name])['html']);
                $r3 = tamrehab_send_resend_email($email, tamrehab_sequence_email('close', ['name' => $name])['subject'], tamrehab_sequence_email('close', ['name' => $name])['html']);
                $emailResult = ['success' => $r1['success'] && $r2['success'] && $r3['success'], 'test' => true, 'details' => [$r1, $r2, $r3]];
            } else {
                $seq = tamrehab_sequence_email('welcome', ['name' => $name]);
                $emailResult = tamrehab_send_resend_email($email, $seq['subject'], $seq['html']);
            }
        } catch (Throwable $e) {
            $emailResult = ['success' => false, 'message' => $e->getMessage()];
        }
    }

    echo json_encode(['success' => true, 'email' => $emailResult]);
    exit;
}

if ($action === 'delete_customer') {
    $stmt = $pdo->prepare('DELETE FROM customers WHERE id = :id');
    $stmt->execute([':id' => (int)($input['id'] ?? 0)]);
    echo json_encode(['success' => $stmt->rowCount() > 0]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid action']);
