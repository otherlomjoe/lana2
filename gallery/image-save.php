<?php
require __DIR__ . '/gallery-lib.php';

session_start();

header('Content-Type: application/json; charset=utf-8');

try {
    if (empty($_SESSION['gallery_admin_authenticated']) || $_SESSION['gallery_admin_authenticated'] !== true) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    $payload = $_POST;
    if (empty($payload) && !empty($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json')) {
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw ?: '{}', true) ?: [];
    }

    $result = gallery_save_image($payload, $_FILES ?? []);
    echo json_encode(['success' => true, 'result' => $result]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
