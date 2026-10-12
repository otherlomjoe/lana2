<?php
require __DIR__ . '/admin-auth.php';

// admin_auth will start the session if needed and produce an appropriate response
gallery_require_admin();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Accept either application/json or form-encoded
$for = null;
if (!empty($_POST['for'])) {
    $for = $_POST['for'];
} else {
    $raw = file_get_contents('php://input');
    if ($raw) {
        $json = @json_decode($raw, true);
        if (is_array($json) && isset($json['for'])) $for = $json['for'];
    }
}

// Normalise: if numeric, treat as image id
if ($for !== null && is_numeric($for)) {
    $for = (int) $for;
}

// Mark dismissed and clear any stored warnings for the session
$_SESSION['gallery_admin_warnings_dismissed_for'] = $for;
unset($_SESSION['gallery_admin_warnings'], $_SESSION['gallery_admin_warnings_for_id']);

echo json_encode(['success' => true, 'dismissed_for' => $for]);
