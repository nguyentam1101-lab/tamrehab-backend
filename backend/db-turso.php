<?php
require_once __DIR__ . '/vendor/autoload.php';
use Libsql\Database;

$configFile = __DIR__ . '/turso_config.txt';
$config = is_file($configFile) ? parse_ini_file($configFile) : [];
$url = $config['TURSO_DATABASE_URL'] ?? getenv('TURSO_DATABASE_URL');
$token = $config['TURSO_AUTH_TOKEN'] ?? getenv('TURSO_AUTH_TOKEN');

try {
    $db = new Database(url: $url, authToken: $token);
    error_log("[Turso] Connected");
} catch (Exception $e) {
    error_log("[Turso] Failed: " . $e->getMessage());
    $db = new SQLite3(__DIR__ . '/brain.db');
}

function getTursoDB() { global $db; return $db; }
?>
