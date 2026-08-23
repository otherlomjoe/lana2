<?php
require __DIR__ . '/gallery-lib.php';

session_start();
header('Content-Type: text/html; charset=utf-8');
if (empty($_SESSION['gallery_admin_authenticated']) || $_SESSION['gallery_admin_authenticated'] !== true) {
    header('Location: /gallery/admin-login.php');
    exit;
}

$pdo = gallery_init_db();
$images = gallery_list_images($pdo);
$exhibitions = gallery_list_exhibitions($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Exhibition Preview</title>
  <link href="../scripts/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../styles/custom.css" rel="stylesheet">
</head>
<body>
  <div class="container">
    <?php require __DIR__ . '/admin-nav.php'; ?>
    <h1>Exhibition Preview</h1>
    <?php foreach ($exhibitions as $exhibition): ?>
      <div class="well">
        <h3><?= htmlspecialchars((string) $exhibition['title']) ?></h3>
        <p><?= htmlspecialchars((string) ($exhibition['location'] ?? '')) ?></p>
        <div class="row-fluid">
          <?php foreach ($images as $image): ?>
            <?php if (!empty($image['thumbnail'])): ?>
              <div class="span2">
                <img src="<?= htmlspecialchars((string) $image['thumbnail']) ?>" alt="<?= htmlspecialchars((string) ($image['title'] ?? 'Artwork')) ?>" style="width:100%; height:auto;">
                <small><?= htmlspecialchars((string) $image['title']) ?></small>
              </div>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</body>
</html>
