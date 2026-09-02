<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/email-lib.php';

function tamrehab_upsert_customer(PDO $pdo, string $name, string $phone, string $email): int
{
    $email = trim($email);
    $phone = trim($phone);
    $name = trim($name);

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Email không hợp lệ');
    }

    if ($email !== '') {
        $stmt = $pdo->prepare('SELECT id FROM customers WHERE LOWER(email) = LOWER(:email) LIMIT 1');
        $stmt->execute([':email' => $email]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($customer) {
            $customerId = (int) $customer['id'];
            $pdo->prepare('UPDATE customers SET name = :name, phone = :phone WHERE id = :id')->execute([
                ':name' => $name,
                ':phone' => $phone,
                ':id' => $customerId,
            ]);
            return $customerId;
        }
    }

    if ($phone !== '') {
        $stmt = $pdo->prepare('SELECT id FROM customers WHERE phone = :phone LIMIT 1');
        $stmt->execute([':phone' => $phone]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($customer) {
            $customerId = (int) $customer['id'];
            if ($email !== '') {
                $pdo->prepare('UPDATE customers SET name = :name, email = :email WHERE id = :id')->execute([
                    ':name' => $name,
                    ':email' => $email,
                    ':id' => $customerId,
                ]);
            }
            return $customerId;
        }
    }

    $stmt = $pdo->prepare('INSERT INTO customers (name, phone, email, zalo, registered_at, created_at) VALUES (:name, :phone, :email, :zalo, CURRENT_DATE, CURRENT_TIMESTAMP)');
    $stmt->execute([
        ':name' => $name,
        ':phone' => $phone !== '' ? $phone : null,
        ':email' => $email !== '' ? $email : null,
        ':zalo' => '',
    ]);

    return (int) $pdo->lastInsertId();
}

function tamrehab_queue_email(PDO $pdo, int $customerId, string $emailType, string $toEmail, string $subject, string $body, string $sendAt): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO email_queue (customer_id, email_type, to_email, subject, body, send_at, status, created_at) VALUES (:customer_id, :email_type, :to_email, :subject, :body, :send_at, :status, CURRENT_TIMESTAMP)'
    );
    $stmt->execute([
        ':customer_id' => $customerId,
        ':email_type' => $emailType,
        ':to_email' => $toEmail,
        ':subject' => $subject,
        ':body' => $body,
        ':send_at' => $sendAt,
        ':status' => 'pending',
    ]);
}

function tamrehab_process_due_email_queue(PDO $pdo): void
{
    $rows = $pdo->query("SELECT * FROM email_queue WHERE status = 'pending' AND send_at <= datetime('now') ORDER BY send_at ASC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $result = tamrehab_send_resend_email((string) $row['to_email'], (string) $row['subject'], (string) $row['body']);
        $status = $result['success'] ? 'sent' : 'failed';
        $pdo->prepare(
            'UPDATE email_queue SET status = :status, sent_at = :sent_at, provider_message_id = :provider_message_id, error_message = :error_message WHERE id = :id'
        )->execute([
            ':status' => $status,
            ':sent_at' => $result['success'] ? date('Y-m-d H:i:s') : null,
            ':provider_message_id' => $result['provider_message_id'] ?? null,
            ':error_message' => $result['success'] ? null : ($result['message'] ?? 'Unknown error'),
            ':id' => (int) $row['id'],
        ]);
    }
}

function tamrehab_is_test_email(string $email): bool
{
    return preg_match('/\+test@/i', $email) === 1;
}

$pdo = tamrehab_db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$name = trim((string) ($input['name'] ?? ''));
$email = trim((string) ($input['email'] ?? ''));
$phone = trim((string) ($input['phone'] ?? ''));
$source = trim((string) ($input['source'] ?? 'waitlist'));

tamrehab_log('Incoming request: source=' . $source . ' name=' . $name . ' email=' . $email . ' phone=' . $phone);

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email không hợp lệ']);
    exit;
}

if ($name === '' || ($email === '' && $phone === '')) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập tên và ít nhất một thông tin liên hệ']);
    exit;
}

try {
    $customerId = tamrehab_upsert_customer($pdo, $name, $phone, $email);
    $sequence = tamrehab_email_sequence();
    $testMode = tamrehab_is_test_email($email);

    if ($testMode) {
        $sent = [];
        foreach ($sequence as $emailItem) {
            $result = tamrehab_send_resend_email($email, $emailItem['subject'], $emailItem['body']);
            $sent[] = ['type' => $emailItem['type'], 'success' => $result['success'], 'message' => $result['message'] ?? null];
        }

        echo json_encode([
            'success' => true,
            'test' => true,
            'message' => 'Đã gửi email test',
            'customer_id' => $customerId,
            'sent' => $sent,
            'source' => $source,
        ]);
        exit;
    }

    $now = new DateTimeImmutable('now');
    $finalPayload = [
        'customer_id' => $customerId,
        'source' => $source,
        'test' => false,
    ];

    $first = $sequence[0];
    $firstResult = tamrehab_send_resend_email($email, $first['subject'], $first['body']);
    $finalPayload['email_1'] = $firstResult;

    if ($firstResult['success']) {
        $firstQueueData = [
            'customer_id' => $customerId,
            'email_type' => $first['type'],
            'to_email' => $email,
            'subject' => $first['subject'],
            'body' => $first['body'],
            'send_at' => $now->format('Y-m-d H:i:s'),
            'status' => 'sent',
            'sent_at' => $now->format('Y-m-d H:i:s'),
            'provider_message_id' => $firstResult['provider_message_id'] ?? null,
        ];
        $pdo->prepare('INSERT INTO email_queue (customer_id, email_type, to_email, subject, body, send_at, status, sent_at, provider_message_id, created_at) VALUES (:customer_id, :email_type, :to_email, :subject, :body, :send_at, :status, :sent_at, :provider_message_id, CURRENT_TIMESTAMP)')->execute($firstQueueData);
    }

    $queueAt = [
        1 => $now->modify('+2 days')->format('Y-m-d H:i:s'),
        2 => $now->modify('+3 days')->format('Y-m-d H:i:s'),
    ];

    for ($i = 1; $i <= 2; $i++) {
        $item = $sequence[$i];
        tamrehab_queue_email($pdo, $customerId, $item['type'], $email, $item['subject'], $item['body'], $queueAt[$i]);
    }

    tamrehab_process_due_email_queue($pdo);

    echo json_encode([
        'success' => true,
        'test' => false,
        'message' => 'Đã lưu khách hàng và lên lịch email sequence',
        'customer_id' => $customerId,
        'email_1' => $firstResult,
        'scheduled' => [
            'email_2' => $queueAt[1],
            'email_3' => $queueAt[2],
        ],
    ]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi khi gửi email sequence: ' . $e->getMessage(),
    ]);
}
