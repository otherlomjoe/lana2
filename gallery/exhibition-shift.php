<?php
require __DIR__ . '/gallery-lib.php';
session_start();
if (empty($_SESSION['gallery_admin_authenticated']) || $_SESSION['gallery_admin_authenticated'] !== true) {
    header('Location: /gallery/admin-login.php');
    exit;
}
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$direction = (int) ($_GET['direction'] ?? $_POST['direction'] ?? 0);
if ($id <= 0 || !in_array($direction, [-1, 1], true)) {
    $_SESSION['gallery_admin_message'] = 'Invalid shift parameters.';
    header('Location: /gallery/admin-list-exhibitions.php');
    exit;
}
// CSRF protection for POST actions
$csrf = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
if (empty($_SESSION['gallery_csrf_token']) || !hash_equals((string) $_SESSION['gallery_csrf_token'], (string) $csrf)) {
    $err = 'Invalid CSRF token.';
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' || ($_POST['ajax'] ?? $_GET['ajax'] ?? '') === '1') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => $err]);
        exit;
    }
    $_SESSION['gallery_admin_message'] = $err;
    header('Location: /gallery/admin-list-exhibitions.php');
    exit;
}

try {
    $moved = gallery_shift_exhibition($id, $direction);
    $msg = $moved ? 'Exhibition order updated.' : 'Could not move exhibition.';
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' || ($_POST['ajax'] ?? $_GET['ajax'] ?? '') === '1') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => (bool) $moved, 'message' => $msg]);
        exit;
    }
    $_SESSION['gallery_admin_message'] = $msg;
} catch (Throwable $e) {
    $err = 'Error updating exhibition order: ' . $e->getMessage();
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' || ($_POST['ajax'] ?? $_GET['ajax'] ?? '') === '1') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => $err]);
        exit;
    }
    $_SESSION['gallery_admin_message'] = $err;
}
header('Location: /gallery/admin-list-exhibitions.php');
exit;