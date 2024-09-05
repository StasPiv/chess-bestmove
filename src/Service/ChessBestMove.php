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
use StasPiv\ChessBestMove\Model\Diff;
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

    private $currentScore = -99999;

    private $moveScores = [];

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
        $bestMove->setScore($this->moveScores[1]['score']);

        return $bestMove;
    }

    public function getDiff(string $fen, string $move, int $moveTime = 3000): Diff
    {
        $this->moveScores = [];
        if (!is_resource($this->resource)) {
            $this->startGame();
        }

        $this->sendCommand('position fen ' . $fen);

        $bestMove = $this->getBestMoveFromFen($fen, $moveTime);
        $this->moveScores = [];
        $this->sendGo($moveTime,  $bestMove . ' ' . $move);
        $this->searchBestMove($this->pipes[1]);

        $gameScore = $bestScore = -999999;

        foreach ($this->moveScores as $moveScore) {
            if ($moveScore['move'] == $move) {
                $gameScore = $moveScore['score'];
            }
            if ($moveScore['score'] > $bestScore) {
                $bestScore = $moveScore['score'];
            }
        }

        return new Diff($fen, $move, $bestMove, $bestScore, $gameScore);
    }

    private function closeEngine(): void
    {
        foreach ($this->pipes as $pipe) {
            fclose($pipe);
        }

        proc_close($this->resource);
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
                $this->closeEngine();
                throw new BotIsFailedException;
            }
        } while (strpos($content, $needle) === false);

        return $content;
    }

    /**
     * @param int $moveTime
     * @param string|null $moves
     */
    private function sendGo(int $moveTime, ?string $moves = null)
    {
        if ($moveTime === 0) {
            $this->sendCommand('go infinite');

            return;
        }

        $command = 'go movetime ' . $moveTime;

        if ($moves) {
            $command .= ' searchmoves ' . $moves;
        }

        $this->sendCommand($command);
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
        if (preg_match('/multipv (\d+) score (.+) nodes/', $content, $matches)) {
            $multipv = $matches[1];

            if (preg_match('/mate (\-?\d+)/', $matches[2], $matchesEstimation)) {
                $currentScore = $matchesEstimation[1] > 0 ? 99999 : -99999;
            } elseif (preg_match('/cp (\-?\d+)/', $matches[2], $matchesEstimation)) {
                $currentScore = $matchesEstimation[1];
            } else {
                return;
            }

            if (preg_match('/ pv (\w+)/', $content, $matches)) {
                $currentMove = $matches[1];
                $this->moveScores[$multipv] = ['move' => $currentMove, 'score' => $currentScore];
            }
        }
    }
}
