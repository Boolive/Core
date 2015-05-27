<?php
/**
 * CLI
 * @author Vladimir Shestakov
 * @version 1.0
 */
namespace boolive\core\cli;

class CLI
{
    static function write($str)
    {
        fwrite(STDOUT, $str);
    }

    static function writeln($str)
    {
        self::write($str."\n");
    }

    static function read()
    {
        return fgets(STDIN);
    }
} 