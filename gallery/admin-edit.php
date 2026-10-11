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
$exhibitions = gallery_list_exhibitions($pdo, true);
$message = $_SESSION['gallery_admin_message'] ?? '';
$error = $_SESSION['gallery_admin_message_error'] ?? '';
unset($_SESSION['gallery_admin_message'], $_SESSION['gallery_admin_message_error']);
$id = (int) ($_GET['id'] ?? 0);
$isNew = $id === 0;
$item = null;
$prevId = null; $nextId = null;
if ($id > 0) {
$stmt = $pdo->prepare('SELECT i.*, GROUP_CONCAT(t.name) AS tag_names FROM images i LEFT JOIN image_tags it ON it.image_id = i.id LEFT JOIN tags t ON t.id = it.tag_id WHERE i.id = :id GROUP BY i.id LIMIT 1');
$stmt->execute([':id' => $id]);
$item = $stmt->fetch();
if ($item) {
  $exhibitionStmt = $pdo->prepare('SELECT exhibition_id FROM image_exhibitions WHERE image_id = :id ORDER BY sort_order LIMIT 1');
  $exhibitionStmt->execute([':id' => $id]);
  $item['exhibition_id'] = $exhibitionStmt->fetchColumn() ?: '';

  // compute previous and next image ids (simple id-based navigation)
  $prevStmt = $pdo->prepare('SELECT id FROM images WHERE id < :id ORDER BY id DESC LIMIT 1');
  $prevStmt->execute([':id' => $id]);
  $prevId = $prevStmt->fetchColumn() ?: null;
  $nextStmt = $pdo->prepare('SELECT id FROM images WHERE id > :id ORDER BY id ASC LIMIT 1');
  $nextStmt->execute([':id' => $id]);
  $nextId = $nextStmt->fetchColumn() ?: null;
}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= $isNew ? 'Add Image' : 'Edit Image' ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="../scripts/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../styles/custom.css" rel="stylesheet">
  <link href="../styles/admin.css" rel="stylesheet">
</head>
<body>
  <div class="container admin-friendly">
    <?php require __DIR__ . '/admin-nav.php'; ?>
    <?php
      $versionFile = dirname(__DIR__) . '/version.txt';
      if (is_readable($versionFile)) {
        $v = trim((string) file_get_contents($versionFile));
        if ($v !== '') {
          echo '<div id="deploy-version" style="position:fixed;top:8px;left:8px;background:#222;color:#fff;padding:6px 10px;border-radius:4px;z-index:9999;font-size:12px;opacity:0.9">v' . htmlspecialchars($v, ENT_QUOTES, 'UTF-8') . '</div>';
        }
      }
    ?>
    <h1><?= $isNew ? 'Add Image' : 'Edit Image' ?></h1>
    <?php if ($message !== ''): ?><div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($isNew || $item): ?>
      <form method="post" action="/gallery/image-save.php" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= (int) ($item['id'] ?? 0) ?>">
        <fieldset><legend>Images</legend>
          <div class="control-group">
            <label>Full image</label>
            <?php if (!empty($item['full_url'])): ?><p><img src="<?= htmlspecialchars((string) $item['full_url'], ENT_QUOTES, 'UTF-8') ?>" alt="Current full image" style="max-width:320px;height:auto;"><br><small><?= htmlspecialchars((string) ($item['full_file'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small></p><?php else: ?><p>No full image is currently stored.</p><?php endif; ?>
            <input id="full-image" data-media-input="full image" type="file" name="full" accept="image/*"<?= $isNew ? ' required' : '' ?>><div data-media-preview="full-image"></div>
            <p class="help-block">Choose a replacement full image.</p>
            <?php if (!empty($item['full_file'])): ?>
              <button type="button" class="btn btn-small" onclick="if(confirm('Remove the full image from storage and the database?')){var f=document.createElement('form');f.method='post';f.action='/gallery/asset-delete.php';f.style.display='none';var i1=document.createElement('input');i1.type='hidden';i1.name='id';i1.value='<?= (int) $item['id'] ?>';f.appendChild(i1);var i2=document.createElement('input');i2.type='hidden';i2.name='asset';i2.value='full';f.appendChild(i2);document.body.appendChild(f);f.submit();}">Remove full image</button>
            <?php endif; ?>
          </div>
          <div class="control-group">
            <label>Thumbnail</label>
            <?php if (!empty($item['thumbnail_url'])): ?><p><img src="<?= htmlspecialchars((string) $item['thumbnail_url'], ENT_QUOTES, 'UTF-8') ?>" alt="Current thumbnail" style="max-width:200px;max-height:165px;height:auto;"><br><small><?= htmlspecialchars((string) ($item['thumbnail_file'] ?? ''), ENT_QUOTES, 'UTF-8') ?><?php $thumbnailInfo = !empty($item['thumbnail_file']) && is_file($item['thumbnail_file']) ? @getimagesize($item['thumbnail_file']) : false; ?><?php if ($thumbnailInfo): ?> | <?= (int) $thumbnailInfo[0] ?> x <?= (int) $thumbnailInfo[1] ?> px; <?= number_format((int) filesize($item['thumbnail_file']) / 1024, 1) ?> KB<?php endif; ?></small></p><?php else: ?><p>No thumbnail is currently stored.</p><?php endif; ?>
            <input id="thumbnail-file" data-media-input="thumbnail" type="file" name="thumbnail" accept="image/*"><div data-media-preview="thumbnail-file"></div>
            <p class="help-block">Choose a replacement thumbnail. The filename should end in <strong>thumb</strong>.</p>
            <input id="thumbnail-filename" type="text" readonly class="form-control" style="margin-top:8px;" placeholder="Thumbnail filename" value="<?= htmlspecialchars(basename((string) ($item['thumbnail_file'] ?? $item['thumbnail_url'] ?? '')), ENT_QUOTES, 'UTF-8') ?>">
            <div id="thumbnail-warning" class="alert alert-warning" hidden>Thumbnail filenames should end in <strong>thumb</strong>, for example image-namethumb.jpg.</div>
            <?php if (!empty($item['thumbnail_file'])): ?>
              <button type="button" class="btn btn-small" onclick="if(confirm('Remove the thumbnail from storage and the database?')){var f=document.createElement('form');f.method='post';f.action='/gallery/asset-delete.php';f.style.display='none';var i1=document.createElement('input');i1.type='hidden';i1.name='id';i1.value='<?= (int) $item['id'] ?>';f.appendChild(i1);var i2=document.createElement('input');i2.type='hidden';i2.name='asset';i2.value='thumbnail';f.appendChild(i2);document.body.appendChild(f);f.submit();}">Remove thumbnail</button>
            <?php endif; ?>
            <button type="button" id="generate-thumbnail-btn" class="btn">Generate correctly named 200 x 165 thumbnail</button>
          </div>
        </fieldset>
        <div class="control-group image-title-active-row"><label>Title</label><input id="image-title" type="text" name="title" value="<?= htmlspecialchars((string) $item['title']) ?>"><label class="active-checkbox"><input type="checkbox" name="active" value="1"<?= ($item['active'] ?? 1) ? ' checked' : '' ?>> Active</label></div>
        <fieldset><legend>Public information</legend>
        <div class="control-group"><label>Public price</label><input type="text" name="pricePublic" value="<?= htmlspecialchars((string) ($item['price_public'] ?? '')) ?>"></div>
        <div class="control-group"><label>Artwork creation date (editable)</label><input type="date" name="artworkCreatedAt" value="<?= htmlspecialchars((string) ($item['artwork_created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><p class="help-block">This is the modifiable artwork date. New uploads default to the image EXIF date where available, otherwise the file timestamp.</p></div>
        <div class="control-group"><label><input type="checkbox" name="sold" value="1"<?= empty($item['available']) ? ' checked' : '' ?>> Sold</label></div>
        <div class="control-group"><label>Prints available</label><input type="checkbox" name="printsAvailable" value="1" <?= !empty($item['prints_available']) ? 'checked' : '' ?>></div>
        <div class="control-group"><label>Medium <span class="searchable-badge">Searchable</span></label><input type="text" name="medium" value="<?= htmlspecialchars((string) ($item['medium'] ?? '')) ?>" list="medium-options"><datalist id="medium-options"><?php foreach ($lookups['mediums'] as $value): ?><option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"><?php endforeach; ?></datalist></div>
        <div class="control-group"><label>Genre <span class="searchable-badge">Searchable</span></label><input type="text" name="genre" value="<?= htmlspecialchars((string) ($item['genre'] ?? '')) ?>" list="genre-options"><datalist id="genre-options"><?php foreach ($lookups['genres'] as $value): ?><option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"><?php endforeach; ?></datalist></div>
        <div class="control-group"><label>Collection <span class="searchable-badge">Searchable</span></label><input type="text" name="collection" value="<?= htmlspecialchars((string) ($item['collection'] ?? '')) ?>" list="collection-options"><datalist id="collection-options"><?php foreach ($lookups['collections'] as $value): ?><option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"><?php endforeach; ?></datalist></div>
        <div class="control-group"><label>Award title</label><input type="text" name="awardTitle" value="<?= htmlspecialchars((string) ($item['award_title'] ?? '')) ?>"></div>
        <div class="control-group"><label>Award description</label><textarea name="awardDescription"><?= htmlspecialchars((string) ($item['award_description'] ?? '')) ?></textarea></div>
        <div class="control-group"><label>Dimensions</label><input type="text" name="dimensions" value="<?= htmlspecialchars((string) ($item['dimensions'] ?? '')) ?>"></div>
        <div class="control-group description-editor"><label>Description</label><div class="description-preview-layout"><div><p class="help-block">Formatting: <code>#</code> main heading, <code>##</code> section heading, <code>###</code>-<code>######</code> smaller headings, <strong>**bold**</strong>, <em>*italic*</em>, blank lines for paragraphs, <code>- list items</code>, and <code>[link](https://example.com)</code>.</p><textarea id="description-input" name="description" rows="8"><?= htmlspecialchars((string) ($item['description'] ?? '')) ?></textarea></div><div><span class="admin-preview-label">Real-time preview (matches the work page style)</span><div class="admin-preview-frame"><div id="description-preview" class="page-content"></div></div></div></div></div>
        <div class="control-group"><label>Location <span class="searchable-badge">Searchable</span></label><textarea name="location"><?= htmlspecialchars((string) ($item['location'] ?? '')) ?></textarea></div>
                <div class="control-group"><label>Tags</label><input type="text" name="tags" value="<?= htmlspecialchars((string) ($item['tag_names'] ?? '')) ?>" placeholder="comma-separated tags (used for search)"></div>
        <div class="control-group"><label>Exhibition</label><select name="exhibition"><option value="">Not assigned</option><?php foreach ($exhibitions as $exhibition): ?><option value="<?= (int) $exhibition['id'] ?>"<?= (string) ($item['exhibition_id'] ?? '') === (string) $exhibition['id'] ? ' selected' : '' ?>><?= htmlspecialchars((string) $exhibition['title'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
        <div class="control-group"><label>Alt text</label><input type="text" name="altText" value="<?= htmlspecialchars((string) ($item['alt_text'] ?? '')) ?>"></div>
        </fieldset>
        <fieldset><legend>Private administration</legend>
        <div class="control-group"><label>Private price</label><input type="text" name="pricePrivate" value="<?= htmlspecialchars((string) ($item['price_private'] ?? '')) ?>"></div>
        <div class="control-group"><label>Private notes</label><textarea name="privateNotes"><?= htmlspecialchars((string) ($item['private_notes'] ?? '')) ?></textarea></div>
        <div class="control-group"><label>Copies sold</label><input type="number" min="0" name="copiesSold" value="<?= (int) ($item['copies_sold'] ?? 0) ?>"></div>
        </fieldset>
        <div class="form-actions">
          <?php if (!empty($prevId)): ?>
            <a class="btn nav-anchor" href="/gallery/admin-edit.php?id=<?= (int)$prevId ?>" data-direction="prev">&larr; Prev</a>
          <?php endif; ?>
          <button type="submit" name="save_mode" value="stay" class="btn btn-primary">Save and stay</button>
          <?php if (!empty($nextId)): ?>
            <button type="submit" name="save_mode" value="stay" class="btn btn-primary" onclick="ensureGoto('next')">Save and go to Next &rarr;</button>
          <?php endif; ?>
          <button type="submit" name="save_mode" value="list" class="btn btn-primary">Save and return to list</button>
          <?php if (!empty($prevId)): ?>
            <button type="submit" name="save_mode" value="stay" class="btn" onclick="ensureGoto('prev')">Save and go to Prev</button>
          <?php endif; ?>
          <?php if (!empty($item['deleted_at'])): ?><a class="btn" href="/gallery/image-restore.php?id=<?= (int) $item['id'] ?>">Undelete</a> <a class="btn btn-danger" href="/gallery/image-delete-permanent.php?id=<?= (int) $item['id'] ?>" onclick="return confirm('Permanently delete this image and all files? This cannot be undone.')">Delete permanently</a><?php else: ?><a class="btn" href="/gallery/image-delete.php?id=<?= (int) $item['id'] ?>" onclick="return confirm('Move this image to deleted items?')">Delete</a><?php endif; ?>
          <a class="btn" href="/gallery/admin-list-images.php">Cancel</a>
          <a class="btn" href="/gallery/admin-list-images.php">Close</a>
          <button type="button" class="btn" id="preview-work-btn">Preview</button>
        </div>
      </form>
      <script>
        function renderDescriptionPreview(text) {
          const escaped = (text || '').replace(/[&<>"']/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[character]));
          const withInline = escaped.replace(/\[([^\]]+)\]\((https?:\/\/[^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>').replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>').replace(/\*(.+?)\*/g, '<em>$1</em>');
          return withInline.split(/\n\s*\n/).filter(Boolean).map(paragraph => {
            const lines = paragraph.split('\n').filter(Boolean);
            if (lines.every(line => /^-\s+/.test(line))) return '<ul>' + lines.map(line => '<li>' + line.replace(/^-\s+/, '') + '</li>').join('') + '</ul>';
            return lines.map(line => { const match = line.match(/^(#{1,6})\s+(.+)$/); return match ? '<h' + match[1].length + '>' + match[2] + '</h' + match[1].length + '>' : '<p>' + line + '</p>'; }).join('');
          }).join('');
        }
        const descriptionInput = document.getElementById('description-input');
        const descriptionPreview = document.getElementById('description-preview');
        function updateDescriptionPreview() { descriptionPreview.innerHTML = renderDescriptionPreview(descriptionInput.value); }
        descriptionInput.addEventListener('input', updateDescriptionPreview);
        updateDescriptionPreview();
      </script>
      <script>
        // Generate a correctly sized 200x165 thumbnail from the selected full image without submitting the form.
        async function createThumbnailFileFromImage(file, width = 200, height = 165, mime = 'image/jpeg', quality = 0.9) {
          return new Promise((resolve, reject) => {
            const fr = new FileReader();
            fr.onerror = () => reject(new Error('Failed to read file'));
            fr.onload = () => {
              const img = new Image();
              img.onerror = () => reject(new Error('Could not load image')); 
              img.onload = () => {
                const sw = img.width, sh = img.height;
                // cover-fit: scale to cover the target and crop centered
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
                    // Some browsers may not support File constructor; fallback to blob with name property
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

        document.getElementById('generate-thumbnail-btn').addEventListener('click', async function () {
          const fullInput = document.getElementById('full-image');
          const thumbInput = document.getElementById('thumbnail-file');
          if (!fullInput || !fullInput.files || !fullInput.files.length) {
            alert('Please choose a full image first.');
            return;
          }
          const file = fullInput.files[0];
          try {
            this.disabled = true;
            this.textContent = 'Generating...';
            const thumbFile = await createThumbnailFileFromImage(file, 200, 165);
            // Set the generated file on the thumbnail input using DataTransfer
            const dt = new DataTransfer();
            dt.items.add(thumbFile);
            thumbInput.files = dt.files;
            // Trigger change so previews update
            thumbInput.dispatchEvent(new Event('change', { bubbles: true }));
            // show a brief status
            const notice = document.createElement('div');
            notice.className = 'alert alert-success';
            notice.textContent = 'Thumbnail generated locally. You can continue editing and click Save when ready.';
            const container = document.querySelector('.container');
            container.insertBefore(notice, container.firstChild);
            setTimeout(() => notice.remove(), 6000);
          } catch (err) {
            alert('Thumbnail generation failed: ' + (err && err.message ? err.message : err));
          } finally {
            this.disabled = false;
            this.textContent = 'Generate correctly named 200 x 165 thumbnail';
          }
        });
      </script>
      <script>
        document.getElementById('preview-work-btn').addEventListener('click', function () {
          var slug = '<?= htmlspecialchars((string) $item['slug'], ENT_QUOTES, 'UTF-8') ?>';
          if (!slug) return;
          window.open('/gallery/work.html#' + encodeURIComponent(slug), '_blank');
        });
        function ensureGoto(dir) {
          var form = document.querySelector('form');
          if (!form) return true;
          var g = form.querySelector('input[name="goto"]');
          if (!g) { g = document.createElement('input'); g.type = 'hidden'; g.name = 'goto'; form.appendChild(g); }
          g.value = dir;
          return true;
        }

        // warn when navigating away via Prev/Next anchors if the form has unsaved changes
        (function () {
          var form = document.querySelector('form');
          if (!form) return;
          var isDirty = false;
          var markDirty = function () { isDirty = true; };
          form.addEventListener('change', markDirty);
          form.addEventListener('input', markDirty);
          form.addEventListener('submit', function () { isDirty = false; });

          // intercept anchor navigation
          var anchors = document.querySelectorAll('a.nav-anchor');
          anchors.forEach(function (a) {
            a.addEventListener('click', function (e) {
              if (!isDirty) return;
              if (!confirm('You have unsaved changes. Leave without saving?')) {
                e.preventDefault();
              }
            });
          });

          // browser-level unload warning
          window.addEventListener('beforeunload', function (e) {
            if (!isDirty) return;
            e.preventDefault();
            // Chrome requires returnValue to be set
            e.returnValue = '';
            return '';
          });
        })();
      </script>
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
          // also set a suggested thumbnail filename if empty
          try {
            const tf = document.getElementById('thumbnail-filename');
            if (tf && !tf.value) {
              const base = this.files[0].name.replace(/\.[^.]+$/, '').replace(/[-_]+$/, '');
              tf.value = base + '-thumb.jpg';
            }
          } catch (e) {}
        });
        document.getElementById('thumbnail-file').addEventListener('change', function () {
          const name = this.files.length ? this.files[0].name.replace(/\.[^.]+$/, '') : '';
          document.getElementById('thumbnail-warning').hidden = !name || /thumb$/i.test(name);
          // update filename display
          try {
            const tf = document.getElementById('thumbnail-filename');
            if (tf) tf.value = this.files.length ? this.files[0].name : '';
          } catch (e) {}
        });
      </script>
      <script>
        // Duplicate-check before creating a NEW image: warns if a thumbnail with similar basename exists in the DB.
        (function () {
          var form = document.querySelector('form');
          if (!form) return;
          form.addEventListener('submit', async function (e) {
            // only run on Add mode
            if (!<?= $isNew ? 'true' : 'false' ?>) return;
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
              if (!resp.ok) return; // allow submit and let server handle errors
              var data = await resp.json();
              if (data && data.success && data.exists) {
                var items = (data.items || []).map(function (it) { return '#' + it.id + ' ' + (it.title || ''); }).join('\n');
                var msg = 'A thumbnail with a similar filename already exists:\n' + items + '\n\nContinuing may create a duplicate entry. Proceed?';
                if (!confirm(msg)) {
                  e.preventDefault();
                }
              }
            } catch (err) {
              // on any failure, do not block submit; server-side will handle duplicates if needed
              return;
            }
          });
        })();
      </script>
    <?php else: ?>
      <p>Image not found.</p>
    <?php endif; ?>
  </div>
  <script src="/scripts/admin-media-preview.js"></script>
</body>
</html>
