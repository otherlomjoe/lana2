<?php
declare(strict_types=1);

putenv('GALLERY_DB_DSN=mysql:host=localhost;dbname=lana_gallery;charset=utf8mb4');
putenv('GALLERY_DB_USER=lana_gallery_user');
putenv('GALLERY_DB_PASS=LanaGallery!2026');
putenv('GALLERY_ADMIN_PASSWORD_HASH=$2y$10$eS8A6j4cQYFyI9W0h0KpceY2hD822G1a4mI4s8QfR7L3Qm5Wv7b0a');

// secure session cookie settings
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
session_set_cookie_params([
    'lifetime' => 60*60*4, // 4 hours
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'] ?? '',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();
header('Content-Type: application/json; charset=utf-8');

function jsonError(string $message, int $status = 400)
{
    http_response_code($status);
    echo json_encode([
        'success' => false,
        'error' => $message,
    ]);
    exit;
}

function connectDatabase(): ?PDO
{
    $dsn = getenv('GALLERY_DB_DSN') ?: 'mysql:host=localhost;dbname=lana_gallery;charset=utf8mb4';
    $user = getenv('GALLERY_DB_USER') ?: 'lana_gallery_user';
    $pass = getenv('GALLERY_DB_PASS') ?: 'LanaGallery!2026';

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $pdo;
    } catch (Throwable $e) {
        return null;
    }
}

function normalizeGalleryItem(array $row): array
{
    return [
        'id' => $row['id'] ?? null,
        'slug' => (string)($row['slug'] ?? ''),
        'title' => (string)($row['title'] ?? ''),
        'medium' => (string)($row['medium'] ?? ''),
        'genre' => (string)($row['genre'] ?? ''),
        'description' => (string)($row['description'] ?? ''),
        'thumbnail' => $row['thumbnail'] ?? $row['thumbnail_path'] ?? '',
        'full' => $row['full'] ?? $row['full_path'] ?? ($row['thumbnail'] ?? $row['thumbnail_path'] ?? ''),
        'dateAdded' => $row['dateAdded'] ?? $row['date_added'] ?? '',
        'sold' => (bool)($row['sold'] ?? false),
        'disabled' => (bool)($row['disabled'] ?? false),
        'source' => 'server',
    ];
}

function requireAuth(): void
{
    if (empty($_SESSION['gallery_admin_authenticated']) || $_SESSION['gallery_admin_authenticated'] !== true) {
        jsonError('Unauthorized. Please log in to manage the gallery.', 401);
    }
}

function slugify(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim((string)$slug, '-');
    return $slug !== '' ? $slug : 'untitled';
}

function ensureUploadDirectories(): void
{
    $directories = [
        __DIR__ . '/uploads/gallery/thumbs',
        __DIR__ . '/uploads/gallery/full',
    ];

    foreach ($directories as $directory) {
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new RuntimeException('Could not create upload directory: ' . $directory);
            }
        }
    }
}

