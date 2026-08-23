<?php
require __DIR__ . '/gallery-lib.php';

$missing = [];
foreach ([
    'gallery_init_db',
    'gallery_list_images',
    'gallery_save_image',
    'gallery_search_images',
    'gallery_save_exhibition',
    'gallery_detect_orientation',
    'gallery_build_title_from_filename',
    'gallery_render_formatted_text',
] as $fn) {
    if (!function_exists($fn)) {
        $missing[] = $fn;
    }
}

if ($missing) {
    fwrite(STDERR, "Missing functions: " . implode(', ', $missing) . PHP_EOL);
    exit(1);
}

$pdo = gallery_database();
if (!$pdo) {
    fwrite(STDERR, "Database connection failed\n");
    exit(1);
}

$built = gallery_build_title_from_filename('blue_bird-flying.JPG');
if ($built !== 'Blue Bird Flying') {
    fwrite(STDERR, "Unexpected title derivation: $built\n");
    exit(1);
}

echo "admin system test passed\n";
