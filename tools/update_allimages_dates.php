<?php
require __DIR__ . '/../gallery/gallery-lib.php';

$jsonPath = __DIR__ . '/../gallery/all/item/allimages.json';
if (!is_file($jsonPath)) {
    echo "Could not find allimages.json at expected path: $jsonPath\n";
    exit(1);
}

$raw = file_get_contents($jsonPath);
$data = json_decode($raw, true);
if (!is_array($data)) {
    echo "Invalid JSON in allimages.json\n";
    exit(1);
}

$backupPath = $jsonPath . '.bak.' . date('YmdHis');
if (!@copy($jsonPath, $backupPath)) {
    echo "Warning: could not create backup to $backupPath\n";
} else {
    echo "Backup created: $backupPath\n";
}

$changed = 0;
$updatedSamples = [];

function fileSlugLocal(string $slug): string { return str_replace('-', '', $slug); }

foreach ($data as $i => $item) {
    if (!is_array($item)) continue;
    $artDate = $item['artworkCreatedAt'] ?? null;
    if (!empty($artDate)) continue; // already set

    $slug = (string) ($item['slug'] ?? ($item['title'] ?? ''));

    // Build candidate paths
    $candidates = [];
    if (!empty($item['full'])) {
        $f = ltrim((string)$item['full'], '/\\');
        $candidates[] = __DIR__ . '/../' . $f;
        $candidates[] = __DIR__ . '/../gallery/' . $f;
        $resolved = realpath(__DIR__ . '/../' . $f);
        if ($resolved) $candidates[] = $resolved;
        $resolved2 = realpath(__DIR__ . '/../gallery/' . $f);
        if ($resolved2) $candidates[] = $resolved2;
    }

    $slugFile = fileSlugLocal($slug) . '.jpg';
    $candidates[] = __DIR__ . '/../gallery/all/full/' . $slugFile;
    $candidates[] = __DIR__ . '/../gallery/all/full/' . ($slug . '.jpg');

    // also try with capitalized and original forms
    $candidates[] = __DIR__ . '/../gallery/all/full/' . ucfirst($slug) . '.jpg';

    $found = null;
    foreach ($candidates as $cand) {
        if (!$cand) continue;
        $candNorm = str_replace(['\\','/'], DIRECTORY_SEPARATOR, $cand);
        if (is_file($candNorm)) { $found = $candNorm; break; }
    }

    $derived = ['date'=>null,'source'=>null];
    if ($found) {
        $derived = gallery_derive_artwork_date($found);
    }

    if (!empty($derived['date'])) {
        $data[$i]['artworkCreatedAt'] = $derived['date'];
        $changed++;
        if (count($updatedSamples) < 40) {
            $updatedSamples[] = ['slug' => $slug, 'found' => $found, 'date' => $derived['date'], 'source' => $derived['source']];
        }
    }
}

if ($changed > 0) {
    $newJson = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($newJson === false) {
        echo "Failed to encode updated JSON\n";
        exit(1);
    }
    if (file_put_contents($jsonPath, $newJson) === false) {
        echo "Failed to write updated allimages.json\n";
        exit(1);
    }
}

echo "Updated entries: $changed\n";
if ($changed > 0) {
    echo "Sample updates:\n";
    foreach ($updatedSamples as $s) {
        echo "- {$s['slug']} -> {$s['date']} ({$s['source']})  found: {$s['found']}\n";
    }
}

echo "Backup location: $backupPath\n";
echo "Done.\n";
