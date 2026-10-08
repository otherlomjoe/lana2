<?php
require __DIR__ . '/gallery-lib.php';
session_start();
if (empty($_SESSION['gallery_admin_authenticated']) || $_SESSION['gallery_admin_authenticated'] !== true) {
    http_response_code(401);
    exit('Unauthorized');
}

$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
$exhibitionId = (int) ($_POST['exhibition_id'] ?? $_GET['exhibition_id'] ?? 0);
$direction = (int) ($_POST['direction'] ?? $_GET['direction'] ?? 0);
if ($id <= 0 || $exhibitionId <= 0 || !in_array($direction, [-1, 1], true)) {
    $_SESSION['gallery_admin_message'] = 'Invalid shift parameters.';
    header('Location: /gallery/admin-list-images.php', true, 303);
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
    header('Location: /gallery/admin-list-images.php', true, 303);
    exit;
}

try {
    $moved = gallery_shift_image_in_exhibition($id, $exhibitionId, $direction);
    $msg = $moved ? 'Image order updated.' : 'Could not move image within exhibition.';
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' || ($_POST['ajax'] ?? $_GET['ajax'] ?? '') === '1') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => (bool) $moved, 'message' => $msg]);
        exit;
    }
    $_SESSION['gallery_admin_message'] = $msg;
} catch (Throwable $e) {
    $err = 'Error updating image order: ' . $e->getMessage();
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' || ($_POST['ajax'] ?? $_GET['ajax'] ?? '') === '1') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => $err]);
        exit;
    }
    $_SESSION['gallery_admin_message'] = $err;
}
header('Location: /gallery/admin-list-images.php', true, 303);
exit;
