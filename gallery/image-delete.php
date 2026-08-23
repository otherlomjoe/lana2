<?php
require __DIR__ . '/gallery-lib.php';

session_start();
if (empty($_SESSION['gallery_admin_authenticated']) || $_SESSION['gallery_admin_authenticated'] !== true) {
    header('Location: /gallery/admin-login.php');
    exit;
}

$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
if ($id > 0) {
    gallery_soft_delete_image($id);
    $_SESSION['gallery_admin_message'] = 'Image moved to deleted items.';
}
header('Location: /gallery/admin-list-images.php');
exit;
