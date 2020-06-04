<?php

use Psr\Log\NullLogger;
use StasPiv\ChessBestMove\Model\EngineConfiguration;
use StasPiv\ChessBestMove\Service\ChessBestMove;
use StasPiv\ChessBestMove\Web\Router;

require_once 'vendor/autoload.php';

$router = new Router(new ChessBestMove(
    (new EngineConfiguration('stockfish'))
        ->addOption('Skill Level', 20)
        ->addOption('Hash', 1024)
        ->addOption('Threads', 8)
        ->addOption('Clear Hash', 1)
        ->addOption('Contempt', 0)
        ->addOption('multipv', 2)
        ->setPathToPolyglotRunDir(__DIR__)
));

header('Content-Type:application\/json');
echo json_encode($router->getResponse($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_REQUEST));