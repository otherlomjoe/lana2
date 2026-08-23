<?php
require __DIR__ . '/gallery-lib.php';

session_start();
$isFormSubmission = $_SERVER['REQUEST_METHOD'] === 'POST'
    && !str_contains(strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json');

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

    $result = gallery_save_exhibition($payload, $_FILES ?? []);
    if ($isFormSubmission) {
        $_SESSION['gallery_admin_message'] = 'Exhibition saved successfully.';
        header('Location: /gallery/admin-list-exhibitions.php', true, 303);
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'result' => $result]);
} catch (Throwable $e) {
    if ($isFormSubmission) {
        http_response_code(400);
        echo '<h1>Save failed</h1><p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
        exit;
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
