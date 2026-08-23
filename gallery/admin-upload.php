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
$exhibitions = gallery_list_exhibitions($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin Upload</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="../scripts/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../styles/custom.css" rel="stylesheet">
</head>
<body>
  <div class="container">
    <?php require __DIR__ . '/admin-nav.php'; ?>
    <h1>Admin Upload</h1>
    <p><a href="/gallery/admin-list-images.php">View images</a> | <a href="/gallery/admin-list-exhibitions.php">View exhibitions</a></p>
    <form method="post" action="/gallery/image-save.php" enctype="multipart/form-data">
      <div class="control-group"><label>Title</label><input id="image-title" type="text" name="title"></div>
      <fieldset><legend>Public information</legend>
      <div class="control-group"><label>Public price</label><input type="text" name="pricePublic"></div>
      <div class="control-group"><label>Artwork creation date (editable)</label><input type="date" name="artworkCreatedAt"><p class="help-block">Defaults to the image EXIF date where available, otherwise the file timestamp. You can change it.</p></div>
      <div class="control-group"><label>Available</label><select name="available"><option value="1">Yes</option><option value="0">No</option></select></div>
      <div class="control-group"><label>Medium</label><input type="text" name="medium" list="medium-options"><datalist id="medium-options"><?php foreach ($lookups['mediums'] as $value): ?><option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"><?php endforeach; ?></datalist></div>
      <div class="control-group"><label>Genre</label><input type="text" name="genre" list="genre-options"><datalist id="genre-options"><?php foreach ($lookups['genres'] as $value): ?><option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"><?php endforeach; ?></datalist></div>
      <div class="control-group"><label>Collection</label><input type="text" name="collection" list="collection-options"><datalist id="collection-options"><?php foreach ($lookups['collections'] as $value): ?><option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"><?php endforeach; ?></datalist></div>
      <div class="control-group"><label>Award title</label><input type="text" name="awardTitle"></div>
      <div class="control-group"><label>Award description</label><textarea name="awardDescription"></textarea></div>
      <div class="control-group"><label>Dimensions</label><input type="text" name="dimensions"></div>
      <div class="control-group"><label>Description</label><p class="help-block">Formatting: <strong>**bold**</strong>, <em>*italic*</em>, blank lines for paragraphs, <code>- list items</code>, and <code>[link](https://example.com)</code>.</p><textarea name="description" rows="8"></textarea></div>
      <div class="control-group"><label>Location</label><textarea name="location"></textarea></div>
      <div class="control-group"><label>Tags</label><input type="text" name="tags"></div>
      <div class="control-group"><label>Exhibition</label><select name="exhibition"><option value="">Not assigned</option><?php foreach ($exhibitions as $exhibition): ?><option value="<?= (int) $exhibition['id'] ?>"><?= htmlspecialchars((string) $exhibition['title'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
      <div class="control-group"><label>Alt text</label><input type="text" name="altText"></div>
      </fieldset>
      <fieldset><legend>Private administration</legend>
      <div class="control-group"><label>Private price</label><input type="text" name="pricePrivate"></div>
      <div class="control-group"><label>Private notes</label><textarea name="privateNotes"></textarea></div>
      <div class="control-group"><label>Copies sold</label><input type="number" min="0" name="copiesSold" value="0"></div>
      </fieldset>
      <div class="control-group"><label>Full image</label><input id="full-image" type="file" name="full" accept="image/*" required></div>
      <div class="control-group"><label>Thumbnail (recommended)</label><input type="file" name="thumbnail" accept="image/*"><p class="help-block">Upload a matching image-name-thumb file when available.</p><label><input type="checkbox" name="generateThumbnail" value="1"> Generate a 200 x 165 thumbnail if no thumbnail is uploaded</label></div>
      <button type="submit" class="btn btn-primary">Save image</button>
    </form>
  </div>
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
</body>
</html>
