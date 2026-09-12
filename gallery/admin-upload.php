<?php
$version = 'unknown';
$versionFile = dirname(__DIR__) . '/version.txt';
if (is_file($versionFile)) {
    $version = trim(file_get_contents($versionFile));
} elseif (is_dir(dirname(__DIR__) . '/.git')) {
    $git = @shell_exec('git -C ' . escapeshellarg(dirname(__DIR__)) . ' describe --tags --always 2>/dev/null');
    if ($git) $version = trim($git);
}
echo '<div id="deploy-version">Version: ' . htmlspecialchars($version, ENT_QUOTES, 'UTF-8') . '</div>';

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
$error = $_SESSION['gallery_admin_message_error'] ?? '';
unset($_SESSION['gallery_admin_message'], $_SESSION['gallery_admin_message_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin Upload</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="../scripts/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../styles/custom.css" rel="stylesheet">
  <style>
#deploy-version {
  position: fixed;
  top: 8px;
  left: 8px;
  background: rgba(0,0,0,0.6);
  color: #fff;
  padding: 4px 8px;
  font-size: 12px;
  z-index: 10000;
  border-radius: 3px;
  pointer-events: none;
}
</style>
</head>
<body>
  <div class="container">
    <?php require __DIR__ . '/admin-nav.php'; ?>
    <h1>Admin Upload</h1>
    <?php if ($message !== ''): ?><div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <p><a href="/gallery/admin-list-images.php">View images</a> | <a href="/gallery/admin-list-exhibitions.php">View exhibitions</a></p>
    <form method="post" action="/gallery/image-save.php" enctype="multipart/form-data">
      <div class="control-group"><label>Full image</label><input id="full-image" data-media-input="full image" type="file" name="full" accept="image/*" required><div data-media-preview="full-image"></div></div>
      <div class="control-group"><label>Thumbnail (recommended)</label><input id="thumbnail-file" data-media-input="thumbnail" type="file" name="thumbnail" accept="image/*"><div data-media-preview="thumbnail-file"></div><p class="help-block">Upload a matching image-name-thumb file when available.</p><div id="thumbnail-warning" class="alert alert-warning" hidden>Thumbnail filenames should end in <strong>thumb</strong>, for example image-name-thumb.jpg.</div><button type="submit" name="generateThumbnail" value="1" class="btn">Generate correctly named 200 x 165 thumbnail</button></div>

      <div class="control-group"><label>Title</label><input id="image-title" type="text" name="title"></div>
      <fieldset><legend>Public information</legend>
      <div class="control-group"><label>Public price</label><input type="text" name="pricePublic"></div>
      <div class="control-group"><label>Artwork creation date (editable)</label><input id="artwork-created-at" type="date" name="artworkCreatedAt"><p class="help-block">Defaults to the image EXIF date where available, otherwise the file timestamp. You can change it.</p></div>
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
      
      <button type="submit" class="btn btn-primary">Save image</button>
    </form>
  </div>
    <script src="/scripts/admin-media-preview.js"></script>
    <script>
      document.getElementById('full-image').addEventListener('change', function () {
        const title = document.getElementById('image-title');
        const dateInput = document.getElementById('artwork-created-at');
        if (this.files && this.files.length) {
          const file = this.files[0];
          if (!title.value.trim()) {
            title.value = file.name
              .replace(/\.[^.]+$/, '')
              .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
              .replace(/[_-]+/g, ' ')
              .replace(/\s+/g, ' ')
              .trim()
              .replace(/\b\w/g, character => character.toUpperCase());
          }

          if (!dateInput.value && file.lastModified) {
            const d = new Date(file.lastModified);
            const yyyy = d.getFullYear();
            const mm = String(d.getMonth() + 1).padStart(2, '0');
            const dd = String(d.getDate()).padStart(2, '0');
            dateInput.value = `${yyyy}-${mm}-${dd}`;
          }
        }
      });
      document.getElementById('thumbnail-file').addEventListener('change', function () {
        const name = this.files.length ? this.files[0].name.replace(/\.[^.]+$/, '') : '';
        document.getElementById('thumbnail-warning').hidden = !name || /thumb$/i.test(name);
      });
    </script>
</body>
</html>
