<?php
/**
 * Created by PhpStorm.
 * User: stas
 * Date: 16.09.16
 * Time: 9:08
 */

namespace StasPiv\ChessBestMove\Exception;


use Exception;

class BotIsFailedException extends \RuntimeException
{
    public function __construct($message = 'Bot is failed', $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

}