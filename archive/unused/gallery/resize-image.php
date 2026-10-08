<?php
require __DIR__ . '/gallery-lib.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $source = (string) ($_GET['source'] ?? $_POST['source'] ?? '');
    $target = (string) ($_GET['target'] ?? $_POST['target'] ?? '');
    $maxWidth = max(1, (int) ($_GET['maxWidth'] ?? $_POST['maxWidth'] ?? 1200));
    $maxHeight = max(1, (int) ($_GET['maxHeight'] ?? $_POST['maxHeight'] ?? 1200));

    if ($source === '' || $target === '') {
        throw new InvalidArgumentException('Source and target files are required.');
    }

    $ok = gallery_resize_image($source, $target, $maxWidth, $maxHeight);
    echo json_encode(['success' => $ok]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
