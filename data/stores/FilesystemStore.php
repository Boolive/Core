<?php
/**
 * FilesystemStrore
 * @aurhor Vladimir Shestakov
 * @version 1.0
 */
namespace boolive\core\data\stores;

use boolive\core\data\Buffer;
use boolive\core\data\Data;
use boolive\core\data\IStore;
use boolive\core\functions\F;

class FilesystemStore implements IStore
{
    function __construct($key, $params)
    {

    }

    function read($uri)
    {
        if (!($entity = Buffer::get_entity($uri))){
            // Экземпляра объекта в буфере нет, проверяем массив его атрибутов в буфере
            if (!($info = Buffer::get_info($uri))){
                try {
                    if ($uri === '') {
                        $file = DIR . 'project.info';
                    } else {
                        $file = DIR . trim($uri, '/') . '/' . File::fileName($uri) . '.info';
                    }
                    if (is_file($file)) {
                        // Чтение информации об объекте
                        $info = file_get_contents($file);
                        $info = json_decode($info, true);
                        $info['uri'] = $uri;
                        if (!isset($info['is_default_logic'])) $info['is_default_logic'] = true;
                        $info['is_exists'] = true;
                        // Инфо о свойствах в буфер
                        $info = Buffer::set_info($info);
                    }
                } catch (\Exception $e) {
                    return false;
                }
            }
            if (empty($info)){
                // Поиск объекта в свойствах объекта
                list($parent_uri, $name) = F::splitRight('/', $uri);
                if ($parent = Data::read($parent_uri)) {
                    $entity = $parent->child($name);
                } else {
                    $entity = false;
                }
            }else{
                // Создать экземпляр без свойств
                $entity = Data::entity($info);
            }
        }
        return $entity;
    }

    function find($cond)
    {
        // select, from, depthdepth
        $dir = DIR.trim($cond['from'],'/');
        $objects =[];
        $trim_pos = mb_strlen(DIR);
        try {
            $dir_iterator = new \DirectoryIterator($dir);
//            $iterator = new \RecursiveIteratorIterator($dir_iterator);
//            $iterator->setMaxDepth(10);

            foreach ($dir_iterator as $d) {
                if ($d->isDir()) {
                    $uri = preg_replace('#\\\\#u', '/', mb_substr($d->getPathname(), $trim_pos));
                    if (!($obj = Buffer::get_entity($uri))) {
                        if (!($info = Buffer::get_info($uri))) {
                            $fname = $d->getPathname() . '/' . $d->getBasename();
                            if (is_file($fname . '.info')) {
                                // Все сведения об объекте в формате json (если нет класса объекта)
                                $f = file_get_contents($fname . '.info');
                                $info = json_decode($f, true);
                                if ($error = json_last_error()) {
                                    throw new \Exception('Ошибка в "' . $d->getPathname() . '"');
                                }
                                $info['uri'] = preg_replace('#\\\\#u', '/', mb_substr($d->getPathname(), $trim_pos));
                                //if (!empty($info['uri'])) $info['uri'] = '/'.$info['uri'];
                                if (!isset($info['is_default_logic'])) $info['is_default_logic'] = true;
                                $info['is_exists'] = true;
                            }
                        }
                        if ($info && empty($info['is_property'])) {
                            $obj = Data::entity($info);
                        }
                    }
                    if ($obj && !$obj->is_property()) {
                        if (!$cond['where'] || $obj->verify($cond['where'])) {
                            $objects[] = $obj;
                        }
                    }
                }
            }
        }catch (\Exception $e){

        }

        // where, key, access
        // order (not for value and object)
        if ($order = $cond['order']) {
            $order_cnt = count($order);
            usort($objects, function ($a, $b) use ($order, $order_cnt) {
                /** @var $a Entity */
                /** @var $b Entity */
                $i = 0;
                do {
                    if (count($order[$i]) == 3) {
                        $a = $a->{$order[$i][0]};
                        $b = $b->{$order[$i][0]};
                        array_shift($order[$i]);
                    }
                    $a = $a ? $a->$order[$i][0]():null;
                    $b = $b ? $b->$order[$i][0]():null;
                    if ($a == $b) {
                        $comp = 0;
                    } else {
                        $comp = ($a > $b || $a == null) ? 1 : -1;
                        if ($order[$i][1] == 'desc') $comp = -$comp;
                    }
                    $i++;
                } while ($comp == 0 && $i < $order_cnt);
                return $comp;
            });
        }
        // limit (not for value and object)

        // calc

        // struct

        return $objects;
    }

    function write($entity)
    {

    }

    function delete($entity)
    {

    }
} 