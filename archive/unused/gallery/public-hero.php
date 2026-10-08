<?php
require __DIR__ . '/gallery-lib.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
$hero = $slug !== '' ? gallery_get_home_hero(0, $slug) : null;
if (!$hero || !$hero['visible']) {
    http_response_code(404);
    exit('Hero not found.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($hero['title'], ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="../scripts/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../styles/custom.css" rel="stylesheet">
</head>
<body id="pageBody">
  <main class="container home-hero-page">
    <p><a href="/index.html">Home</a></p>
    <article class="home-hero-panel alert alert-<?= htmlspecialchars($hero['visualStyle'], ENT_QUOTES, 'UTF-8') ?>">
      <h1><?= htmlspecialchars($hero['title'], ENT_QUOTES, 'UTF-8') ?></h1>
      <?php if ($hero['image'] !== ''): ?><img src="<?= htmlspecialchars($hero['image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($hero['imageAlt'], ENT_QUOTES, 'UTF-8') ?>" loading="eager" style="max-width:100%;height:auto;"><?php endif; ?>
      <div class="home-hero-body"><?= $hero['bodyHtml'] ?></div>
    </article>
  </main>
</body>
</html>
