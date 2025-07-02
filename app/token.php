<?php
// token.php - secure file delivery via signed links

$secret = getenv('SECRET');
if (!$secret) {
    http_response_code(500);
    echo "Server misconfiguration: SECRET missing.";
    exit;
}

// Validate required parameters
$slug    = $_GET['slug'] ?? '';
$file    = $_GET['file'] ?? '';
$expires = $_GET['expires'] ?? '';
$sig     = $_GET['sig'] ?? '';

if (!$slug || !$file || !$expires || !$sig) {
    http_response_code(400);
    echo "Missing required parameters.";
    exit;
}

// Check expiration
if (time() > intval($expires)) {
    http_response_code(403);
    echo "Link expired.";
    exit;
}

// Validate signature
$data = "$slug|$file|$expires";
$expectedSig = hash_hmac('sha256', $data, $secret);

if (!hash_equals($expectedSig, $sig)) {
    http_response_code(403);
    echo "Invalid signature.";
    exit;
}

// Resolve actual file path securely
$basePath = "/var/www/html/files/" . basename($slug) . "/";
$requestedPath = $basePath . $file;
$realPath = realpath($requestedPath);

// Ensure the real path is inside the slug's folder
if ($realPath === false || strpos($realPath, $basePath) !== 0 || !file_exists($realPath)) {
    http_response_code(404);
    echo "File not found.";
    exit;
}

// Serve the file securely
// Serve via Nginx X-Accel-Redirect
header('Content-Type: application/octet-stream');
header('Content-Disposition: inline; filename="' . basename($file) . '"');
header('X-Accel-Redirect: /protected-files/' . $slug . '/' . $file);
exit;
