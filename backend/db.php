<?php

declare(strict_types=1);

function tamrehab_db(): PDO
{
    if (!extension_loaded('pdo_sqlite') && !in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        throw new RuntimeException('PDO SQLite driver is missing. Enable pdo_sqlite in PHP or install the php-sqlite3 package.');
    }

    $dbPath = getenv('DATABASE_PATH') ?: ($_ENV['DATABASE_PATH'] ?? null);
    if (!$dbPath) {
        if (file_exists('/data') && is_dir('/data')) {
            $dbPath = '/data/brain.db';
        } else {
            $dbPath = __DIR__ . DIRECTORY_SEPARATOR . 'brain.db';
        }
    }

    $dir = dirname($dbPath);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    if (!file_exists($dbPath)) {
        @touch($dbPath);
    }

    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            product_type TEXT NOT NULL DEFAULT 'service',
            price REAL NOT NULL DEFAULT 0,
            description TEXT,
            stock_quantity INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS customers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            phone TEXT,
            email TEXT,
            zalo TEXT,
            registered_at TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id TEXT UNIQUE,
            customer_name TEXT,
            phone TEXT,
            email TEXT,
            product_id INTEGER,
            quantity INTEGER NOT NULL DEFAULT 1,
            amount REAL NOT NULL DEFAULT 0,
            content TEXT,
            status TEXT NOT NULL DEFAULT 'pending',
            paid_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )"
    );

    $tables = [
        'products' => ['name', 'product_type', 'price', 'description', 'stock_quantity', 'created_at'],
        'customers' => ['name', 'phone', 'email', 'zalo', 'registered_at', 'created_at'],
        'orders' => ['order_id', 'customer_name', 'phone', 'email', 'product_id', 'quantity', 'amount', 'content', 'status', 'paid_at', 'created_at'],
    ];

    foreach ($tables as $table => $columns) {
        $existing = array_column($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC), 'name');
        foreach ($columns as $column) {
            if (!in_array($column, $existing, true)) {
                $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . tamrehab_column_sql($table, $column));
            }
        }
    }

    $productCount = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
    if ($productCount === 0) {
        $pdo->exec("INSERT INTO products (id, name, product_type, price, description, stock_quantity) VALUES 
            (1, 'Buổi giãn cơ trị liệu chuyên sâu', 'service', 350000, 'Liệu trình giãn cơ chuyên sâu 60 phút giúp giảm căng cơ và phục hồi vận động.', NULL)");
    }

    return $pdo;
}

function tamrehab_column_sql(string $table, string $column): string
{
    $map = [
        'name' => 'TEXT',
        'product_type' => 'TEXT NOT NULL DEFAULT "service"',
        'price' => 'REAL NOT NULL DEFAULT 0',
        'description' => 'TEXT',
        'stock_quantity' => 'INTEGER',
        'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
        'phone' => 'TEXT',
        'email' => 'TEXT',
        'zalo' => 'TEXT',
        'registered_at' => 'TEXT',
        'order_id' => 'TEXT UNIQUE',
        'customer_name' => 'TEXT',
        'product_id' => 'INTEGER',
        'quantity' => 'INTEGER NOT NULL DEFAULT 1',
        'amount' => 'REAL NOT NULL DEFAULT 0',
        'content' => 'TEXT',
        'status' => 'TEXT NOT NULL DEFAULT "pending"',
        'paid_at' => 'DATETIME',
    ];

    return $map[$column] ?? 'TEXT';
}
