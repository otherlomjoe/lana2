<?php
require __DIR__ . '/gallery-lib.php';

session_start();
if (empty($_SESSION['gallery_admin_authenticated']) || $_SESSION['gallery_admin_authenticated'] !== true) {
    header('Location: /gallery/admin-login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /gallery/admin-list-images.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$asset = (string) ($_POST['asset'] ?? '');
if ($id > 0 && gallery_remove_image_asset($id, $asset)) {
    $_SESSION['gallery_admin_message'] = ucfirst($asset) . ' image removed from storage.';
}
header('Location: /gallery/admin-edit.php?id=' . $id);
exit;
