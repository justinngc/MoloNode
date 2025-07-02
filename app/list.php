<?php
// list.php - Serves torrent files securely based on token

$secret = getenv('SECRET'); // Injected from environment or webserver config

header('Content-Type: application/json');

// Step 1: Decode token
if (!isset($_GET['token'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing token']);
    exit;
}

$token = base64_decode($_GET['token']);
$payload = json_decode($token, true);

// Step 2: Validate structure
if (!$payload || !isset($payload['hash'], $payload['file'], $payload['expires_at'], $payload['sig'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid token']);
    exit;
}

// Step 3: Check expiration
if (time() > $payload['expires_at']) {
    http_response_code(403);
    echo json_encode(['error' => 'Link expired']);
    exit;
}

// Step 4: Verify signature
$originalSig = $payload['sig'];
unset($payload['sig']);
$expectedSig = hash_hmac('sha256', json_encode($payload), $secret);

if (!hash_equals($expectedSig, $originalSig)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

// Step 5: Build file path (flat files directory)
$baseDir = '/var/www/html/files';
$fullPath = realpath($baseDir . '/' . $payload['file']);

// Step 6: Validate resolved path

// list.php  (inside Step 6)
error_log("Requested file: " . $payload['file']);
error_log("Resolved path: " . $fullPath);
error_log("Exists: " . (file_exists($fullPath) ? "yes" : "no"));
error_log("Path check: " . (strpos($fullPath, realpath($baseDir)) === 0 ? "pass" : "fail"));



if (!$fullPath || strpos($fullPath, realpath($baseDir)) !== 0 || !is_file($fullPath)) {
    // 🔍 Optional: Debug logging (disable in production)
    error_log("Requested file: " . $payload['file']);
    error_log("Resolved path: " . $fullPath);
    error_log("Exists: " . (file_exists($fullPath) ? "yes" : "no"));
    error_log("Path check: " . (strpos($fullPath, realpath($baseDir)) === 0 ? "pass" : "fail"));

    http_response_code(404);
    echo json_encode(['error' => 'File not found']);
    exit;
}

// Step 7: Serve file
header('Content-Type: application/octet-stream');
header('Content-Disposition: inline; filename="' . basename($fullPath) . '"');
header('X-Accel-Redirect: /protected/' . $payload['file']);
exit;
