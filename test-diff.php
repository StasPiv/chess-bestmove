#!/usr/bin/env php
<?php

use StasPiv\ChessBestMove\Model\EngineConfiguration;
use StasPiv\ChessBestMove\Service\ChessBestMove;

require_once 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$testEngineConfiguration = new EngineConfiguration('stockfish');
$testEngineConfiguration
    ->addOption('MultiPV', '2')
    ->addOption('Threads', '1');
$testEngineConfiguration->setPathToPolyglotRunDir(__DIR__);
$chessBestMoveService = new ChessBestMove($testEngineConfiguration);

$fen = '4r1k1/5ppp/p2br1q1/1p1p4/3P4/2P1BPPb/PP1Q3P/R3RNK1 b - - 6 23';
$move = 'h3f1';

global $start;
$start = microtime(true);
for ($i=0;$i<10;$i++) {
    $diff = $chessBestMoveService->getDiff($fen, $move, 100);
}

//var_dump($diff);
printTime('end');

function printTime(string $lapName) {
    global $start;

    $end = microtime(true);
    $time = $end - $start;
    echo 'Lap ' . $lapName . '. Time: ' . $time . 's' . PHP_EOL;
}