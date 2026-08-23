<?php
require __DIR__ . '/gallery-lib.php';

session_start();
header('Content-Type: text/html; charset=utf-8');
if (empty($_SESSION['gallery_admin_authenticated']) || $_SESSION['gallery_admin_authenticated'] !== true) {
    header('Location: /gallery/admin-login.php');
    exit;
}

$exhibitions = gallery_list_exhibitions(gallery_init_db());
$message = $_SESSION['gallery_admin_message'] ?? '';
unset($_SESSION['gallery_admin_message']);
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
    <?php require __DIR__ . '/admin-nav.php'; ?>
    <h1>Exhibitions</h1>
    <?php if ($message !== ''): ?><div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <p><a href="/gallery/admin-exhibition-edit.php">Create exhibition</a> | <a href="/gallery/admin-exhibition-preview.php">Preview</a></p>
    <table class="table table-striped">
      <thead><tr><th>ID</th><th>Title</th><th>Location</th><th>Dates</th><th>Images</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($exhibitions as $exhibition): ?>
          <tr>
            <td><?= (int) $exhibition['id'] ?></td>
            <td><?= htmlspecialchars((string) $exhibition['title']) ?></td>
            <td><?= htmlspecialchars((string) $exhibition['location']) ?></td>
            <td><?= htmlspecialchars((string) ($exhibition['startDate'] ?? '')) ?> - <?= htmlspecialchars((string) ($exhibition['endDate'] ?? '')) ?></td>
            <td><?= (int) ($exhibition['imageCount'] ?? 0) ?></td>
            <td><a href="/gallery/admin-exhibition-edit.php?id=<?= (int) $exhibition['id'] ?>">Edit</a> | <a href="/gallery/exhibition-delete.php?id=<?= (int) $exhibition['id'] ?>" onclick="return confirm('Delete this exhibition?')">Delete</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</body>
</html>
