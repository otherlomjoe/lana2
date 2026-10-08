<?php
require __DIR__ . '/gallery-lib.php';

session_start();
if (empty($_SESSION['gallery_admin_authenticated']) || $_SESSION['gallery_admin_authenticated'] !== true) {
    http_response_code(401);
    exit('Unauthorized');
}

$id = (int) ($_POST['id'] ?? 0);
try {
    $csrf = (string) ($_POST['csrf'] ?? '');
    if (empty($_SESSION['gallery_csrf_token']) || !hash_equals((string) $_SESSION['gallery_csrf_token'], $csrf)) {
        throw new RuntimeException('Invalid CSRF token.');
    }
    $result = gallery_save_slider_image($_POST, $_FILES ?? []);
    $_SESSION['gallery_admin_message'] = 'Slide saved successfully.';
    $destination = ($_POST['save_mode'] ?? 'list') === 'stay' ? '/gallery/admin-slider-edit.php?id=' . (int) $result['id'] : '/gallery/admin-list-sliders.php';
    header('Location: ' . $destination, true, 303);
    exit;
} catch (Throwable $e) {
    $_SESSION['gallery_admin_message_error'] = $e->getMessage();
    header('Location: /gallery/admin-slider-edit.php' . ($id > 0 ? '?id=' . $id : ''), true, 303);
    exit;
}
