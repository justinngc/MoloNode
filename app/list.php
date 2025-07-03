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

// Step 2.5: Limit to first 3 IPs per token
function getClientIp(): string
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $forwarded = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        return trim($forwarded);
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

try {
    $db = new PDO('sqlite:' . __DIR__ . '/access_control.sqlite', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $db->exec('CREATE TABLE IF NOT EXISTS token_ips (
        token TEXT,
        ip    TEXT,
        PRIMARY KEY (token, ip)
    )');

    $clientIp = getClientIp();
    $tokenKey = hash('sha256', $_GET['token']); // hash to avoid huge raw token storage

    // Check if this IP already recorded for this token
    $stmt = $db->prepare('SELECT 1 FROM token_ips WHERE token = :token AND ip = :ip LIMIT 1');
    $stmt->execute(['token' => $tokenKey, 'ip' => $clientIp]);
    $isKnown = (bool) $stmt->fetchColumn();

    if (!$isKnown) {
        // Count existing unique IPs for this token
        $stmt = $db->prepare('SELECT COUNT(*) FROM token_ips WHERE token = :token');
        $stmt->execute(['token' => $tokenKey]);
        $ipCount = (int) $stmt->fetchColumn();

        if ($ipCount >= 3) {
            http_response_code(403);
            echo json_encode(['error' => 'Limit Reached. Please Generate a new download link.']);
            exit;
        }

        // Insert new IP
        $insert = $db->prepare('INSERT INTO token_ips (token, ip) VALUES (:token, :ip)');
        $insert->execute(['token' => $tokenKey, 'ip' => $clientIp]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
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
if (!$fullPath || strpos($fullPath, realpath($baseDir)) !== 0 || !is_file($fullPath)) {
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
