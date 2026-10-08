<?php
require __DIR__ . '/../gallery/gallery-lib.php';

$jsonFile = __DIR__ . '/../gallery/all/item/allimages.json';
if (!is_file($jsonFile)) {
    echo "allimages.json not found at expected path: $jsonFile\n";
    exit(1);
}

$raw = file_get_contents($jsonFile);
$data = json_decode($raw, true);
if (!is_array($data)) {
    echo "Invalid JSON in allimages.json\n";
    exit(1);
}

$total = count($data);
$haveDate = 0;
$missing = 0;
$provenanceCounts = ['exif'=>0,'iptc'=>0,'filectime'=>0,'filemtime'=>0,'existing'=>0,'none'=>0];
$samples = [];

function fileSlugLocal(string $slug): string {
    return str_replace('-', '', $slug);
}

foreach ($data as $idx => $item) {
    $slug = $item['slug'] ?? ($item['title'] ?? '') ;
    $slug = (string)$slug;
    $artDate = $item['artworkCreatedAt'] ?? null;
    if (!empty($artDate)) {
        $haveDate++;
        $provenanceCounts['existing']++;
        continue;
    }

    $missing++;

    // try to resolve a candidate full image path
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

    // try standard location by slug
    $slugFile = fileSlugLocal($slug) . '.jpg';
    $candidates[] = __DIR__ . '/../gallery/all/full/' . $slugFile;
    $candidates[] = __DIR__ . '/../gallery/all/full/' . ($slug . '.jpg');

    $found = null;
    foreach ($candidates as $cand) {
        if (!$cand) continue;
        $cand = str_replace(['\\','/'], DIRECTORY_SEPARATOR, $cand);
        if (is_file($cand)) { $found = $cand; break; }
    }

    $derived = ['date'=>null,'source'=>null];
    if ($found) {
        $derived = gallery_derive_artwork_date($found);
    }

    $src = $derived['source'] ?? null;
    if ($src && isset($provenanceCounts[$src])) $provenanceCounts[$src]++;
    else $provenanceCounts['none']++;

    if (count($samples) < 20) {
        $samples[] = [
            'slug' => $slug,
            'found' => $found ?: null,
            'derived_date' => $derived['date'] ?? null,
            'source' => $derived['source'] ?? null,
        ];
    }
}

echo "Scan report for: $jsonFile\n";
echo "Total items: $total\n";
echo "Have artworkCreatedAt in JSON: $haveDate\n";
echo "Missing artworkCreatedAt: $missing\n\n";
echo "Provenance breakdown (for missing items):\n";
foreach ($provenanceCounts as $k => $v) {
    echo sprintf("  %-10s %5d\n", $k, $v);
}

echo "\nSample of first 20 missing items and derived info:\n";
foreach ($samples as $s) {
    echo sprintf("- %s\n  found: %s\n  derived_date: %s\n  source: %s\n\n", $s['slug'], $s['found'] ?? '(not found)', $s['derived_date'] ?? '(none)', $s['source'] ?? '(none)');
}

exit(0);
