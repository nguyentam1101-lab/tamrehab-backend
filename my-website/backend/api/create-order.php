<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';
allowCors();
$pdo = getDatabase();

$input = requestJson();
$hoTen = trim((string)($input['ho_ten'] ?? ''));
$soDienThoai = trim((string)($input['so_dien_thoai'] ?? ''));
$moTa = trim((string)($input['mo_ta'] ?? ''));

if ($hoTen === '' && $soDienThoai === '' && $moTa === '') {
    jsonResponse(['success' => false, 'message' => 'Thiếu thông tin đơn hàng'], 400);
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

    $qrUrl = 'https://img.vietqr.io/image/BIDV-96247TGLA3-compact2.png?amount=' . $amount . '&addInfo=' . urlencode($content) . '&accountName=' . urlencode('NGUYEN NGUYEN TAM');

    jsonResponse([
        'success' => true,
        'order_id' => $orderId,
        'amount' => $amount,
        'content' => $content,
        'qr_url' => $qrUrl,
        'message' => 'Đã tạo đơn hàng thành công.'
    ]);
} catch (Throwable $e) {
    error_log('Create order failed: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Không thể lưu đơn hàng.'], 500);
}
