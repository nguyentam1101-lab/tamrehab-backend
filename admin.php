<?php

declare(strict_types=1);

require_once __DIR__ . '/backend/db.php';
$db = tamrehab_db();

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function postString(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

$orderColumns = array_column($db->query('PRAGMA table_info(orders)')->fetchAll(), 'name');
if (!in_array('product_id', $orderColumns, true)) {
    $db->exec('ALTER TABLE orders ADD COLUMN product_id INTEGER');
}
if (!in_array('quantity', $orderColumns, true)) {
    $db->exec('ALTER TABLE orders ADD COLUMN quantity INTEGER NOT NULL DEFAULT 1');
}

$message = '';
$error = '';
$tab = postString('tab') ?: (string) ($_GET['tab'] ?? 'products');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = postString('action');
        $id = (int) ($_POST['id'] ?? 0);

        if ($action === 'save_product') {
            $name = postString('name');
            $type = postString('product_type');
            $price = (float) ($_POST['price'] ?? 0);
            $description = postString('description');
            $stock = $_POST['stock_quantity'] === '' ? null : (int) $_POST['stock_quantity'];

            if ($name === '' || !in_array($type, ['physical', 'digital', 'service'], true) || $price < 0 || ($type === 'physical' && $stock === null) || ($stock !== null && $stock < 0)) {
                throw new InvalidArgumentException('Thông tin sản phẩm không hợp lệ.');
            }

            if ($id > 0) {
                $statement = $db->prepare('UPDATE products SET name = ?, product_type = ?, price = ?, description = ?, stock_quantity = ? WHERE id = ?');
                $statement->execute([$name, $type, $price, $description ?: null, $stock, $id]);
            } else {
                $statement = $db->prepare('INSERT INTO products (name, product_type, price, description, stock_quantity) VALUES (?, ?, ?, ?, ?)');
                $statement->execute([$name, $type, $price, $description ?: null, $stock]);
            }
            $message = 'Đã lưu sản phẩm.';
            $tab = 'products';
        } elseif ($action === 'delete_product' && $id > 0) {
            $db->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
            $message = 'Đã xóa sản phẩm.';
            $tab = 'products';
        } elseif ($action === 'save_customer') {
            $name = postString('name');
            $phone = postString('phone') ?: null;
            $email = postString('email') ?: null;
            $zalo = postString('zalo') ?: null;
            $registeredAt = postString('registered_at') ?: null;

            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Email không hợp lệ.');
            }

            if ($name === '') {
                throw new InvalidArgumentException('Tên khách hàng không được để trống.');
            }

            if ($id > 0) {
                $statement = $db->prepare('UPDATE customers SET name = ?, phone = ?, email = ?, zalo = ?, registered_at = ? WHERE id = ?');
                $statement->execute([$name, $phone, $email !== '' ? $email : null, $zalo, $registeredAt, $id]);
            } else {
                $statement = $db->prepare('INSERT INTO customers (name, phone, email, zalo, registered_at) VALUES (?, ?, ?, ?, ?)');
                $statement->execute([$name, $phone, $email !== '' ? $email : null, $zalo, $registeredAt]);
            }
            $message = 'Đã lưu khách hàng.';
            $tab = 'customers';
        } elseif ($action === 'delete_customer' && $id > 0) {
            $db->prepare('DELETE FROM customers WHERE id = ?')->execute([$id]);
            $message = 'Đã xóa khách hàng.';
            $tab = 'customers';
        } elseif ($action === 'save_order') {
            $orderId = postString('order_id');
            $productId = (int) ($_POST['product_id'] ?? 0);
            $quantity = (int) ($_POST['quantity'] ?? 1);
            $amount = (float) ($_POST['amount'] ?? 0);
            $content = postString('content');
            $status = postString('status') ?: 'pending';
            $customerName = postString('customer_name');
            $phone = postString('phone');
            $email = postString('email');

            if ($orderId === '' || $productId < 1 || $quantity < 1 || $amount < 0 || !in_array($status, ['pending', 'success'], true)) {
                throw new InvalidArgumentException('Thông tin đơn hàng không hợp lệ.');
            }

            $db->beginTransaction();
            $product = $db->prepare('SELECT id, product_type, stock_quantity FROM products WHERE id = ?');
            $product->execute([$productId]);
            $row = $product->fetch();
            if (!$row) {
                throw new InvalidArgumentException('Không tìm thấy sản phẩm.');
            }

            if ($id > 0) {
                $statement = $db->prepare('UPDATE orders SET order_id = ?, amount = ?, content = ?, status = ?, product_id = ?, quantity = ?, customer_name = ?, phone = ?, email = ? WHERE id = ?');
                $statement->execute([$orderId, $amount, $content, $status, $productId, $quantity, $customerName ?: null, $phone ?: null, $email ?: null, $id]);
            } else {
                if ($row['product_type'] === 'physical') {
                    if ($row['stock_quantity'] === null || (int) $row['stock_quantity'] < $quantity) {
                        throw new InvalidArgumentException('Số lượng tồn kho không đủ.');
                    }
                    $stockUpdate = $db->prepare('UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND stock_quantity >= ?');
                    $stockUpdate->execute([$quantity, $productId, $quantity]);
                    if ($stockUpdate->rowCount() !== 1) {
                        throw new InvalidArgumentException('Không thể trừ tồn kho.');
                    }
                }
                $statement = $db->prepare('INSERT INTO orders (order_id, amount, content, status, product_id, quantity, customer_name, phone, email) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $statement->execute([$orderId, $amount, $content, $status, $productId, $quantity, $customerName ?: null, $phone ?: null, $email ?: null]);
            }

            $db->commit();
            $message = 'Đã lưu đơn hàng.';
            if ($id === 0) {
                require_once __DIR__ . '/backend/mailer.php';
                $emailResult = tamrehab_send_order_confirmation($db, $orderId);
                $message .= !empty($emailResult['success'])
                    ? ' Đã gửi email xác nhận.'
                    : ' Email chưa gửi: ' . ($emailResult['message'] ?? 'lỗi không xác định');
            }
            $tab = 'orders';
        } elseif ($action === 'delete_order' && $id > 0) {
            $db->prepare('DELETE FROM orders WHERE id = ?')->execute([$id]);
            $message = 'Đã xóa đơn hàng.';
            $tab = 'orders';
        }
    }
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    $error = $exception instanceof PDOException && $exception->getCode() === '23000'
        ? 'Dữ liệu bị trùng hoặc đang được tham chiếu.'
        : $exception->getMessage();
}

