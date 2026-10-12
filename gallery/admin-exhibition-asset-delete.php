<?php
require __DIR__ . '/gallery-lib.php';
require __DIR__ . '/admin-auth.php';
gallery_require_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $asset = (string) ($_POST['asset'] ?? '');
    if ($id > 0 && gallery_remove_exhibition_asset($id, $asset)) {
        $_SESSION['gallery_admin_message'] = ucfirst($asset) . ' image removed from storage.';
    }
    header('Location: /gallery/admin-exhibition-edit.php?id=' . $id);
    exit;
}
header('Location: /gallery/admin-list-exhibitions.php');
exit;
