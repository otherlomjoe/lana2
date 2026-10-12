<?php
require __DIR__ . '/gallery-lib.php';

require __DIR__ . '/admin-auth.php';
gallery_require_admin();

$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
if ($id > 0) {
    gallery_restore_image($id);
    $_SESSION['gallery_admin_message'] = 'Image restored successfully.';
}
header('Location: /gallery/admin-list-images.php');
exit;
