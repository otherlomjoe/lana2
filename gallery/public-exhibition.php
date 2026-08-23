<?php
require __DIR__ . '/gallery-lib.php';
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Exhibition</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="../scripts/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../styles/custom.css" rel="stylesheet">
</head>
<body>
  <div class="container">
    <h1 id="exhibition-title">Exhibition</h1>
    <div id="exhibition-image"></div>
    <div id="exhibition-output"></div>
  </div>
  <script>
    const slug = new URLSearchParams(window.location.search).get('slug') || window.location.hash.substring(1);
    Promise.all([
      fetch('/gallery-api.php?action=list-exhibitions').then(r => r.json()),
      fetch('/gallery/search.php?page=1&limit=100&exhibition=' + encodeURIComponent(slug)).then(r => r.json())
    ])
      .then(([exhibitions, payload]) => {
        const exhibition = (Array.isArray(exhibitions) ? exhibitions : []).find(item => item.slug === slug);
        if (exhibition) {
          document.getElementById('exhibition-title').textContent = exhibition.title;
          if (exhibition.heroImage || exhibition.thumbnailImage) {
            document.getElementById('exhibition-image').innerHTML = `<img src="${exhibition.heroImage || exhibition.thumbnailImage}" alt="${exhibition.title}" style="max-width:100%;height:auto;">`;
          }
        }
        return payload;
      })
      .then(payload => {
        const items = payload && payload.result ? payload.result.items : [];
        document.getElementById('exhibition-output').innerHTML = items.map(item => `
          <div class="well">
            <h3>${item.title}</h3>
            <img src="${item.thumbnail || item.full}" alt="${item.title || 'Artwork'}" style="max-width:220px;height:auto;">
          </div>
        `).join('');
      });
  </script>
</body>
</html>
