<?php
declare(strict_types=1);

require __DIR__ . '/gallery/gallery-lib.php';

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
session_set_cookie_params([
    'lifetime' => 60 * 60 * 4,
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
header('Content-Type: application/json; charset=utf-8');

function galleryApiError(string $message, int $status = 400): void
{
    http_response_code($status);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

function galleryApiRequireAuth(): void
{
    if (empty($_SESSION['gallery_admin_authenticated']) || $_SESSION['gallery_admin_authenticated'] !== true) {
        galleryApiError('Unauthorized. Please log in to manage the gallery.', 401);
    }
}

function galleryApiEnsureCsrf(): void
{
    $csrf = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    if (empty($_SESSION['gallery_csrf_token']) || !hash_equals($_SESSION['gallery_csrf_token'], (string) $csrf)) {
        galleryApiError('Invalid CSRF token.', 403);
    }
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($action === 'status') {
    echo json_encode([
        'success' => true,
        'authenticated' => !empty($_SESSION['gallery_admin_authenticated']) && $_SESSION['gallery_admin_authenticated'] === true,
    ]);
    exit;
}

if ($action === 'list-home-heroes') {
    header('Cache-Control: public, max-age=60, stale-while-revalidate=300');
    echo json_encode(['success' => true, 'heroes' => gallery_list_home_heroes(false)], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'list-slider-images') {
    header('Cache-Control: public, max-age=60, stale-while-revalidate=300');
    echo json_encode(['success' => true, 'slides' => gallery_list_slider_images(false)], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'login') {
    $password = (string) ($_POST['password'] ?? '');
    $storedHash = getenv('GALLERY_ADMIN_PASSWORD_HASH');

    if ($storedHash === false || $storedHash === '') {
        galleryApiError('Admin authentication is not configured.', 500);
    }

    if (!password_verify($password, $storedHash)) {
        galleryApiError('Incorrect password.', 401);
    }

    session_regenerate_id(true);
    $_SESSION['gallery_admin_authenticated'] = true;
    $_SESSION['gallery_admin_user'] = 'admin';
    $_SESSION['gallery_csrf_token'] = bin2hex(random_bytes(32));

    echo json_encode([
        'success' => true,
        'authenticated' => true,
        'csrf' => $_SESSION['gallery_csrf_token'],
    ]);
    exit;
}

if ($action === 'logout') {
    unset($_SESSION['gallery_admin_authenticated'], $_SESSION['gallery_admin_user'], $_SESSION['gallery_csrf_token']);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'db-setup') {
    $pdo = gallery_init_db();
    if (!$pdo) {
        galleryApiError('Database connection failed.', 500);
    }

    echo json_encode(['success' => true, 'message' => 'Gallery database ready.']);
    exit;
}

if ($action === 'list' || $action === 'list-images') {
    $items = array_map('gallery_public_image', gallery_list_images(gallery_init_db(), false));
    echo json_encode($items);
    exit;
}

if ($action === 'item') {
    $pdo = gallery_init_db();
    if (!$pdo) {
        galleryApiError('Database connection failed.', 500);
    }
    $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
    $slug = trim((string) ($_GET['slug'] ?? $_POST['slug'] ?? ''));
    if ($id <= 0 && $slug === '') {
        galleryApiError('No id or slug supplied.', 400);
    }

    $stmt = $pdo->prepare('SELECT i.*, GROUP_CONCAT(DISTINCT t.name) AS tag_names, GROUP_CONCAT(DISTINCT e.slug) AS exhibition_slugs FROM images i LEFT JOIN image_tags it ON it.image_id = i.id LEFT JOIN tags t ON t.id = it.tag_id LEFT JOIN image_exhibitions ie ON ie.image_id = i.id LEFT JOIN exhibitions e ON e.id = ie.exhibition_id WHERE ' . ($id > 0 ? 'i.id = :id' : 'i.slug = :slug') . ' GROUP BY i.id LIMIT 1');
    $params = [];
    if ($id > 0) {
        $params[':id'] = $id;
    } else {
        $params[':slug'] = $slug;
    }
    $stmt->execute($params);
    $row = $stmt->fetch();
    if (!$row) {
        galleryApiError('Item not found.', 404);
    }
    $item = gallery_normalize_image_row($row);
    if (empty($_SESSION['gallery_admin_authenticated']) || $_SESSION['gallery_admin_authenticated'] !== true) {
        $item = gallery_public_image($item);
    }
    echo json_encode(['success' => true, 'item' => $item]);
    exit;
}

if ($action === 'list-exhibitions') {
    echo json_encode(gallery_list_exhibitions(gallery_init_db()));
    exit;
}

if ($action === 'dropdowns') {
    $pdo = gallery_init_db();
    if (!$pdo) {
        galleryApiError('Database connection failed.', 500);
    }

    echo json_encode(gallery_lookup_values($pdo));
    exit;
}

if ($action === 'search') {
    $filters = $_GET;
    if (empty($filters) && !empty($_POST)) {
        $filters = $_POST;
    }

    $page = max(1, (int) ($filters['page'] ?? 1));
    $limit = max(1, min(100, (int) ($filters['limit'] ?? 20)));
    unset($filters['page'], $filters['limit'], $filters['action']);

    $result = gallery_search_images($filters, $page, $limit);
    echo json_encode(['success' => true, 'result' => $result]);
    exit;
}

if ($action === 'upload') {
    galleryApiRequireAuth();
    galleryApiEnsureCsrf();

    $result = gallery_save_image($_POST, $_FILES ?? []);
    echo json_encode(['success' => true, 'result' => $result]);
    exit;
}

if ($action === 'save-exhibition') {
    galleryApiRequireAuth();
    galleryApiEnsureCsrf();

    $payload = $_POST;
    if (empty($payload) && !empty($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json')) {
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw ?: '{}', true) ?: [];
    }

    $result = gallery_save_exhibition($payload, $_FILES ?? []);
    echo json_encode(['success' => true, 'result' => $result]);
    exit;
}

if ($action === 'delete') {
    galleryApiRequireAuth();
    galleryApiEnsureCsrf();

    $id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
    $slug = trim((string) ($_POST['slug'] ?? $_GET['slug'] ?? ''));
    $deleted = false;

    if ($id > 0) {
        $deleted = gallery_soft_delete_image($id);
    } elseif ($slug !== '') {
        $pdo = gallery_init_db();
        $stmt = $pdo->prepare('SELECT id FROM images WHERE slug = :slug LIMIT 1');
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch();
        $deleted = $row ? gallery_soft_delete_image((int) $row['id']) : false;
    } else {
        galleryApiError('No image id or slug supplied.', 400);
    }

    echo json_encode(['success' => $deleted]);
    exit;
}

if ($action === 'restore') {
    galleryApiRequireAuth();
    galleryApiEnsureCsrf();
    $id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
    echo json_encode(['success' => $id > 0 && gallery_restore_image($id)]);
    exit;
}

if ($action === 'hard-delete') {
    galleryApiRequireAuth();
    galleryApiEnsureCsrf();
    $id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
    $pdo = gallery_init_db();
    $stmt = $pdo ? $pdo->prepare('SELECT deleted_at FROM images WHERE id = :id LIMIT 1') : null;
    $deleted = false;
    if ($stmt) {
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        $deleted = $id > 0 && $row && !empty($row['deleted_at']) && gallery_hard_delete_image($id);
    }
    echo json_encode(['success' => $deleted]);
    exit;
}

if ($action === 'delete-exhibition') {
    galleryApiRequireAuth();
    galleryApiEnsureCsrf();

    $id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
    $pdo = gallery_init_db();
    if (!$pdo) {
        galleryApiError('Database connection failed.', 500);
    }

    $stmt = $pdo->prepare('DELETE FROM exhibitions WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $pdo->prepare('DELETE FROM image_exhibitions WHERE exhibition_id = :id')->execute([':id' => $id]);

    echo json_encode(['success' => true]);
    exit;
}

json_encode(['success' => false, 'error' => 'Unsupported action.']);
http_response_code(400);
exit;
