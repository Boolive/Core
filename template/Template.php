<?php
/**
 * Шаблонизатор
 * "Мост" к конкретным шаблонизаторам. Выбор происходит автоматически по расширениям файлов-шаблонов
 * @link http://boolive.ru/createcms/making-page
 * @version 2.0
 * @author Vladimir Shestakov <boolive@yandex.ru>
 */
namespace boolive\core\template;

use boolive\core\config\Config;
use boolive\core\errors\Error;
use boolive\core\IActivate;

class Template implements IActivate
{
    /** @var array Массив названий классов шаблонизаторов */
    static private $engines;

    /**
     * Загрузка шаблонизаторов
     */
    static function activate(){
        self::$engines = Config::read('template');
    }

    /**
     * Возвращает шаблонизатор для указанного файла шаблона
     * @param string $template
     * @return
     */
    static function getEngine($template)
    {
        foreach (self::$engines as $pattern => $engine){
            if (fnmatch($pattern, $template)){
                if (is_string($engine)){
                    self::$engines[$pattern] = new $engine();
                }
                return self::$engines[$pattern];
            }
        }
        return null;
    }

    /**
     * Создание текста из шаблона
     * В шаблон вставляются переданные значения
     * @param string $template
     * @param array $v
     * @throws Error
     * @return string
     */
    static function render($template, $v = [])
    {
        if ($engine = self::getEngine($template)){
            return $engine->render($template, $v);
        }else{
            throw new Error(array('Template engine for template "%s" not found ', $template));
        }
    }
}