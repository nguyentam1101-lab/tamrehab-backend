<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST is allowed']);
    exit;
}

require_once __DIR__ . '/backend/db.php';
$db = tamrehab_db();

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody ?: '', true);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload']);
    exit;
}

$orderId = trim((string) ($payload['order_id'] ?? $payload['reference'] ?? $payload['code'] ?? ''));
$amount = $payload['amount'] ?? $payload['transferAmount'] ?? $payload['total'] ?? null;
$content = trim((string) ($payload['content'] ?? $payload['description'] ?? $payload['message'] ?? ''));

if ($orderId === '' || !is_numeric($amount) || $content === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing order_id, amount, or content'
    ]);
    exit;
}

try {
    $db->beginTransaction();

    $statement = $db->prepare(
        "UPDATE orders
         SET status = 'success', paid_at = CURRENT_TIMESTAMP
         WHERE order_id = :order_id
           AND status = 'pending'"
    );
    $statement->execute([':order_id' => $orderId]);

    if ($statement->rowCount() === 0) {
        $insertStatement = $db->prepare('INSERT INTO orders (order_id, amount, content, status, paid_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)');
        $insertStatement->execute([$orderId, (float) $amount, $content, 'success']);
    }

    $db->commit();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'order_id' => $orderId,
        'amount' => (float) $amount,
        'content' => $content
    ]);
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    error_log('SePay webhook error: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database update failed']);
}
