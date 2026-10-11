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

// gather counts
function count_table(PDO $pdo, string $table): int {
    try {
        $stmt = $pdo->query('SELECT COUNT(*) FROM ' . preg_replace('/[^a-z0-9_]/i', '', $table));
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

$counts = [];
$counts['images'] = count_table($pdo, 'images');
$counts['exhibitions'] = count_table($pdo, 'exhibitions');
$counts['home_heroes'] = count_table($pdo, 'home_heroes');
$counts['home_slider_images'] = count_table($pdo, 'home_slider_images');
$counts['mediums'] = count_table($pdo, 'mediums');
$counts['genres'] = count_table($pdo, 'genres');
$counts['collections'] = count_table($pdo, 'collections');
$counts['tags'] = count_table($pdo, 'tags');

// quick recent activity samples
$recentImages = [];
try {
    $stmt = $pdo->query('SELECT id, title, created_at FROM images ORDER BY created_at DESC LIMIT 6');
    $recentImages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $recentImages = [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Gallery Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="../scripts/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../styles/custom.css" rel="stylesheet">
  <link href="../styles/admin.css" rel="stylesheet">
</head>
<body>
  <div class="container admin-friendly">
    <?php require __DIR__ . '/admin-nav.php'; ?>
    <h1>Admin Home</h1>
    <p class="lead">Quick summary of gallery data</p>
    <div class="row">
      <div class="col-md-3">
        <div class="panel panel-default">
          <div class="panel-heading">Images</div>
          <div class="panel-body"><strong><?= (int) $counts['images'] ?></strong><br><a href="/gallery/admin-list-images.php">Manage images</a> | <a href="/gallery/admin-image-add.php">Add new</a></div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="panel panel-default">
          <div class="panel-heading">Exhibitions</div>
          <div class="panel-body"><strong><?= (int) $counts['exhibitions'] ?></strong><br><a href="/gallery/admin-list-exhibitions.php">Manage exhibitions</a></div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="panel panel-default">
          <div class="panel-heading">Home heroes</div>
          <div class="panel-body"><strong><?= (int) $counts['home_heroes'] ?></strong><br><a href="/gallery/admin-list-heroes.php">Manage heroes</a></div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="panel panel-default">
          <div class="panel-heading">Home slider</div>
          <div class="panel-body"><strong><?= (int) $counts['home_slider_images'] ?></strong><br><a href="/gallery/admin-list-sliders.php">Manage slider images</a></div>
        </div>
      </div>
    </div>

    <div class="row" style="margin-top:18px;">
      <div class="col-md-3">
        <div class="panel panel-default">
          <div class="panel-heading">Mediums</div>
          <div class="panel-body"><strong><?= (int) $counts['mediums'] ?></strong><br><a href="/gallery/admin-list-mediums.php">Manage</a></div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="panel panel-default">
          <div class="panel-heading">Genres</div>
          <div class="panel-body"><strong><?= (int) $counts['genres'] ?></strong><br><a href="/gallery/admin-list-genres.php">Manage</a></div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="panel panel-default">
          <div class="panel-heading">Collections</div>
          <div class="panel-body"><strong><?= (int) $counts['collections'] ?></strong><br><a href="/gallery/admin-list-collections.php">Manage</a></div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="panel panel-default">
          <div class="panel-heading">Tags</div>
          <div class="panel-body"><strong><?= (int) $counts['tags'] ?></strong><br><a href="/gallery/admin-list-tags.php">Manage</a></div>
        </div>
      </div>
    </div>

    <h2 style="margin-top:24px;">Recent images</h2>
    <?php if (count($recentImages) === 0): ?>
      <p>No recent images.</p>
    <?php else: ?>
      <ul>
        <?php foreach ($recentImages as $ri): ?>
          <li><a href="/gallery/admin-edit.php?id=<?= (int) $ri['id'] ?>"><?= htmlspecialchars((string) $ri['title'], ENT_QUOTES, 'UTF-8') ?></a> — <?= htmlspecialchars((string) $ri['created_at'], ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <h2 style="margin-top:24px;">Suggested maintenance</h2>
    <ul>
      <li>Check the uploads/thumbs directory for orphaned thumbnail files.</li>
      <li>Run the gallery import script for newly added files (if applicable).</li>
      <li>Review images marked unavailable or deleted.</li>
      <li>Verify recent uploads have thumbnails and are accessible on the public site.</li>
    </ul>

  </div>
</body>
</html>
