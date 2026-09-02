<?php

declare(strict_types=1);

function tamrehab_db(): PDO
{
    if (!extension_loaded('pdo_sqlite') && !in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        throw new RuntimeException('PDO SQLite driver is missing. Enable pdo_sqlite in PHP or install the php-sqlite3 package.');
    }

    $dbPath = __DIR__ . DIRECTORY_SEPARATOR . 'brain.db';
    if (!file_exists($dbPath)) {
        touch($dbPath);
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
            product_id INTEGER,
            quantity INTEGER NOT NULL DEFAULT 1,
            amount REAL NOT NULL DEFAULT 0,
            content TEXT,
            status TEXT NOT NULL DEFAULT 'pending',
            paid_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS email_queue (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            customer_id INTEGER,
            email_type TEXT NOT NULL,
            to_email TEXT NOT NULL,
            subject TEXT NOT NULL,
            body TEXT NOT NULL,
            send_at DATETIME NOT NULL,
            status TEXT NOT NULL DEFAULT 'pending',
            sent_at DATETIME,
            provider_message_id TEXT,
            error_message TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )"
    );

    $tables = [
        'products' => ['name', 'product_type', 'price', 'description', 'stock_quantity', 'created_at'],
        'customers' => ['name', 'phone', 'email', 'zalo', 'registered_at', 'created_at'],
        'orders' => ['order_id', 'customer_name', 'phone', 'product_id', 'quantity', 'amount', 'content', 'status', 'paid_at', 'created_at'],
        'email_queue' => ['customer_id', 'email_type', 'to_email', 'subject', 'body', 'send_at', 'status', 'sent_at', 'provider_message_id', 'error_message', 'created_at'],
    ];

    foreach ($tables as $table => $columns) {
        $existing = array_column($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC), 'name');
        foreach ($columns as $column) {
            if (!in_array($column, $existing, true)) {
                $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . tamrehab_column_sql($table, $column));
            }
        }
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
        'customer_id' => 'INTEGER',
        'email_type' => 'TEXT NOT NULL',
        'to_email' => 'TEXT NOT NULL',
        'subject' => 'TEXT NOT NULL',
        'body' => 'TEXT NOT NULL',
        'send_at' => 'DATETIME NOT NULL',
        'sent_at' => 'DATETIME',
        'provider_message_id' => 'TEXT',
        'error_message' => 'TEXT',
    ];

    return $map[$column] ?? 'TEXT';
}
