# TAM REHAB - SePay + SQLite Project

## Cấu trúc dự án

- `frontend/`: các trang HTML tĩnh chạy trên Vercel
- `backend/`: API PHP + SQLite + webhook chạy trên Render

## Mục tiêu

- Frontend tĩnh, nhẹ, dễ deploy trên Vercel
- Backend PHP xử lý tạo đơn hàng, CRUD admin và webhook SePay
- SQLite lưu trữ `products`, `customers`, `orders`

## Chạy local

```bash
php -S 0.0.0.0:10000 -t backend/
```

Sau đó truy cập:

- Frontend: `http://localhost:5500/frontend/thanh-toan.html`
- Backend: `http://localhost:10000/api/products.php`

## Cấu hình deploy

### Vercel
- Build command: bỏ trống hoặc `npm run build` nếu dùng framework
- Output directory: `frontend`
- Publish static files trong `frontend/`

### Render
- Runtime: PHP
- Build command: `mkdir -p public && cp -r backend/* public/`
- Start command: `php -S 0.0.0.0:10000 -t public`
- Hoặc `php -S 0.0.0.0:10000 -t backend/` nếu deploy trực tiếp thư mục backend

## DB schema

```sql
CREATE TABLE IF NOT EXISTS products (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT,
  product_type TEXT,
  price REAL,
  description TEXT,
  stock_quantity INTEGER,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS customers (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT,
  phone TEXT,
  zalo TEXT,
  registered_at TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS orders (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  order_id TEXT UNIQUE,
  customer_name TEXT,
  phone TEXT,
  product_id INTEGER,
  quantity INTEGER DEFAULT 1,
  amount REAL,
  content TEXT,
  status TEXT DEFAULT 'pending',
  paid_at DATETIME,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

## SePay webhook

Webhook URL nên trỏ đến:

```text
https://your-backend-domain.com/webhook-sepay.php
```

Phương thức yêu cầu: `POST`

Dữ liệu JSON có thể chứa các trường như:

```json
{
  "order_id": "TAM-20240829-123",
  "amount": 2000,
  "content": "TAMPAY123",
  "code": "00"
}
```

Webhook sẽ cập nhật order từ `pending` thành `success` nếu khớp.

## Ghi chú

- `brain.db` không nên được commit lên repo nếu dự án đang ở môi trường production.
- Nếu cần, bạn có thể import dữ liệu từ markdown hiện có vào SQLite bằng script riêng.
