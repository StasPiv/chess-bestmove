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
use StasPiv\ChessBestMove\Exception\NotValidBestMoveHaystackException;
use StasPiv\ChessBestMove\Exception\ResourceUnavailableException;
use StasPiv\ChessBestMove\Model\EngineConfiguration;
use StasPiv\ChessBestMove\Model\Move;

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

    /**
     * ChessBestMove constructor.
     * @param EngineConfiguration $engineConfiguration
     * @param LoggerInterface     $logger
     */
    public function __construct(EngineConfiguration $engineConfiguration, LoggerInterface $logger = null)
    {
        $this->engineConfiguration = $engineConfiguration;
        $this->logger = $logger;
        $this->startGame();
    }

    /**
     * @return bool
     * @throws ResourceUnavailableException
     */
    private function startGame()
    {
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

        $this->sendCommand('uci');
        $this->waitFor('uciok', $this->pipes[1]);
        $this->sendCommand('ucinewgame');
        $this->sendCommand('isready');
        $this->waitFor('readyok', $this->pipes[1]);

        foreach ($this->engineConfiguration->getOptions() as $name => $value) {
            $this->sendCommand('setoption name '.$name.' value '.$value);
        }

        if (!is_resource($this->resource)) {
            throw new ResourceUnavailableException;
        }

        return true;
    }

    /**
     * @param string $fen startpos by default
     * @param int $wtime
     * @param int $btime
     * @return Move
     */
    public function getBestMoveFromFen(string $fen = self::START_POSITION, int $wtime = 3000, int $btime = 3000): Move
    {
        $this->sendCommand('position fen '.$fen);

        $this->sendGo($wtime, $btime);

        return $this->searchBestMove($this->pipes[1]);
    }

    /**
     * @param array|Move[] $moves
     * @param int $wtime
     * @param int $btime
     * @param string $startPosition
     * @return Move
     */
    public function getBestMoveFromMovesArray(
        array $moves,
        int $wtime = 3000,
        int $btime = 3000,
        string $startPosition = self::START_POSITION
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

        $this->sendGo($wtime, $btime);

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
                function (array $moveArray)
                {
                    return $this->buildMoveFromArray($moveArray);
                },
                $movesArray
            ),
            $wtime,
            $btime,
            $startPosition
        );
    }

    /**
     * @param resource $handle
     * @return Move
     */
    public function searchBestMove($handle)
    {
        try {
            return $this->parseBestMove($content = $this->waitFor('bestmove', $handle));
        } catch (NotValidBestMoveHaystackException $e) {
            /** @var string $content */
            return $this->parseBestMove($content.' '.fgets($handle));
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
            @fclose($this->resource);
        }
    }

    /**
     * @param $needle
     * @param $handle
     * @return string
     */
    private function waitFor($needle, $handle): string
    {
        do {
            $content = fgets($handle);

            if (empty($content)) {
                $this->restartProcess();
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
    private function parseBestMove(string $content)
    {
        if (!preg_match(
            "/bestmove\s*(?P<from>[a-h]\d)(?P<to>[a-h]\d)(?P<promotion>\w)?/i",
            $content,
            $matches
        )) {
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
     * @param int $wtime
     * @param int $btime
     */
    private function sendGo(int $wtime, int $btime)
    {
        fwrite(
            $this->pipes[0],
            'go wtime '.$wtime.' btime '.$btime.PHP_EOL
        );
    }

    /**
     * @param string $command
     * @return int
     */
    private function sendCommand(string $command)
    {
        $this->logger->debug('SEND: '.$command);
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