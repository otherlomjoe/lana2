<?php
declare(strict_types=1);

$configPath = dirname(__DIR__) . '/gallery-config.php';
if (is_readable($configPath)) {
    require_once $configPath;
}

function gallery_database(): ?PDO
{
    $dsn = getenv('GALLERY_DB_DSN');
    $user = getenv('GALLERY_DB_USER');
    $pass = getenv('GALLERY_DB_PASS');

    if ($dsn === false || $dsn === '' || $user === false || $user === '' || $pass === false) {
        return null;
    }

    try {
        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (Throwable $e) {
        return null;
    }
}

function gallery_ensure_upload_directories(): void
{
    $directories = [
        __DIR__ . '/uploads/full',
        __DIR__ . '/uploads/thumbs',
        __DIR__ . '/uploads/deleted',
        __DIR__ . '/all/imported',
    ];

    foreach ($directories as $directory) {
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Could not create gallery directory: ' . $directory);
        }
    }
}

function gallery_init_db(): ?PDO
{
    $pdo = gallery_database();
    if (!$pdo) {
        return null;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS mediums (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL UNIQUE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS genres (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL UNIQUE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS collections (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL UNIQUE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS tags (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL UNIQUE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS exhibitions (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(255) NOT NULL UNIQUE,
        title VARCHAR(255) NOT NULL,
        start_date DATE,
        end_date DATE,
        location VARCHAR(255),
        description TEXT,
        hero_image VARCHAR(1024),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS images (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(255) NOT NULL UNIQUE,
        title VARCHAR(255) NOT NULL,
        full_file VARCHAR(1024),
        thumbnail_file VARCHAR(1024),
        full_url VARCHAR(1024),
        thumbnail_url VARCHAR(1024),
        price_public VARCHAR(255),
        price_private VARCHAR(255),
        available TINYINT(1) NOT NULL DEFAULT 1,
        medium VARCHAR(255),
        medium_id INT UNSIGNED,
        genre VARCHAR(255),
        genre_id INT UNSIGNED,
        collection VARCHAR(255),
        collection_id INT UNSIGNED,
        award_title VARCHAR(255),
        award_description TEXT,
        dimensions VARCHAR(255),
        description TEXT,
        location VARCHAR(255),
        private_notes TEXT,
        copies_sold INT UNSIGNED NOT NULL DEFAULT 0,
        orientation VARCHAR(32),
        alt_text VARCHAR(1024),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    foreach ([
        'deleted_at' => 'DATETIME NULL',
        'deleted_full_file' => 'VARCHAR(1024) NULL',
        'deleted_thumbnail_file' => 'VARCHAR(1024) NULL',
    ] as $column => $definition) {
        try {
            $pdo->exec("ALTER TABLE images ADD COLUMN {$column} {$definition}");
        } catch (PDOException $e) {
            if ((int) $e->errorInfo[1] !== 1060) {
                throw $e;
            }
        }
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS image_tags (
        image_id INT UNSIGNED NOT NULL,
        tag_id INT UNSIGNED NOT NULL,
        PRIMARY KEY (image_id, tag_id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS image_exhibitions (
        image_id INT UNSIGNED NOT NULL,
        exhibition_id INT UNSIGNED NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        PRIMARY KEY (image_id, exhibition_id)
    )");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_images_title ON images(title)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_images_medium ON images(medium)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_images_genre ON images(genre)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_images_collection ON images(collection)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_images_available ON images(available)");

    return $pdo;
}

function gallery_slugify(string $value): string
{
    $value = trim(strtolower($value));
    $value = preg_replace('/[^a-z0-9]+/u', '-', $value);
    $value = trim((string) $value, '-');
    return $value !== '' ? $value : 'untitled';
}

function gallery_build_title_from_filename(string $filename): string
{
    $filename = basename($filename);
    $name = preg_replace('/\.[^.]+$/', '', $filename);
    if ($name === '') {
        return 'Untitled';
    }

    $name = preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', $name);
    $name = preg_replace('/[_\-]+/', ' ', $name);
    $name = preg_replace('/\s+/', ' ', $name);
    $name = trim($name);

    if ($name === '') {
        return 'Untitled';
    }

    $parts = array_values(array_filter(explode(' ', $name), 'strlen'));
    foreach ($parts as &$part) {
        $part = ucfirst(strtolower($part));
    }

    return implode(' ', $parts);
}

function gallery_generate_alt_text(string $title, ?string $filename = null): string
{
    $cleanTitle = trim($title);
    if ($cleanTitle === '') {
        $cleanTitle = gallery_build_title_from_filename((string) $filename);
    }

    return $cleanTitle !== '' ? $cleanTitle : 'Artwork image';
}

function gallery_detect_orientation(string $imagePath): string
{
    if (!is_file($imagePath)) {
        return 'landscape';
    }

    $size = @getimagesize($imagePath);
    if (!$size || !isset($size[0], $size[1])) {
        return 'landscape';
    }

    return $size[0] >= $size[1] ? 'landscape' : 'portrait';
}

function gallery_string_or_empty($value): string
{
    return is_string($value) ? trim($value) : '';
}

function gallery_get_or_create_lookup(PDO $pdo, string $table, string $value): ?int
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $check = $pdo->prepare("SELECT id FROM {$table} WHERE name = :value LIMIT 1");
    $check->execute([':value' => $value]);
    $row = $check->fetch();
    if ($row) {
        return (int) $row['id'];
    }

    $insert = $pdo->prepare("INSERT INTO {$table} (name) VALUES (:value)");
    $insert->execute([':value' => $value]);
    return (int) $pdo->lastInsertId();
}

function gallery_lookup_values(PDO $pdo): array
{
    $tables = ['mediums', 'genres', 'collections', 'tags'];
    $output = [];

    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT name FROM {$table} ORDER BY name ASC");
        $output[$table] = array_map(static fn ($row) => $row['name'], $stmt->fetchAll());
    }

    return $output;
}

function gallery_format_rich_text(string $text): string
{
    $trimmed = trim($text);
    if ($trimmed === '') {
        return '';
    }

    $escaped = htmlspecialchars($trimmed, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $escaped = preg_replace('/\r\n|\r/', "\n", $escaped);

    $escaped = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $escaped);
    $escaped = preg_replace('/\*(.+?)\*/u', '<em>$1</em>', $escaped);
    $escaped = preg_replace('/\[(.+?)\]\((https?:\/\/[^)]+)\)/u', '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>', $escaped);

    $paragraphs = preg_split('/\n\s*\n/', $escaped);
    $htmlParts = [];
    foreach ($paragraphs as $paragraph) {
        $lines = preg_split('/\n/', $paragraph);
        $items = [];
        $isList = true;
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^-\s+/', $line)) {
                $items[] = '<li>' . preg_replace('/^-\s+/', '', $line) . '</li>';
            } else {
                $isList = false;
            }
        }

        if ($isList && count($items) > 0) {
            $htmlParts[] = '<ul>' . implode('', $items) . '</ul>';
            continue;
        }

        $htmlParts[] = '<p>' . str_replace("\n", '<br>', $paragraph) . '</p>';
    }

    return implode('', $htmlParts);
}

function gallery_render_formatted_text(string $text): string
{
    $rendered = gallery_format_rich_text($text);
    if ($rendered === '') {
        return '';
    }

    return $rendered;
}

function gallery_ensure_tag_links(PDO $pdo, int $imageId, array $tags): void
{
    $pdo->prepare('DELETE FROM image_tags WHERE image_id = :image_id')->execute([':image_id' => $imageId]);

    foreach ($tags as $tag) {
        $tagName = trim((string) $tag);
        if ($tagName === '') {
            continue;
        }

        $tagId = gallery_get_or_create_lookup($pdo, 'tags', $tagName);
        if ($tagId === null) {
            continue;
        }

        $pdo->prepare('INSERT INTO image_tags (image_id, tag_id) VALUES (:image_id, :tag_id)')->execute([
            ':image_id' => $imageId,
            ':tag_id' => $tagId,
        ]);
    }
}

function gallery_ensure_image_exhibitions(PDO $pdo, int $imageId, array $exhibitionIds): void
{
    $pdo->prepare('DELETE FROM image_exhibitions WHERE image_id = :image_id')->execute([':image_id' => $imageId]);

    foreach ($exhibitionIds as $index => $exhibitionId) {
        $exhibitionId = (int) $exhibitionId;
        if ($exhibitionId <= 0) {
            continue;
        }

        $pdo->prepare('INSERT INTO image_exhibitions (image_id, exhibition_id, sort_order) VALUES (:image_id, :exhibition_id, :sort_order)')->execute([
            ':image_id' => $imageId,
            ':exhibition_id' => $exhibitionId,
            ':sort_order' => $index,
        ]);
    }
}

function gallery_create_slug_exists(PDO $pdo, string $title, ?int $ignoreId = null): string
{
    $base = gallery_slugify($title);
    $candidate = $base;
    $counter = 1;

    while (true) {
        $exists = $pdo->prepare('SELECT id FROM images WHERE slug = :slug' . ($ignoreId !== null ? ' AND id != :ignore_id' : ''));
        $params = [':slug' => $candidate];
        if ($ignoreId !== null) {
            $params[':ignore_id'] = $ignoreId;
        }
        $exists->execute($params);

        if (!$exists->fetch()) {
            return $candidate;
        }

        $candidate = $base . '-' . $counter;
        $counter++;
    }
}

function gallery_resize_image(string $sourcePath, string $destinationPath, int $maxWidth = 1200, int $maxHeight = 1200, int $quality = 88): bool
{
    if (!is_file($sourcePath)) {
        return false;
    }

    $size = getimagesize($sourcePath);
    if ($size === false) {
        return false;
    }

    $originalWidth = (int) $size[0];
    $originalHeight = (int) $size[1];
    $type = $size[2];

    $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight, 1);
    $newWidth = max(1, (int) round($originalWidth * $ratio));
    $newHeight = max(1, (int) round($originalHeight * $ratio));

    $image = null;
    switch ($type) {
        case IMAGETYPE_JPEG:
            $image = imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_PNG:
            $image = imagecreatefrompng($sourcePath);
            break;
        case IMAGETYPE_WEBP:
            $image = imagecreatefromwebp($sourcePath);
            break;
        default:
            return false;
    }

    if ($image === false) {
        return false;
    }

    $resized = imagecreatetruecolor($newWidth, $newHeight);
    imagealphablending($resized, false);
    imagesavealpha($resized, true);
    imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

    $saved = false;
    switch ($type) {
        case IMAGETYPE_JPEG:
            $saved = imagejpeg($resized, $destinationPath, $quality);
            break;
        case IMAGETYPE_PNG:
            $saved = imagepng($resized, $destinationPath, 9);
            break;
        case IMAGETYPE_WEBP:
            $saved = imagewebp($resized, $destinationPath, $quality);
            break;
    }

    imagedestroy($image);
    imagedestroy($resized);

    return $saved;
}

function gallery_store_uploaded_asset(array $file, string $directory, string $prefix): array
{
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Uploaded file is invalid.');
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only JPG, PNG, and WEBP images are supported.');
    }

    $extension = $allowed[$mime];
    $filename = $prefix . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
    $target = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('Could not save the uploaded file.');
    }

    return ['path' => $target, 'filename' => $filename, 'url' => '/gallery/uploads/' . basename(dirname($directory)) . '/' . $filename];
}

function gallery_normalize_image_row(array $row): array
{
    $tags = [];
    if (!empty($row['tag_names'])) {
        $tags = array_values(array_unique(array_filter(array_map('trim', explode(',', (string) $row['tag_names']), 'strlen'))));
    }

    return [
        'id' => (int) ($row['id'] ?? 0),
        'slug' => (string) ($row['slug'] ?? ''),
        'title' => (string) ($row['title'] ?? ''),
        'full' => (string) ($row['full_url'] ?? $row['full_file'] ?? ''),
        'thumbnail' => (string) ($row['thumbnail_url'] ?? $row['thumbnail_file'] ?? ''),
        'price' => (string) (($row['price_public'] ?? '') !== '' ? $row['price_public'] : ($row['price_private'] ?? '')),
        'pricePrivate' => (string) ($row['price_private'] ?? ''),
        'available' => (bool) ($row['available'] ?? 1),
        'medium' => (string) ($row['medium'] ?? ''),
        'genre' => (string) ($row['genre'] ?? ''),
        'collection' => (string) ($row['collection'] ?? ''),
        'awardTitle' => (string) ($row['award_title'] ?? ''),
        'awardDescription' => (string) ($row['award_description'] ?? ''),
        'dimensions' => (string) ($row['dimensions'] ?? ''),
        'description' => (string) ($row['description'] ?? ''),
        'descriptionHtml' => gallery_render_formatted_text((string) ($row['description'] ?? '')),
        'location' => (string) ($row['location'] ?? ''),
        'privateNotes' => (string) ($row['private_notes'] ?? ''),
        'copiesSold' => (int) ($row['copies_sold'] ?? 0),
        'orientation' => (string) ($row['orientation'] ?? 'landscape'),
        'altText' => (string) ($row['alt_text'] ?? ''),
        'tags' => $tags,
        'source' => 'server',
        'createdAt' => $row['created_at'] ?? null,
        'deletedAt' => $row['deleted_at'] ?? null,
        'status' => !empty($row['deleted_at']) ? 'Deleted' : (!empty($row['available']) ? 'Active' : 'Unavailable'),
    ];
}

function gallery_list_images(?PDO $pdo = null, bool $includeDeleted = false): array
{
    $pdo = $pdo ?: gallery_init_db();
    if (!$pdo) {
        return [];
    }

        $deletedCondition = $includeDeleted ? '' : ' WHERE i.deleted_at IS NULL';
        $sql = "SELECT i.*, GROUP_CONCAT(DISTINCT t.name, ',') AS tag_names
            FROM images i
            LEFT JOIN image_tags it ON it.image_id = i.id
            LEFT JOIN tags t ON t.id = it.tag_id
            {$deletedCondition}
            GROUP BY i.id
            ORDER BY i.created_at DESC";

    $items = [];
    foreach ($pdo->query($sql) as $row) {
        $items[] = gallery_normalize_image_row($row);
    }

    return $items;
}

function gallery_list_exhibitions(?PDO $pdo = null): array
{
    $pdo = $pdo ?: gallery_init_db();
    if (!$pdo) {
        return [];
    }

    $stmt = $pdo->query("SELECT * FROM exhibitions ORDER BY start_date DESC, title ASC");
    $exhibitions = [];
    foreach ($stmt as $row) {
        $exhibitions[] = [
            'id' => (int) $row['id'],
            'slug' => $row['slug'],
            'title' => $row['title'],
            'startDate' => $row['start_date'],
            'endDate' => $row['end_date'],
            'location' => $row['location'],
            'description' => $row['description'],
            'heroImage' => $row['hero_image'],
        ];
    }

    return $exhibitions;
}

function gallery_save_exhibition(array $data): array
{
    $pdo = gallery_init_db();
    if (!$pdo) {
        throw new RuntimeException('Gallery database is unavailable.');
    }

    $title = trim((string) ($data['title'] ?? ''));
    if ($title === '') {
        throw new InvalidArgumentException('Exhibition title is required.');
    }

    $slug = gallery_slugify((string) ($data['slug'] ?? $title));
    $id = isset($data['id']) ? (int) $data['id'] : 0;

    if ($id > 0) {
        $stmt = $pdo->prepare('UPDATE exhibitions SET slug = :slug, title = :title, start_date = :start_date, end_date = :end_date, location = :location, description = :description, hero_image = :hero_image WHERE id = :id');
        $stmt->execute([
            ':slug' => $slug,
            ':title' => $title,
            ':start_date' => trim((string) ($data['startDate'] ?? $data['start_date'] ?? '')),
            ':end_date' => trim((string) ($data['endDate'] ?? $data['end_date'] ?? '')),
            ':location' => trim((string) ($data['location'] ?? '')),
            ':description' => trim((string) ($data['description'] ?? '')),
            ':hero_image' => trim((string) ($data['heroImage'] ?? '')),
            ':id' => $id,
        ]);
        $exhibitionId = $id;
    } else {
        $stmt = $pdo->prepare('INSERT INTO exhibitions (slug, title, start_date, end_date, location, description, hero_image) VALUES (:slug, :title, :start_date, :end_date, :location, :description, :hero_image)');
        $stmt->execute([
            ':slug' => $slug,
            ':title' => $title,
            ':start_date' => trim((string) ($data['startDate'] ?? $data['start_date'] ?? '')),
            ':end_date' => trim((string) ($data['endDate'] ?? $data['end_date'] ?? '')),
            ':location' => trim((string) ($data['location'] ?? '')),
            ':description' => trim((string) ($data['description'] ?? '')),
            ':hero_image' => trim((string) ($data['heroImage'] ?? '')),
        ]);
        $exhibitionId = (int) $pdo->lastInsertId();
    }

    $imageIds = [];
    if (!empty($data['imageIds'])) {
        $imageIds = array_map('intval', (array) $data['imageIds']);
    }

    $pdo->prepare('DELETE FROM image_exhibitions WHERE exhibition_id = :exhibition_id')->execute([':exhibition_id' => $exhibitionId]);
    foreach ($imageIds as $index => $imageId) {
        $pdo->prepare('INSERT INTO image_exhibitions (image_id, exhibition_id, sort_order) VALUES (:image_id, :exhibition_id, :sort_order)')->execute([
            ':image_id' => $imageId,
            ':exhibition_id' => $exhibitionId,
            ':sort_order' => $index,
        ]);
    }

    return ['id' => $exhibitionId, 'slug' => $slug, 'title' => $title];
}

function gallery_search_images(array $filters = [], int $page = 1, int $limit = 20): array
{
    $pdo = gallery_init_db();
    if (!$pdo) {
        return ['items' => [], 'total' => 0, 'page' => $page, 'pages' => 0];
    }

    $conditions = ['i.deleted_at IS NULL'];
    $params = [];
    $query = trim((string) ($filters['q'] ?? ''));

    if ($query !== '') {
        $conditions[] = '(
            i.title LIKE :q OR
            i.award_title LIKE :q OR
            i.award_description LIKE :q OR
            i.description LIKE :q OR
            i.location LIKE :q OR
            i.medium LIKE :q OR
            i.genre LIKE :q OR
            i.collection LIKE :q OR
            EXISTS (SELECT 1 FROM image_tags it JOIN tags t ON t.id = it.tag_id WHERE it.image_id = i.id AND t.name LIKE :q)
        )';
        $params[':q'] = '%' . $query . '%';
    }

    foreach (['medium', 'genre', 'collection'] as $field) {
        if (!empty($filters[$field])) {
            $conditions[] = 'i.' . $field . ' = :' . $field;
            $params[':' . $field] = trim((string) $filters[$field]);
        }
    }

    $available = $filters['available'] ?? null;
    if ($available !== null && $available !== '') {
        $conditions[] = 'i.available = :available';
        $params[':available'] = (int) $available;
    }

    $tagFilter = $filters['tag'] ?? $filters['tags'] ?? null;
    if (!empty($tagFilter)) {
        $tags = is_array($tagFilter) ? $tagFilter : [$tagFilter];
        foreach ($tags as $idx => $tag) {
            $conditions[] = 'EXISTS (SELECT 1 FROM image_tags it JOIN tags t ON t.id = it.tag_id WHERE it.image_id = i.id AND t.name = :tag_' . $idx . ')';
            $params[':tag_' . $idx] = trim((string) $tag);
        }
    }

    $exhibition = trim((string) ($filters['exhibition'] ?? ''));
    if ($exhibition !== '') {
        $conditions[] = 'EXISTS (SELECT 1 FROM image_exhibitions ie JOIN exhibitions e ON e.id = ie.exhibition_id WHERE ie.image_id = i.id AND e.slug = :exhibition)';
        $params[':exhibition'] = $exhibition;
    }

    $whereSql = ' WHERE ' . implode(' AND ', $conditions);

    $totalStmt = $pdo->prepare('SELECT COUNT(*) AS total FROM images i ' . $whereSql);
    $totalStmt->execute($params);
    $total = (int) $totalStmt->fetchColumn();

    $page = max(1, (int) $page);
    $limit = max(1, (int) $limit);
    $offset = ($page - 1) * $limit;
    $pages = $total > 0 ? (int) ceil($total / $limit) : 0;

    $sql = 'SELECT i.*, GROUP_CONCAT(DISTINCT t.name) AS tag_names FROM images i
            LEFT JOIN image_tags it ON it.image_id = i.id
            LEFT JOIN tags t ON t.id = it.tag_id
            ' . $whereSql . '
            GROUP BY i.id
            ORDER BY i.created_at DESC
            LIMIT :offset, :limit';

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $items = [];
    foreach ($stmt as $row) {
        $items[] = gallery_normalize_image_row($row);
    }

    return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages, 'limit' => $limit];
}

