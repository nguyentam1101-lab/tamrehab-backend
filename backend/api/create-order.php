<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../db.php';
$pdo = tamrehab_db();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$hoTen = trim((string)($input['ho_ten'] ?? ''));
$soDienThoai = trim((string)($input['so_dien_thoai'] ?? ''));
$moTa = trim((string)($input['mo_ta'] ?? ''));

if ($hoTen === '' || $soDienThoai === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập họ tên và số điện thoại']);
    exit;
}

if (!preg_match('/^[0-9+() .-]{8,20}$/', $soDienThoai)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Số điện thoại không hợp lệ']);
    exit;
}

$orderId = 'TAM-' . date('YmdHis') . '-' . rand(100, 999);
$amount = 2000;
$content = preg_replace('/[^A-Za-z0-9]/', '', $orderId);

try {
    $customer = $pdo->prepare('SELECT id FROM customers WHERE phone = :phone LIMIT 1');
    $customer->execute([':phone' => $soDienThoai]);
    if ($customer->fetchColumn()) {
        $updateCustomer = $pdo->prepare('UPDATE customers SET name = :name WHERE phone = :phone');
        $updateCustomer->execute([':name' => $hoTen, ':phone' => $soDienThoai]);
    } else {
        $insertCustomer = $pdo->prepare('INSERT INTO customers (name, phone, registered_at, created_at) VALUES (:name, :phone, CURRENT_DATE, CURRENT_TIMESTAMP)');
        $insertCustomer->execute([':name' => $hoTen, ':phone' => $soDienThoai]);
    }

    $stmt = $pdo->prepare('INSERT INTO orders (order_id, customer_name, phone, amount, content, status, created_at) VALUES (:order_id, :customer_name, :phone, :amount, :content, :status, CURRENT_TIMESTAMP)');
    $stmt->execute([
        ':order_id' => $orderId,
        ':customer_name' => $hoTen ?: 'Khách hàng',
        ':phone' => $soDienThoai ?: '',
        ':amount' => $amount,
        ':content' => $content,
        ':status' => 'pending'
    ]);

    $qrUrl = 'https://img.vietqr.io/image/BIDV-96247TGLA3-compact.png?amount=' . $amount . '&addInfo=' . urlencode($content) . '&accountName=' . urlencode('NGUYEN NGUYEN TAM');

    echo json_encode([
        'success' => true,
        'order_id' => $orderId,
        'amount' => $amount,
        'content' => $content,
        'qr_url' => $qrUrl,
        'message' => 'Đã tạo đơn hàng thành công.'
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Không thể lưu đơn hàng: ' . $e->getMessage()]);
}
