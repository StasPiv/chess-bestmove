<?php
/**
 * Created by PhpStorm.
 * User: stas
 * Date: 02.09.16
 * Time: 12:35
 */

namespace StasPiv\ChessBestMove\Exception;

use Exception;

class NotValidBestMoveHaystackException extends \RuntimeException
{
    public function __construct($message = 'Not valid best move haystack', $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}