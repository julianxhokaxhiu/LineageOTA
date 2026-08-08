<?php

function sendStaticFile(string $file): void
{
    $size = filesize($file);
    $mimeType = mime_content_type($file) ?: 'application/octet-stream';

    header('Accept-Ranges: bytes');
    header('Content-Type: ' . $mimeType);

    $rangeHeader = $_SERVER['HTTP_RANGE'] ?? null;

    if ($rangeHeader === null || !preg_match('/^bytes=(\d*)-(\d*)$/', trim($rangeHeader), $matches)) {
        header('Content-Length: ' . $size);
        readfile($file);
        return;
    }

    $start = $matches[1] === '' ? null : (int) $matches[1];
    $end = $matches[2] === '' ? null : (int) $matches[2];

    // Support a single byte range request, as used by the OTA updater.
    if ($start === null) {
        $suffixLength = $end;
        if ($suffixLength <= 0) {
            http_response_code(416);
            header('Content-Range: bytes */' . $size);
            return;
        }

        $start = max(0, $size - $suffixLength);
        $end = $size - 1;
    } else {
        if ($end === null || $end >= $size) {
            $end = $size - 1;
        }
    }

    if ($start < 0 || $start > $end || $start >= $size) {
        http_response_code(416);
        header('Content-Range: bytes */' . $size);
        return;
    }

    $length = ($end - $start) + 1;

    http_response_code(206);
    header("Content-Range: bytes {$start}-{$end}/{$size}");
    header('Content-Length: ' . $length);

    $handle = fopen($file, 'rb');
    if ($handle === false) {
        http_response_code(500);
        return;
    }

    fseek($handle, $start);

    $remaining = $length;
    while (!feof($handle) && $remaining > 0) {
        $chunkSize = min(8192, $remaining);
        $buffer = fread($handle, $chunkSize);
        if ($buffer === false) {
            break;
        }

        echo $buffer;
        $remaining -= strlen($buffer);
    }

    fclose($handle);
}

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$root = realpath(__DIR__ . '/..');
$candidate = $root . '/' . ltrim($path, '/');
$resolvedFile = realpath($candidate);

if (
    $path !== '/'
    && $resolvedFile !== false
    && !is_dir($resolvedFile)
    && str_starts_with($resolvedFile, $root . DIRECTORY_SEPARATOR)
) {
    sendStaticFile($resolvedFile);
    return true;
}

require __DIR__ . '/../index.php';
