<?php
/**
 * Модуль для чтения и записи файлов конфигурации
 *
 * @version 1.0
 * @date 21.08.2014
 * @author Vladimir Shestakov <boolive@yandex.ru>
 */
namespace boolive\core\config;

use boolive\core\functions\F;
use boolive\core\IActivate;

class Config implements IActivate
{
    /**
     * Признак, использовать ли синтакиси квадратных скобок в опредлении массива
     * @var bool
     */
    static $bracket_syntax = true;
    static $dirs = [];


    static function activate()
    {
        $json = file_get_contents(DIR.'vendor/composer/installed.json');
        $packages = json_decode($json, true);
        self::$dirs[] = DIR_CONFIG;
        foreach ($packages as $p){
            $dir = DIR.'vendor/'.$p['name'].'/config/';
            echo $dir.'</br>';
            if (is_dir($dir)){
                self::$dirs[] = $dir;
            };
        }
    }

    /**
     * Получение конфигурации по названию
     * По имени находится файл конфиграции в директрии DIR_CONFIG
     * @param $name string Название конфигурации
     * @return mixed
     */
    static function read($name)
    {
        $config = [];
        foreach (self::$dirs as $dir){
            try{
                $c = include $dir.$name.'.php';
                $config = array_merge_recursive($config, $c);
            }catch (\Exception $e){}
        }
        return $config;
    }

    /**
     * Запись кофигруации в файл
     * @param $name string Название конфигурации
     * @param $config mixed
     * @param null $comment
     * @param bool $pretty
     */
    static function write($name, $config, $comment = null, $pretty = true)
    {
        $fp = fopen(DIR_CONFIG.$name.'.php', 'w');
        fwrite($fp, self::generate($config, $comment, $pretty));
        fclose($fp);
    }

    /**
     * Проверка возможности записать файл конфигураци
     * @param $name string Название конфигурации
     * @return bool
     */
    static function is_writable($name)
    {
        if (file_exists(DIR_CONFIG.$name.'.php')){
            return is_writable(DIR_CONFIG.$name.'.php');
        }
        return is_writable(DIR_CONFIG);
    }

    static function file_name($name)
    {
        return DIR_CONFIG.$name.'.php';
    }

    /**
     * Генератор содержимого файла конфигурации
     * @param array $config
     * @param string $comment
     * @param bool $pretty
     * @return string
     */
    static function generate(array $config, $comment = '', $pretty = true)
    {
        $arraySyntax = [
            'open' => self::$bracket_syntax ? '[' : 'array(',
            'close' => self::$bracket_syntax ? ']' : ')'
        ];
        $code = "<?php\n";
        if ($comment){
            $comment = explode("\n",$comment);
            $code.="/**\n";
            foreach ($comment as $line) $code.=' * '.$line."\n";
            $code.=" */\n";
        }
        return $code.
               "return " . $arraySyntax['open'] . "\n" .
        ($pretty ? F::arrayToCode($config, $arraySyntax) : var_export($config, true) ).
               $arraySyntax['close'] . ";\n";
    }
}
 