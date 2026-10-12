<?php
require __DIR__ . '/gallery-lib.php';

require __DIR__ . '/admin-auth.php';
gallery_require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /gallery/admin-list-images.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$asset = (string) ($_POST['asset'] ?? '');
if ($id > 0 && gallery_remove_image_asset($id, $asset)) {
    $_SESSION['gallery_admin_message'] = ucfirst($asset) . ' image removed from storage.';
}
header('Location: /gallery/admin-image-edit.php?id=' . $id);
exit;
