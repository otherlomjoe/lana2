<?php
require __DIR__ . '/gallery-lib.php';

session_start();
if (empty($_SESSION['gallery_admin_authenticated']) || $_SESSION['gallery_admin_authenticated'] !== true) {
    header('Location: /gallery/admin-login.php');
    exit;
}

$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
if ($id > 0) {
    $pdo = gallery_init_db();
    if ($pdo) {
        $pdo->prepare('DELETE FROM image_exhibitions WHERE exhibition_id = :id')->execute([':id' => $id]);
        $pdo->prepare('DELETE FROM exhibitions WHERE id = :id')->execute([':id' => $id]);
        $_SESSION['gallery_admin_message'] = 'Exhibition deleted.';
    }
}
header('Location: /gallery/admin-list-exhibitions.php');
exit;
