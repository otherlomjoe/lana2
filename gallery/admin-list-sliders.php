<?php
require __DIR__ . '/gallery-lib.php';

session_start();
if (empty($_SESSION['gallery_admin_authenticated']) || $_SESSION['gallery_admin_authenticated'] !== true) {
    header('Location: /gallery/admin-login.php');
    exit;
}

$slides = gallery_list_slider_images(true);
$message = $_SESSION['gallery_admin_message'] ?? '';
$error = $_SESSION['gallery_admin_message_error'] ?? '';
unset($_SESSION['gallery_admin_message'], $_SESSION['gallery_admin_message_error']);
$csrf = gallery_set_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Home Slider</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="../scripts/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../styles/custom.css" rel="stylesheet">
</head>
<body>
  <div class="container admin-friendly">
    <?php require __DIR__ . '/admin-nav.php'; ?>
    <h1>Home slider</h1>
    <?php if ($message !== ''): ?><div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <div class="admin-help-card">
      <p><strong>Recommended image size:</strong> 1600 &times; 600px, landscape, JPG/PNG/WEBP, under 10 MB.</p>
      <p>Active slides appear on the homepage in the order shown below. Turn a slide off to keep it without showing it.</p>
    </div>
    <p><a class="btn btn-primary btn-large" href="/gallery/admin-slider-edit.php">Add slide</a></p>
    <table class="table table-striped admin-friendly-table">
      <thead><tr><th>Image</th><th>Order</th><th>Title</th><th>Link</th><th>Active</th><th>Updated</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($slides as $slide): ?>
        <tr>
          <td><?php if ($slide['image']): ?><img src="<?= htmlspecialchars($slide['image'], ENT_QUOTES, 'UTF-8') ?>" alt="" class="admin-list-thumbnail"><?php endif; ?></td>
          <td><?= (int) $slide['displayOrder'] ?></td>
          <td><?= htmlspecialchars($slide['title'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= $slide['linkUrl'] !== '' ? htmlspecialchars($slide['linkUrl'], ENT_QUOTES, 'UTF-8') : '&mdash;' ?></td>
          <td><?= $slide['active'] ? 'Yes' : 'Off' ?></td>
          <td><?= htmlspecialchars((string) ($slide['updatedAt'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
          <td class="admin-friendly-actions">
            <form method="post" action="/gallery/slider-action.php"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="id" value="<?= (int) $slide['id'] ?>"><input type="hidden" name="action" value="shift"><input type="hidden" name="direction" value="-1"><button type="submit" class="btn" aria-label="Move slide up">Up</button></form>
            <form method="post" action="/gallery/slider-action.php"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="id" value="<?= (int) $slide['id'] ?>"><input type="hidden" name="action" value="shift"><input type="hidden" name="direction" value="1"><button type="submit" class="btn" aria-label="Move slide down">Down</button></form>
            <a class="btn" href="/gallery/admin-slider-edit.php?id=<?= (int) $slide['id'] ?>">Edit</a>
            <form method="post" action="/gallery/slider-action.php"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="id" value="<?= (int) $slide['id'] ?>"><input type="hidden" name="action" value="active"><input type="hidden" name="active" value="<?= $slide['active'] ? '0' : '1' ?>"><button type="submit" class="btn"><?= $slide['active'] ? 'Turn off' : 'Turn on' ?></button></form>
            <form method="post" action="/gallery/slider-action.php" onsubmit="return confirm('Permanently delete this slide and its image? This cannot be undone.')"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="id" value="<?= (int) $slide['id'] ?>"><input type="hidden" name="action" value="hard-delete"><button type="submit" class="btn btn-danger">Delete</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$slides): ?><tr><td colspan="7">No slider images have been added.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</body>
</html>
