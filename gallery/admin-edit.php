<?php
require __DIR__ . '/gallery-lib.php';

session_start();
header('Content-Type: text/html; charset=utf-8');

if (empty($_SESSION['gallery_admin_authenticated']) || $_SESSION['gallery_admin_authenticated'] !== true) {
    header('Location: /gallery/admin-login.php');
    exit;
}

$pdo = gallery_init_db();
if (!$pdo) {
  http_response_code(500);
  exit('Gallery database is unavailable.');
}
$lookups = gallery_lookup_values($pdo);
$exhibitions = gallery_list_exhibitions($pdo);
$message = $_SESSION['gallery_admin_message'] ?? '';
unset($_SESSION['gallery_admin_message']);
$id = (int) ($_GET['id'] ?? 0);
$item = null;
if ($id > 0) {
    $stmt = $pdo->prepare('SELECT i.*, GROUP_CONCAT(t.name) AS tag_names FROM images i LEFT JOIN image_tags it ON it.image_id = i.id LEFT JOIN tags t ON t.id = it.tag_id WHERE i.id = :id GROUP BY i.id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $item = $stmt->fetch();
    if ($item) {
      $exhibitionStmt = $pdo->prepare('SELECT exhibition_id FROM image_exhibitions WHERE image_id = :id ORDER BY sort_order LIMIT 1');
      $exhibitionStmt->execute([':id' => $id]);
      $item['exhibition_id'] = $exhibitionStmt->fetchColumn() ?: '';
    }
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
    <?php require __DIR__ . '/admin-nav.php'; ?>
    <h1>Edit Image</h1>
    <?php if ($message !== ''): ?><div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($item): ?>
      <p><img src="<?= htmlspecialchars((string) ($item['thumbnail_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" alt="Current thumbnail" style="max-width:200px;max-height:165px;height:auto;"><br><small><?= htmlspecialchars((string) ($item['thumbnail_file'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small></p>
      <p><img src="<?= htmlspecialchars((string) ($item['full_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" alt="Current full image" style="max-width:320px;height:auto;"><br><small><?= htmlspecialchars((string) ($item['full_file'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small></p>
      <form method="post" action="/gallery/image-save.php" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= htmlspecialchars((string) $item['id']) ?>">
        <div class="control-group"><label>Title</label><input id="image-title" type="text" name="title" value="<?= htmlspecialchars((string) $item['title']) ?>"></div>
        <fieldset><legend>Public information</legend>
        <div class="control-group"><label>Public price</label><input type="text" name="pricePublic" value="<?= htmlspecialchars((string) ($item['price_public'] ?? '')) ?>"></div>
        <div class="control-group"><label>Artwork creation date (editable)</label><input type="date" name="artworkCreatedAt" value="<?= htmlspecialchars((string) ($item['artwork_created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><p class="help-block">This is the modifiable artwork date. New uploads default to the image EXIF date where available, otherwise the file timestamp.</p></div>
        <div class="control-group"><label>Available</label><select name="available"><option value="1"<?= ($item['available'] ?? 1) ? ' selected' : '' ?>>Yes</option><option value="0"<?= !($item['available'] ?? 1) ? ' selected' : '' ?>>No</option></select></div>
        <div class="control-group"><label>Medium</label><input type="text" name="medium" value="<?= htmlspecialchars((string) ($item['medium'] ?? '')) ?>" list="medium-options"><datalist id="medium-options"><?php foreach ($lookups['mediums'] as $value): ?><option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"><?php endforeach; ?></datalist></div>
        <div class="control-group"><label>Genre</label><input type="text" name="genre" value="<?= htmlspecialchars((string) ($item['genre'] ?? '')) ?>" list="genre-options"><datalist id="genre-options"><?php foreach ($lookups['genres'] as $value): ?><option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"><?php endforeach; ?></datalist></div>
        <div class="control-group"><label>Collection</label><input type="text" name="collection" value="<?= htmlspecialchars((string) ($item['collection'] ?? '')) ?>" list="collection-options"><datalist id="collection-options"><?php foreach ($lookups['collections'] as $value): ?><option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"><?php endforeach; ?></datalist></div>
        <div class="control-group"><label>Award title</label><input type="text" name="awardTitle" value="<?= htmlspecialchars((string) ($item['award_title'] ?? '')) ?>"></div>
        <div class="control-group"><label>Award description</label><textarea name="awardDescription"><?= htmlspecialchars((string) ($item['award_description'] ?? '')) ?></textarea></div>
        <div class="control-group"><label>Dimensions</label><input type="text" name="dimensions" value="<?= htmlspecialchars((string) ($item['dimensions'] ?? '')) ?>"></div>
        <div class="control-group"><label>Description</label><p class="help-block">Formatting: <strong>**bold**</strong>, <em>*italic*</em>, blank lines for paragraphs, <code>- list items</code>, and <code>[link](https://example.com)</code>.</p><textarea name="description" rows="8"><?= htmlspecialchars((string) ($item['description'] ?? '')) ?></textarea></div>
        <div class="control-group"><label>Location</label><textarea name="location"><?= htmlspecialchars((string) ($item['location'] ?? '')) ?></textarea></div>
        <div class="control-group"><label>Tags</label><input type="text" name="tags" value="<?= htmlspecialchars((string) ($item['tag_names'] ?? '')) ?>"></div>
        <div class="control-group"><label>Exhibition</label><select name="exhibition"><option value="">Not assigned</option><?php foreach ($exhibitions as $exhibition): ?><option value="<?= (int) $exhibition['id'] ?>"<?= (string) ($item['exhibition_id'] ?? '') === (string) $exhibition['id'] ? ' selected' : '' ?>><?= htmlspecialchars((string) $exhibition['title'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
        <div class="control-group"><label>Alt text</label><input type="text" name="altText" value="<?= htmlspecialchars((string) ($item['alt_text'] ?? '')) ?>"></div>
        </fieldset>
        <fieldset><legend>Private administration</legend>
        <div class="control-group"><label>Private price</label><input type="text" name="pricePrivate" value="<?= htmlspecialchars((string) ($item['price_private'] ?? '')) ?>"></div>
        <div class="control-group"><label>Private notes</label><textarea name="privateNotes"><?= htmlspecialchars((string) ($item['private_notes'] ?? '')) ?></textarea></div>
        <div class="control-group"><label>Copies sold</label><input type="number" min="0" name="copiesSold" value="<?= (int) ($item['copies_sold'] ?? 0) ?>"></div>
        </fieldset>
          <div class="control-group"><label>Full image</label><input id="full-image" type="file" name="full" accept="image/*"></div>
        <div class="control-group"><label>Thumbnail (recommended)</label><input id="thumbnail-file" type="file" name="thumbnail" accept="image/*"><p class="help-block">Leave blank to keep the current thumbnail, or upload a matching image-name-thumb file.</p><div id="thumbnail-warning" class="alert alert-warning" hidden>Thumbnail filenames should end in <strong>thumb</strong>, for example image-name-thumb.jpg.</div><label><input type="checkbox" name="generateThumbnail" value="1"> Generate a 200 x 165 thumbnail if no thumbnail is uploaded</label></div>
        <div class="form-actions">
          <button type="submit" name="save_mode" value="stay" class="btn btn-primary">Save and stay</button>
          <button type="submit" name="save_mode" value="list" class="btn btn-primary">Save and return to list</button>
          <?php if (!empty($item['deleted_at'])): ?><a class="btn" href="/gallery/image-restore.php?id=<?= (int) $item['id'] ?>">Undelete</a> <a class="btn btn-danger" href="/gallery/image-delete-permanent.php?id=<?= (int) $item['id'] ?>" onclick="return confirm('Permanently delete this image and all files? This cannot be undone.')">Delete permanently</a><?php else: ?><a class="btn" href="/gallery/image-delete.php?id=<?= (int) $item['id'] ?>" onclick="return confirm('Move this image to deleted items?')">Delete</a><?php endif; ?>
          <a class="btn" href="/gallery/admin-list-images.php">Cancel</a>
          <a class="btn" href="/gallery/admin-list-images.php">Close</a>
        </div>
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
        document.getElementById('thumbnail-file').addEventListener('change', function () {
          const name = this.files.length ? this.files[0].name.replace(/\.[^.]+$/, '') : '';
          document.getElementById('thumbnail-warning').hidden = !name || /thumb$/i.test(name);
        });
      </script>
    <?php else: ?>
      <p>Image not found.</p>
    <?php endif; ?>
  </div>
</body>
</html>
