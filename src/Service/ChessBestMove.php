<?php
/**
 * Created by PhpStorm.
 * User: stas
 * Date: 28.08.16
 * Time: 19:23
 */

namespace StasPiv\ChessBestMove\Service;

use JMS\Serializer\SerializerBuilder;
use Psr\Log\LoggerInterface;
use Spatie\Async\Pool;
use Spatie\Async\Process\ParallelProcess;
use StasPiv\ChessBestMove\Exception\BotIsFailedException;
use StasPiv\ChessBestMove\Exception\GameOverException;
use StasPiv\ChessBestMove\Exception\NotValidBestMoveHaystackException;
use StasPiv\ChessBestMove\Exception\ResourceUnavailableException;
use StasPiv\ChessBestMove\Model\EngineConfiguration;
use StasPiv\ChessBestMove\Model\Move;
use Throwable;
use TypeError;

class ChessBestMove
{
    const START_POSITION = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';

    private $resource;

    /** @var array */
    private $pipes;

    /**
     * @var EngineConfiguration
     */
    private $engineConfiguration;

    /**
     * @var LoggerInterface
     */
    private $logger;

    private $currentScore = -9999;

    private $process;
    private $pool;

    /**
     * ChessBestMove constructor.
     * @param EngineConfiguration $engineConfiguration
     * @param LoggerInterface     $logger
     */
    public function __construct(EngineConfiguration $engineConfiguration, LoggerInterface $logger = null)
    {
        $this->engineConfiguration = $engineConfiguration;
        $this->logger = $logger;
        $this->pool = Pool::create();
    }

    /**
     * @return bool
     * @throws ResourceUnavailableException
     */
    public function startGame()
    {
        $this->setErrorHandlers();

        $this->resource = proc_open(
            '/usr/games/polyglot -ec /usr/games/' . $this->engineConfiguration->getEngine(),
            [
                0 => ["pipe", "r"],
                1 => ["pipe", "w"],
                2 => ["file", "/tmp/uci_err", "w+"]
            ],
            $this->pipes,
            $this->engineConfiguration->getPathToPolyglotRunDir(),
            []
        );

        if (!is_resource($this->resource)) {
            throw new ResourceUnavailableException;
        }

        $this->sendCommand('uci');
        $this->waitFor('uciok', $this->pipes[1]);
        $this->sendCommand('ucinewgame');
        $this->sendCommand('isready');
        $this->waitFor('readyok', $this->pipes[1]);

        foreach ($this->engineConfiguration->getOptions() as $name => $value) {
            $this->sendCommand('setoption name '.$name.' value '.$value);
        }

        return true;
    }

    /**
     * @param string        $fen startpos by default
     * @param int           $moveTime
     * @param callable      $callback
     *
     * @return Move
     */
    public function getBestMoveFromFen(string $fen = self::START_POSITION, int $moveTime = 3000, callable $callback = null): Move
    {
        $this->sendCommand('position fen '.$fen);

        $this->sendGo($moveTime);

        return $this->searchBestMove($this->pipes[1], $callback);
    }

    private function setErrorHandlers()
    {
        set_error_handler(
            function () {
                $this->shutDown();
            },
            E_ALL
        );

        set_exception_handler(
            function (Throwable $exception) {
                $this->shutDown();
                throw $exception;
            }
        );
    }

    /**
     * @param string        $fen startpos by default
     * @param callable|null $callable
     *
     * @return void
     */
    public function runInfiniteAnalyze(string $fen = self::START_POSITION, callable $callable = null): void
    {
        $this->stopRunningProcess();

        $this->process = $this->pool->add(
            function () use ($callable, $fen) {
                $this->sendCommand('position fen ' . $fen);
                $this->sendCommand('go infinite');
                do {
                    $content = fgets($this->pipes[1]);

                    if ($callable) {
                        call_user_func($callable, $content);
                    }

                    if (empty($content)) {
                        throw new BotIsFailedException;
                    }
                } while (true);
            }
        )->catch(
            function (Throwable $throwable) {
                $this->shutDown();
            }
        );
    }

    private function stopRunningProcess(): void
    {
        try {
            if ($this->process instanceof ParallelProcess) {
                $this->process->stop();
                $this->pool->markAsFinished($this->process);
            }
        } catch (TypeError $exception) {
            $this->stopRunningProcess();
        }
    }

