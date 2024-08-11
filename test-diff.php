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

$fen = 'r1b1kbnr/pp1ppppp/1qn5/8/4P3/1N6/PPP2PPP/RNBQKB1R b KQkq - 2 5';

$diff = $chessBestMoveService->getDiff(
    $fen,
    'g7g6',
    100,
);

var_dump($diff);