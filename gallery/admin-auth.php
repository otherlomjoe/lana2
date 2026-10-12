<?php
// Centralized admin auth utilities for the gallery admin area.
// Require this file and call gallery_require_admin() at the top of any admin page
// or admin API endpoint to ensure only authenticated admins can access.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function gallery_current_admin(): ?array
{
    return (!empty($_SESSION['gallery_admin_authenticated']) && $_SESSION['gallery_admin_authenticated'] === true && !empty($_SESSION['gallery_admin_user']))
        ? $_SESSION['gallery_admin_user']
        : null;
}

function gallery_require_admin(): void
{
    // basic authenticated check; redirects to login for HTML pages and returns 401 for API calls
    if (empty($_SESSION['gallery_admin_authenticated']) || $_SESSION['gallery_admin_authenticated'] !== true) {
        // JSON/API clients should expect JSON
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (strpos($accept, 'application/json') !== false || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        // otherwise redirect to admin login
        header('Location: /gallery/admin-login.php');
        exit;
    }

    // update last activity (simple idle timeout could be added here)
    $_SESSION['gallery_admin_last_activity'] = time();
}

function gallery_admin_has_role(string $role): bool
{
    $user = gallery_current_admin();
    if (!$user) return false;
    return in_array($role, $user['roles'] ?? [], true);
}

function gallery_admin_log(string $action, array $meta = []): void
{
    $user = gallery_current_admin();
    $who = $user['username'] ?? 'unknown';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $entry = sprintf("[%s] user=%s ip=%s action=%s meta=%s\n", date('c'), $who, $ip, $action, json_encode($meta, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
    @file_put_contents(__DIR__ . '/admin-actions.log', $entry, FILE_APPEND | LOCK_EX);
}
