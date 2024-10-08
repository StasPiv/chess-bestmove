#!/usr/bin/env php
<?php

use StasPiv\ChessBestMove\Model\EngineConfiguration;
use StasPiv\ChessBestMove\Service\ChessBestMove;

require_once 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$testEngineConfiguration = new EngineConfiguration('stockfish');
$testEngineConfiguration
    ->addOption('Hash', '256')
    ->addOption('MultiPV', '100')
    ->addOption('Threads', '1');
$testEngineConfiguration->setPathToPolyglotRunDir(__DIR__);
$chessBestMoveService = new ChessBestMove($testEngineConfiguration);

$bestMove = $chessBestMoveService->getBestMoveFromFen(
    '4r1k1/5ppp/p3r3/1p1p4/3P4/2P1BPq1/PP1Q4/R3RK2 w - - 0 26',
    100,
);

echo $bestMove . PHP_EOL;
echo $bestMove->getScore(). PHP_EOL;