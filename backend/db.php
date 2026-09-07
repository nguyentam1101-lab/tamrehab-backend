<?php

declare(strict_types=1);

// ---------------------------------------------------------------------------
// TursoStatement – PDOStatement-compatible wrapper around Libsql\Statement
// ---------------------------------------------------------------------------
class TursoStatement
{
    private ?array $rows = null;
    private int $affectedRows = 0;
    private array $boundParams = [];

    public function __construct(
        private readonly \Libsql\Statement $stmt,
        private readonly string $sql,
        private readonly \Libsql\Connection $conn,
    ) {}

    /** Accepts named (:key) or positional (?) param arrays */
    public function execute(?array $params = null): bool
    {
        $params = $params ?? $this->boundParams;
        $this->stmt->bind($params);

        // Decide SELECT vs write
        $isSelect = (bool) preg_match('/^\s*(SELECT|PRAGMA|WITH)\s/i', $this->sql);
        if ($isSelect) {
            $this->rows = $this->stmt->query()->fetchArray();
        } else {
            $this->affectedRows = $this->stmt->execute();
        }
        $this->stmt->reset();
        $this->boundParams = [];
        return true;
    }

    public function bindParam(string|int $param, mixed &$value): bool
    {
        $this->boundParams[$param] = $value;
        return true;
    }

    public function fetch(int $mode = PDO::FETCH_ASSOC): mixed
    {
        if (empty($this->rows)) {
            return false;
        }
        $row = array_shift($this->rows);
        return $this->mapFetch($row, $mode);
    }

    public function fetchAll(int $mode = PDO::FETCH_ASSOC): array
    {
        $result = array_map(fn($r) => $this->mapFetch($r, $mode), $this->rows ?? []);
        $this->rows = [];
        return $result;
    }

    public function fetchColumn(int $col = 0): mixed
    {
        $row = $this->fetch(PDO::FETCH_NUM);
        return $row[$col] ?? false;
    }

    public function rowCount(): int
    {
        return $this->affectedRows;
    }

    private function mapFetch(array $row, int $mode): mixed
    {
        return match ($mode) {
            PDO::FETCH_ASSOC, PDO::FETCH_NAMED, PDO::FETCH_DEFAULT => $row,
            PDO::FETCH_NUM  => array_values($row),
            PDO::FETCH_BOTH => array_merge($row, array_values($row)),
            PDO::FETCH_OBJ  => (object) $row,
            default         => $row,
        };
    }
}

// ---------------------------------------------------------------------------
// TursoPDO – PDO-compatible wrapper around Libsql\Connection
// ---------------------------------------------------------------------------
class TursoPDO
{
    private \Libsql\Connection $conn;
    private ?\Libsql\Transaction $tx = null;
    private bool $inTx = false;

    public function __construct(\Libsql\Connection $conn)
    {
        $this->conn = $conn;
    }

    public function setAttribute(int $attr, mixed $value): bool
    {
        return true; // no-op – Turso handles defaults
    }

    public function query(string $sql): TursoStatement
    {
        $stmt = $this->prepare($sql);
        $stmt->execute();
        return $stmt;
    }

    public function exec(string $sql): int|false
    {
        try {
            return ($this->inTx ? $this->tx : $this->conn)->execute($sql);
        } catch (\Throwable $e) {
            error_log('[TursoPDO::exec] ' . $e->getMessage());
            return false;
        }
    }

    public function prepare(string $sql): TursoStatement
    {
        $raw = ($this->inTx ? $this->tx : $this->conn)->prepare($sql);
        return new TursoStatement($raw, $sql, $this->conn);
    }

    public function beginTransaction(): bool
    {
        $this->tx   = $this->conn->transaction();
        $this->inTx = true;
        return true;
    }

    public function commit(): bool
    {
        $this->tx->commit();
        $this->inTx = false;
        $this->tx   = null;
        return true;
    }

    public function rollBack(): bool
    {
        $this->tx?->rollback();
        $this->inTx = false;
        $this->tx   = null;
        return true;
    }

    public function inTransaction(): bool
    {
        return $this->inTx;
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return (string) $this->conn->lastInsertId();
    }
}

// ---------------------------------------------------------------------------
// tamrehab_db() – public API (replaces old PDO SQLite version)
//
// Returns a TursoPDO that is 100% compatible with every ->prepare / ->exec /
// ->query / ->beginTransaction call already in the codebase.
// Falls back to native PDO SQLite if Turso credentials are absent.
// ---------------------------------------------------------------------------
function tamrehab_db(): TursoPDO|PDO
{
    // Try Turso first
    $configFile = __DIR__ . '/turso_config.txt';
    $config     = is_file($configFile) ? parse_ini_file($configFile) : [];
    $url        = trim((string) ($config['TURSO_DATABASE_URL'] ?? getenv('TURSO_DATABASE_URL') ?? ''));
    $authToken  = trim((string) ($config['TURSO_AUTH_TOKEN']   ?? getenv('TURSO_AUTH_TOKEN')   ?? ''));

    if ($url !== '' && $authToken !== '') {
        try {
            require_once __DIR__ . '/vendor/autoload.php';
            $db   = new \Libsql\Database(url: $url, authToken: $authToken);
            $conn = $db->connect();
            error_log('[db] Connected to Turso');
            return new TursoPDO($conn);
        } catch (\Throwable $e) {
            error_log('[db] Turso failed: ' . $e->getMessage() . ' – falling back to SQLite');
        }
    }

    // Fallback: local SQLite (same schema-init as before)
    $dbPath = __DIR__ . DIRECTORY_SEPARATOR . 'brain.db';
    if (!file_exists($dbPath)) {
        touch($dbPath);
    }
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    _tamrehab_ensure_schema($pdo);
    return $pdo;
}

function _tamrehab_ensure_schema(PDO $pdo): void
{
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
}

// Keep old helper name as alias
function tamrehab_column_sql(string $table, string $column): string
{
    $map = [
        'name'                 => 'TEXT',
        'product_type'         => 'TEXT NOT NULL DEFAULT "service"',
        'price'                => 'REAL NOT NULL DEFAULT 0',
        'description'          => 'TEXT',
        'stock_quantity'       => 'INTEGER',
        'created_at'           => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
        'phone'                => 'TEXT',
        'email'                => 'TEXT',
        'zalo'                 => 'TEXT',
        'registered_at'        => 'TEXT',
        'order_id'             => 'TEXT UNIQUE',
        'customer_name'        => 'TEXT',
        'product_id'           => 'INTEGER',
        'quantity'             => 'INTEGER NOT NULL DEFAULT 1',
        'amount'               => 'REAL NOT NULL DEFAULT 0',
        'content'              => 'TEXT',
        'status'               => 'TEXT NOT NULL DEFAULT "pending"',
        'paid_at'              => 'DATETIME',
        'customer_id'          => 'INTEGER',
        'email_type'           => 'TEXT NOT NULL',
        'to_email'             => 'TEXT NOT NULL',
        'subject'              => 'TEXT NOT NULL',
        'body'                 => 'TEXT NOT NULL',
        'send_at'              => 'DATETIME NOT NULL',
        'sent_at'              => 'DATETIME',
        'provider_message_id'  => 'TEXT',
        'error_message'        => 'TEXT',
    ];
    return $map[$column] ?? 'TEXT';
}