$products = $db->query('SELECT * FROM products ORDER BY id DESC')->fetchAll();
$customers = $db->query('SELECT * FROM customers ORDER BY id DESC')->fetchAll();
$orders = $db->query('SELECT orders.*, products.name AS product_name FROM orders LEFT JOIN products ON products.id = orders.product_id ORDER BY orders.id DESC')->fetchAll();
$editId = (int) ($_GET['edit'] ?? 0);
$editProduct = null;
if ($tab === 'products' && $editId > 0) {
    $statement = $db->prepare('SELECT * FROM products WHERE id = ?');
    $statement->execute([$editId]);
    $editProduct = $statement->fetch() ?: null;
}
$editCustomer = null;
if ($tab === 'customers' && $editId > 0) {
    $statement = $db->prepare('SELECT * FROM customers WHERE id = ?');
    $statement->execute([$editId]);
    $editCustomer = $statement->fetch() ?: null;
}
$editOrder = null;
if ($tab === 'orders' && $editId > 0) {
    $statement = $db->prepare('SELECT * FROM orders WHERE id = ?');
    $statement->execute([$editId]);
    $editOrder = $statement->fetch() ?: null;
}
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin - T.A.M REHAB</title>
    <style>
        :root { font-family: 'Segoe UI', Arial, sans-serif; color: #29251f; background: #f4efe6; }
        body { max-width: 1200px; margin: 0 auto; padding: 24px; }
        .header { display: flex; align-items: center; gap: 14px; padding-bottom: 18px; border-bottom: 2px solid #e9dcc0; margin-bottom: 18px; }
        .logo { width: 48px; height: 48px; background: #4a3524; color: #fff; border-radius: 10px; display: grid; place-items: center; font-weight: 800; font-size: 16px; letter-spacing: 1px; }
        .brand h1 { margin: 0; color: #4a3524; font-size: 22px; font-weight: 800; }
        .brand .subtitle { margin: 2px 0 0; color: #756a5d; font-size: 13px; }
        h1 { margin: 0 0 18px; }
        nav { display: flex; gap: 8px; margin-bottom: 18px; flex-wrap: wrap; }
        nav a, button { border: 1px solid #9d7b49; background: #fffaf2; color: inherit; padding: 9px 13px; border-radius: 5px; text-decoration: none; cursor: pointer; }
        nav a.active, button.primary { background: #4c8b45; color: white; border-color: #4c8b45; }
        nav a:hover { background: #f0e4cc; }
        nav a.active:hover { background: #3d7238; }
        .notice { padding: 10px 12px; margin: 10px 0; background: #e2f1df; border-left: 4px solid #4c8b45; }
        .error { background: #fae2df; border-color: #ae4035; }
        .layout { display: grid; grid-template-columns: minmax(260px, 330px) 1fr; gap: 20px; align-items: start; }
        form, table { background: #fffdf9; border: 1px solid #dbcdb9; border-radius: 6px; }
        form { padding: 16px; }
        form h2 { margin: 0 0 10px; color: #4a3524; font-size: 18px; }
        label { display: block; margin: 10px 0 5px; font-weight: bold; font-size: 14px; }
        input, select, textarea { box-sizing: border-box; width: 100%; padding: 9px; border: 1px solid #cbbba4; border-radius: 4px; background: white; }
        textarea { min-height: 80px; resize: vertical; }
        .actions { display: flex; gap: 7px; margin-top: 14px; flex-wrap: wrap; }
        table { width: 100%; border-collapse: collapse; overflow: hidden; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #eadfce; vertical-align: top; }
        th { background: #eee4d5; font-size: 13px; }
        td form { border: 0; padding: 0; background: transparent; }
        .muted { color: #756a5d; }
        footer { text-align: center; color: #756a5d; font-size: 13px; padding: 20px 0 8px; border-top: 1px solid #e9dcc0; margin-top: 28px; }
        @media (max-width: 760px) { body { padding: 14px; } .layout { grid-template-columns: 1fr; } table { display: block; overflow-x: auto; white-space: nowrap; } .brand h1 { font-size: 18px; } }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">TAM</div>
        <div class="brand">
            <h1>T.A.M REHAB - Quản trị hệ thống</h1>
            <p class="subtitle">Dịch vụ giãn cơ trị liệu chuyên sâu</p>
        </div>
    </div>
    <nav>
        <?php foreach (['products' => 'Sản phẩm', 'customers' => 'Khách hàng', 'orders' => 'Đơn hàng'] as $key => $label): ?>
            <a class="<?= $tab === $key ? 'active' : '' ?>" href="?tab=<?= $key ?>"><?= h($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <?php if ($message): ?><div class="notice"><?= h($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="notice error"><?= h($error) ?></div><?php endif; ?>

    <?php if ($tab === 'products'): ?>
        <div class="layout">
            <form method="post">
                <h2><?= $editProduct ? 'Chỉnh sửa sản phẩm' : 'Thêm sản phẩm' ?></h2>
                <input type="hidden" name="action" value="save_product"><input type="hidden" name="tab" value="products"><input type="hidden" name="id" value="<?= h((string) ($editProduct['id'] ?? 0)) ?>">
                <label>Tên sản phẩm</label><input name="name" value="<?= h($editProduct['name'] ?? '') ?>" required>
                <label>Loại</label><select name="product_type" id="product_type"><?php foreach (['physical', 'digital', 'service'] as $type): ?><option value="<?= $type ?>" <?= ($editProduct['product_type'] ?? 'service') === $type ? 'selected' : '' ?>><?= $type ?></option><?php endforeach; ?></select>
                <label>Giá</label><input type="number" name="price" value="<?= h((string) ($editProduct['price'] ?? '')) ?>" min="0" step="0.01" required>
                <label>Mô tả</label><textarea name="description"><?= h($editProduct['description'] ?? '') ?></textarea>
                <label>Số lượng tồn</label><input type="number" name="stock_quantity" value="<?= h((string) ($editProduct['stock_quantity'] ?? '')) ?>" id="stock_quantity" min="0"><small class="muted">Bắt buộc với physical.</small>
                <div class="actions"><button class="primary"><?= $editProduct ? 'Lưu thay đổi' : 'Thêm mới' ?></button><?php if ($editProduct): ?><a href="?tab=products">Hủy</a><?php endif; ?></div>
            </form>
            <table><tr><th>Tên</th><th>Loại</th><th>Giá</th><th>Tồn</th><th></th></tr>
                <?php foreach ($products as $product): ?><tr><td><?= h($product['name']) ?><br><small><?= h($product['description']) ?></small></td><td><?= h($product['product_type']) ?></td><td><?= number_format((float) $product['price'], 0, ',', '.') ?></td><td><?= $product['stock_quantity'] === null ? 'Không áp dụng' : h((string) $product['stock_quantity']) ?></td><td><a href="?tab=products&edit=<?= $product['id'] ?>">Sửa</a> <form method="post"><input type="hidden" name="action" value="delete_product"><input type="hidden" name="tab" value="products"><input type="hidden" name="id" value="<?= h((string) $product['id']) ?>"><?= h($product['id']) ?><button onclick="return confirm('Xóa sản phẩm này?')">Xóa</button></form></td></tr><?php endforeach; ?>
            </table>
        </div>
    <?php elseif ($tab === 'customers'): ?>
        <div class="layout">
            <form method="post"><h2><?= $editCustomer ? 'Chỉnh sửa khách hàng' : 'Thêm khách hàng' ?></h2><input type="hidden" name="action" value="save_customer"><input type="hidden" name="tab" value="customers"><input type="hidden" name="id" value="<?= h((string) ($editCustomer['id'] ?? 0)) ?>"><label>Tên</label><input name="name" value="<?= h($editCustomer['name'] ?? '') ?>" required><label>Số điện thoại</label><input name="phone" value="<?= h($editCustomer['phone'] ?? '') ?>"><label>Email</label><input name="email" type="email" value="<?= h($editCustomer['email'] ?? '') ?>"><label>Zalo</label><input name="zalo" value="<?= h($editCustomer['zalo'] ?? '') ?>"><label>Ngày đăng ký</label><input type="date" name="registered_at" value="<?= h($editCustomer['registered_at'] ?? '') ?>"><div class="actions"><button class="primary"><?= $editCustomer ? 'Lưu thay đổi' : 'Thêm mới' ?></button><?php if ($editCustomer): ?><a href="?tab=customers">Hủy</a><?php endif; ?></div></form>
            <table><tr><th>Tên</th><th>Điện thoại</th><th>Email</th><th>Zalo</th><th>Ngày đăng ký</th><th></th></tr><?php foreach ($customers as $customer): ?><tr><td><?= h($customer['name']) ?></td><td><?= h($customer['phone']) ?></td><td><?= h($customer['email'] ?? '') ?></td><td><?= h($customer['zalo']) ?></td><td><?= h($customer['registered_at']) ?></td><td><a href="?tab=customers&edit=<?= $customer['id'] ?>">Sửa</a> <form method="post"><input type="hidden" name="action" value="delete_customer"><input type="hidden" name="tab" value="customers"><input type="hidden" name="id" value="<?= h((string) $customer['id']) ?>"><button onclick="return confirm('Xóa khách hàng này?')">Xóa</button></form></td></tr><?php endforeach; ?></table>
        </div>
    <?php else: ?>
        <div class="layout">
            <form method="post"><h2><?= $editOrder ? 'Chỉnh sửa đơn hàng' : 'Thêm đơn hàng' ?></h2><input type="hidden" name="action" value="save_order"><input type="hidden" name="tab" value="orders"><input type="hidden" name="id" value="<?= h((string) ($editOrder['id'] ?? 0)) ?>"><label>Mã đơn hàng</label><input name="order_id" value="<?= h($editOrder['order_id'] ?? '') ?>" required><label>Sản phẩm/dịch vụ</label><select name="product_id" required><option value="">Chọn</option><?php foreach ($products as $product): ?><option value="<?= h((string) $product['id']) ?>" <?= (int) ($editOrder['product_id'] ?? 0) === (int) $product['id'] ? 'selected' : '' ?>><?= h($product['name']) ?> (<?= h($product['product_type']) ?>)</option><?php endforeach; ?></select><label>Số lượng</label><input type="number" name="quantity" value="<?= h((string) ($editOrder['quantity'] ?? 1)) ?>" min="1" required><label>Số tiền</label><input type="number" name="amount" value="<?= h((string) ($editOrder['amount'] ?? '')) ?>" min="0" step="0.01" required><label>Nội dung</label><textarea name="content"><?= h($editOrder['content'] ?? '') ?></textarea><label>Trạng thái</label><select name="status"><option value="pending" <?= ($editOrder['status'] ?? 'pending') === 'pending' ? 'selected' : '' ?>>pending</option><option value="success" <?= ($editOrder['status'] ?? '') === 'success' ? 'selected' : '' ?>>success</option></select><div class="actions"><button class="primary"><?= $editOrder ? 'Lưu thay đổi' : 'Thêm đơn' ?></button><?php if ($editOrder): ?><a href="?tab=orders">Hủy</a><?php endif; ?></div></form>
            <table><tr><th>Mã đơn</th><th>Sản phẩm</th><th>SL</th><th>Số tiền</th><th>Trạng thái</th><th></th></tr><?php foreach ($orders as $order): ?><tr><td><?= h($order['order_id']) ?><br><small><?= h($order['content']) ?></small></td><td><?= h($order['product_name'] ?: 'Không xác định') ?></td><td><?= h((string) $order['quantity']) ?></td><td><?= number_format((float) $order['amount'], 0, ',', '.') ?></td><td><?= h($order['status']) ?></td><td><a href="?tab=orders&edit=<?= $order['id'] ?>">Sửa</a> <form method="post"><input type="hidden" name="action" value="delete_order"><input type="hidden" name="tab" value="orders"><input type="hidden" name="id" value="<?= h((string) $order['id']) ?>"><button onclick="return confirm('Xóa đơn hàng này?')">Xóa</button></form></td></tr><?php endforeach; ?></table>
        </div>
    <?php endif; ?>
    <script>const type = document.getElementById('product_type'); const stock = document.getElementById('stock_quantity'); function updateStock() { const physical = type && type.value === 'physical'; if (stock) { stock.required = physical; stock.disabled = !physical; if (!physical) stock.value = ''; } } if (type) { type.addEventListener('change', updateStock); updateStock(); }</script>
    <footer>&copy; 2026 T.A.M REHAB - Dịch vụ giãn cơ trị liệu chuyên sâu</footer>
</body>
</html>
