<?php
/**
 * Created by PhpStorm.
 * User: stas
 * Date: 28.08.16
 * Time: 19:21
 */

namespace StasPiv\ChessBestMove\Model;

use JMS\Serializer\Annotation as JMS;

class Move
{
    /**
     * @var string
     *
     * @JMS\Type("string")
     */
    private string $from;

    /**
     * @var string
     *
     * @JMS\Type("string")
     */
    private string $to;

    /**
     * @var string
     *
     * @JMS\Type("string")
     */
    private string $promotion;

    /**
     * @var integer
     *
     * @JMS\Type("integer")
     */
    private int $score;

    /**
     * @return string
     */
    public function getFrom(): string
    {
        return $this->from;
    }

    /**
     * @param string $from
     * @return Move
     */
    public function setFrom(string $from): self
    {
        $this->from = $from;

        return $this;
    }

    /**
     * @return string
     */
    public function getTo(): string
    {
        return $this->to;
    }

    /**
     * @param string $to
     * @return Move
     */
    public function setTo(string $to): self
    {
        $this->to = $to;

        return $this;
    }

    /**
     * @return string
     */
    public function getPromotion(): string
    {
        return (string)$this->promotion;
    }

    /**
     * @param string $promotion
     * @return Move
     */
    public function setPromotion(string $promotion): self
    {
        $this->promotion = $promotion;

        return $this;
    }

    /**
     * @return int
     */
    public function getScore(): int
    {
        return $this->score;
    }

    /**
     * @param int $score
     *
     * @return Move
     */
    public function setScore(int $score): Move
    {
        $this->score = $score;
        return $this;
    }

    function __toString()
    {
        return $this->getFrom().$this->getTo().$this->getPromotion();
    }
}
