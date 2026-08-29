<?php

declare(strict_types=1);

$db = new PDO('sqlite:' . __DIR__ . DIRECTORY_SEPARATOR . 'brain.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function generateOrderId(): string
{
    return 'TEST_' . date('Ymd_His');
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hoTen = trim((string) ($_POST['ho_ten'] ?? ''));
    $soDienThoai = trim((string) ($_POST['so_dien_thoai'] ?? ''));
    $moTa = trim((string) ($_POST['mo_ta'] ?? ''));

    if ($hoTen === '' || $soDienThoai === '') {
        $error = 'Vui lòng nhập họ tên và số điện thoại.';
    } else {
        $orderId = generateOrderId();
        $amount = 2000;
        $content = $orderId;

        try {
            $db->prepare('INSERT INTO orders (order_id, amount, content, status) VALUES (?, ?, ?, ?)')
                ->execute([$orderId, $amount, $content, 'pending']);

            $message = 'Tạo đơn hàng thành công.';
        } catch (Throwable $e) {
            $error = 'Không thể lưu đơn hàng: ' . $e->getMessage();
        }
    }
}

$orderId = $orderId ?? generateOrderId();
$amount = 2000;
$content = $orderId;
$qrUrl = 'https://vietqr.app/img?bank=BIDV&acc=96247TGLA3&template=compact&showinfo=true&holder=NGUYEN%20NGUYEN%20TAM';
$paymentPageUrl = 'https://www.tamrehab.com/thanh-toan.php';
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Thanh toán test SePay</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f7f3ee;
            margin: 0;
            padding: 32px 16px;
            color: #1f1a17;
        }
        .wrap {
            max-width: 900px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #e3d8c6;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.04);
        }
        h1 { margin-top: 0; }
        .grid { display: grid; grid-template-columns: 1fr 1.1fr; gap: 24px; }
        .field { margin-bottom: 14px; }
        label { display: block; font-weight: 700; margin-bottom: 6px; }
        input, textarea, button {
            width: 100%;
            box-sizing: border-box;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid #d6c3a2;
            font-size: 15px;
        }
        textarea { min-height: 100px; resize: vertical; }
        button {
            background: #1e2d2f;
            border: none;
            color: white;
            cursor: pointer;
            font-weight: 700;
        }
        .panel {
            border: 1px solid #e4d6bf;
            border-radius: 10px;
            background: #fffaf5;
            padding: 18px;
        }
        .muted { color: #665d55; }
        .qr { width: 280px; max-width: 100%; border: 4px solid #fff; border-radius: 10px; }
        .status { margin-top: 12px; padding: 10px 12px; border-radius: 8px; }
        .success { background: #eaf7eb; border: 1px solid #7dbb82; }
        .error { background: #fdeceb; border: 1px solid #d77970; }
        @media (max-width: 760px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>Test thanh toán SePay</h1>

        <div class="grid">
            <div>
                <form method="post">
                    <div class="field">
                        <label>Họ tên</label>
                        <input type="text" name="ho_ten" value="<?= h($_POST['ho_ten'] ?? '') ?>" required>
                    </div>
                    <div class="field">
                        <label>Số điện thoại</label>
                        <input type="text" name="so_dien_thoai" value="<?= h($_POST['so_dien_thoai'] ?? '') ?>" required>
                    </div>
                    <div class="field">
                        <label>Mô tả đơn hàng</label>
                        <textarea name="mo_ta" id="mo_ta"><?= h($_POST['mo_ta'] ?? '') ?></textarea>
                    </div>
                    <button type="submit">Tạo đơn hàng</button>
                </form>

                <?php if ($message): ?><div class="status success"><?= h($message) ?></div><?php endif; ?>
                <?php if ($error): ?><div class="status error"><?= h($error) ?></div><?php endif; ?>
            </div>

            <div class="panel">
                <h2>Thông tin thanh toán</h2>
                <p><strong>Số tiền:</strong> 2.000đ</p>
                <p><strong>Nội dung chuyển khoản:</strong> <span><?= h($content) ?></span></p>
                <p class="muted">Trang thanh toán: <?= h($paymentPageUrl) ?></p>
                <img class="qr" src="<?= h($qrUrl) ?>" alt="QR SePay">
            </div>
        </div>
    </div>

    <script>
        const formName = document.querySelector('input[name="ho_ten"]');
        const textarea = document.getElementById('mo_ta');
        const autoText = function () {
            const name = (formName?.value || '').trim();
            if (!name) {
                textarea.value = '';
                return;
            }
            textarea.value = 'TEST - ' + name;
        };
        formName?.addEventListener('input', autoText);
        if (!textarea.value.trim()) {
            autoText();
        }
    </script>
</body>
</html>
