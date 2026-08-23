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
    $items = gallery_list_images(gallery_init_db());
    echo json_encode($items);
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

    $result = gallery_save_exhibition($payload);
    echo json_encode(['success' => true, 'result' => $result]);
    exit;
}

if ($action === 'delete') {
    galleryApiRequireAuth();
    galleryApiEnsureCsrf();

    $id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
    $slug = trim((string) ($_POST['slug'] ?? $_GET['slug'] ?? ''));

    if ($id > 0) {
        $deleted = gallery_delete_image($id);
    } elseif ($slug !== '') {
        $pdo = gallery_init_db();
        $stmt = $pdo->prepare('SELECT id FROM images WHERE slug = :slug LIMIT 1');
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch();
        $deleted = $row ? gallery_delete_image((int) $row['id']) : false;
    } else {
        galleryApiError('No image id or slug supplied.', 400);
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
