<?php
require __DIR__ . '/gallery-lib.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $page = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
    $limit = max(1, min(100, (int) ($_GET['limit'] ?? $_POST['limit'] ?? 20)));
    $filters = $_GET;
    if (empty($filters) && !empty($_POST)) {
        $filters = $_POST;
    }

    $result = gallery_search_images($filters, $page, $limit);
    echo json_encode(['success' => true, 'result' => $result]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
