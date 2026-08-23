<?php
require_once __DIR__ . '/gallery-lib.php';

session_start();
header('Content-Type: text/html; charset=utf-8');

if (!empty($_SESSION['gallery_admin_authenticated']) && $_SESSION['gallery_admin_authenticated'] === true) {
    header('Location: /gallery/admin-upload.php');
    exit;
}

$hash = getenv('GALLERY_ADMIN_PASSWORD_HASH');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');
  if ($hash !== false && $hash !== '' && password_verify($password, $hash)) {
        $_SESSION['gallery_admin_authenticated'] = true;
        $_SESSION['gallery_admin_user'] = 'admin';
        header('Location: /gallery/admin-upload.php');
        exit;
    }
    $error = 'Incorrect password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="../scripts/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../styles/custom.css" rel="stylesheet">
</head>
<body>
  <div class="container">
    <h1>Gallery Admin</h1>
    <?php if ($error !== ''): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post">
      <div class="control-group">
        <label for="password">Password</label>
        <input id="password" type="password" name="password" required>
      </div>
      <button type="submit" class="btn btn-primary">Login</button>
    </form>
  </div>
</body>
</html>
