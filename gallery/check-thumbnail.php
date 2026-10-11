<?php
require __DIR__ . '/gallery-lib.php';

session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['gallery_admin_authenticated']) || $_SESSION['gallery_admin_authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$basename = trim((string) ($_GET['basename'] ?? $_POST['basename'] ?? ''));
if ($basename === '') {
    echo json_encode(['success' => true, 'exists' => false, 'items' => []]);
    exit;
}

$pdo = gallery_init_db();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database unavailable']);
    exit;
}

// look for thumbnails that contain the basename (case-insensitive)
try {
    $like = '%' . $basename . '%';
    $stmt = $pdo->prepare('SELECT id, title, thumbnail_file, thumbnail_url FROM images WHERE (thumbnail_file LIKE :like OR thumbnail_url LIKE :like) AND deleted_at IS NULL');
    $stmt->execute([':like' => $like]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $items = [];
    foreach ($rows as $r) {
        $items[] = [
            'id' => (int) $r['id'],
            'title' => (string) $r['title'],
            'thumbnail_file' => $r['thumbnail_file'],
            'thumbnail_url' => $r['thumbnail_url'],
        ];
    }
    echo json_encode(['success' => true, 'exists' => count($items) > 0, 'items' => $items]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
