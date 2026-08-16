<?php
declare(strict_types=1);

// Optional: set these environment variables in your server config instead of editing this file.
putenv('GALLERY_DB_DSN=mysql:host=localhost;dbname=lana_gallery;charset=utf8mb4');
putenv('GALLERY_DB_USER=lana_gallery_user');
putenv('GALLERY_DB_PASS=LanaGallery!2026');

$dsn = getenv('GALLERY_DB_DSN') ?: 'mysql:host=localhost;dbname=lana_gallery;charset=utf8mb4';
$user = getenv('GALLERY_DB_USER') ?: 'lana_gallery_user';
$pass = getenv('GALLERY_DB_PASS') ?: 'LanaGallery!2026';

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // gallery items table
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS gallery_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(255) NOT NULL UNIQUE,
            title VARCHAR(255) NOT NULL,
            medium VARCHAR(255) DEFAULT NULL,
            genre VARCHAR(255) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            thumbnail_path VARCHAR(500) DEFAULT NULL,
            full_path VARCHAR(500) DEFAULT NULL,
            date_added DATE DEFAULT NULL,
            sold TINYINT(1) DEFAULT 0,
            disabled TINYINT(1) DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    // ensure upload folders exist
    $folders = [
        __DIR__ . '/uploads/gallery/thumbs',
        __DIR__ . '/uploads/gallery/full',
    ];

    foreach ($folders as $folder) {
        if (!is_dir($folder)) {
            if (!mkdir($folder, 0775, true) && !is_dir($folder)) {
                throw new RuntimeException('Could not create folder: ' . $folder);
            }
        }
    }

    echo "Gallery database and upload folders are ready.\n";
    echo "Authentication uses GALLERY_ADMIN_PASSWORD_HASH only (single-user mode).\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Setup failed: ' . $e->getMessage();
}
