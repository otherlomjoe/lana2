<?php
require __DIR__ . '/gallery-lib.php';

session_start();
if (empty($_SESSION['gallery_admin_authenticated']) || $_SESSION['gallery_admin_authenticated'] !== true) {
    header('Location: /gallery/admin-login.php');
    exit;
}

$heroes = gallery_list_home_heroes(true);
$message = $_SESSION['gallery_admin_message'] ?? '';
$error = $_SESSION['gallery_admin_message_error'] ?? '';
unset($_SESSION['gallery_admin_message'], $_SESSION['gallery_admin_message_error']);
$csrf = gallery_set_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Home Heroes</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="../scripts/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../styles/custom.css" rel="stylesheet">
</head>
<body>
  <div class="container">
    <?php require __DIR__ . '/admin-nav.php'; ?>
    <h1>Home heroes</h1>
    <?php if ($message !== ''): ?><div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <p><a class="btn btn-primary" href="/gallery/admin-hero-edit.php">Add home hero</a></p>
    <p>Visible heroes appear on the homepage in the order shown below. Uncheck visibility to keep a hero as a draft.</p>
    <table class="table table-striped">
      <thead><tr><th>Order</th><th>Title</th><th>Style</th><th>Visible</th><th>Updated</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($heroes as $hero): ?>
        <tr>
          <td><?= (int) $hero['displayOrder'] ?></td>
          <td><?= htmlspecialchars($hero['title'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($hero['visualStyle'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= $hero['visible'] ? 'Yes' : 'Draft' ?></td>
          <td><?= htmlspecialchars((string) ($hero['updatedAt'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
          <td class="admin-friendly-actions">
            <form method="post" action="/gallery/hero-action.php" style="display:inline"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="id" value="<?= (int) $hero['id'] ?>"><input type="hidden" name="action" value="shift"><input type="hidden" name="direction" value="-1"><button type="submit" class="btn-link" aria-label="Move hero up">Up</button></form>
            <form method="post" action="/gallery/hero-action.php" style="display:inline"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="id" value="<?= (int) $hero['id'] ?>"><input type="hidden" name="action" value="shift"><input type="hidden" name="direction" value="1"><button type="submit" class="btn-link" aria-label="Move hero down">Down</button></form>
            <form method="post" action="/gallery/hero-action.php" style="display:inline"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="id" value="<?= (int) $hero['id'] ?>"><input type="hidden" name="action" value="duplicate"><button type="submit" class="btn-link">Duplicate</button></form>
            <a href="/gallery/admin-hero-edit.php?id=<?= (int) $hero['id'] ?>">Edit</a>
            <form method="post" action="/gallery/hero-action.php" style="display:inline">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="id" value="<?= (int) $hero['id'] ?>">
              <input type="hidden" name="action" value="visibility">
              <input type="hidden" name="visible" value="<?= $hero['visible'] ? '0' : '1' ?>">
              <button type="submit" class="btn-link"><?= $hero['visible'] ? 'Hide' : 'Publish' ?></button>
            </form>
            <form method="post" action="/gallery/hero-action.php" style="display:inline" onsubmit="return confirm('Permanently delete this hero and its image? This cannot be undone.')">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="id" value="<?= (int) $hero['id'] ?>">
              <input type="hidden" name="action" value="hard-delete">
              <button type="submit" class="btn-link text-error">Hard delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$heroes): ?><tr><td colspan="6">No home heroes have been created.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</body>
</html>
