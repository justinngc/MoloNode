<?php
header('Content-Type: application/json');

// Debug log (optional)
function log_debug($msg) {
    file_put_contents('/tmp/delete-debug.log', "[" . date('Y-m-d H:i:s') . "] $msg\n", FILE_APPEND);
}

log_debug("=== Incoming delete request ===");

// 1. Validate environment secret
$secret = getenv('SECRET');
if (!$secret) {
    log_debug("SECRET missing");
    http_response_code(500);
    echo json_encode(['error' => 'Server misconfiguration: SECRET missing.']);
    exit;
}

// 2. Get query parameters
$hash = $_GET['hash'] ?? '';
$sig  = $_GET['sig'] ?? '';

if (!$hash || !$sig) {
    log_debug("Missing hash or sig");
    http_response_code(400);
    echo json_encode(['error' => 'Missing hash or signature']);
    exit;
}

// 3. Validate signature
$expectedSig = hash_hmac('sha256', $hash, $secret);
if (!hash_equals($expectedSig, $sig)) {
    log_debug("Invalid signature: expected $expectedSig, got $sig");
    http_response_code(403);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

// 4. Set credentials
$username = 'node';
$password = $secret;
$host = '127.0.0.1:9091'; // Adjust if your RPC port is different
$auth = "$host -n {$username}:{$password}";

// 5. List torrents and find matching info hash
$torrentId = null;
$cmdList = "transmission-remote $auth -l | awk 'NR>1 {print \$1}'";
log_debug("Executing: $cmdList");
exec($cmdList, $ids, $code);

if ($code !== 0 || empty($ids)) {
    log_debug("No torrents found or list command failed.");
    http_response_code(404);
    echo json_encode(['error' => 'No torrents found']);
    exit;
}

foreach ($ids as $id) {
    $id = trim($id);
    if (!is_numeric($id)) continue;

    $cmdInfo = "transmission-remote $auth -t $id -i";
    exec($cmdInfo, $details);

    foreach ($details as $line) {
        if (stripos($line, 'Hash:') !== false) {
            log_debug("Checking ID $id: $line");
            if (str_contains($line, $hash)) {
                $torrentId = $id;
                break 2;
            }
        }
    }
}

if (!$torrentId) {
    log_debug("No matching torrent found with hash $hash");
    http_response_code(404);
    echo json_encode(['error' => 'Torrent not found']);
    exit;
}

// 6. Remove torrent and delete data
$cmdDelete = "transmission-remote $auth -t $torrentId --remove-and-delete";
log_debug("Executing delete: $cmdDelete");
exec($cmdDelete . " 2>&1", $deleteOut, $deleteCode);
log_debug("Delete output: " . implode('; ', $deleteOut));
log_debug("Delete code: $deleteCode");

if ($deleteCode === 0) {
    log_debug("Delete successful for ID $torrentId");
    echo json_encode(['success' => true, 'deleted_id' => $torrentId]);
} else {
    log_debug("Delete failed.");
    http_response_code(500);
    echo json_encode(['error' => 'Failed to delete torrent']);
}
