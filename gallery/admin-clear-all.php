<?php
require __DIR__ . '/gallery-lib.php';

session_start();
header('Content-Type: text/html; charset=utf-8');

try {
    gallery_require_auth();
} catch (Throwable $e) {
    http_response_code(403);
    echo 'Unauthorized';
    exit;
}

$pdo = gallery_init_db();
if (!$pdo) {
    http_response_code(500);
    exit('Gallery database is unavailable.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string) ($_POST['csrf'] ?? '');
    if (empty($_SESSION['gallery_csrf_token']) || !hash_equals((string) $_SESSION['gallery_csrf_token'], $csrf)) {
        http_response_code(400);
        exit('Invalid CSRF token.');
    }

    $confirm = (string) ($_POST['confirm_text'] ?? '');
    if ($confirm !== 'DELETE ALL') {
        $_SESSION['gallery_admin_message'] = 'Confirmation text did not match. No changes made.';
        header('Location: /gallery/admin-list-images.php');
        exit;
    }

    try {
        $pdo->beginTransaction();

        $tables = [
            'image_exhibitions',
            'image_tags',
            'images',
            'exhibitions',
            'tags',
            'mediums',
            'genres',
            'collections',
        ];

        foreach ($tables as $t) {
            $pdo->exec("DELETE FROM {$t}");
        }

        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            foreach ($tables as $t) {
                $pdo->exec("ALTER TABLE {$t} AUTO_INCREMENT = 1");
            }
        } elseif ($driver === 'sqlite') {
            foreach ($tables as $t) {
                $pdo->exec('DELETE FROM sqlite_sequence WHERE name = ' . $pdo->quote($t));
            }
            $pdo->exec('VACUUM');
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo 'Database error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        exit;
    }

    gallery_ensure_upload_directories();
    $dirs = [
        __DIR__ . '/uploads/full',
        __DIR__ . '/uploads/thumbs',
        __DIR__ . '/uploads/deleted',
        __DIR__ . '/uploads/exhibitions/full',
        __DIR__ . '/uploads/exhibitions/thumbs',
        __DIR__ . '/all/imported',
    ];
    $removed = 0;
    foreach ($dirs as $d) {
        if (!is_dir($d)) continue;
        $files = glob($d . '/*');
        if ($files === false) continue;
        foreach ($files as $f) {
            if (is_file($f)) {
                @unlink($f);
                $removed++;
            }
        }
    }

    $_SESSION['gallery_admin_message'] = 'Cleared dynamic data and removed ' . $removed . ' uploaded files.';
    header('Location: /gallery/admin-list-images.php');
    exit;
}

$token = gallery_set_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin — Clear All</title>
  <link href="../scripts/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../styles/custom.css" rel="stylesheet">
</head>
<body>
  <div class="container admin-friendly">
    <?php require __DIR__ . '/admin-nav.php'; ?>
    <h1>Clear All — Destructive</h1>
    <div class="alert alert-danger">
      This operation will delete all dynamic gallery data (images, exhibitions, tags, lookup rows) and remove uploaded files from the uploads folders. This cannot be undone. Back up your database and uploads first.
    </div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
      <p>Type <strong>DELETE ALL</strong> in the box below to confirm.</p>
      <div class="control-group">
        <label for="confirm_text">Confirmation</label>
        <input id="confirm_text" name="confirm_text" type="text" required>
      </div>
      <button type="submit" class="btn btn-danger" onclick="return confirm('Are you absolutely sure? This is irreversible.')">Delete everything</button>
      <a href="/gallery/admin-list-images.php" class="btn">Cancel</a>
    </form>
  </div>
</body>
</html>
