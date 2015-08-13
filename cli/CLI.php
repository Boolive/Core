<?php
/**
 * CLI
 * @author Vladimir Shestakov
 * @version 1.0
 */
namespace boolive\core\cli;

use boolive\core\config\Config;

class CLI
{
    const STYLE_BOLD = '1';
    const STYLE_UNDERLINE = '4';
    /** text colors */
    const COLOR_BLACK = '0;30';
    const COLOR_GRAY_DARK = '1;30';
    const COLOR_BLUE = '0;34';
    const COLOR_BLUE_LIGHT = '1;34';
    const COLOR_GREEN = '0;32';
    const COLOR_GREEN_LIGHT = '1;32';
    const COLOR_CYAN = '0;36';
    const COLOR_CYAN_LIGHT = '1;36';
    const COLOR_RED = '0;31';
    const COLOR_RED_LIGHT = '1;31';
    const COLOR_PURPLE = '0;35';
    const COLOR_PURPLE_LIGHT = '1;35';
    const COLOR_BROWN = '0;33';
    const COLOR_YELLOW = '1;33';
    const COLOR_GRAY_LIGHT = '0;37';
    const COLOR_WHITE = '1;37';
    /** background colors */
    const BG_COLOR_BLACK = '40';
    const BG_COLOR_RED = '41';
    const BG_COLOR_GREEN = '42';
    const BG_COLOR_YELLOW = '43';
    const BG_COLOR_BLUE = '44';
    const BG_COLOR_MAGENTA = '45';
    const BG_COLOR_CYAN = '46';
    const BG_COLOR_GRAY_LIGHT = '47';
    /** @var null|bool Признак подержки форматирования вывода */
    static private $is_ansicon = null;
    static private $stdin;
    static private $stdout;
    static private $running_commands = [];

    static function get_stdin()
    {
        if (!self::$stdin || feof(self::$stdin)) {
            self::$stdin = fopen('php://stdin', 'r');
        }
        return self::$stdin;
    }

    static function get_stdout()
    {
        if (!self::$stdout || feof(self::$stdout)) {
            self::$stdout = fopen('php://stdout', 'w');
        }
        return self::$stdout;
    }


    static function write($str)
    {
        fwrite(self::get_stdout(), $str);
    }

    static function writeln($str)
    {
        self::write($str."\n");
    }

    static function read($format = null)
    {
        do {
            if ($format){
                fscanf(self::get_stdin(), $format."\n", $in);
            }else{
                $in = fgets(self::get_stdin());
            }
        }while ($in===false);
        return $in;
    }



    static function style($value, $styles = [])
    {
        if (is_bool($value)) $value = $value ? 'true' : 'false';
        if (is_null($value)) $value = 'null';
        if ($styles && self::is_ansicon()) {
            if (!is_array($styles)) $styles = [$styles];
            $str_f = '';
            foreach ($styles as $f) {
                $str_f .= "\033[" . $f . "m";
            }
            $value = $str_f . $value . "\033[0m";
        }
        return $value;
    }

    static function is_eof()
    {
        return feof(STDIN);
    }


    static function is_ansicon()
    {
        if (!isset(self::$is_ansicon)){
            self::$is_ansicon = getenv('ANSICON') !== false ||
                (DIRECTORY_SEPARATOR != '\\' && function_exists('posix_isatty') && @posix_isatty(STDOUT));
        }
        return self::$is_ansicon;
    }

    /**
     * Установка признака, использовать или нет форматирование ANSICON (цвета)
     * Если null, то определиться автоматически
     * @param null $use
     */
    static function use_style($use = null)
    {
        self::$is_ansicon = $use;
    }

    /**
     * Исполнение php скрипта в командной строке
     * @param string $command Команда - запускаемый скрипт с аргументами
     * @param bool $background_mode Признак, запускать в фоновом режиме. По умолчанию нет
     * @param bool $ignore_duplicates Признак, игнорировать дубликаты
     */
    static function run_php($command, $background_mode = false, $ignore_duplicates = true)
    {
        if (!$ignore_duplicates || empty(self::$running_commands[$command])) {
            $config = Config::read('core');
            $php = empty($config['php']) ? 'php' : $config['php'];
            if (substr(php_uname(), 0, 7) == "Windows") {
                pclose(popen('start' . ($background_mode ? ' /B ' : ' ') . $php . ' ' . $command, "r"));
            } else {
                exec($php . ' ' . $command . ($background_mode ? " > /dev/null &" : ''));
            }
        }
        self::$running_commands[$command] = true;
    }

    /**
     * Удаления признака, что команда была запущена
     * @param null $command Команда. Если null, то удаляются все команды
     */
    static function clear_running_commands($command = null)
    {
        if (empty($command)){
            self::$running_commands = [];
        }else
        if (array_key_exists($command, self::$running_commands)){
            unset(self::$running_commands, $command);
        }
    }
}