<?php
require __DIR__ . '/gallery-lib.php';

session_start();
if (empty($_SESSION['gallery_admin_authenticated']) || $_SESSION['gallery_admin_authenticated'] !== true) {
    header('Location: /gallery/admin-login.php');
    exit;
}

$pdo = gallery_init_db();
$images = gallery_list_images($pdo);
$message = $_SESSION['gallery_admin_message'] ?? '';
unset($_SESSION['gallery_admin_message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Create Exhibition</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="../scripts/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../styles/custom.css" rel="stylesheet">
</head>
<body>
  <div class="container">
    <?php require __DIR__ . '/admin-nav.php'; ?>
    <h1>Create Exhibition</h1>
    <?php if ($message !== ''): ?><div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <form method="post" action="/gallery/exhibition-save.php">
      <div class="control-group"><label>Title</label><input type="text" name="title" required></div>
      <div class="control-group"><label>Start date</label><input type="date" name="startDate"></div>
      <div class="control-group"><label>End date</label><input type="date" name="endDate"></div>
      <div class="control-group"><label>Location</label><input type="text" name="location"></div>
      <div class="control-group"><label>Description</label><textarea name="description" rows="8" placeholder="Use **bold**, *italic*, blank lines, - lists, and [links](https://example.com)"></textarea></div>
      <div class="control-group"><label>Images</label><select name="imageIds[]" multiple><?php foreach ($images as $image): ?><option value="<?= (int) $image['id'] ?>"><?= htmlspecialchars((string) $image['title'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
      <button type="submit" class="btn btn-primary">Save exhibition</button>
      <a class="btn" href="/gallery/admin-list-exhibitions.php">Cancel</a>
    </form>
  </div>
</body>
</html>
