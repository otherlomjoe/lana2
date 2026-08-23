<?php
require __DIR__ . '/gallery-lib.php';

session_start();
if (empty($_SESSION['gallery_admin_authenticated']) || $_SESSION['gallery_admin_authenticated'] !== true) {
    header('Location: /gallery/admin-login.php');
    exit;
}

$pdo = gallery_init_db();
$id = (int) ($_GET['id'] ?? 0);
$exhibition = null;
$selectedImageIds = [];
if ($id > 0) {
  $stmt = $pdo->prepare('SELECT * FROM exhibitions WHERE id = :id LIMIT 1');
  $stmt->execute([':id' => $id]);
  $exhibition = $stmt->fetch();
  if ($exhibition) {
    $links = $pdo->prepare('SELECT image_id FROM image_exhibitions WHERE exhibition_id = :id ORDER BY sort_order');
    $links->execute([':id' => $id]);
    $selectedImageIds = array_map('intval', array_column($links->fetchAll(), 'image_id'));
  }
}
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
    <h1><?= $exhibition ? 'Edit Exhibition' : 'Create Exhibition' ?></h1>
    <?php if ($message !== ''): ?><div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <form method="post" action="/gallery/exhibition-save.php">
      <input type="hidden" name="id" value="<?= (int) $id ?>">
      <div class="control-group"><label>Title</label><input type="text" name="title" value="<?= htmlspecialchars((string) ($exhibition['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required></div>
      <div class="control-group"><label>Start date</label><input type="date" name="startDate" value="<?= htmlspecialchars((string) ($exhibition['start_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
      <div class="control-group"><label>End date</label><input type="date" name="endDate" value="<?= htmlspecialchars((string) ($exhibition['end_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
      <div class="control-group"><label>Location</label><input type="text" name="location" value="<?= htmlspecialchars((string) ($exhibition['location'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
      <div class="control-group"><label>Description</label><p class="help-block">Formatting: <strong>**bold**</strong>, <em>*italic*</em>, blank lines for paragraphs, <code>- list items</code>, and <code>[link](https://example.com)</code>.</p><textarea name="description" rows="8"><?= htmlspecialchars((string) ($exhibition['description'] ?? '')) ?></textarea></div>
      <div class="control-group"><label>Images in exhibition</label><select name="imageIds[]" multiple><?php foreach ($images as $image): ?><option value="<?= (int) $image['id'] ?>"<?= in_array((int) $image['id'], $selectedImageIds, true) ? ' selected' : '' ?>><?= htmlspecialchars((string) $image['title'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
      <button type="submit" class="btn btn-primary">Save exhibition</button>
      <a class="btn" href="/gallery/admin-list-exhibitions.php">Cancel</a>
    </form>
  </div>
</body>
</html>
