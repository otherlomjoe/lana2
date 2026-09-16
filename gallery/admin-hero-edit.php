<?php
require __DIR__ . '/gallery-lib.php';

session_start();
if (empty($_SESSION['gallery_admin_authenticated']) || $_SESSION['gallery_admin_authenticated'] !== true) {
    header('Location: /gallery/admin-login.php');
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
$hero = $id > 0 ? gallery_get_home_hero($id) : null;
if ($id > 0 && !$hero) {
    http_response_code(404);
    exit('Hero not found.');
}
$exhibitions = gallery_list_exhibitions();
$message = $_SESSION['gallery_admin_message'] ?? '';
$error = $_SESSION['gallery_admin_message_error'] ?? '';
unset($_SESSION['gallery_admin_message'], $_SESSION['gallery_admin_message_error']);
$csrf = gallery_set_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= $hero ? 'Edit home hero' : 'Create home hero' ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="../scripts/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../styles/custom.css" rel="stylesheet">
</head>
<body>
  <div class="container">
    <?php require __DIR__ . '/admin-nav.php'; ?>
    <h1><?= $hero ? 'Edit home hero' : 'Create home hero' ?></h1>
    <?php if ($message !== ''): ?><div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <form method="post" action="/gallery/hero-save.php" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="id" value="<?= (int) ($hero['id'] ?? 0) ?>">
      <div class="control-group"><label for="hero-title">Title</label><input id="hero-title" type="text" name="title" required value="<?= htmlspecialchars((string) ($hero['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
      <div class="control-group"><label for="hero-slug">Slug</label><input id="hero-slug" type="text" name="slug" value="<?= htmlspecialchars((string) ($hero['slug'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><p class="help-block">Leave blank to create it from the title.</p></div>
      <div class="control-group"><label><input type="checkbox" name="visible" value="1"<?= !empty($hero['visible']) ? ' checked' : '' ?>> Display on homepage</label></div>
      <div class="control-group"><label for="display-order">Display order</label><input id="display-order" type="number" min="0" step="1" name="displayOrder" value="<?= (int) ($hero['displayOrder'] ?? 0) ?>"></div>
      <div class="control-group"><label for="visual-style">Panel style</label><select id="visual-style" name="visualStyle"><option value="info"<?= ($hero['visualStyle'] ?? 'info') === 'info' ? ' selected' : '' ?>>Info (blue)</option><option value="success"<?= ($hero['visualStyle'] ?? '') === 'success' ? ' selected' : '' ?>>Success (green)</option><option value="neutral"<?= ($hero['visualStyle'] ?? '') === 'neutral' ? ' selected' : '' ?>>Neutral</option></select></div>
      <div class="control-group"><label for="hero-image">Hero image</label><?php if (!empty($hero['image'])): ?><p><img src="<?= htmlspecialchars($hero['image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($hero['imageAlt'], ENT_QUOTES, 'UTF-8') ?>" style="max-width:320px;height:auto;"><br><small>Current image</small></p><?php endif; ?><input id="hero-image" type="file" name="image" accept="image/jpeg,image/png,image/webp"><p class="help-block">Optional JPG, PNG, or WEBP image.</p></div>
      <div class="control-group"><label for="image-alt">Image alt text</label><input id="image-alt" type="text" name="imageAlt" value="<?= htmlspecialchars((string) ($hero['imageAlt'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
      <div class="control-group"><label for="exhibition-id">Related exhibition</label><select id="exhibition-id" name="exhibitionId"><option value="">None</option><?php foreach ($exhibitions as $exhibition): ?><option value="<?= (int) $exhibition['id'] ?>"<?= (string) ($hero['exhibitionId'] ?? '') === (string) $exhibition['id'] ? ' selected' : '' ?>><?= htmlspecialchars($exhibition['title'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
      <div class="description-preview-layout hero-editor-layout">
        <div><label for="hero-body">Hero content</label><p class="help-block">Formatting: <code>#</code> main heading, <code>##</code> section heading, <code>###</code>-<code>######</code> smaller headings, <code>**bold**</code>, <code>*italic*</code>, blank lines for paragraphs, <code>- list items</code>, and <code>[link text](https://example.com)</code>. Use the title field as the page's main heading; normally begin content headings at <code>##</code>.</p><textarea id="hero-body" name="body" rows="18"><?= htmlspecialchars((string) ($hero['body'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea></div>
        <div><strong>Real-time preview</strong><div id="hero-preview" class="description-preview" aria-live="polite"></div></div>
      </div>
      <div class="form-actions"><button type="submit" name="save_mode" value="stay" class="btn btn-primary">Save and stay</button><button type="submit" name="save_mode" value="list" class="btn btn-primary">Save and return to list</button><?php if ($hero): ?><a class="btn" href="/gallery/public-hero.php?slug=<?= rawurlencode($hero['slug']) ?>" target="_blank" rel="noopener">Preview page</a><?php endif; ?><a class="btn" href="/gallery/admin-list-heroes.php">Cancel</a></div>
    </form>
  </div>
<script>
(function () {
  const input = document.getElementById('hero-body');
  const preview = document.getElementById('hero-preview');
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
</body>
</html>
