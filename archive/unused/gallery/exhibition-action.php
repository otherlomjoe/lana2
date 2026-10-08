<?php
require __DIR__ . '/gallery-lib.php';

session_start();
if (empty($_SESSION['gallery_admin_authenticated']) || $_SESSION['gallery_admin_authenticated'] !== true) {
    http_response_code(401);
    exit('Unauthorized');
}

try {
    $csrf = (string) ($_POST['csrf'] ?? $_POST['csrf_token'] ?? '');
    if (empty($_SESSION['gallery_csrf_token']) || !hash_equals((string) $_SESSION['gallery_csrf_token'], $csrf)) {
        throw new RuntimeException('Invalid CSRF token.');
    }

    $id = (int) ($_POST['id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');

    if ($id <= 0) throw new InvalidArgumentException('A valid exhibition is required.');

    if ($action === 'shift') {
        $direction = (int) ($_POST['direction'] ?? 0);
        gallery_shift_exhibition($id, $direction);
        $_SESSION['gallery_admin_message'] = 'Exhibition order updated.';
    } elseif ($action === 'visibility' || $action === 'toggle') {
        $explicit = isset($_POST['active']) ? (int) $_POST['active'] : null;
        if ($explicit === null) {
            // toggle current
            $pdo = gallery_init_db();
            if (!$pdo) throw new RuntimeException('Database unavailable.');
            $stmt = $pdo->prepare('SELECT active FROM exhibitions WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();
            if (!$row) throw new InvalidArgumentException('Exhibition not found.');
            $new = !empty($row['active']);
            $new = !$new;
        } else {
            $new = (bool) $explicit;
        }
        $updated = gallery_set_exhibition_active($id, $new);
        if (!$updated) throw new RuntimeException('Could not update visibility.');
        $_SESSION['gallery_admin_message'] = $updated ? 'Exhibition visibility updated.' : 'Could not update visibility.';
    } else {
        throw new InvalidArgumentException('Unknown exhibition action.');
    }
} catch (Throwable $e) {
    $_SESSION['gallery_admin_message_error'] = $e->getMessage();
}

// If AJAX requested, return JSON summary, otherwise redirect back to list
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' || ($_POST['ajax'] ?? '') === '1') {
    $error = $_SESSION['gallery_admin_message_error'] ?? null;
    $message = $_SESSION['gallery_admin_message'] ?? ($error ? $error : '');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => $error ? false : true, 'message' => $message]);
    exit;
}

header('Location: /gallery/admin-list-exhibitions.php', true, 303);
exit;
