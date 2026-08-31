<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST is allowed']);
    exit;
}

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody ?: '', true);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload']);
    exit;
}

$amount = $payload['amount'] ?? $payload['transferAmount'] ?? $payload['total'] ?? null;
$content = trim((string) ($payload['content'] ?? $payload['description'] ?? $payload['message'] ?? ''));
$reference = trim((string) ($payload['reference'] ?? $payload['code'] ?? $payload['order_id'] ?? ''));

if ($content === '' && $reference === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing content/description/message or reference/code/order_id'
    ]);
    exit;
}

try {
    require_once __DIR__ . '/lib/db.php';
    $db = getDatabase();

    $db->beginTransaction();

    $searchTerm = $content !== '' ? $content : $reference;

    $statement = $db->prepare(
        "UPDATE orders
         SET status = 'success', paid_at = CURRENT_TIMESTAMP
         WHERE status = 'pending'
           AND order_id = :search"
    );
    $statement->execute([
        ':search' => $searchTerm,
    ]);

    if ($statement->rowCount() === 0) {
        $db->rollBack();
        error_log("Webhook: Order not found. Content: {$content}, Reference: {$reference}");
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Order not found'
        ]);
        exit;
    }

    $db->commit();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'order_id' => $searchTerm,
        'amount' => (float) $amount,
        'content' => $content,
        'message' => 'Order updated successfully'
    ]);
} catch (Throwable $error) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }

    error_log('SePay webhook error: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database update failed']);
}
