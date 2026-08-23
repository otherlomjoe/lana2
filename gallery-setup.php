<?php
declare(strict_types=1);

require __DIR__ . '/gallery/gallery-lib.php';

try {
    $pdo = gallery_init_db();
    if (!$pdo) {
        throw new RuntimeException('Database connection failed.');
    }

    gallery_ensure_upload_directories();

    echo "Gallery database and upload folders are ready.\n";
    echo "Authentication uses the configured GALLERY_ADMIN_PASSWORD_HASH setting.\n";
    echo "Schema includes: mediums, genres, collections, tags, exhibitions, images, image_tags, image_exhibitions\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Setup failed: ' . $e->getMessage();
}
