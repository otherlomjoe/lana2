<?php
require __DIR__ . '/gallery-lib.php';

session_start();
header('Content-Type: text/html; charset=utf-8');
if (empty($_SESSION['gallery_admin_authenticated']) || $_SESSION['gallery_admin_authenticated'] !== true) {
    header('Location: /gallery/admin-login.php');
    exit;
}

$pdo = gallery_init_db();
$items = gallery_list_images($pdo);
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
    <p><a href="/gallery/admin-upload.php">Add image</a> | <a href="/gallery/admin-manage-dropdowns.php">Manage dropdowns</a></p>
    <table class="table table-striped">
      <thead><tr><th>ID</th><th>Title</th><th>Medium</th><th>Genre</th><th>Tags</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($items as $item): ?>
          <tr>
            <td><?= (int) $item['id'] ?></td>
            <td><?= htmlspecialchars((string) $item['title']) ?></td>
            <td><?= htmlspecialchars((string) $item['medium']) ?></td>
            <td><?= htmlspecialchars((string) $item['genre']) ?></td>
            <td><?= htmlspecialchars(implode(', ', (array) $item['tags'])) ?></td>
            <td><a href="/gallery/admin-edit.php?id=<?= (int) $item['id'] ?>">Edit</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</body>
</html>
