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
    <h1>Exhibition</h1>
    <div id="exhibition-output"></div>
  </div>
  <script>
    fetch('/gallery/search.php?page=1&limit=20')
      .then(r => r.json())
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
