<?php
$domain = $_SERVER['HTTP_HOST'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Molo Node – <?= $domain ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    html, body {
      margin: 0;
      padding: 0;
      font-family: "Segoe UI", sans-serif;
      background: #f8f9fa;
      color: #333;
    }
    .container {
      max-width: 700px;
      margin: 5% auto;
      padding: 2rem;
      background: #fff;
      box-shadow: 0 0 15px rgba(0,0,0,0.05);
      border-radius: 10px;
      text-align: center;
    }
    h1 {
      font-size: 2em;
      color: #202124;
    }
    p {
      font-size: 1.1em;
      margin: 1em 0;
    }
    ul {
      text-align: left;
      margin-top: 1.5rem;
    }
    ul li {
      margin-bottom: 0.5rem;
      line-height: 1.6;
    }
    a {
      color: #1a73e8;
      text-decoration: none;
    }
    a:hover {
      text-decoration: underline;
    }
    .footer {
      margin-top: 2rem;
      font-size: 0.9em;
      color: #999;
    }
    .loader {
      color: #aaa;
      font-style: italic;
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>Welcome to a UseMolo.com Node</h1>

    <p>This is a <a href="https://usemolo.com" target="_blank">Molo</a> transmission node.</p>
    <p>It executes user-submitted torrents as part of the Molo distributed torrent network.</p>

    <p><strong>What is Molo?</strong><br>
    UseMolo is a decentralized torrent execution network. Users submit torrents on UseMolo.com, and they are seeded, downloaded, or analyzed across a global network of participating nodes like this one.</p>

    <h2>Node Access</h2>
    <ul>
      <li><strong>RPC Endpoint:</strong> <a href="https://<?= $domain ?>/transmission/rpc/">/transmission/rpc</a></li>
      <li><strong>Web Interface:</strong> <a href="https://<?= $domain ?>/transmission/web/">/transmission/web</a></li>
    </ul>

    <h2>Node Info (auto-refreshes every 10s)</h2>
    <div id="node-info" class="loader">Loading node stats...</div>

    <div class="footer">
      &copy; <?= date('Y') ?> <?= $domain ?> &middot; Powered by <a href="https://usemolo.com">Molo Web3 Torrent</a>
    </div>
  </div>

  <script>
  async function loadStats() {
    try {
      const res = await fetch('node-stats.php');
      const data = await res.json();

      document.getElementById('node-info').innerHTML = `
        <ul>
          <li>Active torrents: ${data.activeTorrents ?? 'N/A'}</li>
          <li>Total torrents: ${data.torrentCount ?? 'N/A'}</li>
          <li>Download speed: ${data.downloadSpeed ?? 'N/A'} KB/s</li>
          <li>Free disk space: ${data.diskFreeGB ?? 'N/A'} GB</li>
          <li>Last node check: ${data.timestamp}</li>
        </ul>
      `;
    } catch (e) {
      document.getElementById('node-info').innerHTML = '⚠️ Error loading stats.';
    }
  }

  loadStats();
  setInterval(loadStats, 10000);
  </script>
</body>
</html>
