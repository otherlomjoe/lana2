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
    if ($id <= 0) throw new InvalidArgumentException('A valid hero is required.');
    if ($action === 'visibility') {
        gallery_set_home_hero_visibility($id, !empty($_POST['visible']));
        $_SESSION['gallery_admin_message'] = !empty($_POST['visible']) ? 'Hero published.' : 'Hero hidden.';
    } elseif ($action === 'shift') {
        gallery_shift_home_hero($id, (int) ($_POST['direction'] ?? 0));
        $_SESSION['gallery_admin_message'] = 'Hero order updated.';
    } elseif ($action === 'duplicate') {
        $newId = gallery_duplicate_home_hero($id);
        $_SESSION['gallery_admin_message'] = 'Hero duplicated as a hidden draft.';
        header('Location: /gallery/admin-hero-edit.php?id=' . $newId, true, 303);
        exit;
    } elseif ($action === 'hard-delete') {
        if (!gallery_hard_delete_home_hero($id)) throw new RuntimeException('Hero could not be deleted.');
        $_SESSION['gallery_admin_message'] = 'Hero permanently deleted.';
    } else {
        throw new InvalidArgumentException('Unknown hero action.');
    }
} catch (Throwable $e) {
    $_SESSION['gallery_admin_message_error'] = $e->getMessage();
}
header('Location: /gallery/admin-list-heroes.php', true, 303);
exit;
