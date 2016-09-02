<?php
/**
 * Created by PhpStorm.
 * User: stas
 * Date: 02.09.16
 * Time: 12:54
 */

namespace StasPiv\ChessBestMove\Exception;


use Exception;

class ResourceUnavailableException extends \RuntimeException
{
    public function __construct($message = 'Resource unavailable !', $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

}