<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$dbFile = __DIR__ . '/../brain.db';
$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$hoTen = trim((string)($input['ho_ten'] ?? ''));
$soDienThoai = trim((string)($input['so_dien_thoai'] ?? ''));
$moTa = trim((string)($input['mo_ta'] ?? ''));

if ($hoTen === '' && $soDienThoai === '' && $moTa === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Thiếu thông tin đơn hàng']);
    exit;
}

$orderId = 'TAM-' . date('YmdHis') . '-' . rand(100, 999);
$amount = 2000;
$content = 'TAM' . strtoupper(substr(str_replace([' ', '-'], '', $hoTen), 0, 8) ?: 'PAY') . '-' . substr($orderId, -4);

try {
    $stmt = $pdo->prepare('INSERT INTO orders (order_id, customer_name, phone, amount, content, status, created_at) VALUES (:order_id, :customer_name, :phone, :amount, :content, :status, CURRENT_TIMESTAMP)');
    $stmt->execute([
        ':order_id' => $orderId,
        ':customer_name' => $hoTen ?: 'Khách hàng',
        ':phone' => $soDienThoai ?: '',
        ':amount' => $amount,
        ':content' => $content,
        ':status' => 'pending'
    ]);

    $qrUrl = 'https://img.vietqr.io/image/970422-123456789-compact.png?amount=' . $amount . '&addInfo=' . urlencode($content) . '&accountName=' . urlencode('TAM REHAB');

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