function gallery_save_image(array $data, array $files = []): array
{
    $pdo = gallery_init_db();
    if (!$pdo) {
        throw new RuntimeException('Gallery database is unavailable.');
    }

    gallery_ensure_upload_directories();

    $fullInput = $files['full'] ?? $files['fullImage'] ?? null;
    $thumbInput = $files['thumbnail'] ?? $files['thumb'] ?? null;
    $filename = trim((string) ($data['filename'] ?? ($fullInput['name'] ?? '')));
    $title = trim((string) ($data['title'] ?? ''));
    if ($title === '' && $filename !== '') {
        $title = gallery_build_title_from_filename($filename);
    }

    if ($title === '') {
        throw new InvalidArgumentException('Image title could not be derived. Enter a title or choose an image file.');
    }

    $fullPath = trim((string) ($data['fullPath'] ?? ($data['full'] ?? '')));
    $thumbPath = trim((string) ($data['thumbnailPath'] ?? ($data['thumbnail'] ?? '')));
    $fullUrl = trim((string) ($data['fullUrl'] ?? ''));
    $thumbUrl = trim((string) ($data['thumbUrl'] ?? ($data['thumbnailUrl'] ?? '')));
    $generateThumbnail = !empty($data['generateThumbnail']);

    $imageId = !empty($data['id']) ? (int) $data['id'] : null;
    if ($imageId !== null) {
        $existingStmt = $pdo->prepare('SELECT full_file, thumbnail_file, full_url, thumbnail_url FROM images WHERE id = :id LIMIT 1');
        $existingStmt->execute([':id' => $imageId]);
        $existing = $existingStmt->fetch();
        if (!$existing) {
            throw new InvalidArgumentException('Image not found.');
        }

        if ($fullPath === '') {
            $fullPath = (string) ($existing['full_file'] ?? '');
        }
        if ($thumbPath === '') {
            $thumbPath = (string) ($existing['thumbnail_file'] ?? '');
        }
        if ($fullUrl === '') {
            $fullUrl = (string) ($existing['full_url'] ?? '');
        }
        if ($thumbUrl === '') {
            $thumbUrl = (string) ($existing['thumbnail_url'] ?? '');
        }
        if ($title === '' && $filename === '') {
            $filename = basename((string) ($existing['full_file'] ?? ''));
            $title = gallery_build_title_from_filename($filename);
        }
    }

    if ($fullInput && !empty($fullInput['tmp_name'])) {
        $stored = gallery_store_uploaded_asset($fullInput, __DIR__ . '/uploads/full', 'full');
        $fullPath = $stored['path'];
        $fullUrl = '/gallery/uploads/full/' . $stored['filename'];
    }

    if ($thumbInput && !empty($thumbInput['tmp_name'])) {
        $storedThumb = gallery_store_uploaded_asset($thumbInput, __DIR__ . '/uploads/thumbs', 'thumb');
        $thumbPath = $storedThumb['path'];
        $thumbUrl = '/gallery/uploads/thumbs/' . $storedThumb['filename'];
        if (!gallery_resize_image($thumbPath, $thumbPath, 200, 165, 88)) {
            throw new RuntimeException('Could not resize the thumbnail image.');
        }
    } elseif ($imageId === null && $filename !== '') {
        $thumbBase = gallery_slugify(pathinfo($filename, PATHINFO_FILENAME)) . '-thumb';
        foreach (['jpg', 'png', 'webp'] as $thumbExtension) {
            $candidateFilename = $thumbBase . '.' . $thumbExtension;
            $candidatePath = __DIR__ . '/uploads/thumbs/' . $candidateFilename;
            if (is_file($candidatePath)) {
                $thumbPath = $candidatePath;
                $thumbUrl = '/gallery/uploads/thumbs/' . $candidateFilename;
                break;
            }
        }
    }

    if ($thumbPath === '' && $fullPath !== '' && $generateThumbnail) {
        $thumbFilename = gallery_slugify(pathinfo($filename, PATHINFO_FILENAME)) . '-thumb.jpg';
        if (is_file(__DIR__ . '/uploads/thumbs/' . $thumbFilename)) {
            $thumbFilename = gallery_slugify(pathinfo($filename, PATHINFO_FILENAME)) . '-thumb-' . bin2hex(random_bytes(4)) . '.jpg';
        }
        $thumbPath = __DIR__ . '/uploads/thumbs/' . $thumbFilename;
        if (!gallery_resize_image($fullPath, $thumbPath, 200, 165, 88)) {
            throw new RuntimeException('Could not generate the thumbnail image.');
        }
        $thumbUrl = '/gallery/uploads/thumbs/' . $thumbFilename;
    }

    if ($fullPath === '') {
        throw new InvalidArgumentException('A full image is required.');
    }

    if ($thumbPath === '') {
        throw new InvalidArgumentException('Upload a thumbnail, select an existing image-name-thumb file, or enable thumbnail generation.');
    }

    if ($fullPath !== '' && !is_file($fullPath)) {
        throw new InvalidArgumentException('The full image file does not exist on disk.');
    }

    if ($thumbPath !== '' && !is_file($thumbPath)) {
        throw new InvalidArgumentException('The thumbnail file does not exist on disk.');
    }

    $slug = gallery_slugify((string) ($data['slug'] ?? $title));
    $slug = gallery_create_slug_exists($pdo, $slug, $imageId);

    $mediumValue = trim((string) ($data['medium'] ?? ''));
    $genreValue = trim((string) ($data['genre'] ?? ''));
    $collectionValue = trim((string) ($data['collection'] ?? ''));

    $mediumId = $mediumValue !== '' ? gallery_get_or_create_lookup($pdo, 'mediums', $mediumValue) : null;
    $genreId = $genreValue !== '' ? gallery_get_or_create_lookup($pdo, 'genres', $genreValue) : null;
    $collectionId = $collectionValue !== '' ? gallery_get_or_create_lookup($pdo, 'collections', $collectionValue) : null;

    $fullUrl = $fullUrl !== '' ? $fullUrl : '/gallery/uploads/full/' . basename($fullPath);
    $thumbUrl = $thumbUrl !== '' ? $thumbUrl : '/gallery/uploads/thumbs/' . basename($thumbPath);

    $orientation = trim((string) ($data['orientation'] ?? ''));
    if ($orientation === '') {
        $orientation = gallery_detect_orientation($fullPath);
    }

    $altText = trim((string) ($data['altText'] ?? ''));
    if ($altText === '') {
        $altText = gallery_generate_alt_text($title, $filename ?: $fullPath);
    }

    $description = trim((string) ($data['description'] ?? ''));
    $location = trim((string) ($data['location'] ?? ''));

    if ($imageId !== null) {
        $stmt = $pdo->prepare('UPDATE images SET slug = :slug, title = :title, full_file = :full_file, thumbnail_file = :thumbnail_file, full_url = :full_url, thumbnail_url = :thumbnail_url, price_public = :price_public, price_private = :price_private, available = :available, medium = :medium, medium_id = :medium_id, genre = :genre, genre_id = :genre_id, collection = :collection, collection_id = :collection_id, award_title = :award_title, award_description = :award_description, dimensions = :dimensions, description = :description, location = :location, private_notes = :private_notes, copies_sold = :copies_sold, orientation = :orientation, alt_text = :alt_text, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $stmt->execute([
            ':slug' => $slug,
            ':title' => $title,
            ':full_file' => $fullPath,
            ':thumbnail_file' => $thumbPath,
            ':full_url' => $fullUrl,
            ':thumbnail_url' => $thumbUrl,
            ':price_public' => trim((string) ($data['pricePublic'] ?? $data['price'] ?? '')),
            ':price_private' => trim((string) ($data['pricePrivate'] ?? '')),
            ':available' => isset($data['available']) ? ((int) $data['available']) : 1,
            ':medium' => $mediumValue,
            ':medium_id' => $mediumId,
            ':genre' => $genreValue,
            ':genre_id' => $genreId,
            ':collection' => $collectionValue,
            ':collection_id' => $collectionId,
            ':award_title' => trim((string) ($data['awardTitle'] ?? '')),
            ':award_description' => trim((string) ($data['awardDescription'] ?? '')),
            ':dimensions' => trim((string) ($data['dimensions'] ?? '')),
            ':description' => $description,
            ':location' => $location,
            ':private_notes' => trim((string) ($data['privateNotes'] ?? '')),
            ':copies_sold' => (int) ($data['copiesSold'] ?? 0),
            ':orientation' => $orientation,
            ':alt_text' => $altText,
            ':id' => $imageId,
        ]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO images (slug, title, full_file, thumbnail_file, full_url, thumbnail_url, price_public, price_private, available, medium, medium_id, genre, genre_id, collection, collection_id, award_title, award_description, dimensions, description, location, private_notes, copies_sold, orientation, alt_text, created_at, updated_at) VALUES (:slug, :title, :full_file, :thumbnail_file, :full_url, :thumbnail_url, :price_public, :price_private, :available, :medium, :medium_id, :genre, :genre_id, :collection, :collection_id, :award_title, :award_description, :dimensions, :description, :location, :private_notes, :copies_sold, :orientation, :alt_text, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)');
        $stmt->execute([
            ':slug' => $slug,
            ':title' => $title,
            ':full_file' => $fullPath,
            ':thumbnail_file' => $thumbPath,
            ':full_url' => $fullUrl,
            ':thumbnail_url' => $thumbUrl,
            ':price_public' => trim((string) ($data['pricePublic'] ?? $data['price'] ?? '')),
            ':price_private' => trim((string) ($data['pricePrivate'] ?? '')),
            ':available' => isset($data['available']) ? ((int) $data['available']) : 1,
            ':medium' => $mediumValue,
            ':medium_id' => $mediumId,
            ':genre' => $genreValue,
            ':genre_id' => $genreId,
            ':collection' => $collectionValue,
            ':collection_id' => $collectionId,
            ':award_title' => trim((string) ($data['awardTitle'] ?? '')),
            ':award_description' => trim((string) ($data['awardDescription'] ?? '')),
            ':dimensions' => trim((string) ($data['dimensions'] ?? '')),
            ':description' => $description,
            ':location' => $location,
            ':private_notes' => trim((string) ($data['privateNotes'] ?? '')),
            ':copies_sold' => (int) ($data['copiesSold'] ?? 0),
            ':orientation' => $orientation,
            ':alt_text' => $altText,
        ]);
        $imageId = (int) $pdo->lastInsertId();
    }

    $tags = [];
    if (!empty($data['tags'])) {
        $tags = is_array($data['tags']) ? $data['tags'] : preg_split('/[,;\n]/', (string) $data['tags']);
    }
    gallery_ensure_tag_links($pdo, $imageId, $tags);

    if (!empty($data['exhibition'])) {
        $exhibitionId = (int) $data['exhibition'];
        if ($exhibitionId > 0) {
            $pdo->prepare('DELETE FROM image_exhibitions WHERE image_id = :image_id')->execute([':image_id' => $imageId]);
            $pdo->prepare('INSERT INTO image_exhibitions (image_id, exhibition_id, sort_order) VALUES (:image_id, :exhibition_id, 0)')->execute([
                ':image_id' => $imageId,
                ':exhibition_id' => $exhibitionId,
            ]);
        }
    }

    if ((empty($data['fullUrl']) || empty($data['thumbUrl'])) && $thumbPath !== '' && $fullPath !== '') {
        $thumbUrl = preg_replace('#^' . preg_quote(__DIR__, '#') . '#', '', $thumbPath);
        $fullUrl = preg_replace('#^' . preg_quote(__DIR__, '#') . '#', '', $fullPath);
    }

    return ['id' => $imageId, 'slug' => $slug, 'title' => $title, 'thumbnail' => $thumbUrl, 'full' => $fullUrl, 'orientation' => $orientation];
}

