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
$exhibitions = gallery_list_exhibitions($pdo, true);
$message = $_SESSION['gallery_admin_message'] ?? '';
$error = $_SESSION['gallery_admin_message_error'] ?? '';
unset($_SESSION['gallery_admin_message'], $_SESSION['gallery_admin_message_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin — Add Image</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="../scripts/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../styles/custom.css" rel="stylesheet">
  <link href="../styles/admin.css" rel="stylesheet">
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
  <div class="container admin-friendly">
    <?php require __DIR__ . '/admin-nav.php'; ?>
    <h1>Add Image</h1>
    <?php if ($message !== ''): ?><div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <p><a href="/gallery/admin-list-images.php">View images</a> | <a href="/gallery/admin-list-exhibitions.php">View exhibitions</a></p>
    <form method="post" action="/gallery/image-save.php" enctype="multipart/form-data">
      <div class="control-group"><label>Full image</label><input id="full-image" data-media-input="full image" type="file" name="full" accept="image/*" required><div data-media-preview="full-image"></div></div>
      <div class="control-group"><label>Thumbnail (recommended)</label><input id="thumbnail-file" data-media-input="thumbnail" type="file" name="thumbnail" accept="image/*"><div data-media-preview="thumbnail-file"></div>
<p class="help-block">Upload a matching image-name-thumb file when available.</p>
<input id="thumbnail-filename" type="text" readonly class="form-control" style="margin-top:8px;" placeholder="Thumbnail filename">
<div id="thumbnail-warning" class="alert alert-warning" hidden>Thumbnail filenames should end in <strong>thumb</strong>, for example image-name-thumb.jpg.</div>
<button type="button" id="generate-thumbnail-btn" class="btn">Generate correctly named 200 x 165 thumbnail</button></div>

      <div class="control-group image-title-active-row"><label>Title</label><input id="image-title" type="text" name="title"><label class="active-checkbox"><input type="checkbox" name="active" value="1" checked> Active</label></div>
      <fieldset><legend>Public information</legend>
      <div class="control-group"><label>Public price</label><input type="text" name="pricePublic"></div>
      <div class="control-group"><label>Artwork creation date (editable)</label><input id="artwork-created-at" type="date" name="artworkCreatedAt"><p class="help-block">Defaults to the image EXIF date where available, otherwise the file timestamp. You can change it.</p></div>
      <div class="control-group"><label><input type="checkbox" name="sold" value="1"> Sold</label></div>
      <div class="control-group"><label>Medium <span class="searchable-badge">Searchable</span></label><input type="text" name="medium" list="medium-options"><datalist id="medium-options"><?php foreach ($lookups['mediums'] as $value): ?><option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"><?php endforeach; ?></datalist></div>
      <div class="control-group"><label>Genre <span class="searchable-badge">Searchable</span></label><input type="text" name="genre" list="genre-options"><datalist id="genre-options"><?php foreach ($lookups['genres'] as $value): ?><option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"><?php endforeach; ?></datalist></div>
      <div class="control-group"><label>Collection <span class="searchable-badge">Searchable</span></label><input type="text" name="collection" list="collection-options"><datalist id="collection-options"><?php foreach ($lookups['collections'] as $value): ?><option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"><?php endforeach; ?></datalist></div>
      <div class="control-group"><label>Award title</label><input type="text" name="awardTitle"></div>
      <div class="control-group"><label>Award description</label><textarea name="awardDescription"></textarea></div>
      <div class="control-group"><label>Dimensions</label><input type="text" name="dimensions"></div>
      <div class="control-group"><label>Description</label><p class="help-block">Formatting: <strong>**bold**</strong>, <em>*italic*</em>, blank lines for paragraphs, <code>- list items</code>, and <code>[link](https://example.com)</code>.</p><textarea name="description" rows="8"></textarea></div>
      <div class="control-group"><label>Location <span class="searchable-badge">Searchable</span></label><textarea name="location"></textarea></div>
      <div class="control-group"><label>Tags</label><input type="text" name="tags" placeholder="comma-separated tags (used for search)"></div>
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
      const name = this.files.length ? (this.files[0] && this.files[0].name ? this.files[0].name.replace(/\.[^.]+$/, '').replace(/[-_]+$/, '') : '') : '';
      document.getElementById('thumbnail-warning').hidden = !name || /thumb$/i.test(name);
    });
    // Attach the same local thumbnail generator used in admin-edit.php
    (function(){
      async function createThumbnailFileFromImage(file, width = 200, height = 165, mime = 'image/jpeg', quality = 0.9) {
        return new Promise((resolve, reject) => {
          const fr = new FileReader();
          fr.onerror = () => reject(new Error('Failed to read file'));
          fr.onload = () => {
            const img = new Image();
            img.onerror = () => reject(new Error('Could not load image'));
            img.onload = () => {
              const sw = img.width, sh = img.height;
              const scale = Math.max(width / sw, height / sh);
              const sWidth = Math.round(width / scale);
              const sHeight = Math.round(height / scale);
              const sx = Math.max(0, Math.round((sw - sWidth) / 2));
              const sy = Math.max(0, Math.round((sh - sHeight) / 2));
              const canvas = document.createElement('canvas');
              canvas.width = width; canvas.height = height;
              const ctx = canvas.getContext('2d');
              ctx.fillStyle = '#fff'; ctx.fillRect(0,0,width,height);
              ctx.drawImage(img, sx, sy, sWidth, sHeight, 0, 0, width, height);
              canvas.toBlob(function (blob) {
                if (!blob) return reject(new Error('Canvas export failed'));
                try {
                  const baseName = (file.name || 'thumbnail').replace(/\.[^.]+$/, '');
                  const name = baseName + '-thumb.jpg';
                  const thumbFile = new File([blob], name, { type: mime });
                  resolve(thumbFile);
                } catch (e) {
                  blob.name = (file.name || 'thumbnail').replace(/\.[^.]+$/, '') + '-thumb.jpg';
                  resolve(blob);
                }
              }, mime, quality);
            };
            img.src = fr.result;
          };
          fr.readAsDataURL(file);
        });
      }
      const btn = document.getElementById('generate-thumbnail-btn');
      if (btn) {
        btn.addEventListener('click', async function () {
          const fullInput = document.getElementById('full-image');
          const thumbInput = document.getElementById('thumbnail-file');
          if (!fullInput || !fullInput.files || !fullInput.files.length) {
            alert('Please choose a full image first.');
            return;
          }
          const file = fullInput.files[0];
          try {
            btn.disabled = true; btn.textContent = 'Generating...';
            const thumbFile = await createThumbnailFileFromImage(file, 200, 165);
            const dt = new DataTransfer(); dt.items.add(thumbFile); thumbInput.files = dt.files; thumbInput.dispatchEvent(new Event('change', { bubbles: true }));
            const notice = document.createElement('div'); notice.className = 'alert alert-success'; notice.textContent = 'Thumbnail generated locally. You can continue editing and click Save when ready.'; const container = document.querySelector('.container'); container.insertBefore(notice, container.firstChild); setTimeout(() => notice.remove(), 6000);
          } catch (err) { alert('Thumbnail generation failed: ' + (err && err.message ? err.message : err)); }
          finally { btn.disabled = false; btn.textContent = 'Generate correctly named 200 x 165 thumbnail'; }
        });
      }
    })();

    // keep the thumbnail filename display in sync
    document.getElementById('thumbnail-file').addEventListener('change', function () {
      const name = this.files.length ? this.files[0].name.replace(/\.[^.]+$/, '') : '';
      document.getElementById('thumbnail-warning').hidden = !name || /thumb$/i.test(name);
      try { const tf = document.getElementById('thumbnail-filename'); if (tf) tf.value = this.files.length ? this.files[0].name : ''; } catch (e) {}
    });

    // suggest a thumbnail filename when a full image is selected
    document.getElementById('full-image').addEventListener('change', function () {
      try {
        const tf = document.getElementById('thumbnail-filename');
        if (tf && !tf.value && this.files && this.files.length) {
          const base = this.files[0].name.replace(/\.[^.]+$/, '').replace(/[-_]+$/, '');
          tf.value = base + '-thumb.jpg';
        }
      } catch (e) {}
    });

    // preflight duplicate-check on Add: warn if a similar thumbnail basename exists in DB
    (function(){
      var form = document.querySelector('form');
      if (!form) return;
      form.addEventListener('submit', async function (e) {
        try {
          var tf = document.getElementById('thumbnail-filename');
          var base = '';
          if (tf && tf.value) base = tf.value.replace(/\.[^.]+$/, '').replace(/-thumb$/i, '');
          if (!base) {
            var full = document.getElementById('full-image');
            if (full && full.files && full.files.length) base = (full.files[0].name || '').replace(/\.[^.]+$/, '').replace(/-thumb$/i, '');
          }
          base = base.trim();
          if (!base) return;
          var url = '/gallery/check-thumbnail.php?basename=' + encodeURIComponent(base);
          var resp = await fetch(url, { credentials: 'same-origin' });
          if (!resp.ok) return;
          var data = await resp.json();
          if (data && data.success && data.exists) {
            var items = (data.items || []).map(function (it) { return '#' + it.id + ' ' + (it.title || ''); }).join('\n');
            var msg = 'A thumbnail with a similar filename already exists:\n' + items + '\n\nContinuing may create a duplicate entry. Proceed?';
            if (!confirm(msg)) { e.preventDefault(); }
          }
        } catch (err) { return; }
      });
    })();

    </script>
</body>
</html>
