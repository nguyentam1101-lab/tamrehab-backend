<?php

declare(strict_types=1);

function databasePath(): string
{
    $configuredPath = trim((string) (getenv('DB_PATH') ?: ''));
    if ($configuredPath !== '') {
        return $configuredPath;
    }

    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'brain.db';
}

function getDatabase(): PDO
{
    static $database;

    if ($database instanceof PDO) {
        return $database;
    }

    $path = databasePath();
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Không thể tạo thư mục dữ liệu.');
    }

    $database = new PDO('sqlite:' . $path);
    $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $database->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $database->exec('PRAGMA busy_timeout = 5000');
    $database->exec('PRAGMA foreign_keys = ON');
    initializeDatabase($database);

    return $database;
}

function initializeDatabase(PDO $database): void
{
    $database->exec(
        'CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL DEFAULT "",
            product_type TEXT NOT NULL DEFAULT "service",
            price REAL NOT NULL DEFAULT 0,
            description TEXT,
            stock_quantity INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )'
    );
    $database->exec(
        'CREATE TABLE IF NOT EXISTS customers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL DEFAULT "",
            phone TEXT,
            zalo TEXT,
            registered_at TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )'
    );
    $database->exec(
        'CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id TEXT UNIQUE,
            customer_name TEXT,
            phone TEXT,
            product_id INTEGER,
            quantity INTEGER NOT NULL DEFAULT 1,
            amount REAL NOT NULL DEFAULT 0,
            content TEXT,
            status TEXT NOT NULL DEFAULT "pending",
            paid_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )'
    );

    $columns = array_column($database->query('PRAGMA table_info(orders)')->fetchAll(), 'name');
    if (!in_array('customer_name', $columns, true)) {
        $database->exec('ALTER TABLE orders ADD COLUMN customer_name TEXT');
    }
    if (!in_array('phone', $columns, true)) {
        $database->exec('ALTER TABLE orders ADD COLUMN phone TEXT');
    }
    if (!in_array('product_id', $columns, true)) {
        $database->exec('ALTER TABLE orders ADD COLUMN product_id INTEGER');
    }
    if (!in_array('quantity', $columns, true)) {
        $database->exec('ALTER TABLE orders ADD COLUMN quantity INTEGER NOT NULL DEFAULT 1');
    }

    $customerColumns = array_column($database->query('PRAGMA table_info(customers)')->fetchAll(), 'name');
    if (!in_array('created_at', $customerColumns, true)) {
        $database->exec('ALTER TABLE customers ADD COLUMN created_at DATETIME');
    }
}

function jsonResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function allowCors(): void
{
    $origin = trim((string) (getenv('CORS_ORIGIN') ?: '*'));
    header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function requestJson(): array
{
    $decoded = json_decode(file_get_contents('php://input') ?: '', true);
    return is_array($decoded) ? $decoded : [];
}