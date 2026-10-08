<?php
require __DIR__ . '/gallery-lib.php';

header('Content-Type: text/plain; charset=utf-8');
$pdo = gallery_init_db();
if (!$pdo) {
    echo "DB: no\n";
    exit(1);
}

try {
    $stmt = $pdo->query('SELECT 1');
    $stmt->fetch();
    echo "DB: ok\n";
} catch (Throwable $e) {
    echo "DB error: " . $e->getMessage() . "\n";
    exit(1);
}
