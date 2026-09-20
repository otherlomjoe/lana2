<?php
require __DIR__ . '/gallery-lib.php';

session_start();
if (empty($_SESSION['gallery_admin_authenticated']) || $_SESSION['gallery_admin_authenticated'] !== true) {
    http_response_code(401);
    exit('Unauthorized');
}

try {
    $csrf = (string) ($_POST['csrf'] ?? '');
    if (empty($_SESSION['gallery_csrf_token']) || !hash_equals((string) $_SESSION['gallery_csrf_token'], $csrf)) {
        throw new RuntimeException('Invalid CSRF token.');
    }
    $id = (int) ($_POST['id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    if ($id <= 0) throw new InvalidArgumentException('A valid slide is required.');
    if ($action === 'active') {
        gallery_set_slider_image_active($id, !empty($_POST['active']));
        $_SESSION['gallery_admin_message'] = !empty($_POST['active']) ? 'Slide turned on.' : 'Slide turned off.';
    } elseif ($action === 'shift') {
        gallery_shift_slider_image($id, (int) ($_POST['direction'] ?? 0));
        $_SESSION['gallery_admin_message'] = 'Slide order updated.';
    } elseif ($action === 'hard-delete') {
        if (!gallery_hard_delete_slider_image($id)) throw new RuntimeException('Slide could not be deleted.');
        $_SESSION['gallery_admin_message'] = 'Slide permanently deleted.';
    } else {
        throw new InvalidArgumentException('Unknown slide action.');
    }
} catch (Throwable $e) {
    $_SESSION['gallery_admin_message_error'] = $e->getMessage();
}
header('Location: /gallery/admin-list-sliders.php', true, 303);
exit;
