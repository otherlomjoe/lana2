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
        $saveMode = ($_POST['save_mode'] ?? 'list');
        $destination = $saveMode === 'stay' ? ('/gallery/admin-exhibition-edit.php?id=' . (int) $result['id']) : '/gallery/admin-list-exhibitions.php';
        header('Location: ' . $destination, true, 303);
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'result' => $result]);
} catch (Throwable $e) {
    if ($isFormSubmission) {
        $_SESSION['gallery_admin_message_error'] = $e->getMessage();
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $target = $id > 0 ? '/gallery/admin-exhibition-edit.php?id=' . $id : '/gallery/admin-exhibition-edit.php';
        header('Location: ' . $target, true, 303);
        exit;
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
