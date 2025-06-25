<?php
$secret = getenv('SECRET');
if (!isset($_GET['token'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing token']);
    exit;
}

$token = base64_decode($_GET['token']);
$payload = json_decode($token, true);

if (!$payload || !isset($payload['hash'], $payload['file'], $payload['expires_at'], $payload['sig'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid token']);
    exit;
}

if (time() > $payload['expires_at']) {
    http_response_code(403);
    echo json_encode(['error' => 'Link expired']);
    exit;
}

$originalSig = $payload['sig'];
unset($payload['sig']);
$expectedSig = hash_hmac('sha256', json_encode($payload), $secret);
if (!hash_equals($expectedSig, $originalSig)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

$baseDir = '/var/www/html/files';
$fullPath = realpath($baseDir . '/' . $payload['file']);
if (!$fullPath || strpos($fullPath, realpath($baseDir)) !== 0 || !is_file($fullPath)) {
    http_response_code(404);
    echo json_encode(['error' => 'File not found']);
    exit;
}

$size = filesize($fullPath);
$mime = mime_content_type($fullPath);
$fp = fopen($fullPath, 'rb');

$start = 0;
$end = $size - 1;
$httpStatus = 200;

if (isset($_SERVER['HTTP_RANGE'])) {
    if (preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $matches)) {
        $start = intval($matches[1]);
        if (!empty($matches[2])) {
            $end = intval($matches[2]);
        }
        $httpStatus = 206;
    }
}

$length = $end - $start + 1;

http_response_code($httpStatus);
header("Content-Type: $mime");
header('Accept-Ranges: bytes');
header("Content-Length: $length");
if ($httpStatus === 206) {
    header("Content-Range: bytes $start-$end/$size");
}
header('Content-Disposition: inline; filename="' . basename($fullPath) . '"');

fseek($fp, $start);
$bufferSize = 8192;
while (!feof($fp) && ($pos = ftell($fp)) <= $end) {
    if ($pos + $bufferSize > $end) {
        $bufferSize = $end - $pos + 1;
    }
    echo fread($fp, $bufferSize);
    flush();
}
fclose($fp);
exit;