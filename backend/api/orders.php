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
require_once __DIR__ . '/../email-lib.php';
$pdo = tamrehab_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $orderId = trim((string)($_GET['order_id'] ?? ''));
    if ($orderId !== '') {
        $stmt = $pdo->prepare('SELECT o.*, p.name AS product_name, p.product_type AS product_type FROM orders o LEFT JOIN products p ON p.id = o.product_id WHERE o.order_id = :order_id LIMIT 1');
        $stmt->execute([':order_id' => $orderId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'item' => $row ?: null]);
        exit;
    }
    $stmt = $pdo->query('SELECT o.*, p.name AS product_name, p.product_type AS product_type FROM orders o LEFT JOIN products p ON p.id = o.product_id ORDER BY o.id DESC');
    echo json_encode(['success' => true, 'items' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || $input === []) {
    // JavaScript normally sends JSON. Accept normal form posts as a safe fallback.
    $input = $_POST;
}
$action = $input['action'] ?? 'save_order';

if ($action === 'save_order') {
    $id = (int)($input['id'] ?? 0);
    $orderId = trim((string)($input['order_id'] ?? ''));
    $customerName = trim((string)($input['customer_name'] ?? ''));
    $phone = trim((string)($input['phone'] ?? ''));
    $email = trim((string)($input['email'] ?? ''));
    $emailValid = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL);
    error_log('[api/orders] save_order: order_id=' . $orderId . ' email="' . $email . '" email hợp lệ=' . ($emailValid ? 'YES' : 'NO'));
    $productId = (int)($input['product_id'] ?? 0);
    $quantity = max(1, (int)($input['quantity'] ?? 1));
    $amount = (float)($input['amount'] ?? 0);
    $content = trim((string)($input['content'] ?? ''));
    $status = trim((string)($input['status'] ?? 'pending'));

    if ($orderId === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email không hợp lệ']);
        exit;
    }

    if ($id > 0) {
        $stmt = $pdo->prepare('UPDATE orders SET order_id = :order_id, customer_name = :customer_name, phone = :phone, email = :email, product_id = :product_id, quantity = :quantity, amount = :amount, content = :content, status = :status WHERE id = :id');
        $params = [
            ':order_id' => $orderId,
            ':customer_name' => $customerName !== '' ? $customerName : null,
            ':phone' => $phone !== '' ? $phone : null,
            ':email' => $email !== '' ? $email : null,
            ':product_id' => $productId ?: null,
            ':quantity' => $quantity,
            ':amount' => $amount,
            ':content' => $content,
            ':status' => $status,
            ':id' => $id
        ];
    } else {
        $stmt = $pdo->prepare('INSERT INTO orders (order_id, customer_name, phone, email, product_id, quantity, amount, content, status, created_at) VALUES (:order_id, :customer_name, :phone, :email, :product_id, :quantity, :amount, :content, :status, CURRENT_TIMESTAMP)');
        $params = [
            ':order_id' => $orderId,
            ':customer_name' => $customerName !== '' ? $customerName : null,
            ':phone' => $phone !== '' ? $phone : null,
            ':email' => $email !== '' ? $email : null,
            ':product_id' => $productId ?: null,
            ':quantity' => $quantity,
            ':amount' => $amount,
            ':content' => $content,
            ':status' => $status
        ];
    }
    $previousEmail = '';
    if ($id > 0) {
        $oldStmt = $pdo->prepare('SELECT email FROM orders WHERE id = :id LIMIT 1');
        $oldStmt->execute([':id' => $id]);
        $oldRow = $oldStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $previousEmail = trim((string) ($oldRow['email'] ?? ''));
    }

    $pdo->beginTransaction();
    try {
        $stmt->execute($params);
        if ($id === 0 && $productId > 0) {
            $stock = $pdo->prepare('SELECT product_type, stock_quantity FROM products WHERE id = :id');
            $stock->execute([':id' => $productId]);
            $product = $stock->fetch();
            if ($product && $product['product_type'] === 'physical') {
                $update = $pdo->prepare('UPDATE products SET stock_quantity = stock_quantity - :quantity WHERE id = :id AND stock_quantity >= :quantity');
                $update->execute([':quantity' => $quantity, ':id' => $productId]);
                if ($update->rowCount() !== 1) throw new RuntimeException('Không đủ tồn kho sản phẩm vật lý');
            }
        }
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        $message = $error->getMessage();
        if (str_contains($message, 'UNIQUE') || (string) $error->getCode() === '23000') {
            $message = 'Mã đơn đã tồn tại. Hãy dùng mã mới (ví dụ TAM-' . date('YmdHis') . ').';
        }
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }

    $response = ['success' => true, 'message' => 'Đã lưu đơn hàng.'];
    $shouldSend = $id === 0 || ($email !== '' && $previousEmail === '');
    error_log('[api/orders] save_order: should_send=' . ($shouldSend ? 'YES' : 'NO') . ' (id=' . $id . ', email="' . $email . '", prev="' . $previousEmail . '")');
    if ($shouldSend) {
        $productStmt = $pdo->prepare('SELECT name, product_type FROM products WHERE id = :id LIMIT 1');
        $productStmt->execute([':id' => $productId]);
        $product = $productStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $response['email_confirmation'] = tamrehab_send_order_confirmation($pdo, [
            'order_id' => $orderId,
            'customer_name' => $customerName,
            'email' => $email,
            'product_name' => $product['name'] ?? '',
            'product_type' => $product['product_type'] ?? 'service',
            'quantity' => $quantity,
            'amount' => $amount,
        ]);
        if (!empty($response['email_confirmation']['success'])) {
            $response['message'] = $response['email_confirmation']['message'] ?? 'Đã lưu đơn hàng và gửi email xác nhận.';
        } elseif (!empty($response['email_confirmation']['skipped'])) {
            $response['message'] = 'Đã lưu đơn hàng. Chưa gửi email vì thiếu địa chỉ email hợp lệ.';
        } else {
            $response['message'] = 'Đã lưu đơn hàng nhưng gửi email xác nhận thất bại: ' . ($response['email_confirmation']['message'] ?? '');
        }
    }

    echo json_encode($response);
    exit;
}

if ($action === 'send_confirmation') {
    $id = (int) ($input['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT o.*, p.name AS product_name, p.product_type AS product_type FROM orders o LEFT JOIN products p ON p.id = o.product_id WHERE o.id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy đơn hàng']);
        exit;
    }
    $result = tamrehab_send_order_confirmation($pdo, $order);
    echo json_encode([
        'success' => !empty($result['success']),
        'email_confirmation' => $result,
        'message' => $result['message'] ?? 'Gửi email thất bại',
    ]);
    exit;
}

if ($action === 'set_success') {
    $stmt = $pdo->prepare('UPDATE orders SET status = "success", paid_at = COALESCE(paid_at, CURRENT_TIMESTAMP) WHERE id = :id AND status != "success"');
    $stmt->execute([':id' => (int)($input['id'] ?? 0)]);
    echo json_encode(['success' => $stmt->rowCount() > 0]);
    exit;
}

if ($action === 'delete_order') {
    $stmt = $pdo->prepare('DELETE FROM orders WHERE id = :id');
    $stmt->execute([':id' => (int)($input['id'] ?? 0)]);
    echo json_encode(['success' => $stmt->rowCount() > 0]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid action']);
