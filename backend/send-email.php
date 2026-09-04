<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/mailer.php';

$input = json_decode((string) file_get_contents('php://input'), true) ?? [];
$typeRaw = (string) ($input['type'] ?? $_GET['type'] ?? 'order');
$isTest = !empty($input['test'])
    || isset($_GET['test'])
    || str_contains($typeRaw, '+test');
$type = strtolower(trim(str_replace('+test', '', $typeRaw)));
if ($type === '') {
    $type = 'order';
}

$to = trim((string) ($input['to'] ?? $input['email'] ?? $_GET['to'] ?? ''));
$name = trim((string) ($input['name'] ?? $input['customer_name'] ?? 'bạn'));

if ($isTest && $to === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Chế độ +test cần tham số to=email@thật.vd',
    ]);
    exit;
}

if (in_array($type, ['all', 'sequence', 'waitlist'], true)) {
    $t1 = tamrehab_sequence_email('welcome', ['name' => $name]);
    $t2 = tamrehab_sequence_email('nurture', ['name' => $name]);
    $t3 = tamrehab_sequence_email('close', ['name' => $name]);
    $r1 = tamrehab_send_resend_email($to, $t1['subject'], $t1['html']);
    $r2 = tamrehab_send_resend_email($to, $t2['subject'], $t2['html']);
    $r3 = tamrehab_send_resend_email($to, $t3['subject'], $t3['html']);
    echo json_encode([
        'success' => $r1['success'] && $r2['success'] && $r3['success'],
        'type' => $type,
        'test' => $isTest,
        'results' => [
            'welcome' => $r1,
            'nurture' => $r2,
            'close' => $r3,
        ]
    ]);
    exit;
}

if (in_array($type, ['welcome', 'nurture', 'close'], true)) {
    $template = tamrehab_sequence_email($type, ['name' => $name]);
    $result = tamrehab_send_resend_email($to, $template['subject'], $template['html']);
    echo json_encode(['success' => $result['success'], 'type' => $type, 'test' => $isTest] + $result);
    exit;
}

if ($type === 'order') {
    $orderId = trim((string) ($input['order_id'] ?? $_GET['order_id'] ?? ''));
    if ($orderId !== '') {
        require_once __DIR__ . '/db.php';
        $result = tamrehab_send_order_confirmation(tamrehab_db(), $orderId);
        echo json_encode(['success' => $result['success'], 'type' => 'order', 'test' => $isTest] + $result);
        exit;
    }

    $template = tamrehab_order_confirmation_email([
        'customer_name' => $name,
        'order_id' => $input['order_id_label'] ?? 'TEST-ORDER',
        'product_name' => $input['product_name'] ?? 'Buổi giãn cơ trị liệu chuyên sâu',
        'amount' => $input['amount'] ?? 0,
        'quantity' => $input['quantity'] ?? 1,
        'content' => $input['content'] ?? '',
    ]);
    $result = tamrehab_send_resend_email($to, $template['subject'], $template['html']);
    echo json_encode(['success' => $result['success'], 'type' => 'order', 'test' => $isTest] + $result);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'type không hợp lệ. Dùng welcome, nurture, close hoặc order']);
