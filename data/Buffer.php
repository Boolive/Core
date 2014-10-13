<?php
/**
 * Бефер сущностей
 *
 * @version 2.0
 * @author Vladimir Shestakov <boolive@yandex.ru>
 */
namespace boolive\core\data;

class Buffer
{
    static private $list = array();

    static function activate()
    {

    }

    static function get($key)
    {
        if (isset(self::$list[$key])){
            return self::$list[$key];
        }
        return null;
    }

    /**
     * @param Entity $entity
     */
    static function set($entity)
    {
        self::$list[$entity->uri()] = $entity;
    }

    static function remove($key)
    {
        if (isset(self::$list[$key])) unset(self::$list[$key]);
    }

    static function is_exists($key)
    {
        return isset($key) && isset(self::$list[$key]);
    }
}