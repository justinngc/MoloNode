<?php

header('Content-Type: text/html');

$domain = $_SERVER['HTTP_HOST'];
$rpcUrl = "http://localhost/transmission/rpc";

// Load auth from env
$rpcUser = 'node';
$secret  = getenv('SECRET');

if (!$secret) {
    http_response_code(500);
    echo "Server misconfiguration: SECRET missing.";
    exit;
}

// Prepare session-stats payload
$headers = [ "Content-Type: application/json" ];
$payload = json_encode(["method" => "session-stats"]);

$ch = curl_init($rpcUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_USERPWD, "$rpcUser:$secret");

$response = curl_exec($ch);

// Retry if session ID needed
if (preg_match('/X-Transmission-Session-Id: (.+)/', $response, $match)) {
    $sessionId = trim($match[1]);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, [
        "X-Transmission-Session-Id: $sessionId"
    ]));
    $response = curl_exec($ch);
}

curl_close($ch);

// Parse JSON result
$body = substr($response, strpos($response, "\r\n\r\n") + 4);
$data = json_decode($body, true);
$stats = $data['arguments'] ?? [];

$activeTorrents = $stats['activeTorrentCount'] ?? 'N/A';
$torrentCount = $stats['torrentCount'] ?? 'N/A';
$downloadSpeed = isset($stats['downloadSpeed']) ? round($stats['downloadSpeed'] / 1024, 2) . ' KB/s' : 'N/A';
$freeSpace = disk_free_space("/") ?? 0;
$freeGB = round($freeSpace / (1024 * 1024 * 1024), 2);
$version = "3.00";
$lastCheck = date("Y-m-d H:i:s");

?>

<!DOCTYPE html>
<html>
<head>
    <title>5gb.io Node</title>
</head>
<body>
    <h1>5gb.io Node</h1>
    <p>This is a <a href="https://5gb.io">5gb.io</a> transmission node.</p>
    <p>It executes user submissions from the 5gb.io torrent network.</p>

    <h2>Node URLs:</h2>
    <ul>
        <li><a href="https://<?= $domain ?>/transmission/rpc/">/transmission/rpc/</a></li>
        <li><a href="https://<?= $domain ?>/transmission/web/">/transmission/web/</a></li>
    </ul>

    <h2>Node Info</h2>
    <ul>
        <li>Running version: <?= $version ?> <em>(Latest Version!)</em></li>
        <li>Active torrents: <?= $activeTorrents ?></li>
        <li>Total torrents: <?= $torrentCount ?></li>
        <li>Download speed: <?= $downloadSpeed ?></li>
        <li>Free disk space: <?= $freeGB ?> GB</li>
        <li>Last node check: <?= $lastCheck ?></li>
    </ul>
</body>
</html>
