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

$targets = [
    'sliders' => 'DELETE SLIDERS',
    'heroes' => 'DELETE HEROES',
    'exhibitions' => 'DELETE EXHIBITIONS',
    'images' => 'DELETE IMAGES',
    'all' => 'DELETE ALL',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string) ($_POST['csrf'] ?? '');
    if (empty($_SESSION['gallery_csrf_token']) || !hash_equals((string) $_SESSION['gallery_csrf_token'], $csrf)) {
        http_response_code(400);
        exit('Invalid CSRF token.');
    }

    $target = (string) ($_POST['target'] ?? '');
    if (!isset($targets[$target])) {
        http_response_code(400);
        exit('Unknown clear target.');
    }

    $confirm = (string) ($_POST['confirm_text'] ?? '');
    if ($confirm !== $targets[$target]) {
        $_SESSION['gallery_admin_message'] = 'Confirmation text did not match. No changes made.';
        header('Location: /gallery/admin-clear-all.php');
        exit;
    }

    gallery_ensure_upload_directories();

    try {
        if ($target === 'sliders') {
            $removed = gallery_clear_home_sliders();
            $_SESSION['gallery_admin_message'] = 'Cleared home slider images and removed ' . $removed . ' uploaded files.';
        } elseif ($target === 'heroes') {
            $removed = gallery_clear_home_heroes();
            $_SESSION['gallery_admin_message'] = 'Cleared home heroes and removed ' . $removed . ' uploaded files.';
        } elseif ($target === 'exhibitions') {
            $removed = gallery_clear_exhibitions_only();
            $_SESSION['gallery_admin_message'] = 'Cleared exhibitions and removed ' . $removed . ' uploaded files.';
        } elseif ($target === 'images') {
            $removed = gallery_clear_images_only();
            $_SESSION['gallery_admin_message'] = 'Cleared images and removed ' . $removed . ' uploaded files.';
        } else {
            $pdo->beginTransaction();

            $tables = [
                'image_exhibitions',
                'image_tags',
                'images',
                'exhibitions',
                'home_heroes',
                'home_slider_images',
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

            $dirs = [
                __DIR__ . '/uploads/full',
                __DIR__ . '/uploads/thumbs',
                __DIR__ . '/uploads/deleted',
                __DIR__ . '/uploads/exhibitions/full',
                __DIR__ . '/uploads/exhibitions/thumbs',
                __DIR__ . '/uploads/heroes',
                __DIR__ . '/uploads/sliders',
                __DIR__ . '/all/imported',
            ];
            $removed = 0;
            foreach ($dirs as $d) {
                $removed += gallery_clear_directory_files($d);
            }

            $_SESSION['gallery_admin_message'] = 'Cleared all dynamic data and removed ' . $removed . ' uploaded files.';
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo 'Database error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        exit;
    }

    header('Location: /gallery/admin-clear-all.php');
    exit;
}

$token = gallery_set_csrf_token();
$message = $_SESSION['gallery_admin_message'] ?? '';
unset($_SESSION['gallery_admin_message']);
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
    <h1>Clear data — Destructive</h1>
    <?php if ($message !== ''): ?><div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <div class="alert alert-danger">
      Each option below permanently deletes data and uploaded files for that area only, except "Delete everything" which clears the whole gallery. Back up your database and uploads first. None of this can be undone.
    </div>
    <form method="post" id="clear-all-form">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="target" id="clear-target" value="">
      <div class="control-group">
        <label for="confirm_text">Confirmation</label>
        <input id="confirm_text" name="confirm_text" type="text" required>
        <p class="help-block" id="confirm-hint">Choose an option below; it will tell you what to type here.</p>
      </div>
      <div class="admin-friendly-actions">
        <button type="submit" class="btn btn-danger" data-target="sliders" data-phrase="DELETE SLIDERS">Delete home slider only</button>
        <button type="submit" class="btn btn-danger" data-target="heroes" data-phrase="DELETE HEROES">Delete home heroes only</button>
        <button type="submit" class="btn btn-danger" data-target="exhibitions" data-phrase="DELETE EXHIBITIONS">Delete exhibitions only</button>
        <button type="submit" class="btn btn-danger" data-target="images" data-phrase="DELETE IMAGES">Delete images only</button>
        <button type="submit" class="btn btn-danger" data-target="all" data-phrase="DELETE ALL">Delete everything</button>
        <a href="/gallery/admin-list-images.php" class="btn">Cancel</a>
      </div>
    </form>
  </div>
  <script>
    document.querySelectorAll('#clear-all-form button[data-target]').forEach(function (button) {
      button.addEventListener('click', function (event) {
        var phrase = button.getAttribute('data-phrase');
        document.getElementById('clear-target').value = button.getAttribute('data-target');
        document.getElementById('confirm-hint').textContent = 'Type ' + phrase + ' above to confirm this action.';
        if (!confirm('Are you sure? Type ' + phrase + ' in the box, then this cannot be undone.')) {
          event.preventDefault();
        }
      });
    });
  </script>
</body>
</html>
