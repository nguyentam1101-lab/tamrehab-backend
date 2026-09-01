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

$explicitId = trim((string) ($payload['order_id'] ?? ''));
$amount = $payload['transferAmount'] ?? $payload['amount'] ?? $payload['total'] ?? null;
$content = trim((string) ($payload['content'] ?? $payload['transactionContent'] ?? $payload['description'] ?? $payload['message'] ?? ''));
$identifierText = $explicitId . ' ' . $content;
$orderId = preg_match('/TAM-\d{14}-\d{3}/i', $identifierText, $match)
    ? strtoupper($match[0])
    : ($explicitId !== '' ? $explicitId : '');
$normalizedContent = tamrehab_normalize_transfer_content($content);

if ($orderId === '' && $content === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing order identifier or transfer content'
    ]);
    exit;
}

try {
    $db->beginTransaction();

    $amountValue = is_numeric($amount) ? (float) $amount : null;
    $candidates = $db->prepare('SELECT order_id, content FROM orders WHERE status = "pending" AND (:amount IS NULL OR :amount <= 0 OR amount = :amount)');
    $candidates->execute([':amount' => $amountValue]);
    $matchedOrderId = '';
    while ($candidate = $candidates->fetch(PDO::FETCH_ASSOC)) {
        $candidateContent = tamrehab_normalize_transfer_content((string) $candidate['content']);
        if (($orderId !== '' && strcasecmp($candidate['order_id'], $orderId) === 0)
            || ($normalizedContent !== '' && $candidateContent === $normalizedContent)) {
            $matchedOrderId = $candidate['order_id'];
            break;
        }
    }

    $statement = $db->prepare(
        "UPDATE orders
         SET status = 'success', paid_at = CURRENT_TIMESTAMP,
             amount = CASE WHEN :amount IS NOT NULL AND :amount > 0 THEN :amount ELSE amount END,
             content = CASE WHEN :content != '' THEN :content ELSE content END
         WHERE order_id = :order_id AND status = 'pending'"
    );
    $statement->execute([
        ':amount' => $amountValue,
        ':content' => $content,
        ':order_id' => $matchedOrderId
    ]);

    if ($statement->rowCount() === 0) {
        $db->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'No pending order matched']);
        exit;
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

function tamrehab_normalize_transfer_content(string $value): string
{
    $value = trim($value);
    $transliterated = function_exists('iconv') ? iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) : $value;
    return strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', $transliterated));
}
