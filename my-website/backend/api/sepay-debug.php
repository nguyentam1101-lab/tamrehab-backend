<?php

declare(strict_types=1);

$logFile = '/tmp/sepay_webhook.log';
$rawBody = file_get_contents('php://input');
$timestamp = date('Y-m-d H:i:s');

$logEntry = "=== {$timestamp} ===\n";
$logEntry .= "Method: {$_SERVER['REQUEST_METHOD']}\n";
$logEntry .= "Content-Type: {$_SERVER['CONTENT_TYPE']}\n";
$logEntry .= "Raw Body:\n{$rawBody}\n";
$logEntry .= "POST data: " . print_r($_POST, true) . "\n";
$logEntry .= "GET data: " . print_r($_GET, true) . "\n";
$logEntry .= "---\n\n";

file_put_contents($logFile, $logEntry, FILE_APPEND);

header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'Logged to ' . $logFile]);
