<?php

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$root = realpath(__DIR__ . '/..');
$file = realpath($root . '/' . ltrim($path, '/'));

if ($path === '/' || $file === false || is_dir($file) || !str_starts_with($file, $root . DIRECTORY_SEPARATOR)) {
    require __DIR__ . '/../index.php';
    return;
}

$size = filesize($file);
header('Accept-Ranges: bytes');
header('Content-Type: application/zip');

if (!isset($_SERVER['HTTP_RANGE'])) {
    header('Content-Length: ' . $size);
    $fp = fopen($file, 'rb');
    while (!feof($fp)) {
        echo fread($fp, 8192);
    }
    fclose($fp);
    return;
}

if (!preg_match('/^bytes=(\d*)-(\d*)$/', trim($_SERVER['HTTP_RANGE']), $m)) {
    http_response_code(416);
    header('Content-Range: bytes */' . $size);
    return;
}

$start = $m[1] === '' ? $size - (int)$m[2] : (int)$m[1];
$end = $m[2] === '' ? $size - 1 : (int)$m[2];

if ($start < 0 || $start >= $size || $end >= $size || $start > $end) {
    http_response_code(416);
    header('Content-Range: bytes */' . $size);
    return;
}

$end = min($end, $size - 1);
$length = $end - $start + 1;

http_response_code(206);
header("Content-Range: bytes $start-$end/$size");
header('Content-Length: ' . $length);

$fp = fopen($file, 'rb');
fseek($fp, $start);
$remaining = $length;
while ($remaining > 0) {
    $chunk = fread($fp, min(8192, $remaining));
    if ($chunk === false) break;
    echo $chunk;
    $remaining -= strlen($chunk);
}
fclose($fp);
