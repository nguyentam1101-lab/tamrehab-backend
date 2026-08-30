<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$documentRoot = realpath(__DIR__) ?: __DIR__;
$requestedFile = realpath($documentRoot . $path);

if ($requestedFile !== false && str_starts_with($requestedFile, $documentRoot) && is_file($requestedFile)) {
    return false;
}

if ($path === '/api/healthz') {
    require __DIR__ . '/api/healthz.php';
    return true;
}

if ($path === '/') {
    require __DIR__ . '/index.php';
    return true;
}

http_response_code(404);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => false, 'message' => 'Not found'], JSON_UNESCAPED_UNICODE);