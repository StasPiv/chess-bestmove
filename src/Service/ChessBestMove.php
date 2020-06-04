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
use StasPiv\ChessBestMove\Exception\BotIsFailedException;
use StasPiv\ChessBestMove\Exception\GameOverException;
use StasPiv\ChessBestMove\Exception\NotValidBestMoveHaystackException;
use StasPiv\ChessBestMove\Exception\ResourceUnavailableException;
use StasPiv\ChessBestMove\Model\EngineConfiguration;
use StasPiv\ChessBestMove\Model\Move;

class ChessBestMove
{
    private $resource;

    /** @var array */
    private $pipes;

    /**
     * @var EngineConfiguration
     */
    private $engineConfiguration;

    private $currentScore = -9999;

    /**
     * ChessBestMove constructor.
     *
     * @param EngineConfiguration $engineConfiguration
     */
    public function __construct(EngineConfiguration $engineConfiguration)
    {
        $this->engineConfiguration = $engineConfiguration;
    }

    /**
     * @param string $fen startpos by default
     * @param int    $moveTime
     *
     * @return Move
     */
    public function getBestMoveFromFen(string $fen, int $moveTime = 3000): Move
    {
        if (!is_resource($this->resource)) {
            $this->startGame();
        }

        $this->sendCommand('position fen ' . $fen);

        $this->sendGo($moveTime);
        $bestMove = $this->searchBestMove($this->pipes[1]);
        $bestMove->setScore($this->currentScore);

        return $bestMove;
    }

    /**
     * @param string $fen startpos by default
     *
     * @param string $wsUrl
     *
     * @return void
     */
    public function startInfinite(string $fen, string $wsUrl): void
    {
        if (!is_resource($this->resource)) {
            file_put_contents('engine.log', '');
            $this->startGame();
        }

        $this->sendCommand('stop');
        $this->sendCommand('isready');
        $this->waitFor('readyok', $this->pipes[1]);
        $this->sendCommand('position fen ' . $fen);
        $this->sendCommand('go infinite');
    }

    public function stopInfinite()
    {
        $this->sendCommand('stop');
    }

    /**
     * @param string $command
     *
     * @return int
     */
    private function sendCommand(string $command)
    {
        return fwrite($this->pipes[0], $command . PHP_EOL);
    }

    /**
     * @return bool
     * @throws ResourceUnavailableException
     */
    private function startGame()
    {
        $this->resource = proc_open(
            '/usr/games/polyglot -ec /usr/games/' . $this->engineConfiguration->getEngine() . ' ' .
            '-log true -lf "engine.log"',
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
            $this->sendCommand('setoption name ' . $name . ' value ' . $value);
        }

        return true;
    }

    /**
     * @param               $needle
     * @param               $handle
     * @param callable|null $callable
     *
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
     * @param int $moveTime
     */
    private function sendGo(int $moveTime)
    {
        if ($moveTime === 0) {
            $this->sendCommand('go infinite');

            return;
        }

        $this->sendCommand('go movetime ' . $moveTime);
    }

    /**
     * @param resource $handle
     *
     * @return Move
     */
    private function searchBestMove($handle = null)
    {
        if (!$handle) {
            $handle = $this->pipes[1];
        }

        try {
            $callback = [$this, 'searchScore'];
            return $this->parseBestMove($content = $this->waitFor('bestmove', $handle, $callback));
        } catch (NotValidBestMoveHaystackException $e) {
            /** @var string $content */
            return $this->parseBestMove($content . ' ' . fgets($handle));
        }
    }

    /**
     * @param string $content
     *
     * @return Move
     * @throws NotValidBestMoveHaystackException
     */
    private function parseBestMove(string $content)
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

    /**
     * @param array $moveArray
     *
     * @return Move
     */
    private function buildMoveFromArray(array $moveArray): Move
    {
        return SerializerBuilder::create()->build()->deserialize(json_encode($moveArray), Move::class, 'json');
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
}
