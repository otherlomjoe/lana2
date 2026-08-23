<?php
require __DIR__ . '/gallery-lib.php';

session_start();
header('Content-Type: text/html; charset=utf-8');
if (empty($_SESSION['gallery_admin_authenticated']) || $_SESSION['gallery_admin_authenticated'] !== true) {
    header('Location: /gallery/admin-login.php');
    exit;
}

$pdo = gallery_init_db();
$items = gallery_list_images($pdo, true);
$message = $_SESSION['gallery_admin_message'] ?? '';
unset($_SESSION['gallery_admin_message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Images</title>
  <link href="../scripts/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../styles/custom.css" rel="stylesheet">
</head>
<body>
  <div class="container">
    <h1>Images</h1>
    <?php if ($message !== ''): ?><div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <p><a href="/gallery/admin-upload.php">Add image</a> | <a href="/gallery/admin-exhibition-edit.php">Create exhibition</a> | <a href="/gallery/admin-list-exhibitions.php">Exhibitions</a></p>
    <table class="table table-striped">
      <thead><tr><th>ID</th><th>Title</th><th>Medium</th><th>Genre</th><th>Tags</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($items as $item): ?>
          <tr>
            <td><?= (int) $item['id'] ?></td>
            <td><?= htmlspecialchars((string) $item['title']) ?></td>
            <td><?= htmlspecialchars((string) $item['medium']) ?></td>
            <td><?= htmlspecialchars((string) $item['genre']) ?></td>
            <td><?= htmlspecialchars(implode(', ', (array) $item['tags'])) ?></td>
            <td><?= htmlspecialchars((string) $item['status']) ?></td>
            <td><a href="/gallery/admin-edit.php?id=<?= (int) $item['id'] ?>">Edit</a><?php if ($item['status'] === 'Deleted'): ?> | <a href="/gallery/image-restore.php?id=<?= (int) $item['id'] ?>">Undelete</a> | <a href="/gallery/image-delete-permanent.php?id=<?= (int) $item['id'] ?>" onclick="return confirm('Permanently delete this image and all files? This cannot be undone.')">Delete permanently</a><?php else: ?> | <a href="/gallery/image-delete.php?id=<?= (int) $item['id'] ?>" onclick="return confirm('Move this image to deleted items?')">Delete</a><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</body>
</html>
