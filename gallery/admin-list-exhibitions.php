<?php
require __DIR__ . '/gallery-lib.php';

session_start();
header('Content-Type: text/html; charset=utf-8');
if (empty($_SESSION['gallery_admin_authenticated']) || $_SESSION['gallery_admin_authenticated'] !== true) {
    header('Location: /gallery/admin-login.php');
    exit;
}

$exhibitions = gallery_list_exhibitions(gallery_init_db());
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Exhibitions</title>
  <link href="../scripts/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../styles/custom.css" rel="stylesheet">
</head>
<body>
  <div class="container">
    <h1>Exhibitions</h1>
    <p><a href="/gallery/admin-upload.php">Add image</a> | <a href="/gallery/admin-exhibition-preview.php">Preview</a></p>
    <table class="table table-striped">
      <thead><tr><th>ID</th><th>Title</th><th>Location</th><th>Dates</th></tr></thead>
      <tbody>
        <?php foreach ($exhibitions as $exhibition): ?>
          <tr>
            <td><?= (int) $exhibition['id'] ?></td>
            <td><?= htmlspecialchars((string) $exhibition['title']) ?></td>
            <td><?= htmlspecialchars((string) $exhibition['location']) ?></td>
            <td><?= htmlspecialchars((string) ($exhibition['startDate'] ?? '')) ?> - <?= htmlspecialchars((string) ($exhibition['endDate'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</body>
</html>
