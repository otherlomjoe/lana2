<?php
require __DIR__ . '/gallery-lib.php';
session_start();
if (empty($_SESSION['gallery_admin_authenticated']) || $_SESSION['gallery_admin_authenticated'] !== true) {
    header('Location: /gallery/admin-login.php');
    exit;
}
// CSRF check
$csrf = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['gallery_csrf_token']) || !hash_equals($_SESSION['gallery_csrf_token'], (string) $csrf)) {
    $_SESSION['gallery_admin_message'] = 'Invalid CSRF token.';
    header('Location: /gallery/admin-list-exhibitions.php');
    exit;
}
$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['gallery_admin_message'] = 'Invalid exhibition ID.';
    header('Location: /gallery/admin-list-exhibitions.php');
    exit;
}
$pdo = gallery_init_db();
if (!$pdo) {
    $_SESSION['gallery_admin_message'] = 'Database unavailable.';
    header('Location: /gallery/admin-list-exhibitions.php');
    exit;
}
$stmt = $pdo->prepare('SELECT active FROM exhibitions WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$row = $stmt->fetch();
if (!$row) {
    $_SESSION['gallery_admin_message'] = 'Exhibition not found.';
    header('Location: /gallery/admin-list-exhibitions.php');
    exit;
}
$current = !empty($row['active']);
$updated = gallery_set_exhibition_active($id, !$current);
$msg = $updated ? 'Exhibition visibility updated.' : 'Could not update visibility.';
// if AJAX, return JSON
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' || ($_POST['ajax'] ?? $_GET['ajax'] ?? '') === '1') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => (bool) $updated, 'active' => $updated ? (!$current) : $current, 'message' => $msg]);
    exit;
}
$_SESSION['gallery_admin_message'] = $msg;
header('Location: /gallery/admin-list-exhibitions.php');
exit;