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
    private $engine;

    /**
     * @var array hash map (key=>value)
     */
    private $options;

    /**
     * @var int
     */
    private $wtime;

    /**
     * @var int
     */
    private $btime;

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
     * @return int
     */
    public function getWtime(): int
    {
        return $this->wtime;
    }

    /**
     * @param int $wtime
     * @return EngineConfiguration
     */
    public function setWtime(int $wtime): self
    {
        $this->wtime = $wtime;

        return $this;
    }

    /**
     * @return int
     */
    public function getBtime(): int
    {
        return $this->btime;
    }

    /**
     * @param int $btime
     * @return EngineConfiguration
     */
    public function setBtime(int $btime): self
    {
        $this->btime = $btime;

        return $this;
    }
}