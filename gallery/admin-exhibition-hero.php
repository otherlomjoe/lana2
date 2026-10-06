<?php
require __DIR__ . '/gallery-lib.php';
session_start();
if (empty($_SESSION['gallery_admin_authenticated']) || $_SESSION['gallery_admin_authenticated'] !== true) {
    header('Location: /gallery/admin-login.php');
    exit;
}

$pdo = gallery_init_db();
if (!$pdo) {
    die('Gallery DB unavailable.');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $exId = (int) ($_POST['exhibition_id'] ?? 0);
        $imageId = (int) ($_POST['hero_image_id'] ?? 0);
        if ($exId <= 0 || $imageId <= 0) throw new RuntimeException('Invalid exhibition or image selection.');

        // fetch image full path
        $stmt = $pdo->prepare('SELECT full_file, thumbnail_file FROM images WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $imageId]);
        $img = $stmt->fetch();
        if (!$img) throw new RuntimeException('Image not found.');

        $fullPath = $img['full_file'] ?? '';
        if ($fullPath === '' || !is_file($fullPath)) throw new RuntimeException('Source image file not available on server.');

        // ensure dirs
        $exFullDir = __DIR__ . '/uploads/exhibitions/full';
        $exThumbDir = __DIR__ . '/uploads/exhibitions/thumbs';
        if (!is_dir($exFullDir)) mkdir($exFullDir, 0775, true);
        if (!is_dir($exThumbDir)) mkdir($exThumbDir, 0775, true);

        // copy full
        $exSlugStmt = $pdo->prepare('SELECT slug FROM exhibitions WHERE id = :id LIMIT 1');
        $exSlugStmt->execute([':id' => $exId]);
        $exSlug = $exSlugStmt->fetchColumn() ?: 'ex' . $exId;
        $ext = pathinfo($fullPath, PATHINFO_EXTENSION) ?: 'jpg';
        $heroFilename = 'exhibition-' . $exSlug . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        $heroDest = $exFullDir . '/' . $heroFilename;
        if (!@copy($fullPath, $heroDest)) throw new RuntimeException('Could not copy hero image.');
        $heroUrl = '/gallery/uploads/exhibitions/full/' . $heroFilename;

        // generate thumb
        $thumbFilename = pathinfo($heroFilename, PATHINFO_FILENAME) . '-thumb.jpg';
        $thumbDest = $exThumbDir . '/' . $thumbFilename;
        if (!gallery_resize_cover($heroDest, $thumbDest, 200, 165, 88)) {
            $thumbDest = '';
            $thumbUrl = null;
        } else {
            $thumbUrl = '/gallery/uploads/exhibitions/thumbs/' . $thumbFilename;
        }

        // update exhibition
        $updateEx = $pdo->prepare('UPDATE exhibitions SET hero_image = :hero_image, hero_file = :hero_file, thumbnail_file = :thumbnail_file, thumbnail_url = :thumbnail_url WHERE id = :id');
        $updateEx->execute([
            ':hero_image' => $heroUrl,
            ':hero_file' => $heroDest,
            ':thumbnail_file' => $thumbDest ?: null,
            ':thumbnail_url' => $thumbUrl ?: null,
            ':id' => $exId,
        ]);

        $success = 'Exhibition hero updated.';

    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$exhibitions = gallery_list_exhibitions($pdo);
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Assign Exhibition Heroes</title>
    <link href="/scripts/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
    .thumb { width:120px; height:auto; margin:6px; border:1px solid #ccc; padding:4px; }
    .ex-block { margin-bottom:30px; }
    </style>
</head>
<body>
<?php include __DIR__ . '/admin-nav.php'; ?>
<div class="container">
    <h1>Assign Exhibition Heroes</h1>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php foreach ($exhibitions as $ex): ?>
        <div class="ex-block">
            <h3><?php echo htmlspecialchars($ex['title']); ?> (<?php echo htmlspecialchars($ex['startDate'] ?? ''); ?>)</h3>
            <?php $images = gallery_list_exhibition_images((int)$ex['id'], $pdo); ?>
            <?php if (count($images) === 0): ?>
                <p><em>No images attached to this exhibition.</em></p>
            <?php else: ?>
                <form method="post">
                    <input type="hidden" name="exhibition_id" value="<?php echo (int)$ex['id']; ?>">
                    <div>
                        <?php foreach ($images as $img): ?>
                            <label style="display:inline-block;text-align:center;margin:6px;">
                                <input type="radio" name="hero_image_id" value="<?php echo (int)$img['id']; ?>" <?php echo (!empty($ex['heroImage']) && $ex['heroImage'] === $img['full']) ? 'checked' : ''; ?>>
                                <div><img class="thumb" src="<?php echo htmlspecialchars($img['thumbnail'] ?: $img['full']); ?>" alt=""></div>
                                <div style="max-width:120px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis"><?php echo htmlspecialchars($img['title']); ?></div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top:8px;"><button class="btn btn-primary">Set selected image as hero</button></div>
                </form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
</body>
</html>