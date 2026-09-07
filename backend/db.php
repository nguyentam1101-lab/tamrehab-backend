<?php

declare(strict_types=1);

// ---------------------------------------------------------------------------
// TamrehabPDO – thin subclass of \Libsql\PDO that fixes query() for remote
//
// Problem: \Libsql\PDOStatement::execute() uses columnCount() to decide if a
// query is a SELECT. On remote Turso connections columnCount() always returns
// 0 before execution, so every query is treated as a write and rows are never
// fetched. We fix this by overriding query() and prepare() to always force the
// SELECT path via a regex check on the SQL string (same logic as the old
// TursoStatement wrapper, but now using the SDK's own PDOStatement).
// ---------------------------------------------------------------------------
class TamrehabPDO extends \Libsql\PDO
{
    /**
     * Execute a query and always return results for SELECT/PRAGMA/WITH.
     * Falls back to parent for write statements.
     */
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false
    {
        $stmt = $this->prepare($query);
        if ($stmt === false) {
            return false;
        }
        $stmt->execute([]);
        if ($fetchMode !== null) {
            $stmt->setFetchMode($fetchMode, ...$fetchModeArgs);
        }
        return $stmt;
    }

    /**
     * prepare() – returns a \Libsql\PDOStatement but wraps it so that
     * execute() always takes the SELECT path for SELECT/PRAGMA/WITH queries,
     * regardless of what columnCount() says on remote connections.
     */
    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        /** @var \Libsql\PDOStatement $stmt */
        $stmt = parent::prepare($query, $options);
        if ($stmt === false) {
            return false;
        }

        // Patch the statement so it always uses the query (SELECT) path for
        // SELECT / PRAGMA / WITH, bypassing the broken columnCount() check.
        $isSelect = (bool) preg_match('/^\s*(SELECT|PRAGMA|WITH)\s/i', $query);
        if ($isSelect) {
            // Wrap in our thin shim via anonymous class extension
            return new class($stmt, $query) extends \Libsql\PDOStatement {
                private \Libsql\PDOStatement $inner;
                private string $sql;

                public function __construct(\Libsql\PDOStatement $inner, string $sql)
                {
                    $this->inner = $inner;
                    $this->sql   = $sql;
                }

                public function execute(?array $params = null): bool
                {
                    // Access the underlying \Libsql\Statement via reflection
                    // and call query() directly to force the SELECT path.
                    $ref  = new \ReflectionObject($this->inner);
                    $prop = $ref->getProperty('statement');
                    $prop->setAccessible(true);
                    /** @var \Libsql\Statement $raw */
                    $raw = $prop->getValue($this->inner);

                    if ($params !== null && count($params) > 0) {
                        $raw->bind($params);
                    }

                    $rows = $raw->query()->fetchArray();

                    $rowsProp = $ref->getProperty('rows');
                    $rowsProp->setAccessible(true);
                    $rowsProp->setValue($this->inner, $rows);

                    return true;
                }

                public function fetch(int $mode = \PDO::FETCH_DEFAULT, ...$args): mixed
                {
                    return $this->inner->fetch($mode, ...$args);
                }

                public function fetchAll(int $mode = \PDO::FETCH_DEFAULT, ...$args): array
                {
                    return $this->inner->fetchAll($mode, ...$args);
                }

                public function fetchColumn(int $column = 0): mixed
                {
                    $row = $this->fetch(\PDO::FETCH_NUM);
                    return $row[$column] ?? false;
                }

                public function rowCount(): int
                {
                    return $this->inner->rowCount();
                }

                public function setFetchMode(int $mode, mixed ...$args): bool
                {
                    return $this->inner->setFetchMode($mode, ...$args);
                }
            };
        }

        return $stmt;
    }
}

// ---------------------------------------------------------------------------
// tamrehab_db() – public API
//
// Returns TamrehabPDO (Turso) if credentials are present, otherwise native
// PDO SQLite as fallback.
// ---------------------------------------------------------------------------
function tamrehab_db(): TamrehabPDO|\PDO
{
    $configFile = __DIR__ . '/turso_config.txt';
    $config     = is_file($configFile) ? parse_ini_file($configFile) : [];
    $url        = trim((string) ($config['TURSO_DATABASE_URL'] ?? getenv('TURSO_DATABASE_URL') ?? ''));
    $authToken  = trim((string) ($config['TURSO_AUTH_TOKEN']   ?? getenv('TURSO_AUTH_TOKEN')   ?? ''));

    if ($url !== '' && $authToken !== '') {
        try {
            require_once __DIR__ . '/vendor/autoload.php';
            $db = new TamrehabPDO(
                options: ['url' => $url],
                password: $authToken,
            );
            error_log('[db] Connected to Turso via TamrehabPDO');
            return $db;
        } catch (\Throwable $e) {
            error_log('[db] Turso failed: ' . $e->getMessage() . ' – falling back to SQLite');
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
