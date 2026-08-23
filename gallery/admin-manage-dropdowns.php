<?php
require __DIR__ . '/gallery-lib.php';

session_start();
header('Content-Type: text/html; charset=utf-8');
if (empty($_SESSION['gallery_admin_authenticated']) || $_SESSION['gallery_admin_authenticated'] !== true) {
    header('Location: /gallery/admin-login.php');
    exit;
}

$pdo = gallery_init_db();
$values = gallery_lookup_values($pdo);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['mediums', 'genres', 'collections', 'tags'] as $table) {
        if (!empty($_POST[$table])) {
            $list = gallery_normalize_string_array($_POST[$table]);
            foreach ($list as $item) {
                gallery_get_or_create_lookup($pdo, $table, $item);
            }
        }
    }
    header('Location: /gallery/admin-manage-dropdowns.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Manage Dropdowns</title>
  <link href="../scripts/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../styles/custom.css" rel="stylesheet">
</head>
<body>
  <div class="container">
    <h1>Manage Dropdowns</h1>
    <form method="post">
      <?php foreach (['mediums' => 'Mediums', 'genres' => 'Genres', 'collections' => 'Collections', 'tags' => 'Tags'] as $key => $label): ?>
        <div class="control-group">
          <label><?= htmlspecialchars($label) ?></label>
          <textarea name="<?= htmlspecialchars($key) ?>" rows="5"><?= htmlspecialchars(implode("\n", $values[$key] ?? [])) ?></textarea>
        </div>
      <?php endforeach; ?>
      <button type="submit" class="btn btn-primary">Save</button>
    </form>
  </div>
</body>
</html>
