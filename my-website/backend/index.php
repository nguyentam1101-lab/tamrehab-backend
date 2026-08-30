<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/db.php';

jsonResponse([
    'service' => 'tamrehab-backend',
    'status' => 'ok',
    'endpoints' => [
        'health' => '/api/healthz',
        'products' => '/api/products.php',
        'customers' => '/api/customers.php',
        'orders' => '/api/orders.php',
        'create_order' => '/api/create-order.php',
        'webhook' => '/webhook-sepay.php',
    ],
]);