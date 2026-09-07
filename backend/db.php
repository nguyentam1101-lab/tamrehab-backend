<?php

declare(strict_types=1);

// ---------------------------------------------------------------------------
// TamrehabStatement – plain wrapper around \Libsql\Statement
//
// Does NOT extend \PDOStatement (which cannot be directly instantiated).
// Provides the same interface used throughout the codebase:
//   execute(), fetch(), fetchAll(), fetchColumn(), rowCount(), setFetchMode()
//
// The SELECT path is always forced via regex so remote Turso works correctly
// (columnCount() always returns 0 on remote connections before execution).
// ---------------------------------------------------------------------------
class TamrehabStatement
{
    private ?array $rows    = null;
    private int    $affected = 0;
    private int    $mode    = \PDO::FETCH_ASSOC;
    private array  $bound   = [];

    public function __construct(
        private readonly \Libsql\Statement $stmt,
        private readonly string            $sql,
    ) {}

    public function setFetchMode(int $mode, mixed ...$args): bool
    {
        $this->mode = $mode;
        return true;
    }

    public function execute(?array $params = null): bool
    {
        $p = $params ?? $this->bound;
        if (count($p) > 0) {
            $this->stmt->bind($p);
        }

        $isSelect = (bool) preg_match('/^\s*(SELECT|PRAGMA|WITH)\s/i', $this->sql);
        if ($isSelect) {
            $this->rows     = $this->stmt->query()->fetchArray();
            $this->affected = 0;
        } else {
            $this->affected = (int) $this->stmt->execute();
            $this->rows     = null;
        }

        $this->stmt->reset();
        $this->bound = [];
        return true;
    }

    public function bindParam(string|int $param, mixed &$value): bool
    {
        $this->bound[$param] = $value;
        return true;
    }

    public function fetch(int $mode = \PDO::FETCH_DEFAULT): mixed
    {
        if (empty($this->rows)) {
            return false;
        }
        $row = array_shift($this->rows);
        return $this->mapRow($row, $mode === \PDO::FETCH_DEFAULT ? $this->mode : $mode);
    }

    public function fetchAll(int $mode = \PDO::FETCH_DEFAULT): array
    {
        $m      = $mode === \PDO::FETCH_DEFAULT ? $this->mode : $mode;
        $result = array_map(fn($r) => $this->mapRow($r, $m), $this->rows ?? []);
        $this->rows = [];
        return $result;
    }

    public function fetchColumn(int $col = 0): mixed
    {
        $row = $this->fetch(\PDO::FETCH_NUM);
        return is_array($row) ? ($row[$col] ?? false) : false;
    }

    public function rowCount(): int
    {
        return $this->affected;
    }

    private function mapRow(array $row, int $mode): mixed
    {
        return match ($mode) {
            \PDO::FETCH_ASSOC,
            \PDO::FETCH_NAMED,
            \PDO::FETCH_DEFAULT => $row,
            \PDO::FETCH_NUM     => array_values($row),
            \PDO::FETCH_BOTH    => array_merge($row, array_values($row)),
            \PDO::FETCH_OBJ     => (object) $row,
            default             => $row,
        };
    }
}

// ---------------------------------------------------------------------------
// TamrehabPDO – thin wrapper around \Libsql\Connection
//
// Provides PDO-compatible API (prepare, query, exec, beginTransaction, etc.)
// without extending \PDO at all, avoiding the constructor/dsn issues.
// ---------------------------------------------------------------------------
class TamrehabPDO
{
    private \Libsql\Connection   $conn;
    private ?\Libsql\Transaction $tx    = null;
    private bool                 $inTx  = false;

    public function __construct(\Libsql\Connection $conn)
    {
        $this->conn = $conn;
    }

    public function prepare(string $sql): TamrehabStatement
    {
        $raw = ($this->inTx ? $this->tx : $this->conn)->prepare($sql);
        return new TamrehabStatement($raw, $sql);
    }

