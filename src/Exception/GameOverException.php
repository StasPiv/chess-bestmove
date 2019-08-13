<?php
/**
 * Created by PhpStorm.
 * User: stas
 * Date: 02.09.16
 * Time: 12:35
 */

namespace StasPiv\ChessBestMove\Exception;

use Exception;

class GameOverException extends \RuntimeException
{
    public function __construct($message = 'Game over', $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}