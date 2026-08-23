<?php
require __DIR__ . '/gallery-lib.php';
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Gallery</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="../scripts/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../styles/custom.css" rel="stylesheet">
  <style>
    .thumb-grid { display:flex; flex-wrap:wrap; gap:16px; }
    .thumb-card { width:220px; }
    .thumb-card img { width:100%; height:auto; }
  </style>
</head>
<body>
  <div class="container">
    <h1>Gallery</h1>
    <div id="gallery-grid" class="thumb-grid"></div>
  </div>
  <script>
    fetch('/gallery/search.php?page=1&limit=20')
      .then(r => r.json())
      .then(payload => {
        const grid = document.getElementById('gallery-grid');
        const items = payload && payload.result ? payload.result.items : [];
        grid.innerHTML = items.map(item => `
          <div class="thumb-card">
            <a href="/gallery/public-image.php?id=${item.id}"><img src="${item.thumbnail || item.full}" alt="${item.title || 'Artwork'}"></a>
            <div><strong>${item.title}</strong></div>
            ${item.price ? `<div>${item.price}</div>` : ''}
          </div>
        `).join('');
      });
  </script>
</body>
</html>
