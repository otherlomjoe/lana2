<?php
// Temporary debug helper — remove this file after use.
// Place this file in the gallery folder on the server and open it in your browser
// e.g. https://lana.lombard.id.au/gallery/debug-run.php

error_reporting(E_ALL);
ini_set('display_errors','1');

$out = [];
$out[] = "Timestamp: " . date('c');
$out[] = "sys_get_temp_dir(): " . sys_get_temp_dir();

// System temp debug log
$sysLog = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'gallery-debug-sql.log';
if (is_file($sysLog)) {
    $out[] = "----- FOUND: $sysLog -----";
    $content = file_get_contents($sysLog);
    $out[] = "(first 20 lines)";
    $lines = preg_split('/\r?\n/', $content);
    $out = array_merge($out, array_slice($lines, 0, 20));
    $out[] = "... (truncated) ...";
} else {
    $out[] = "No $sysLog found";
}

// Gallery fallback log in this folder
$galleryLog = __DIR__ . DIRECTORY_SEPARATOR . 'debug-sql.log';
if (is_file($galleryLog)) {
    $out[] = "----- FOUND: $galleryLog -----";
    $content = file_get_contents($galleryLog);
    $out[] = "(first 20 lines)";
    $lines = preg_split('/\r?\n/', $content);
    $out = array_merge($out, array_slice($lines, 0, 20));
    $out[] = "... (truncated) ...";
} else {
    $out[] = "No $galleryLog found";
}

// Also show existence of possible alternate path used earlier (lana2)
$altGalleryLog = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lana2.lombard.id.au' . DIRECTORY_SEPARATOR . 'gallery' . DIRECTORY_SEPARATOR . 'debug-sql.log';
if (is_file($altGalleryLog)) {
    $out[] = "----- FOUND: $altGalleryLog -----";
    $content = file_get_contents($altGalleryLog);
    $out[] = "(first 20 lines)";
    $lines = preg_split('/\r?\n/', $content);
    $out = array_merge($out, array_slice($lines, 0, 20));
    $out[] = "... (truncated) ...";
} else {
    $out[] = "No $altGalleryLog found";
}

// Show gallery folder listing (brief)
$files = glob(__DIR__ . DIRECTORY_SEPARATOR . '*');
$out[] = '----- Gallery folder listing -----';
foreach ($files as $f) {
    $out[] = basename($f) . ' | ' . (is_file($f) ? 'file' : 'dir') . ' | ' . date('Y-m-d H:i:s', filemtime($f));
}

echo '<pre>' . htmlspecialchars(implode("\n", $out)) . '</pre>';

// IMPORTANT: delete this file from the server after use to avoid exposing logs.
