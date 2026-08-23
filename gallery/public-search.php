<?php
require __DIR__ . '/gallery-lib.php';
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Search</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="../scripts/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../styles/custom.css" rel="stylesheet">
</head>
<body>
  <div class="container">
    <h1>Search</h1>
    <form id="search-form">
      <input type="text" name="q" placeholder="Search title, medium, description">
      <button type="submit" class="btn btn-primary">Search</button>
    </form>
    <div id="results"></div>
  </div>
  <script>
    document.getElementById('search-form').addEventListener('submit', function (event) {
      event.preventDefault();
      const q = new FormData(this).get('q');
      fetch('/gallery/search.php?q=' + encodeURIComponent(q || ''))
        .then(r => r.json())
        .then(payload => {
          const results = payload && payload.result ? payload.result.items : [];
          document.getElementById('results').innerHTML = results.map(item => `
            <div class="well"><a href="/gallery/public-image.php?id=${item.id}">${item.title}</a></div>
          `).join('') || '<p>No results found.</p>';
        });
    });
  </script>
</body>
</html>
