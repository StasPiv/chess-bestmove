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

        fwrite($this->pipes[0], 'uci'.PHP_EOL);
        $this->waitFor('uciok', $this->pipes[1]);
        fwrite($this->pipes[0], 'ucinewgame'.PHP_EOL);
        fwrite($this->pipes[0], 'isready'.PHP_EOL);
        $this->waitFor('readyok', $this->pipes[1]);

        foreach ($this->engineConfiguration->getOptions() as $name => $value) {
            fwrite($this->pipes[0], 'setoption name '.$name.' value '.$value.PHP_EOL);
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
    public function getBestMoveFromFen(string $fen = self::START_POSITION, $wtime = 3000, $btime = 3000): Move
    {
        fwrite($this->pipes[0], 'position fen '.$fen.PHP_EOL);

        fwrite(
            $this->pipes[0],
            'go wtime '.$wtime.' btime '.$btime.PHP_EOL
        );

        return $this->searchBestMove($this->pipes[1]);
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

    private function shutDown()
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
            $this->logger->debug($content);
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

        return SerializerBuilder::create()->build()->deserialize(json_encode($matches), Move::class, 'json');
    }

    public function __destruct()
    {
        $this->shutDown();
    }
}