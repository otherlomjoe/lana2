<?php
// admin-exhibition-hero.php has been removed and its functionality folded into the exhibition edit page.
// Redirect to the exhibitions list so admins use the exhibition edit UI to assign heroes per-exhibition.
session_start();
if (empty($_SESSION['gallery_admin_authenticated']) || $_SESSION['gallery_admin_authenticated'] !== true) {
    header('Location: /gallery/admin-login.php');
    exit;
}
header('Location: /gallery/admin-list-exhibitions.php', true, 303);
exit;
?>