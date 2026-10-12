<?php
require __DIR__ . '/gallery-lib.php';

require __DIR__ . '/admin-auth.php';
gallery_require_admin();

$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
if ($id > 0) {
    $pdo = gallery_init_db();
    $stmt = $pdo->prepare('SELECT deleted_at FROM images WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $item = $stmt->fetch();
    if ($item && !empty($item['deleted_at']) && gallery_hard_delete_image($id)) {
        $_SESSION['gallery_admin_message'] = 'Image permanently deleted.';
    }
}
header('Location: /gallery/admin-list-images.php');
exit;
