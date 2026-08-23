<?php
require __DIR__ . '/gallery-lib.php';

session_start();
header('Content-Type: text/html; charset=utf-8');

if (empty($_SESSION['gallery_admin_authenticated']) || $_SESSION['gallery_admin_authenticated'] !== true) {
    header('Location: /gallery/admin-login.php');
    exit;
}

$pdo = gallery_init_db();
$id = (int) ($_GET['id'] ?? 0);
$item = null;
if ($id > 0) {
    $stmt = $pdo->prepare('SELECT i.*, GROUP_CONCAT(t.name) AS tag_names FROM images i LEFT JOIN image_tags it ON it.image_id = i.id LEFT JOIN tags t ON t.id = it.tag_id WHERE i.id = :id GROUP BY i.id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $item = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Edit Image</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="../scripts/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../styles/custom.css" rel="stylesheet">
</head>
<body>
  <div class="container">
    <h1>Edit Image</h1>
    <?php if ($item): ?>
      <form method="post" action="/gallery/image-save.php" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= htmlspecialchars((string) $item['id']) ?>">
        <div class="control-group"><label>Title</label><input type="text" name="title" value="<?= htmlspecialchars((string) $item['title']) ?>" required></div>
        <div class="control-group"><label>Price</label><input type="text" name="pricePublic" value="<?= htmlspecialchars((string) ($item['price_public'] ?? '')) ?>"></div>
        <div class="control-group"><label>Available</label><select name="available"><option value="1"<?= ($item['available'] ?? 1) ? ' selected' : '' ?>>Yes</option><option value="0"<?= !($item['available'] ?? 1) ? ' selected' : '' ?>>No</option></select></div>
        <div class="control-group"><label>Medium</label><input type="text" name="medium" value="<?= htmlspecialchars((string) ($item['medium'] ?? '')) ?>"></div>
        <div class="control-group"><label>Genre</label><input type="text" name="genre" value="<?= htmlspecialchars((string) ($item['genre'] ?? '')) ?>"></div>
        <div class="control-group"><label>Collection</label><input type="text" name="collection" value="<?= htmlspecialchars((string) ($item['collection'] ?? '')) ?>"></div>
        <div class="control-group"><label>Award title</label><input type="text" name="awardTitle" value="<?= htmlspecialchars((string) ($item['award_title'] ?? '')) ?>"></div>
        <div class="control-group"><label>Award description</label><textarea name="awardDescription"><?= htmlspecialchars((string) ($item['award_description'] ?? '')) ?></textarea></div>
        <div class="control-group"><label>Dimensions</label><input type="text" name="dimensions" value="<?= htmlspecialchars((string) ($item['dimensions'] ?? '')) ?>"></div>
        <div class="control-group"><label>Description</label><textarea name="description"><?= htmlspecialchars((string) ($item['description'] ?? '')) ?></textarea></div>
        <div class="control-group"><label>Location</label><textarea name="location"><?= htmlspecialchars((string) ($item['location'] ?? '')) ?></textarea></div>
        <div class="control-group"><label>Tags</label><input type="text" name="tags" value="<?= htmlspecialchars((string) ($item['tag_names'] ?? '')) ?>"></div>
        <div class="control-group"><label>Full image</label><input type="file" name="full" accept="image/*"></div>
        <div class="control-group"><label>Thumbnail</label><input type="file" name="thumbnail" accept="image/*"></div>
        <button type="submit" class="btn btn-primary">Save changes</button>
      </form>
    <?php else: ?>
      <p>Image not found.</p>
    <?php endif; ?>
  </div>
</body>
</html>
