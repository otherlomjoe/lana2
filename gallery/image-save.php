<?php
require __DIR__ . '/gallery-lib.php';

session_start();

$isFormSubmission = $_SERVER['REQUEST_METHOD'] === 'POST'
    && !str_contains(strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json');

function imageSaveError(string $message, int $status): void
{
    if ($GLOBALS['isFormSubmission']) {
        $_SESSION['gallery_admin_message_error'] = $message;
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $target = $id > 0 ? '/gallery/admin-edit.php?id=' . $id : '/gallery/admin-upload.php';
        header('Location: ' . $target, true, 303);
        exit;
    }

    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

try {
    if (empty($_SESSION['gallery_admin_authenticated']) || $_SESSION['gallery_admin_authenticated'] !== true) {
        imageSaveError('Unauthorized', 401);
    }

    $payload = $_POST;
    if (empty($payload) && !empty($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json')) {
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw ?: '{}', true) ?: [];
    }

    $result = gallery_save_image($payload, $_FILES ?? []);

    if ($isFormSubmission) {
        $_SESSION['gallery_admin_message'] = 'Image saved successfully.';
        $saveMode = ($_POST['save_mode'] ?? 'list');
        if ($saveMode === 'stay') {
            $destination = '/gallery/admin-edit.php?id=' . (int) $result['id'];
        } else {
            $destination = '/gallery/admin-list-images.php?saved=1';
            if (!empty($_POST['exhibition'])) {
                $destination = '/gallery/admin-list-images.php?exhibition=' . (int) $_POST['exhibition'] . '&saved=1';
            }
        }
        header('Location: ' . $destination, true, 303);
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'result' => $result]);
} catch (Throwable $e) {
    imageSaveError($e->getMessage(), 400);
}
