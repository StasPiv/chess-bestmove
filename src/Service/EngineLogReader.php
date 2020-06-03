<?php

namespace StasPiv\ChessBestMove\Service;

use Throwable;
use WebSocket\Client;

class EngineLogReader
{
    private string $fileName;

    private Client $wsClient;

    /**
     * ReadEngineLog constructor.
     *
     * @param string $fileName
     * @param Client $wsClient
     */
    public function __construct(string $fileName, Client $wsClient)
    {
        $this->fileName = $fileName;
        $this->wsClient = $wsClient;
    }

    public function reading()
    {
        $this->waitWhileEngineLogNotEmpty();
        
        $handle = fopen($this->fileName, 'r');

        while (true) {
            $line = stream_get_line($handle, 1024, PHP_EOL);

            if (false === strpos($line, 'Adapter->GUI:') || !preg_match('/seldepth/', $line)) {
                continue;
            }

            $cleanLine = preg_replace('/(.+: )/', '', $line);

            try {
                $this->wsClient->send('Engine output: ' . $cleanLine);
            } catch (Throwable $exception) {

            }
        }
    }

    private function waitWhileEngineLogNotEmpty()
    {
        $oldSize = filesize($this->fileName);
        do {
            sleep(1);
            clearstatcache();
            $currentSize = filesize($this->fileName);
        } while ($oldSize === $currentSize);
    }
}