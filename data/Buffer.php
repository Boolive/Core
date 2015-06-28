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
    static private $list_entity = [];
    static private $list_info = [];
    static private $list_props = [];

    /**
     * Выбор экземпляра сущности
     * @param string $uri
     * @return null|Entity
     */
    static function get_entity($uri)
    {
        if (isset(self::$list_entity[$uri])){
            return self::$list_entity[$uri];
        }
        return null;
    }

    /**
     * Запись в буфер экземпляра сущности
     * @param Entity $entity
     */
    static function set_entity($entity)
    {
        self::$list_entity[$entity->uri()] = $entity;
    }

    /**
     * Удаление из буфера экземпляра сущности
     * @param string $uri
     */
    static function unset_entity($uri)
    {
        if (isset(self::$list_entity[$uri])) unset(self::$list_entity[$uri]);
    }

    /**
     * Выбор информации о сущности (только атрибуты сущности)
     * @param string $uri
     * @return null|array
     */
    static function get_info($uri)
    {
        if (isset(self::$list_info[$uri])) {
            return self::$list_info[$uri];
        }
        return null;
    }

    /**
     * Запись в буфер информации о сущности
     * @param array $info Атрибуты и свойства сущности
     * @return array Атрибуты без свойств
     */
    static function set_info($info)
    {
        if (!isset($info['proto']) && $info['uri'] !='/vendor/boolive/basic/object'){
            $info['proto'] = '/vendor/boolive/basic/object';
        }
        if (isset($info['properties'])){
            foreach ($info['properties'] as $name => $child){
                if (is_scalar($child) || is_null($child)){
                    $child = ['value' => $child];
                    if (isset($info['proto'])) $child['proto'] = $info['proto'].'/'.$name;
                }
                $child['name'] = $name;
                $child['is_property'] = true;
                if (!empty($child['value'])) $child['is_default_value'] = false;
                if (!empty($child['file'])) $child['is_default_file'] = false;
                if (!isset($child['is_default_logic'])) $child['is_default_logic'] = true;
                if (!isset($child['created']) && isset($info['created'])) $child['created'] = $info['created'];
                if (!isset($child['updated']) && isset($info['updated'])) $child['updated'] = $info['updated'];
                $child['is_exists'] = $info['is_exists'];
                $child['uri'] = $info['uri'].'/'.$name;
                self::$list_props[$info['uri']][$name] = $child['uri'];
                self::set_info($child);
            }
            unset($info['properties']);
        }
        self::$list_info[$info['uri']] = $info;
        return $info;
    }

    static function get_props($uri)
    {
        if (isset(self::$list_props[$uri])) {
            return self::$list_props[$uri];
        }
        return null;
    }


    /**
     * Удалениеинформации о сущности из буфера
     * @param string $uri
     */
    static function unset_info($uri)
    {
        if (isset(self::$list_info[$uri])) unset(self::$list_info[$uri]);
    }

    /**
     * Очистка буфера
     */
    static function clear()
    {
        self::$list_entity = [];
        self::$list_info = [];
        self::$list_props = [];
    }
}