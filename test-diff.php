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

$diff = $chessBestMoveService->getDiff(
    'r1bq1rk1/5ppp/p1pb4/1p1n4/3P4/1BP5/PP3PPP/RNBQR1K1 b - - 2 13',
    'd8c7',
    100,
);

var_dump($diff);