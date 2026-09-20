<?php
require __DIR__ . '/gallery-lib.php';

session_start();
if (empty($_SESSION['gallery_admin_authenticated']) || $_SESSION['gallery_admin_authenticated'] !== true) {
    header('Location: /gallery/admin-login.php');
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
$slide = $id > 0 ? gallery_get_slider_image($id) : null;
if ($id > 0 && !$slide) {
    http_response_code(404);
    exit('Slide not found.');
}
$message = $_SESSION['gallery_admin_message'] ?? '';
$error = $_SESSION['gallery_admin_message_error'] ?? '';
unset($_SESSION['gallery_admin_message'], $_SESSION['gallery_admin_message_error']);
$csrf = gallery_set_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= $slide ? 'Edit slide' : 'Add slide' ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="../scripts/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../styles/custom.css" rel="stylesheet">
</head>
<body>
  <div class="container admin-friendly">
    <?php require __DIR__ . '/admin-nav.php'; ?>
    <h1><?= $slide ? 'Edit slide' : 'Add slide' ?></h1>
    <?php if ($message !== ''): ?><div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <form method="post" action="/gallery/slider-save.php" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="id" value="<?= (int) ($slide['id'] ?? 0) ?>">
      <div class="control-group"><label for="slide-image">Slide image</label><?php if (!empty($slide['image'])): ?><p><img src="<?= htmlspecialchars($slide['image'], ENT_QUOTES, 'UTF-8') ?>" alt="" style="max-width:320px;height:auto;"><br><small>Current image</small></p><?php endif; ?><input id="slide-image" type="file" name="image" accept="image/jpeg,image/png,image/webp"<?= $slide ? '' : ' required' ?>><p class="help-block"><strong>Recommended size: 1600 &times; 600px</strong> (landscape banner), JPG/PNG/WEBP, under 10 MB.</p></div>
      <div class="control-group"><label for="slide-title">Title</label><input id="slide-title" type="text" name="title" value="<?= htmlspecialchars((string) ($slide['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><p class="help-block">Shown as the caption over the slide.</p></div>
      <div class="control-group"><label for="slide-link">Link (optional)</label><input id="slide-link" type="text" name="linkUrl" placeholder="https://example.com or /gallery/all/item/example.html" value="<?= htmlspecialchars((string) ($slide['linkUrl'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><p class="help-block">If set, clicking the slide opens this page.</p></div>
      <div class="control-group"><label for="display-order">Display order</label><input id="display-order" type="number" min="0" step="1" name="displayOrder" value="<?= (int) ($slide['displayOrder'] ?? 0) ?>"></div>
      <div class="control-group"><label><input type="checkbox" name="active" value="1"<?= !empty($slide['active']) ? ' checked' : '' ?>> Show on homepage</label></div>
      <div class="form-actions"><button type="submit" name="save_mode" value="stay" class="btn btn-primary btn-large">Save and stay</button><button type="submit" name="save_mode" value="list" class="btn btn-primary btn-large">Save and return to list</button><a class="btn btn-large" href="/gallery/admin-list-sliders.php">Cancel</a></div>
    </form>
  </div>
</body>
</html>
