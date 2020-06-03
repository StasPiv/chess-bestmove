#!/usr/bin/env php
<?php

require_once 'vendor/autoload.php';

use StasPiv\ChessBestMove\Service\EngineLogReader;
use WebSocket\Client;

$reader = new EngineLogReader(
        'engine.log',
        $wsClient = new Client(
    isset($argv[1]) ? $argv[1] : 'ws://websockets:8000',
        [
            'timeout' => 60 * 60 * 24
        ]
    )
);

$reader->reading();