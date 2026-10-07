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
$images = gallery_list_images($pdo, true);
// Sort images alphabetically by title (A-Z) for the exhibition multi-select
usort($images, function(array $a, array $b) {
    return strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
});
$message = $_SESSION['gallery_admin_message'] ?? '';
$error = $_SESSION['gallery_admin_message_error'] ?? '';
unset($_SESSION['gallery_admin_message'], $_SESSION['gallery_admin_message_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Create Exhibition</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="../scripts/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../styles/custom.css" rel="stylesheet">
  <link href="../styles/admin.css" rel="stylesheet">
</head>
<body>
  <div class="container admin-friendly">
    <?php require __DIR__ . '/admin-nav.php'; ?>
    <h1><?= $exhibition ? 'Edit Exhibition' : 'Create Exhibition' ?></h1>
    <?php if ($message !== ''): ?><div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <form method="post" action="/gallery/exhibition-save.php" enctype="multipart/form-data">
      <input type="hidden" name="id" value="<?= (int) $id ?>">
      <div class="control-group"><label>Title</label><input type="text" name="title" value="<?= htmlspecialchars((string) ($exhibition['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required></div>
      <div class="control-group"><label>Start date</label><input type="date" name="startDate" value="<?= htmlspecialchars((string) ($exhibition['start_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
      <div class="control-group"><label>End date</label><input type="date" name="endDate" value="<?= htmlspecialchars((string) ($exhibition['end_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
      <div class="control-group"><label>Location</label><input type="text" name="location" value="<?= htmlspecialchars((string) ($exhibition['location'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
      <div class="control-group"><label><input type="checkbox" name="active" value="1" <?= (isset($exhibition['active']) && $exhibition['active']) ? 'checked' : '' ?>> Visible</label></div>
      <fieldset><legend>Exhibition images</legend>
      <div class="control-group"><label>Hero image</label><?php if (!empty($exhibition['hero_image'])): ?><p><img src="<?= htmlspecialchars((string) $exhibition['hero_image'], ENT_QUOTES, 'UTF-8') ?>" alt="Current exhibition hero image" style="max-width:320px;height:auto;"><br><small><?= htmlspecialchars((string) ($exhibition['hero_file'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small></p><?php else: ?><p>No hero image is currently stored.</p><?php endif; ?><input id="exhibition-hero-file" data-media-input="hero image" type="file" name="hero" accept="image/*"><div data-media-preview="exhibition-hero-file"></div><p class="help-block">Choose a replacement hero image.</p><?php if (!empty($exhibition['hero_file'])): ?><button type="submit" formaction="/gallery/exhibition-asset-delete.php" formmethod="post" name="asset" value="hero" class="btn btn-small" onclick="return confirm('Remove the hero image from storage and the database?')">Remove hero image</button><?php endif; ?></div>
      <div class="control-group"><label>Thumbnail</label><?php if (!empty($exhibition['thumbnail_url'])): ?><p><img src="<?= htmlspecialchars((string) $exhibition['thumbnail_url'], ENT_QUOTES, 'UTF-8') ?>" alt="Current exhibition thumbnail" style="max-width:200px;max-height:165px;height:auto;"><br><small><?= htmlspecialchars((string) ($exhibition['thumbnail_file'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small></p><?php else: ?><p>No thumbnail is currently stored.</p><?php endif; ?><input id="exhibition-thumbnail-file" data-media-input="thumbnail" type="file" name="thumbnail" accept="image/*"><div data-media-preview="exhibition-thumbnail-file"></div><p class="help-block">Choose a replacement thumbnail. The filename should end in <strong>thumb</strong>.</p><div id="exhibition-thumbnail-warning" class="alert alert-warning" hidden>Thumbnail filenames should end in <strong>thumb</strong>, for example image-namethumb.jpg.</div><button type="button" id="generate-exhibition-thumbnail-btn" class="btn">Generate correctly named 200 x 165 thumbnail</button><br><?php if (!empty($exhibition['thumbnail_file'])): ?><button type="submit" formaction="/gallery/exhibition-asset-delete.php" formmethod="post" name="asset" value="thumbnail" class="btn btn-small" onclick="return confirm('Remove the thumbnail from storage and the database?')">Remove thumbnail</button><?php endif; ?></div>
      </fieldset>
      <div class="exhibition-description-layout">
        <div><label>Description</label><p class="help-block">Formatting: <code>#</code> main heading, <code>##</code> section heading, <code>###</code>-<code>######</code> smaller headings, <strong>**bold**</strong>, <em>*italic*</em>, blank lines for paragraphs, <code>- list items</code>, and <code>[link](https://example.com)</code>.</p><textarea id="exhibition-description" name="description" rows="10"><?= htmlspecialchars((string) ($exhibition['description'] ?? '')) ?></textarea></div>
        <div>
          <span class="admin-preview-label">Real-time preview (matches the exhibition page style)</span>
          <div class="admin-preview-frame"><div id="exhibition-description-preview" class="page-content" aria-live="polite"></div></div>
        </div>
      </div>
      <div class="control-group"><label>Images in exhibition</label>
        <div class="ex-images-controls" style="margin-bottom:8px;">
          <button type="button" id="select-all-images" class="btn btn-sm">Select all</button>
          <button type="button" id="clear-all-images" class="btn btn-sm">Clear all</button>
        </div>
        <p class="help-block">Use the checkboxes to pick images. The top-to-bottom order in this list determines the exhibition order.</p>
        <?php
          // Ensure images are ordered alphabetically ascending by title for the list
          usort($images, function(array $a, array $b){
            return strcasecmp((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));
          });
        ?>
        <ul class="ex-images-list" role="listbox" aria-multiselectable="true">
          <?php foreach ($images as $image): ?>
            <li>
              <label>
                <input type="checkbox" name="imageIds[]" value="<?= (int) $image['id'] ?>" <?= in_array((int) $image['id'], $selectedImageIds, true) ? 'checked' : '' ?>>
                <span class="item-title"><?= htmlspecialchars((string) ($image['title'] ?? 'Untitled'), ENT_QUOTES, 'UTF-8') ?></span>
              </label>
            </li>
          <?php endforeach; ?>
        </ul>
        <script>
          (function(){
            const list = document.querySelector('.ex-images-list');
            if (!list) return;
            function updateLabelForCheckbox(ch) {
              const label = ch.closest('label');
              if (!label) return;
              if (ch.checked) label.classList.add('checked'); else label.classList.remove('checked');
            }
            // Initialize labels based on initial state
            list.querySelectorAll('input[type="checkbox"][name="imageIds[]"]').forEach(ch => updateLabelForCheckbox(ch));
            // Handle changes
            list.addEventListener('change', (e) => {
              const t = e.target;
              if (t && t.matches('input[type="checkbox"][name="imageIds[]"]')) updateLabelForCheckbox(t);
            });
            // Select all / Clear all controls
            const selectAllBtn = document.getElementById('select-all-images');
            const clearAllBtn = document.getElementById('clear-all-images');
            if (selectAllBtn) selectAllBtn.addEventListener('click', () => {
              list.querySelectorAll('input[type="checkbox"][name="imageIds[]"]').forEach(ch => { ch.checked = true; updateLabelForCheckbox(ch); });
            });
            if (clearAllBtn) clearAllBtn.addEventListener('click', () => {
              list.querySelectorAll('input[type="checkbox"][name="imageIds[]"]').forEach(ch => { ch.checked = false; updateLabelForCheckbox(ch); });
            });
          })();
        </script>
      </div>
      <button type="submit" name="save_mode" value="stay" class="btn btn-primary">Save and stay</button>
      <button type="submit" name="save_mode" value="list" class="btn btn-primary">Save and return to list</button>
      <a class="btn" href="/gallery/admin-list-exhibitions.php">Cancel</a>
    </form>
    <script>
      document.getElementById('exhibition-thumbnail-file').addEventListener('change', function () {
        const name = this.files.length ? this.files[0].name.replace(/\.[^.]+$/, '') : '';
        document.getElementById('exhibition-thumbnail-warning').hidden = !name || /thumb$/i.test(name);
      });
      document.getElementById('generate-exhibition-thumbnail-btn').addEventListener('click', function () {
        const form = this.form || document.querySelector('form');
        if (!form) return;
        let g = form.querySelector('input[name="generateThumbnail"]');
        if (!g) { g = document.createElement('input'); g.type = 'hidden'; g.name = 'generateThumbnail'; g.value = '1'; form.appendChild(g); }
        let s = form.querySelector('input[name="save_mode"]');
        if (!s) { s = document.createElement('input'); s.type = 'hidden'; s.name = 'save_mode'; s.value = 'stay'; s.id = 'save_mode_hidden'; form.appendChild(s); } else { s.value = 'stay'; }
        form.submit();
      });
      (function () {
        const input = document.getElementById('exhibition-description');
        const preview = document.getElementById('exhibition-description-preview');
        function escapeHtml(value) { return value.replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c])); }
        function render(text) {
          const escaped = escapeHtml(text.trim()).replace(/\r\n|\r/g, '\n').replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>').replace(/\*(.+?)\*/g, '<em>$1</em>').replace(/\[([^\]]+)\]\((https:\/\/[^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');
          return escaped.split(/\n\s*\n/).filter(Boolean).map(block => {
            const lines = block.split('\n').map(line => line.trim()).filter(Boolean);
            if (lines.every(line => /^-\s+/.test(line))) return '<ul>' + lines.map(line => '<li>' + line.replace(/^-\s+/, '') + '</li>').join('') + '</ul>';
            return lines.map(line => { const match = line.match(/^(#{1,6})\s+(.+)$/); return match ? '<h' + match[1].length + '>' + match[2] + '</h' + match[1].length + '>' : '<p>' + line + '</p>'; }).join('');
          }).join('');
        }
        function update() { preview.innerHTML = render(input.value); }
        input.addEventListener('input', update); update();
      }());
    </script>
    <script src="/scripts/admin-media-preview.js"></script>
  </div>
</body>
</html>