    /**
     * @param array|Move[] $moves
     * @param int $moveTime
     * @param string $startPosition
     * @return Move
     */
    public function getBestMoveFromMovesArray(
        array $moves, int $moveTime = 3000, string $startPosition = self::START_POSITION
    ): Move
    {
        $moveString = implode(' ', array_map(
            function (Move $move)
            {
                return $move;
            },
            $moves
        ));

        $this->sendCommand('position fen '.$startPosition.' moves '.$moveString);

        $this->sendGo($moveTime);

        return $this->searchBestMove($this->pipes[1]);
    }

    /**
     * @param array  $movesArray
     * @param int    $wtime
     * @param int    $btime
     * @param string $startPosition
     * @return Move
     */
    public function getBestMoveFrom2DimensionalMovesArray(
        array $movesArray,
        int $wtime = 3000,
        int $btime = 3000,
        string $startPosition = self::START_POSITION
    ): Move
    {
        return $this->getBestMoveFromMovesArray(
            array_map(
                function (array $moveArray) {
                    return $this->buildMoveFromArray($moveArray);
                },
                $movesArray
            ), $wtime, $startPosition
        );
    }

    /**
     * @param resource      $handle
     * @param callable|null $callback
     *
     * @return Move
     */
    public function searchBestMove($handle = null, callable $callback = null)
    {
        if (!$handle) {
            $handle = $this->pipes[1];
        }

        try {
            if (!$callback) {
                $callback = [$this, 'searchScore'];
            }
            return $this->parseBestMove($content = $this->waitFor('bestmove', $handle, $callback));
        } catch (NotValidBestMoveHaystackException $e) {
            /** @var string $content */
            return $this->parseBestMove($content.' '.fgets($handle));
        }
    }

    /**
     * @return int
     */
    public function getCurrentScore(): int
    {
        return $this->currentScore;
    }

    private function searchScore(string $content)
    {
        if (preg_match('/score (.+) nodes/', $content, $matches)) {
            if (preg_match('/mate (\-?\d+)/', $matches[1], $matchesEstimation)) {
                $this->currentScore = $matchesEstimation[1] > 0 ? 99999 : -99999;
            } elseif (preg_match('/cp (\-?\d+)/', $matches[1], $matchesEstimation)) {
                $this->currentScore = $matchesEstimation[1];
            }
        }
    }

    public function shutDown()
    {
        for ($i=0; $i<=2; $i++) {
            if (isset($this->pipes[$i])) {
                @fclose($this->pipes[$i]);
            }
        }

        if (is_resource($this->resource)) {
            @proc_close($this->resource);
        }

        $this->stopRunningProcess();
    }

    /**
     * @param $needle
     * @param $handle
     * @param callable|null $callable
     * @return string
     */
    private function waitFor($needle, $handle, callable $callable = null): string
    {
        do {
            $content = fgets($handle);

            if ($callable) {
                call_user_func($callable, $content);
            }

            if (empty($content)) {
                throw new BotIsFailedException;
            }
        } while (strpos($content, $needle) === false);

        return $content;
    }

    /**
     * @param string $content
     * @return Move
     * @throws NotValidBestMoveHaystackException
     */
    public function parseBestMove(string $content)
    {
        if (!preg_match(
            "/bestmove\s*(?P<from>[a-h]\d)(?P<to>[a-h]\d)(?P<promotion>\w)?/i",
            $content,
            $matches
        )) {
            if (strpos($content, '(none)')) {
                throw new GameOverException();
            }
            
            throw new NotValidBestMoveHaystackException;
        }

        $moveArray = $matches;

        return $this->buildMoveFromArray($moveArray);
    }

    public function __destruct()
    {
        $this->shutDown();
    }

    /**
     * @param int $moveTime
     */
    private function sendGo(int $moveTime)
    {
        if ($moveTime === 0) {
            $this->sendCommand('go infinite');

            return;
        }

        $this->sendCommand('go movetime '.$moveTime);
    }

    /**
     * @param string $command
     * @return int
     */
    public function sendCommand(string $command)
    {
        if (!is_resource($this->resource)) {
            $this->startGame();
        }

        return fwrite($this->pipes[0], $command.PHP_EOL);
    }

    /**
     * @param array $moveArray
     * @return Move
     */
    private function buildMoveFromArray(array $moveArray): Move
    {
        return SerializerBuilder::create()->build()->deserialize(json_encode($moveArray), Move::class, 'json');
    }

    private function restartProcess()
    {
        $this->shutDown();
        $this->startGame();
    }
}