function gallery_soft_delete_image(int $imageId): bool
{
    $pdo = gallery_init_db();
    if (!$pdo) {
        return false;
    }

    gallery_ensure_upload_directories();
    $stmt = $pdo->prepare('SELECT full_file, thumbnail_file, deleted_at FROM images WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $imageId]);
    $row = $stmt->fetch();
    if (!$row || !empty($row['deleted_at'])) {
        return false;
    }

    $deletedFull = $row['full_file'] ? __DIR__ . '/uploads/deleted/' . basename($row['full_file']) : null;
    $deletedThumb = $row['thumbnail_file'] ? __DIR__ . '/uploads/deleted/' . basename($row['thumbnail_file']) : null;
    if ($row['full_file'] && is_file($row['full_file']) && !rename($row['full_file'], $deletedFull)) {
        throw new RuntimeException('Could not archive the full image.');
    }
    if ($row['thumbnail_file'] && is_file($row['thumbnail_file']) && !rename($row['thumbnail_file'], $deletedThumb)) {
        throw new RuntimeException('Could not archive the thumbnail image.');
    }

    return $pdo->prepare('UPDATE images SET deleted_at = CURRENT_TIMESTAMP, deleted_full_file = :deleted_full, deleted_thumbnail_file = :deleted_thumb, available = 0 WHERE id = :id')->execute([
        ':deleted_full' => $deletedFull,
        ':deleted_thumb' => $deletedThumb,
        ':id' => $imageId,
    ]);
}

function gallery_restore_image(int $imageId): bool
{
    $pdo = gallery_init_db();
    if (!$pdo) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT full_file, thumbnail_file, deleted_full_file, deleted_thumbnail_file, deleted_at FROM images WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $imageId]);
    $row = $stmt->fetch();
    if (!$row || empty($row['deleted_at'])) {
        return false;
    }

    foreach ([['deleted_full_file', 'full_file'], ['deleted_thumbnail_file', 'thumbnail_file']] as $paths) {
        [$archivedKey, $originalKey] = $paths;
        $archived = (string) ($row[$archivedKey] ?? '');
        $original = (string) ($row[$originalKey] ?? '');
        if ($archived !== '' && is_file($archived)) {
            if (!is_dir(dirname($original))) {
                mkdir(dirname($original), 0775, true);
            }
            if (!rename($archived, $original)) {
                throw new RuntimeException('Could not restore an archived image.');
            }
        }
    }

    return $pdo->prepare('UPDATE images SET deleted_at = NULL, deleted_full_file = NULL, deleted_thumbnail_file = NULL, available = 1 WHERE id = :id')->execute([':id' => $imageId]);
}

