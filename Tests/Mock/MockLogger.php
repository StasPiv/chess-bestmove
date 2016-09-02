<?php
/**
 * Created by PhpStorm.
 * User: stas
 * Date: 02.09.16
 * Time: 11:57
 */

namespace StasPiv\ChessBestMove\Tests\Mock;

use Psr\Log\AbstractLogger;

class MockLogger extends AbstractLogger
{
    public function log($level, $message, array $context = array())
    {
        //echo $level.' '.$message.PHP_EOL;
    }

}