<?php
header('Content-Type: application/json');

// --- Config ---
$rpcUrl = "http://localhost:9091/transmission/rpc";
$rpcUser = getenv('RPC_USERNAME') ?: 'node';
$rpcPass = getenv('SECRET');

$headers = ["Content-Type: application/json"];
$sessionId = null;

// --- Function to make an RPC call with retry ---
function makeRpcCall($rpcUrl, $headers, $rpcUser, $rpcPass, $payload, &$sessionIdOut) {
    $ch = curl_init($rpcUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_HEADER         => true,
        CURLOPT_USERPWD        => "$rpcUser:$rpcPass"
    ]);

    $response = curl_exec($ch);

    // Handle session ID if required
    if (preg_match('/X-Transmission-Session-Id: (.+)/', $response, $match)) {
        $sessionIdOut = trim($match[1]);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, [
            "X-Transmission-Session-Id: $sessionIdOut"
        ]));
        $response = curl_exec($ch);
    }

    curl_close($ch);
    return $response;
}

// --- Step 1: session-stats ---
$sessionStatsPayload = json_encode(["method" => "session-stats"]);
$response = makeRpcCall($rpcUrl, $headers, $rpcUser, $rpcPass, $sessionStatsPayload, $sessionId);
$body = substr($response, strpos($response, "\r\n\r\n") + 4);
$data = json_decode($body, true);
$stats = $data['arguments'] ?? [];

// --- Step 2: torrent-get with rateDownload + status ---
$torrentPayload = json_encode([
    "method" => "torrent-get",
    "arguments" => ["fields" => ["rateDownload", "status"]]
]);
$response2 = makeRpcCall($rpcUrl, $headers, $rpcUser, $rpcPass, $torrentPayload, $sessionId);
$body2 = substr($response2, strpos($response2, "\r\n\r\n") + 4);
$torrentData = json_decode($body2, true);
$torrents = $torrentData['arguments']['torrents'] ?? [];

// --- Filter active torrents with download rate > 50KB/s ---
$activeTorrentsList = array_filter($torrents, function ($t) {
    return isset($t['rateDownload']) && (int)$t['rateDownload'] > 51200;
});
$activeTorrentsOver50KB = count($activeTorrentsList);

// --- Load bandwidth.json (YABS results) ---
$bandwidthData = [];
$bandwidthFile = __DIR__ . '/bandwidth.json';
$yabsUpload = 0;
$yabsDownload = 0;

if (file_exists($bandwidthFile)) {
    $bandwidthRaw = file_get_contents($bandwidthFile);
    $parsed = json_decode($bandwidthRaw, true);

    if (isset($parsed['iperf']) && is_array($parsed['iperf'])) {
        foreach ($parsed['iperf'] as $test) {
            if (isset($test['send']) && preg_match('/([\d.]+)\s*Mbits\/sec/i', $test['send'], $m)) {
                $yabsUpload = max($yabsUpload, floatval($m[1]));
            }
            if (isset($test['recv']) && preg_match('/([\d.]+)\s*Mbits\/sec/i', $test['recv'], $m)) {
                $yabsDownload = max($yabsDownload, floatval($m[1]));
            }
        }

        $bandwidthData = [
            'yabs_upload'       => $yabsUpload,
            'yabs_download'     => $yabsDownload,
            'upload_max_mbps'   => null,
            'download_max_mbps' => null,
            'measured_at'       => $parsed['time'] ?? null,
        ];
    }
}

// --- Live bandwidth from session stats (in Mbps) ---
$downloadSpeedMbps = isset($stats['downloadSpeed']) ? round($stats['downloadSpeed'] / 125, 2) : 0;
$uploadSpeedMbps   = isset($stats['uploadSpeed'])   ? round($stats['uploadSpeed'] / 125, 2) : 0;

$bandwidthData['upload_max_mbps']   = round($yabsUpload + ($uploadSpeedMbps / 1024), 2);
$bandwidthData['download_max_mbps'] = round($yabsDownload + ($downloadSpeedMbps / 1024), 2);

// --- CPU and memory usage ---
$cpuUsage = null;
$memoryUsage = null;

$topOutput = shell_exec("top -bn1 | grep 'Cpu(s)'");
if (preg_match('/(\d+\.\d+)\s*id/', $topOutput, $matches)) {
    $cpuUsage = round(100 - floatval($matches[1]), 2);
}

$memOutput = shell_exec("free -m");
if (preg_match('/Mem:\s+(\d+)\s+(\d+)/', $memOutput, $matches)) {
    $total = floatval($matches[1]);
    $used = floatval($matches[2]);
    $memoryUsage = $total > 0 ? round(($used / $total) * 100, 2) : null;
}

// --- Final JSON response ---
echo json_encode(array_merge([
    'activeTorrents' => $activeTorrentsOver50KB,
    'torrentCount'   => $stats['torrentCount'] ?? null,
    'downloadSpeed'  => isset($stats['downloadSpeed']) ? round($stats['downloadSpeed'] / 1024, 2) : null,
    'uploadSpeed'    => isset($stats['uploadSpeed']) ? round($stats['uploadSpeed'] / 1024, 2) : null,
    'diskFreeGB'     => round((disk_free_space("/") ?? 0) / (1024 * 1024 * 1024), 2),
    'version'        => "qg9wNhW2q8Uxs4WzZF",
    'timestamp'      => date("c"),
    'cpuPercent'     => $cpuUsage,
    'memoryPercent'  => $memoryUsage,
], $bandwidthData));
