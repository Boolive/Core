<?php
/**
 * Управление событиями
 *
 * @version 2.0
 * @author Vladimir Shestakov <boolive@yandex.ru>
 */
namespace boolive\core\events;

use boolive\core\config\Config;
use boolive\core\IActivate;

class Events implements IActivate
{
    /** @var array Реестр обработчиков событий */
    private static $handlers = [];

    /**
     * Активация модуля
     */
    static function activate()
    {
        self::$handlers = Config::read('events');
    }

    /**
     * Добавление обработчика события
     *
     * @param string $event Имя события
     * @param array $handler Обработчик события
     */
    static function on($event, $handler)
    {
        self::$handlers[$event][] = $handler;
    }

    /**
     * Генерация события
     *
     * @param string $event Имя события
     * @param array|mixed $params Параметры события
     * @param bool $all
     * @return mixed Объект события с результатами его обработки
     */
    static function trigger($event, $params = [], $all = true)
    {
        $result = [];
        if (isset(self::$handlers[$event])) {
            foreach (self::$handlers[$event] as $key => $handler) {
                if (isset($handler[0]) && (empty($handler[0]) || mb_strpos($handler[0], '/') !== false)) {
                    $out = call_user_func_array([\boolive\core\data\Data::read($handler[0]), $handler[1]], $params);
                } else {
                    $out = call_user_func_array($handler, $params);
                }
                if ($out === false){
                    if (!$all) return $out;
                }else{
                    $result[$key] = $out;
                }
            }
        }
        if (count($result) ==0 && !$all){
            return null;
        }
        return $result;
    }
}