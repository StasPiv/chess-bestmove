<?php
/**
 * Created by PhpStorm.
 * User: stas
 * Date: 28.08.16
 * Time: 19:27
 */

namespace StasPiv\ChessBestMove\Model;


class EngineConfiguration
{
    /**
     * @var string
     */
    private $engine = 'stockfish';

    /**
     * @var array hash map (key=>value)
     */
    private $options = [];

    /**
     * @var string
     */
    private $pathToPolyglotRunDir;

    /**
     * EngineConfiguration constructor.
     * @param string $engine
     */
    public function __construct($engine)
    {
        $this->engine = $engine;
    }

    /**
     * @return string
     */
    public function getEngine(): string
    {
        return $this->engine;
    }

    /**
     * @param string $engine
     * @return EngineConfiguration
     */
    public function setEngine(string $engine): self
    {
        $this->engine = $engine;

        return $this;
    }

    /**
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @param array $options
     * @return EngineConfiguration
     */
    public function setOptions(array $options): self
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @param string $name
     * @param string $value
     * @return EngineConfiguration
     */
    public function addOption(string $name, string $value): self
    {
        $this->options[$name] = $value;

        return $this;
    }

    /**
     * @param string $name
     * @return string
     * @throws \Exception
     */
    public function getOption(string $name): string
    {
        if (!isset($this->options[$name])) {
            throw new \Exception('This option doesn\'t exist');
        }

        return $this->options[$name];
    }

    /**
     * @return string
     */
    public function getPathToPolyglotRunDir(): string
    {
        return $this->pathToPolyglotRunDir;
    }

    /**
     * @param string $pathToPolyglotRunDir
     * @return EngineConfiguration
     */
    public function setPathToPolyglotRunDir(string $pathToPolyglotRunDir): self
    {
        $this->pathToPolyglotRunDir = $pathToPolyglotRunDir;

        return $this;
    }
}