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
  <title>Admin Upload</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="../scripts/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../styles/custom.css" rel="stylesheet">
</head>
<body>
  <div class="container">
    <h1>Admin Upload</h1>
    <p><a href="/gallery/admin-list-images.php">View images</a> | <a href="/gallery/admin-list-exhibitions.php">View exhibitions</a> | <a href="/gallery/admin-manage-dropdowns.php">Manage dropdowns</a></p>
    <form method="post" action="/gallery/image-save.php" enctype="multipart/form-data">
      <div class="control-group"><label>Title</label><input type="text" name="title" required></div>
      <div class="control-group"><label>Price</label><input type="text" name="pricePublic"></div>
      <div class="control-group"><label>Available</label><select name="available"><option value="1">Yes</option><option value="0">No</option></select></div>
      <div class="control-group"><label>Medium</label><input type="text" name="medium"></div>
      <div class="control-group"><label>Genre</label><input type="text" name="genre"></div>
      <div class="control-group"><label>Collection</label><input type="text" name="collection"></div>
      <div class="control-group"><label>Award title</label><input type="text" name="awardTitle"></div>
      <div class="control-group"><label>Award description</label><textarea name="awardDescription"></textarea></div>
      <div class="control-group"><label>Dimensions</label><input type="text" name="dimensions"></div>
      <div class="control-group"><label>Description</label><textarea name="description"></textarea></div>
      <div class="control-group"><label>Location</label><textarea name="location"></textarea></div>
      <div class="control-group"><label>Tags</label><input type="text" name="tags"></div>
      <div class="control-group"><label>Full image</label><input type="file" name="full" accept="image/*" required></div>
      <div class="control-group"><label>Thumbnail</label><input type="file" name="thumbnail" accept="image/*" required></div>
      <button type="submit" class="btn btn-primary">Save image</button>
    </form>
  </div>
</body>
</html>
