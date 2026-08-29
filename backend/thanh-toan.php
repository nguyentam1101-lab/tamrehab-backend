<?php

decide:

function ensureDatabase()
{
    $dbFile = __DIR__ . DIRECTORY_SEPARATOR . 'brain.db';
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $db->exec('CREATE TABLE IF NOT EXISTS products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT,
        product_type TEXT,
        price REAL,
        description TEXT,
        stock_quantity INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS customers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT,
        phone TEXT,
        zalo TEXT,
        registered_at TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id TEXT UNIQUE,
        customer_name TEXT,
        phone TEXT,
        product_id INTEGER,
        quantity INTEGER DEFAULT 1,
        amount REAL,
        content TEXT,
        status TEXT DEFAULT "pending",
        paid_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');

    $columns = array_column($db->query('PRAGMA table_info(orders)')->fetchAll(), 'name');
    if (!in_array('product_id', $columns, true)) {
        $db->exec('ALTER TABLE orders ADD COLUMN product_id INTEGER');
    }
    if (!in_array('quantity', $columns, true)) {
        $db->exec('ALTER TABLE orders ADD COLUMN quantity INTEGER NOT NULL DEFAULT 1');
    }
    if (!in_array('customer_name', $columns, true)) {
        $db->exec('ALTER TABLE orders ADD COLUMN customer_name TEXT');
    }
    if (!in_array('phone', $columns, true)) {
        $db->exec('ALTER TABLE orders ADD COLUMN phone TEXT');
    }
}

ensureDatabase();

$dbFile = __DIR__ . DIRECTORY_SEPARATOR . 'brain.db';
$db = new PDO('sqlite:' . $dbFile);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$amount = 2000;
$generatedOrderId = null;
$generatedContent = null;
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hoTen = trim((string)($_POST['ho_ten'] ?? ''));
    $soDienThoai = trim((string)($_POST['so_dien_thoai'] ?? ''));
    $moTa = trim((string)($_POST['mo_ta'] ?? ''));

    if ($hoTen === '' && $soDienThoai === '' && $moTa === '') {
        $notice = 'Vui lòng nhập thông tin thanh toán.';
    } else {
        $generatedOrderId = 'TAM-' . date('YmdHis') . '-' . rand(100, 999);
        $generatedContent = 'TAM' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $hoTen ?: 'PAY'), 0, 8) ?: 'PAY') . '-' . substr($generatedOrderId, -4);

        $stmt = $db->prepare('INSERT INTO orders (order_id, customer_name, phone, amount, content, status, created_at) VALUES (:order_id, :customer_name, :phone, :amount, :content, :status, CURRENT_TIMESTAMP)');
        $stmt->execute([
            ':order_id' => $generatedOrderId,
            ':customer_name' => $hoTen ?: 'Khách hàng',
            ':phone' => $soDienThoai ?: '',
            ':amount' => $amount,
            ':content' => $generatedContent,
            ':status' => 'pending'
        ]);

        $notice = 'Đã tạo đơn hàng thành công.';
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Thanh toán SePay</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f6f1ea; margin:0; padding:32px 16px; color:#1d1a17; }
        .wrap { max-width:980px; margin:0 auto; background:#fff; border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,.05); border:1px solid #e8dcc5; padding:24px; }
        .grid { display:grid; grid-template-columns:1fr 1.1fr; gap:24px; }
        .panel { background:#fffaf5; border:1px solid #ebdcbe; border-radius:10px; padding:20px; }
        label { display:block; font-weight:700; margin-bottom:8px; }
        input, textarea, button { width:100%; box-sizing:border-box; padding:10px 12px; border-radius:8px; border:1px solid #d3c1a3; font-size:15px; }
        textarea { min-height:110px; resize:vertical; }
        button { background:#1d2d2f; color:#fff; cursor:pointer; border:none; font-weight:700; }
        .notice { margin-top:16px; padding:10px 12px; border-radius:8px; background:#edf9ee; border:1px solid #7fbc79; }
        .error { background:#fdeceb; border:1px solid #d7726d; }
        .qr { width:100%; max-width:280px; display:block; margin-top:12px; border:4px solid #fff; border-radius:10px; }
        .small { color:#685f59; }
        @media (max-width:760px) { .grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>Thanh toán SePay</h1>
        <div class="grid">
            <div>
                <form method="post">
                    <div>
                        <label>Họ tên</label>
                        <input type="text" name="ho_ten" required />
                    </div>
                    <div style="margin-top:14px;">
                        <label>Số điện thoại</label>
                        <input type="text" name="so_dien_thoai" required />
                    </div>
                    <div style="margin-top:14px;">
                        <label>Mô tả đơn hàng</label>
                        <textarea name="mo_ta" placeholder="Ví dụ: Đăng ký gói T.A.M Rehab"></textarea>
                    </div>
                    <div style="margin-top:16px;">
                        <button type="submit">Tạo đơn hàng</button>
                    </div>
                </form>
                <?php if ($notice !== ''): ?><div class="notice<?= strpos($notice, 'thành công') !== false ? '' : ' error' ?>"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
            </div>

            <div class="panel">
                <h2>Thông tin thanh toán</h2>
                <p><strong>Số tiền:</strong> 2.000đ</p>
                <?php if ($generatedOrderId): ?>
                    <p><strong>Mã đơn:</strong> <?= htmlspecialchars($generatedOrderId, ENT_QUOTES, 'UTF-8') ?></p>
                    <p><strong>Nội dung chuyển khoản:</strong> <?= htmlspecialchars($generatedContent, ENT_QUOTES, 'UTF-8') ?></p>
                    <img class="qr" src="https://img.vietqr.io/image/970422-123456789-compact.png?amount=2000&addInfo=<?= urlencode($generatedContent) ?>&accountName=<?= urlencode('TAM REHAB') ?>" alt="QR SePay" />
                <?php else: ?>
                    <p class="small">Sau khi tạo đơn, QR và nội dung chuyển khoản sẽ hiện ở đây.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
