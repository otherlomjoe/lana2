<?php
require __DIR__ . '/gallery-lib.php';

session_start();
header('Content-Type: text/html; charset=utf-8');
if (empty($_SESSION['gallery_admin_authenticated']) || $_SESSION['gallery_admin_authenticated'] !== true) {
    header('Location: /gallery/admin-login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Bulk Upload</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="../scripts/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../styles/custom.css" rel="stylesheet">
</head>
<body>
  <div class="container">
    <h1>Bulk Upload</h1>
    <p>Upload multiple images at once. Titles, thumbnails, alt text, and orientation are derived automatically.</p>
    <form method="post" enctype="multipart/form-data" action="/gallery/image-save.php">
      <div class="control-group">
        <label>Multiple images</label>
        <input type="file" name="full[]" accept="image/*" multiple>
      </div>
      <button type="submit" class="btn btn-primary">Process files</button>
    </form>
  </div>
</body>
</html>
