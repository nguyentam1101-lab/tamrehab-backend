<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';

try {
    $database = getDatabase();
    $database->query('SELECT 1')->fetchColumn();
    jsonResponse([
        'status' => 'ok',
        'service' => 'tamrehab-backend',
        'database' => 'sqlite',
    ]);
} catch (Throwable $error) {
    error_log('Health check failed: ' . $error->getMessage());
    jsonResponse(['status' => 'error', 'message' => 'Database unavailable'], 503);
}