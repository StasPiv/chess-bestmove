#!/usr/bin/env php7.4
<?php

require_once 'vendor/autoload.php';

use WebSocket\Client;

$wsUrl = isset($argv[1]) ? $argv[1] : 'ws://localhost:8000';
$wsClient = new Client(
    $wsUrl,
    [
        'timeout' => 60 * 60 * 24
    ]
);

$handle = fopen('engine.log', 'r');

while (true) {
    $line = stream_get_line($handle, 1024, PHP_EOL);

    if (false === strpos($line, 'Adapter->GUI:') || !preg_match('/seldepth/', $line)) {
        continue;
    }

    $cleanLine = preg_replace('/(.+: )/', '', $line);
    $wsClient->send('Engine output: ' . $cleanLine);
}