    public function query(string $sql): TamrehabStatement
    {
        $stmt = $this->prepare($sql);
        $stmt->execute([]);
        return $stmt;
    }

    public function exec(string $sql): int|false
    {
        try {
            return (int) ($this->inTx ? $this->tx : $this->conn)->execute($sql);
        } catch (\Throwable $e) {
            error_log('[TamrehabPDO::exec] ' . $e->getMessage());
            return false;
        }
    }

    public function beginTransaction(): bool
    {
        $this->tx   = $this->conn->transaction();
        $this->inTx = true;
        return true;
    }

    public function commit(): bool
    {
        $this->tx?->commit();
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

    // setAttribute is a no-op — kept for interface compatibility
    public function setAttribute(int $attr, mixed $value): bool
    {
        return true;
    }
}

// ---------------------------------------------------------------------------
// tamrehab_db() – public API
//
// Returns TamrehabPDO (Turso remote) if credentials are present,
// otherwise native \PDO with local SQLite as fallback.
// ---------------------------------------------------------------------------
function tamrehab_db(): TamrehabPDO|\PDO
{
    $configFile = __DIR__ . '/turso_config.txt';
    $config     = is_file($configFile) ? parse_ini_file($configFile) : [];
    $url        = trim((string) ($config['TURSO_DATABASE_URL'] ?? getenv('TURSO_DATABASE_URL') ?? ''));
    $authToken  = trim((string) ($config['TURSO_AUTH_TOKEN']   ?? getenv('TURSO_AUTH_TOKEN')   ?? ''));

    if ($url !== '' && $authToken !== '') {
        if (!extension_loaded('ffi')) {
            error_log('[db] FFI extension not available – skipping Turso, using SQLite fallback');
        } else {
            try {
                require_once __DIR__ . '/vendor/autoload.php';
                $libDb = new \Libsql\Database(url: $url, authToken: $authToken);
                $conn  = $libDb->connect();
                error_log('[db] Connected to Turso');
                return new TamrehabPDO($conn);
            } catch (\Throwable $e) {
                error_log('[db] Turso failed: ' . $e->getMessage() . ' – falling back to SQLite');
            }
        }
    }

    // Fallback: local SQLite
    $dbPath = __DIR__ . DIRECTORY_SEPARATOR . 'brain.db';
    if (!file_exists($dbPath)) {
        touch($dbPath);
    }
    $pdo = new \PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
    _tamrehab_ensure_schema($pdo);
    error_log('[db] Using local SQLite fallback');
    return $pdo;
}

function _tamrehab_ensure_schema(\PDO $pdo): void
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

function tamrehab_column_sql(string $table, string $column): string
{
    $map = [
        'name'                => 'TEXT',
        'product_type'        => 'TEXT NOT NULL DEFAULT "service"',
        'price'               => 'REAL NOT NULL DEFAULT 0',
        'description'         => 'TEXT',
        'stock_quantity'      => 'INTEGER',
        'created_at'          => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
        'phone'               => 'TEXT',
        'email'               => 'TEXT',
        'zalo'                => 'TEXT',
        'registered_at'       => 'TEXT',
        'order_id'            => 'TEXT UNIQUE',
        'customer_name'       => 'TEXT',
        'product_id'          => 'INTEGER',
        'quantity'            => 'INTEGER NOT NULL DEFAULT 1',
        'amount'              => 'REAL NOT NULL DEFAULT 0',
        'content'             => 'TEXT',
        'status'              => 'TEXT NOT NULL DEFAULT "pending"',
        'paid_at'             => 'DATETIME',
        'customer_id'         => 'INTEGER',
        'email_type'          => 'TEXT NOT NULL',
        'to_email'            => 'TEXT NOT NULL',
        'subject'             => 'TEXT NOT NULL',
        'body'                => 'TEXT NOT NULL',
        'send_at'             => 'DATETIME NOT NULL',
        'sent_at'             => 'DATETIME',
        'provider_message_id' => 'TEXT',
        'error_message'       => 'TEXT',
    ];
    return $map[$column] ?? 'TEXT';
}
