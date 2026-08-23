<?php
require __DIR__ . '/gallery-lib.php';

session_start();
header('Content-Type: text/html; charset=utf-8');

if (empty($_SESSION['gallery_admin_authenticated']) || $_SESSION['gallery_admin_authenticated'] !== true) {
    header('Location: /gallery/admin-login.php');
    exit;
}

$pdo = gallery_init_db();
$lookups = gallery_lookup_values($pdo);
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
      <p><img src="<?= htmlspecialchars((string) ($item['thumbnail_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" alt="Current thumbnail" style="max-width:200px;max-height:165px;height:auto;"> <img src="<?= htmlspecialchars((string) ($item['full_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" alt="Current full image" style="max-width:320px;height:auto;"></p>
      <form method="post" action="/gallery/image-save.php" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= htmlspecialchars((string) $item['id']) ?>">
        <div class="control-group"><label>Title</label><input id="image-title" type="text" name="title" value="<?= htmlspecialchars((string) $item['title']) ?>"></div>
        <div class="control-group"><label>Price</label><input type="text" name="pricePublic" value="<?= htmlspecialchars((string) ($item['price_public'] ?? '')) ?>"></div>
        <div class="control-group"><label>Available</label><select name="available"><option value="1"<?= ($item['available'] ?? 1) ? ' selected' : '' ?>>Yes</option><option value="0"<?= !($item['available'] ?? 1) ? ' selected' : '' ?>>No</option></select></div>
        <div class="control-group"><label>Medium</label><input type="text" name="medium" value="<?= htmlspecialchars((string) ($item['medium'] ?? '')) ?>" list="medium-options"><datalist id="medium-options"><?php foreach ($lookups['mediums'] as $value): ?><option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"><?php endforeach; ?></datalist></div>
        <div class="control-group"><label>Genre</label><input type="text" name="genre" value="<?= htmlspecialchars((string) ($item['genre'] ?? '')) ?>" list="genre-options"><datalist id="genre-options"><?php foreach ($lookups['genres'] as $value): ?><option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"><?php endforeach; ?></datalist></div>
        <div class="control-group"><label>Collection</label><input type="text" name="collection" value="<?= htmlspecialchars((string) ($item['collection'] ?? '')) ?>" list="collection-options"><datalist id="collection-options"><?php foreach ($lookups['collections'] as $value): ?><option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"><?php endforeach; ?></datalist></div>
        <div class="control-group"><label>Award title</label><input type="text" name="awardTitle" value="<?= htmlspecialchars((string) ($item['award_title'] ?? '')) ?>"></div>
        <div class="control-group"><label>Award description</label><textarea name="awardDescription"><?= htmlspecialchars((string) ($item['award_description'] ?? '')) ?></textarea></div>
        <div class="control-group"><label>Dimensions</label><input type="text" name="dimensions" value="<?= htmlspecialchars((string) ($item['dimensions'] ?? '')) ?>"></div>
        <div class="control-group"><label>Description</label><textarea name="description" rows="8" placeholder="Use **bold**, *italic*, blank lines, - lists, and [links](https://example.com)"><?= htmlspecialchars((string) ($item['description'] ?? '')) ?></textarea></div>
        <div class="control-group"><label>Location</label><textarea name="location"><?= htmlspecialchars((string) ($item['location'] ?? '')) ?></textarea></div>
        <div class="control-group"><label>Tags</label><input type="text" name="tags" value="<?= htmlspecialchars((string) ($item['tag_names'] ?? '')) ?>"></div>
          <div class="control-group"><label>Full image</label><input id="full-image" type="file" name="full" accept="image/*"></div>
        <div class="control-group"><label>Thumbnail (recommended)</label><input type="file" name="thumbnail" accept="image/*"><p class="help-block">Leave blank to keep the current thumbnail, or upload a matching image-name-thumb file.</p><label><input type="checkbox" name="generateThumbnail" value="1"> Generate a 200 x 165 thumbnail if no thumbnail is uploaded</label></div>
        <button type="submit" class="btn btn-primary">Save changes</button>
      </form>
      <script>
        document.getElementById('full-image').addEventListener('change', function () {
          const title = document.getElementById('image-title');
          if (title.value.trim() || !this.files.length) return;
          title.value = this.files[0].name
            .replace(/\.[^.]+$/, '')
            .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
            .replace(/[_-]+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim()
            .replace(/\b\w/g, character => character.toUpperCase());
        });
      </script>
    <?php else: ?>
      <p>Image not found.</p>
    <?php endif; ?>
  </div>
</body>
</html>
