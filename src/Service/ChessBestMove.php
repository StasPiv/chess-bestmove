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
use StasPiv\ChessBestMove\Model\EngineConfiguration;
use StasPiv\ChessBestMove\Model\Move;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;

class ChessBestMove
{
    private $resource;

    private $pipes;

    /**
     * @var EngineConfiguration
     */
    private $engineConfiguration;

    /**
     * @var BestMoveParser
     */
    private $parser;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * ChessBestMove constructor.
     * @param EngineConfiguration $engineConfiguration
     * @param LoggerInterface $logger
     */
    public function __construct(EngineConfiguration $engineConfiguration, LoggerInterface $logger = null)
    {
        $this->engineConfiguration = $engineConfiguration;
        $this->logger = $logger;
    }

    /**
     * @param string $engine
     * @return bool
     * @throws \Exception
     */
    private function startGame(string $engine)
    {
        $this->resource = proc_open(
            '/usr/games/' . $engine,
            [
                0 => ["pipe", "r"],
                1 => ["pipe", "w"],
                2 => ["file", "/tmp/uci_err", "w+"]
            ],
            $this->pipes,
            '/tmp',
            []
        );

        $this->parser = new BestMoveParser($this->pipes[1], $this->logger);

        if (!is_resource($this->resource)) {
            $this->shutDown();

            throw new \Exception("Resource unavailable !");
        } else {
            return true;
        }
    }

    /**
     * @param string $fen
     * @return Move
     */
    public function getBestMoveFromFen(string $fen): Move
    {
        $this->startGame($this->engineConfiguration->getEngine());

        fwrite($this->pipes[0], 'uci'.PHP_EOL);
        fwrite($this->pipes[0], 'ucinewgame'.PHP_EOL);
        fwrite($this->pipes[0], 'isready'.PHP_EOL);

        if (empty($fen)) {
            fwrite($this->pipes[0], "position startpos\n");
        } else {
            fwrite($this->pipes[0], "position fen $fen\n");
        }

        foreach ($this->engineConfiguration->getOptions() as $name => $value) {
            fwrite($this->pipes[0], 'setoption name '.$name.' value '.$value.PHP_EOL);
        }

        fwrite(
            $this->pipes[0],
            'go wtime '.$this->engineConfiguration->getWtime().' btime '.$this->engineConfiguration->getBtime().PHP_EOL
        );

        return $this->parser->parseBestMove();
    }

    public function __destruct()
    {
        $this->shutDown();
    }

    protected function shutDown()
    {
        @fclose($this->pipes[0]);
        @fclose($this->pipes[1]);
        @fclose($this->pipes[2]);
        @fclose($this->resource);
    }
}