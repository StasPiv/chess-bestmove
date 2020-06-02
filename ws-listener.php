#!/usr/bin/env php7.4
<?php

require_once 'vendor/autoload.php';

use Psr\Log\NullLogger;
use StasPiv\ChessBestMove\Model\EngineConfiguration;
use StasPiv\ChessBestMove\Service\ChessBestMove;
use WebSocket\Client;
use WebSocket\ConnectionException;

$wsUrl = isset($argv[1]) ? $argv[1] : 'ws://localhost:8000';
$wsClient = new Client(
    $wsUrl,
    [
        'timeout' => 60 * 60 * 24
    ]
);

$bestMove = new ChessBestMove(
    (new EngineConfiguration('stockfish'))
        ->addOption('Skill Level', 20)
        ->addOption('Hash', 1024)
        ->addOption('Threads', 8)
        ->addOption('Clear Hash', 1)
        ->addOption('Contempt', 0)
        ->addOption('multipv', 2)
        ->setPathToPolyglotRunDir(__DIR__), new NullLogger()
);

error_reporting(E_ERROR);
while (true) {
    try {
        $message = $wsClient->receive();
    } catch (ConnectionException $exception) {
        continue;
    }

    if (empty($message)) {
        continue;
    }

    echo $message . PHP_EOL;

    try {
        if (preg_match('/^start-infinite (.+)$/', $message, $matches)) {
            $bestMove->runInfiniteAnalyze($matches[1], $wsUrl);
        }

        if ($message == 'stop-infinite') {
            $bestMove->stopInfiniteAnalyze();
        }
    } catch (Throwable $exception) {
        echo $exception->getMessage() . PHP_EOL;
        continue;
    }
}