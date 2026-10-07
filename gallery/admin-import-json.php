<?php
require __DIR__ . '/gallery-lib.php';
session_start();
if (empty($_SESSION['gallery_admin_authenticated']) || $_SESSION['gallery_admin_authenticated'] !== true) {
    header('Location: /gallery/admin-login.php');
    exit;
}

$error = '';
$report = [];
$availableFiles = [];
$itemDir = __DIR__ . '/all/item';
if (is_dir($itemDir)) {
    foreach (scandir($itemDir) as $f) {
        if (is_file($itemDir . DIRECTORY_SEPARATOR . $f) && preg_match('/\.json$/i', $f)) {
            $availableFiles[] = $f;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        gallery_ensure_upload_directories();
        $sourceChoice = $_POST['source'] ?? 'server';
        $jsonData = null;
        if ($sourceChoice === 'server') {
            $fn = $_POST['server_file'] ?? '';
            $path = realpath($itemDir . DIRECTORY_SEPARATOR . $fn);
            if (!$path || !is_file($path)) throw new RuntimeException('Selected file not found.');
            $jsonData = json_decode(file_get_contents($path), true);
        } else {
            if (empty($_FILES['upload_json']) || empty($_FILES['upload_json']['tmp_name'])) throw new RuntimeException('No uploaded file.');
            $raw = file_get_contents($_FILES['upload_json']['tmp_name']);
            $jsonData = json_decode($raw, true);
        }
        if (!is_array($jsonData)) throw new RuntimeException('Invalid JSON data.');

        $skipExisting = !empty($_POST['skipExisting']);
        $imported = 0;
        $skipped = 0;
        $failed = 0;
        $details = [];

        $pdo = gallery_init_db();
        if (!$pdo) throw new RuntimeException('Gallery DB unavailable.');

        foreach ($jsonData as $item) {
            $item = (array) $item;
            $slug = trim((string) ($item['slug'] ?? '')) ?: gallery_slugify($item['title'] ?? '');
            // check if exists
            $check = $pdo->prepare('SELECT id FROM images WHERE slug = :slug LIMIT 1');
            $check->execute([':slug' => $slug]);
            $existingId = (int) $check->fetchColumn();
            if ($existingId && $skipExisting && empty($_POST['overwriteExisting'])) { $skipped++; $details[] = ['slug'=>$slug,'status'=>'skipped']; continue; }

            try {
                $title = trim((string) ($item['title'] ?? $slug));
                $description = (string) ($item['description'] ?? '');
                // prefer the plain description with paragraphs; gallery_render_formatted_text will convert to HTML on display
                $medium = (string) ($item['medium'] ?? '');
                $dimensions = (string) ($item['dimensions'] ?? '');
                $artworkCreatedAt = isset($item['artworkCreatedAt']) ? ($item['artworkCreatedAt'] ?: null) : null;
                $altText = (string) ($item['altText'] ?? ($item['imgTitleAttr'] ?? ''));
                $tags = $item['tags'] ?? [];
                if (!is_array($tags)) $tags = preg_split('/[,;\n]+/', (string)$tags);

                // handle files: move from gallery/all/full -> uploads/full, thumbs similarly
                $fullSrc = '';
                if (!empty($item['full'])) {
                    // normalize possible leading /gallery or ../
                    $candidate = ltrim($item['full'], '/');
                    $candidatePath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $candidate);
                    if (!is_file($candidatePath)) {
                        // try relative to gallery root
                        $candidatePath = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . $item['full']);
                    }
                    if ($candidatePath && is_file($candidatePath)) {
                        $fullSrc = $candidatePath;
                    }
                }

                $thumbSrc = '';
                if (!empty($item['thumbnail'])) {
                    $candidate = ltrim($item['thumbnail'], '/');
                    $candidatePath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $candidate);
                    if (!is_file($candidatePath)) {
                        $candidatePath = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . $item['thumbnail']);
                    }
                    if ($candidatePath && is_file($candidatePath)) {
                        $thumbSrc = $candidatePath;
                    }
                }

                // determine destination filenames
                $destFull = '';
                $destThumb = '';
                if ($fullSrc !== '') {
                    $fname = basename($fullSrc);
                    $safe = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $fname);
                    $destDir = __DIR__ . '/uploads/full';
                    $destPath = $destDir . '/' . $safe;
                    $i = 1;
                    while (is_file($destPath)) {
                        $safe = pathinfo($safe, PATHINFO_FILENAME) . '-' . $i . '.' . pathinfo($safe, PATHINFO_EXTENSION);
                        $destPath = $destDir . '/' . $safe;
                        $i++;
                    }

                    // Derive artwork date from the source file BEFORE moving/copying (preserves original timestamps/EXIF)
                    if (empty($artworkCreatedAt)) {
                        $derived = gallery_derive_artwork_date($fullSrc);
                        if (!empty($derived['date'])) {
                            $artworkCreatedAt = $derived['date'];
                        }
                    }

                    if (!@rename($fullSrc, $destPath)) {
                        // try copy
                        if (!@copy($fullSrc, $destPath)) throw new RuntimeException('Could not move/copy full image: ' . $fullSrc);
                    }
                    $destFull = $destPath;
                    $fullUrl = '/gallery/uploads/full/' . basename($destPath);
                } else {
                    $destFull = '';
                    $fullUrl = '';
                }

                if ($thumbSrc !== '') {
                    $tname = basename($thumbSrc);
                    $tsafe = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $tname);
                    $tdestDir = __DIR__ . '/uploads/thumbs';
                    $tdestPath = $tdestDir . '/' . $tsafe;
                    $j = 1;
                    while (is_file($tdestPath)) {
                        $tsafe = pathinfo($tsafe, PATHINFO_FILENAME) . '-' . $j . '.' . pathinfo($tsafe, PATHINFO_EXTENSION);
                        $tdestPath = $tdestDir . '/' . $tsafe;
                        $j++;
                    }
                    if (!@rename($thumbSrc, $tdestPath)) {
                        if (!@copy($thumbSrc, $tdestPath)) throw new RuntimeException('Could not move/copy thumb image: ' . $thumbSrc);
                    }
                    $destThumb = $tdestPath;
                    $thumbUrl = '/gallery/uploads/thumbs/' . basename($tdestPath);
                } elseif ($destFull !== '') {
                    // generate a thumbnail from full
                    $baseName = pathinfo($destFull, PATHINFO_FILENAME) . 'thumb.jpg';
                    $tdestPath = __DIR__ . '/uploads/thumbs/' . $baseName;
                    if (gallery_resize_cover($destFull, $tdestPath, 200, 165, 88)) {
                        $destThumb = $tdestPath;
                        $thumbUrl = '/gallery/uploads/thumbs/' . basename($tdestPath);
                    } else {
                        $destThumb = '';
                        $thumbUrl = '';
                    }
                } else {
                    $destThumb = '';
                    $thumbUrl = '';
                }

                // derive artworkCreatedAt from file metadata if not present
                if (empty($artworkCreatedAt)) {
                    // prefer source path-derived date (already attempted above if fullSrc was present)
                    if (!empty($destFull) && is_file($destFull)) {
                        $derived2 = gallery_derive_artwork_date($destFull);
                        if (!empty($derived2['date'])) {
                            $artworkCreatedAt = $derived2['date'];
                        }
                    }
                }

                // prefer HTML description if provided in JSON, otherwise use the plain text description
                $descToStore = '';
                if (!empty($item['descriptionHtml'])) {
                    // store the HTML from the JSON if present
                    $descToStore = (string) $item['descriptionHtml'];
                } else {
                    $descToStore = $description;
                }

                // prepare data for gallery_save_image
                $imgData = [
                    'title' => $title,
                    'slug' => $slug,
                    'fullPath' => $destFull,
                    'thumbnailPath' => $destThumb,
                    'fullUrl' => $fullUrl,
                    'thumbUrl' => $thumbUrl,
                    'description' => $descToStore,
                    'dimensions' => $dimensions,
                    'medium' => $medium,
                    'artworkCreatedAt' => $artworkCreatedAt,
                    'altText' => $altText,
                    'tags' => $tags,
                    // respect explicit availability flag from JSON when provided
                    'available' => isset($item['available']) ? $item['available'] : (isset($item['sold']) ? (!empty($item['sold']) ? 0 : 1) : null),
                    'privateNotes' => 'Imported from ' . ($item['page'] ?? ''),
                ];

                // if overwriting existing, include the id to update
                if (!empty($existingId) && !empty($_POST['overwriteExisting'])) {
                    $imgData['id'] = $existingId;
                }

                $res = gallery_save_image($imgData, []);

                // link image to exhibitions if provided in JSON
                if (!empty($item['exhibitions'])) {
                    $exList = is_array($item['exhibitions']) ? $item['exhibitions'] : preg_split('/[,;\n]+/', (string)$item['exhibitions']);
                    foreach ($exList as $exRaw) {
                        // normalize exhibition info
                        $exInfo = [];
                        if (is_array($exRaw)) {
                            $exInfo = $exRaw;
                        } elseif (is_object($exRaw)) {
                            $exInfo = (array) $exRaw;
                        } else {
                            $exInfo = ['title' => (string)$exRaw];
                        }

                        $exTitle = trim((string) ($exInfo['title'] ?? $exInfo['name'] ?? ''));
                        if ($exTitle === '') continue;
                        $exSlug = trim((string) ($exInfo['slug'] ?? gallery_slugify($exTitle)));
                        $exStart = trim((string) ($exInfo['startDate'] ?? $exInfo['start_date'] ?? ''));
                        $exEnd = trim((string) ($exInfo['endDate'] ?? $exInfo['end_date'] ?? ''));
                        $exLocation = trim((string) ($exInfo['location'] ?? ''));
                        $exDescription = trim((string) ($exInfo['description'] ?? ''));

                        // find existing exhibition by slug or title
                        $stmtEx = $pdo->prepare('SELECT id FROM exhibitions WHERE slug = :slug LIMIT 1');
                        $stmtEx->execute([':slug' => $exSlug]);
                        $exId = $stmtEx->fetchColumn();
                        if (!$exId) {
                            // try by title
                            $stmtEx2 = $pdo->prepare('SELECT id FROM exhibitions WHERE title = :title LIMIT 1');
                            $stmtEx2->execute([':title' => $exTitle]);
                            $exId = $stmtEx2->fetchColumn();
                        }

                        if (!$exId) {
                            // create the exhibition with available metadata
                            $exPayload = [
                                'title' => $exTitle ?: $exSlug,
                                'slug' => $exSlug,
                                'startDate' => $exStart,
                                'endDate' => $exEnd,
                                'location' => $exLocation,
                                'description' => $exDescription,
                            ];
                            $createdEx = gallery_save_exhibition($exPayload, []);
                            $exId = $createdEx['id'] ?? 0;
                        }

                        if ($exId && !empty($res['id'])) {
                            // ensure link doesn't already exist
                            $checkLink = $pdo->prepare('SELECT 1 FROM image_exhibitions WHERE image_id = :image_id AND exhibition_id = :exhibition_id LIMIT 1');
                            $checkLink->execute([':image_id' => $res['id'], ':exhibition_id' => $exId]);
                            $existsLink = $checkLink->fetchColumn();
                            if (!$existsLink) {
                                // determine next sort order
                                $orderStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 AS next_order FROM image_exhibitions WHERE exhibition_id = :exhibition_id');
                                $orderStmt->execute([':exhibition_id' => $exId]);
                                $nextOrder = (int) $orderStmt->fetchColumn();
                                $insertLink = $pdo->prepare('INSERT INTO image_exhibitions (image_id, exhibition_id, sort_order) VALUES (:image_id, :exhibition_id, :sort_order)');
                                $insertLink->execute([':image_id' => $res['id'], ':exhibition_id' => $exId, ':sort_order' => $nextOrder]);

                                // automatically assign exhibition hero and thumbnail if not set
                                $exRowStmt = $pdo->prepare('SELECT hero_image, thumbnail_url FROM exhibitions WHERE id = :id LIMIT 1');
                                $exRowStmt->execute([':id' => $exId]);
                                $exRow = $exRowStmt->fetch();
                                if ($exRow && (empty($exRow['hero_image']) || empty($exRow['thumbnail_url']))) {
                                    // determine source full image path
                                    if (!empty($destFull) && is_file($destFull)) {
                                        $exFullDir = __DIR__ . '/uploads/exhibitions/full';
                                        $exThumbDir = __DIR__ . '/uploads/exhibitions/thumbs';
                                        if (!is_dir($exFullDir)) mkdir($exFullDir, 0775, true);
                                        if (!is_dir($exThumbDir)) mkdir($exThumbDir, 0775, true);

                                        $ext = pathinfo($destFull, PATHINFO_EXTENSION);
                                        $heroFilename = 'exhibition-' . $exSlug . '-' . bin2hex(random_bytes(4)) . '.' . ($ext ?: 'jpg');
                                        $heroDest = $exFullDir . '/' . $heroFilename;
                                        // copy full to exhibition full
                                        if (!@copy($destFull, $heroDest)) {
                                            // ignore failure
                                        } else {
                                            $heroUrl = '/gallery/uploads/exhibitions/full/' . $heroFilename;
                                            // generate thumbnail
                                            $thumbFilename = pathinfo($heroFilename, PATHINFO_FILENAME) . '-thumb.jpg';
                                            $thumbDest = $exThumbDir . '/' . $thumbFilename;
                                            if (gallery_resize_cover($heroDest, $thumbDest, 200, 165, 88)) {
                                                $thumbUrl = '/gallery/uploads/exhibitions/thumbs/' . $thumbFilename;
                                            } else {
                                                $thumbUrl = '';
                                                $thumbDest = '';
                                            }

                                            // update exhibition record
                                            $updateEx = $pdo->prepare('UPDATE exhibitions SET hero_image = :hero_image, hero_file = :hero_file, thumbnail_file = :thumbnail_file, thumbnail_url = :thumbnail_url WHERE id = :id');
                                            $updateEx->execute([
                                                ':hero_image' => $heroUrl,
                                                ':hero_file' => $heroDest,
                                                ':thumbnail_file' => $thumbDest ?: null,
                                                ':thumbnail_url' => $thumbUrl ?: null,
                                                ':id' => $exId,
                                            ]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                $imported++;
                $details[] = ['slug'=>$slug,'status'=>'imported','id'=>$res['id'] ?? 0];
            } catch (Throwable $e) {
                $failed++;
                $details[] = ['slug'=>$slug,'status'=>'error','message'=>$e->getMessage()];
            }
        }

        $report = ['imported'=>$imported,'skipped'=>$skipped,'failed'=>$failed,'details'=>$details];

    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

?><!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Import images from JSON</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="/scripts/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="/styles/custom.css" rel="stylesheet">
    <link href="/styles/admin.css" rel="stylesheet">
</head>
<body>
<?php include __DIR__ . '/admin-nav.php'; ?>
<div class="container admin-friendly">
    <h1>Import images from JSON</h1>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($report): ?>
        <h3>Import report</h3>
        <p>Imported: <?php echo (int)$report['imported']; ?> — Skipped: <?php echo (int)$report['skipped']; ?> — Failed: <?php echo (int)$report['failed']; ?></p>
        <table class="table table-striped"><thead><tr><th>Slug</th><th>Status</th><th>Info</th></tr></thead><tbody>
            <?php foreach ($report['details'] as $d): ?>
                <tr>
                    <td><?php echo htmlspecialchars($d['slug'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($d['status'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($d['message'] ?? ($d['id'] ?? '')); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody></table>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <div class="form-group">
            <label>Select JSON file on server</label>
            <select name="server_file" class="form-control">
                <?php foreach ($availableFiles as $f): ?>
                    <option value="<?php echo htmlspecialchars($f); ?>"><?php echo htmlspecialchars($f); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Or upload a JSON file</label>
            <input type="file" name="upload_json" accept="application/json" class="form-control" />
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="skipExisting" checked /> Skip images with existing slug</label>
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="overwriteExisting" /> Overwrite existing images (replace files and update metadata)</label>
        </div>
        <input type="hidden" name="source" value="server" />
        <button class="btn btn-primary">Import</button>
    </form>

    <hr />
    <p>After importing you can assign a specific image as an exhibition hero via the exhibition edit page (open an exhibition and choose a hero image).</p>
</div>
</body>
</html>