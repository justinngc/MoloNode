<?php
header('Content-Type: application/json');

// --- Config ---
$rpcUrl = 'https://'.getenv('NODE_URL') . "/transmission/rpc";
$rpcUser = 'node';
$rpcPass = getenv('SECRET');

function makeRpcCall($rpcUrl, $rpcUser, $rpcPass, $payload, $sessionId = null) {
    $headers = ["Content-Type: application/json"];
    if ($sessionId) {
        $headers[] = "X-Transmission-Session-Id: $sessionId";
    }

    $ch = curl_init($rpcUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_USERPWD        => "$rpcUser:$rpcPass",
        CURLOPT_HEADER         => true
    ]);

    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);

    // Check for 409 session ID
    if (preg_match('/X-Transmission-Session-Id:\s*(.+)/i', $header, $match)) {
        $newSessionId = trim($match[1]);
        curl_close($ch);
        // Retry with new session ID
        return makeRpcCall($rpcUrl, $rpcUser, $rpcPass, $payload, $newSessionId);
    }

    curl_close($ch);
    return $body;
}

// --- Step 1: session-stats ---
$sessionStatsPayload = json_encode(["method" => "session-stats"]);
$response = makeRpcCall($rpcUrl, $rpcUser, $rpcPass, $sessionStatsPayload);
$data = json_decode($response, true);
$stats = $data['arguments'] ?? [];

// --- Step 2: torrent-get with rateDownload + status ---
$torrentPayload = json_encode([
    "method" => "torrent-get",
    "arguments" => ["fields" => ["rateDownload", "status"]]
]);
$response2 = makeRpcCall($rpcUrl, $rpcUser, $rpcPass, $torrentPayload);
$torrentData = json_decode($response2, true);
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
            $send = $test['send'] ?? null;
            if ($send && preg_match('/([\d.]+)\s*([MG])bits\/sec/i', $send, $m)) {
                $value = floatval($m[1]);
                $unit = strtoupper($m[2]);
                if ($unit === 'G') {
                    $value *= 1000;
                }
                $yabsUpload = max($yabsUpload, $value);
            }

            $recv = $test['recv'] ?? null;
            if ($recv && preg_match('/([\d.]+)\s*([MG])bits\/sec/i', $recv, $m)) {
                $value = floatval($m[1]);
                $unit = strtoupper($m[2]);
                if ($unit === 'G') {
                    $value *= 1000;
                }
                $yabsDownload = max($yabsDownload, $value);
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

// --- CPU usage ---
$topOutput = shell_exec("top -bn1 | grep 'Cpu(s)'");
if ($topOutput && preg_match('/(\d+\.\d+)\s*id/', $topOutput, $matches)) {
    $cpuUsage = round(100 - floatval($matches[1]), 2);
}

// --- Memory usage ---
$memOutput = shell_exec("free -m");
if ($memOutput && preg_match('/Mem:\s+(\d+)\s+(\d+)\s+/', $memOutput, $matches)) {
    $total = floatval($matches[1]);
    $used = floatval($matches[2]);
    if ($total > 0) {
        $memoryUsage = round(($used / $total) * 100, 2);
    }
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