function extensionFromFileName(string $fileName): string
{
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    return $extension !== '' ? '.' . $extension : '.jpg';
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($action === 'status') {
    echo json_encode([
        'success' => true,
        'authenticated' => !empty($_SESSION['gallery_admin_authenticated']) && $_SESSION['gallery_admin_authenticated'] === true,
    ]);
    exit;
}

if ($action === 'login') {
    $password = (string)($_POST['password'] ?? '');

    // Try DB-backed admin user first (table created by setup), then fallback to env var, then to a safe test password for setup.
    $pdo = connectDatabase();
    $storedHash = null;
    if ($pdo !== null) {
        try {
            $stmt = $pdo->prepare('SELECT password_hash FROM gallery_admins WHERE username = :username LIMIT 1');
            $stmt->execute([':username' => 'admin']);
            $row = $stmt->fetch();
            if ($row && !empty($row['password_hash'])) {
                $storedHash = $row['password_hash'];
            }
        } catch (Throwable $e) {
            // ignore and fallback
        }
    }

    if ($storedHash === null) {
        $storedHash = getenv('GALLERY_ADMIN_PASSWORD_HASH') ?: null;
    }

    $ok = false;
    if ($storedHash !== null) {
        $ok = password_verify($password, $storedHash);
    } else {
        // initial safe default for first-time setup/testing only
        $ok = ($password === 'pwd');
    }

    if (!$ok) {
        jsonError('Incorrect password.', 401);
    }

    session_regenerate_id(true);
    $_SESSION['gallery_admin_authenticated'] = true;
    $_SESSION['gallery_admin_user'] = 'admin';

    // issue CSRF token for subsequent state-changing requests
    $csrf = bin2hex(random_bytes(32));
    $_SESSION['gallery_csrf_token'] = $csrf;

    echo json_encode([
        'success' => true,
        'authenticated' => true,
        'csrf' => $csrf,
    ]);
    exit;
}

if ($action === 'logout') {
    unset($_SESSION['gallery_admin_authenticated']);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'list') {
    $pdo = connectDatabase();
    if ($pdo === null) {
        echo json_encode([]);
        exit;
    }

    try {
        $statement = $pdo->query("SELECT * FROM gallery_items WHERE is_active = 1 ORDER BY created_at DESC");
        $items = array_map('normalizeGalleryItem', $statement->fetchAll());
        echo json_encode($items);
        exit;
    } catch (Throwable $e) {
        echo json_encode([]);
        exit;
    }
}

if ($action === 'upload') {
    requireAuth();

    // CSRF token required for state-changing operations
    $csrf = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['gallery_csrf_token']) || !hash_equals($_SESSION['gallery_csrf_token'], (string)$csrf)) {
        jsonError('Invalid CSRF token.', 403);
    }

    $pdo = connectDatabase();
    if ($pdo === null) {
        jsonError('The gallery database is not configured yet. Update the credentials in the PHP config.', 503);
    }

    $thumbFile = $_FILES['thumbnail'] ?? $_FILES['thumb'] ?? null;
    $fullFile = $_FILES['fullImage'] ?? $_FILES['full'] ?? null;
    $title = trim((string)($_POST['title'] ?? ''));
    $medium = trim((string)($_POST['medium'] ?? ''));
    $genre = trim((string)($_POST['genre'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));

    if (!$title || !$thumbFile || !$fullFile) {
        jsonError('Please provide the title, thumbnail, and detail image.', 400);
    }

    // Basic upload validation
    $allowedMimes = [
        'image/jpeg' => '.jpg',
        'image/png' => '.png',
        'image/webp' => '.webp',
    ];
    $maxThumbBytes = 1 * 1024 * 1024; // 1MB
    $maxFullBytes = 6 * 1024 * 1024; // 6MB

    try {
        ensureUploadDirectories();

        if (!is_uploaded_file($thumbFile['tmp_name']) || !is_uploaded_file($fullFile['tmp_name'])) {
            jsonError('Invalid upload file.', 400);
        }

        if ($thumbFile['size'] > $maxThumbBytes || $fullFile['size'] > $maxFullBytes) {
            jsonError('One or more files exceed the allowed size.', 400);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $thumbMime = $finfo->file($thumbFile['tmp_name']);
        $fullMime = $finfo->file($fullFile['tmp_name']);

        if (!isset($allowedMimes[$thumbMime]) || !isset($allowedMimes[$fullMime])) {
            jsonError('Only JPG, PNG and WEBP images are supported.', 400);
        }

        // create random filenames to avoid collisions and prevent disclosure of original names
        $baseName = bin2hex(random_bytes(12));
        $thumbExtension = $allowedMimes[$thumbMime];
        $fullExtension = $allowedMimes[$fullMime];

        $thumbFilename = $baseName . '-thumb' . $thumbExtension;
        $fullFilename = $baseName . '-full' . $fullExtension;

        $thumbTarget = __DIR__ . '/uploads/gallery/thumbs/' . $thumbFilename;
        $fullTarget = __DIR__ . '/uploads/gallery/full/' . $fullFilename;

        if (!move_uploaded_file($thumbFile['tmp_name'], $thumbTarget)) {
            jsonError('Could not save the thumbnail image.', 500);
        }

        if (!move_uploaded_file($fullFile['tmp_name'], $fullTarget)) {
            // cleanup thumb if full fails
            if (is_file($thumbTarget)) {
                unlink($thumbTarget);
            }
            jsonError('Could not save the detail image.', 500);
        }

        $baseSlug = slugify($title) . '-' . time();
        $thumbnailUrl = '/uploads/gallery/thumbs/' . $thumbFilename;
        $fullUrl = '/uploads/gallery/full/' . $fullFilename;

        $statement = $pdo->prepare(
            'INSERT INTO gallery_items (slug, title, medium, genre, description, thumbnail_path, full_path, date_added, sold, disabled, is_active, created_at)
             VALUES (:slug, :title, :medium, :genre, :description, :thumbnail_path, :full_path, CURDATE(), 0, 0, 1, NOW())'
        );

        $statement->execute([
            ':slug' => $baseSlug,
            ':title' => $title,
            ':medium' => $medium,
            ':genre' => $genre,
            ':description' => $description,
            ':thumbnail_path' => $thumbnailUrl,
            ':full_path' => $fullUrl,
        ]);

        echo json_encode([
            'success' => true,
            'slug' => $baseSlug,
            'item' => normalizeGalleryItem([
                'slug' => $baseSlug,
                'title' => $title,
                'medium' => $medium,
                'genre' => $genre,
                'description' => $description,
                'thumbnail' => $thumbnailUrl,
                'full' => $fullUrl,
                'dateAdded' => date('Y-m-d'),
                'sold' => false,
                'disabled' => false,
            ]),
        ]);
        exit;
    } catch (Throwable $e) {
        jsonError('Could not save the artwork: ' . $e->getMessage(), 500);
    }
}

if ($action === 'delete') {
    requireAuth();

    // CSRF required for deletes
    $csrf = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['gallery_csrf_token']) || !hash_equals($_SESSION['gallery_csrf_token'], (string)$csrf)) {
        jsonError('Invalid CSRF token.', 403);
    }

    $pdo = connectDatabase();
    if ($pdo === null) {
        jsonError('The gallery database is not configured yet.', 503);
    }

    $slug = trim((string)($_GET['slug'] ?? $_POST['slug'] ?? ''));
    if ($slug === '') {
        jsonError('No artwork slug supplied.', 400);
    }

    try {
        $row = $pdo->prepare('SELECT thumbnail_path, full_path FROM gallery_items WHERE slug = :slug LIMIT 1');
        $row->execute([':slug' => $slug]);
        $item = $row->fetch();

        if ($item) {
            $paths = [$item['thumbnail_path'] ?? '', $item['full_path'] ?? ''];
            foreach ($paths as $path) {
                if ($path !== '') {
                    $filePath = __DIR__ . preg_replace('#^/+#', '/', $path);
                    if (is_file($filePath)) {
                        unlink($filePath);
                    }
                }
            }
        }

        $pdo->prepare('DELETE FROM gallery_items WHERE slug = :slug')->execute([':slug' => $slug]);
        echo json_encode(['success' => true]);
        exit;
    } catch (Throwable $e) {
        jsonError('Could not remove the artwork: ' . $e->getMessage(), 500);
    }
}

jsonError('Unsupported action.', 400);