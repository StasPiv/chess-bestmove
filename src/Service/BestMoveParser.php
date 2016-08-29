<?php
/**
 * Created by PhpStorm.
 * User: stas
 * Date: 30.08.16
 * Time: 1:10
 */

namespace StasPiv\ChessBestMove\Service;

use JMS\Serializer\SerializerBuilder;
use Psr\Log\LoggerInterface;
use StasPiv\ChessBestMove\Model\Move;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;

class BestMoveParser
{
    /**
     * @var \Resource
     */
    private $handle;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * BestMoveParser constructor.
     * @param Resource $handler
     * @param LoggerInterface $logger
     */
    public function __construct($handler, LoggerInterface $logger)
    {
        $this->handle = $handler;
        $this->logger = $logger;
    }

    /**
     * @return Move
     */
    public function parseBestMove()
    {
        $prefix = '';

        while (true) {
            $content = $prefix . fread($this->handle, 8192);

            if ($this->logger) {
                $this->logger->debug('Thinking...'.$content);
            }

            if (strpos($content, 'bestmove') === false) {
                continue;
            }

            preg_match(
                "/bestmove\s*(?P<from>[a-h]\d)(?P<to>[a-h]\d)(?P<promotion>\w)?/i",
                $content,
                $matches
            );

            if (isset($matches["from"])) {
                return SerializerBuilder::create()->build()->deserialize(json_encode($matches), Move::class, 'json');
            }

            $prefix = trim($content) . ' ';
        }

        return new Move();
    }
}