<?php
require __DIR__ . '/gallery-lib.php';
header('Content-Type: text/html; charset=utf-8');
$id = (int) ($_GET['id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Artwork</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="../scripts/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../styles/custom.css" rel="stylesheet">
</head>
<body>
  <div class="container">
    <h1 id="title">Artwork</h1>
    <img id="image" src="" alt="" style="max-width:100%; height:auto;">
    <div id="meta"></div>
  </div>
  <script>
    const id = <?= (int) $id ?>;
    fetch('/gallery/search.php?page=1&limit=50')
      .then(r => r.json())
      .then(payload => {
        const item = payload && payload.result ? payload.result.items.find(x => Number(x.id) === Number(id)) : null;
        if (!item) return;
        document.getElementById('title').textContent = item.title || 'Artwork';
        document.getElementById('image').src = item.full || item.thumbnail;
        document.getElementById('image').alt = item.title || 'Artwork';
        const meta = document.getElementById('meta');
        meta.innerHTML = `
          <p><strong>Medium:</strong> ${item.medium || ''}</p>
          <p><strong>Genre:</strong> ${item.genre || ''}</p>
          <p><strong>Collection:</strong> ${item.collection || ''}</p>
          <p>${item.description || ''}</p>
        `;
      });
  </script>
</body>
</html>
