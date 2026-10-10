<?php
require __DIR__ . '/gallery-lib.php';

session_start();

try {
    $pdo = gallery_init_db();
    if (!$pdo) {
        http_response_code(500);
        echo 'Database setup failed.';
        exit;
    }

    echo "Gallery database ready.\n";
    echo "Tables: mediums, genres, collections, tags, exhibitions, images, image_tags, image_exhibitions\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Database setup failed: ' . $e->getMessage();
}
