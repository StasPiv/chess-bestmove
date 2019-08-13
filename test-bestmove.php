#!/usr/bin/env php
<?php

use StasPiv\ChessBestMove\Model\EngineConfiguration;
use StasPiv\ChessBestMove\Service\ChessBestMove;

require_once 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$testEngineConfiguration = new EngineConfiguration('stockfish');
$testEngineConfiguration->addOption('Skill Level', 20)
    ->addOption('Hash', 1024)
    ->addOption('Threads', 8);
$testEngineConfiguration->setPathToPolyglotRunDir(__DIR__);
$chessBestMoveService = new ChessBestMove($testEngineConfiguration, new \StasPiv\ChessBestMove\Tests\Mock\MockLogger());

$bestMove = $chessBestMoveService->getBestMoveFromFen(
    '2k5/Q2r4/2pn4/P2p1P2/1P1K4/8/8/8 w - - 0 1'
);

echo $bestMove . PHP_EOL;
echo $chessBestMoveService->getCurrentScore(). PHP_EOL;