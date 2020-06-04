<?php
/**
 * Created by PhpStorm.
 * User: stas
 * Date: 28.08.16
 * Time: 19:24
 */

namespace StasPiv\ChessBestMove\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use StasPiv\ChessBestMove\Exception\GameOverException;
use StasPiv\ChessBestMove\Model\EngineConfiguration;
use StasPiv\ChessBestMove\Model\Move;
use StasPiv\ChessBestMove\Model\PromotionType;
use StasPiv\ChessBestMove\Service\ChessBestMove;
use StasPiv\ChessBestMove\Tests\Mock\MockLogger;

class ChessBestMoveServiceTest extends TestCase
{
    /**
     * @var EngineConfiguration
     */
    private $testEngineConfiguration;

    /**
     * @var ChessBestMove
     */
    private $chessBestMoveService;

    protected function setUp()
    {
        parent::setUp();
        $this->testEngineConfiguration = new EngineConfiguration('stockfish');

        $this->testEngineConfiguration->addOption('Skill Level', 20)
                                      ->addOption('Hash', 1024)
                                      ->addOption('Threads', 4);

        $this->testEngineConfiguration->setPathToPolyglotRunDir('/home/user/chess-train');

        $this->chessBestMoveService = new ChessBestMove($this->testEngineConfiguration);

    }

    public function testGetBestMoveFromBeginningPosition()
    {
        $bestMove = $this->chessBestMoveService->getBestMoveFromFen(
            'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1'
        );

        $this->assertInstanceOf(Move::class, $bestMove);
    }

    public function testGetBestMoveAfterD2D4()
    {
        $bestMove = $this->chessBestMoveService->getBestMoveFromFen(
            'rnbqkbnr/pppppppp/8/8/3P4/8/PPP1PPPP/RNBQKBNR b KQkq - 0 1'
        );

        $this->assertInstanceOf(Move::class, $bestMove);
    }

    public function testGetBestMoveFromKevinPosition()
    {
        $bestMove = $this->chessBestMoveService->getBestMoveFromFen('5k2/2rr1p2/7R/5K2/5P2/8/8/6R1 w - - 0 66');

        $this->assertInstanceOf(Move::class, $bestMove);
        $this->assertEquals((new Move())->setFrom('h6')->setTo('h8'), $bestMove);
    }

    public function testGetBestMoveKnightPromotion()
    {
        $bestMove = $this->chessBestMoveService->getBestMoveFromFen('4rbb1/4pk1P/4p3/4B2K/8/8/8/8 w - - 0 1');

        $this->assertInstanceOf(Move::class, $bestMove);
        $this->assertEquals((new Move())->setFrom('h7')->setTo('h8')->setPromotion(PromotionType::KNIGHT), $bestMove);
    }

    public function testParseBestMoveOneLine()
    {
        $handler = fopen('/tmp/test.txt', 'w+');
        fwrite($handler, 'bestmove f4h6');

        $move = $this->testProtectedMethod('searchBestMove', [fopen('/tmp/test.txt', 'r')]);

        $this->assertInstanceOf(Move::class, $move);
        $this->assertEquals((new Move())->setFrom('f4')->setTo('h6'), $move);
    }

    public function testParseBestMoveTwoLines()
    {
        $handler = fopen('/tmp/test.txt', 'w+');
        fwrite($handler, 'bestmove'.PHP_EOL);
        fwrite($handler, 'f4h6');

        $move = $this->testProtectedMethod('searchBestMove', [fopen('/tmp/test.txt', 'r')]);

        $this->assertInstanceOf(Move::class, $move);
        $this->assertEquals((new Move())->setFrom('f4')->setTo('h6'), $move);
    }

    public function testParseBestMoveNone()
    {
        $this->setExpectedException(GameOverException::class);

        $this->testProtectedMethod('parseBestMove', ['bestmove (none)']);
    }

    /**
     * @param string $method
     * @param array  $args
     *
     * @return mixed
     * @throws \ReflectionException
     */
    private function testProtectedMethod(string $method, array $args)
    {
        $class = new ReflectionClass(ChessBestMove::class);
        $method = $class->getMethod($method);
        $method->setAccessible(true);
        return $method->invokeArgs($this->chessBestMoveService, $args);
    }
}