function gallery_delete_image(int $imageId): bool
{
    return gallery_soft_delete_image($imageId);
}

function gallery_hard_delete_image(int $imageId): bool
{
    $pdo = gallery_init_db();
    if (!$pdo) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT full_file, thumbnail_file, deleted_full_file, deleted_thumbnail_file FROM images WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $imageId]);
    $row = $stmt->fetch();
    if (!$row) {
        return false;
    }

    $pdo->prepare('DELETE FROM image_tags WHERE image_id = :id')->execute([':id' => $imageId]);
    $pdo->prepare('DELETE FROM image_exhibitions WHERE image_id = :id')->execute([':id' => $imageId]);
    $deleted = $pdo->prepare('DELETE FROM images WHERE id = :id')->execute([':id' => $imageId]);

    if ($deleted) {
        foreach (['full_file', 'thumbnail_file', 'deleted_full_file', 'deleted_thumbnail_file'] as $column) {
            $file = (string) ($row[$column] ?? '');
            if ($file !== '' && is_file($file)) {
                @unlink($file);
            }
        }
    }

    return $deleted;
}

function gallery_require_auth(): void
{
    if (empty($_SESSION['gallery_admin_authenticated']) || $_SESSION['gallery_admin_authenticated'] !== true) {
        throw new RuntimeException('Unauthorized');
    }
}

function gallery_json_error(string $message, int $status = 400): void
{
    http_response_code($status);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

function gallery_set_csrf_token(): string
{
    $token = bin2hex(random_bytes(32));
    $_SESSION['gallery_csrf_token'] = $token;
    return $token;
}

function gallery_normalize_string_array($value): array
{
    if (is_array($value)) {
        return array_values(array_filter(array_map('trim', $value), static fn ($item) => $item !== ''));
    }

    if (is_string($value)) {
        return array_values(array_filter(array_map('trim', preg_split('/[,;\n]+/', $value)), static fn ($item) => $item !== ''));
    }

    return [];
}
