<?php
require __DIR__ . '/gallery-lib.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = gallery_init_db();
    if (!$pdo) {
        throw new RuntimeException('Database connection failed.');
    }

    $fullDir = __DIR__ . '/all/full';
    $thumbDir = __DIR__ . '/all/thumbs';
    $importedDir = __DIR__ . '/uploads/full';

    gallery_ensure_upload_directories();

    $files = [];
    if (is_dir($fullDir)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fullDir, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && preg_match('/\.(jpe?g|png|webp)$/i', $file->getFilename())) {
                $files[] = $file->getPathname();
            }
        }
    }

    $created = 0;
    foreach ($files as $fullPath) {
        $filename = basename($fullPath);
        $baseName = preg_replace('/\.[^.]+$/', '', $filename);
        $thumbCandidate = $thumbDir . DIRECTORY_SEPARATOR . $baseName . 'thumb.jpg';
        $altThumb = $thumbDir . DIRECTORY_SEPARATOR . $baseName . 'thumb.jpeg';
        $thumbPath = is_file($thumbCandidate) ? $thumbCandidate : (is_file($altThumb) ? $altThumb : '');

        if ($thumbPath === '') {
            $thumbPath = $fullPath;
        }

        $title = gallery_build_title_from_filename($filename);
        $slug = gallery_create_slug_exists($pdo, $title);

        $check = $pdo->prepare('SELECT id FROM images WHERE slug = :slug LIMIT 1');
        $check->execute([':slug' => $slug]);
        if ($check->fetch()) {
            continue;
        }

        $fullUrl = '/gallery/all/full/' . rawurlencode($filename);
        $thumbUrl = '/gallery/all/thumbs/' . rawurlencode(basename($thumbPath));
        $orientation = gallery_detect_orientation($fullPath);
        $altText = gallery_generate_alt_text($title, $filename);

        $stmt = $pdo->prepare('INSERT INTO images (slug, title, full_file, thumbnail_file, full_url, thumbnail_url, price_public, available, medium, genre, collection, description, location, orientation, alt_text, created_at, updated_at) VALUES (:slug, :title, :full_file, :thumbnail_file, :full_url, :thumbnail_url, :price_public, :available, :medium, :genre, :collection, :description, :location, :orientation, :alt_text, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)');
        $stmt->execute([
            ':slug' => $slug,
            ':title' => $title,
            ':full_file' => $fullPath,
            ':thumbnail_file' => $thumbPath,
            ':full_url' => $fullUrl,
            ':thumbnail_url' => $thumbUrl,
            ':price_public' => '',
            ':available' => 1,
            ':medium' => '',
            ':genre' => '',
            ':collection' => '',
            ':description' => '',
            ':location' => '',
            ':orientation' => $orientation,
            ':alt_text' => $altText,
        ]);

        $created++;
    }

    echo json_encode(['success' => true, 'created' => $created]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
