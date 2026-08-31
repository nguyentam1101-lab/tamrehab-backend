<?php
require __DIR__ . '/db.php';
$pdo = tamrehab_db();
$cols = $pdo->query('PRAGMA table_info(customers)')->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) { echo $c['name'] . PHP_EOL; }
$count = $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
echo "rows: $count\n";
