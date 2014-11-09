<?php
/**
 * FilesystemStrore
 * @aurhor Vladimir Shestakov
 * @version 1.0
 */
namespace boolive\core\data\stores;

use boolive\core\data\Buffer;
use boolive\core\data\Data;
use boolive\core\data\Entity;
use boolive\core\data\IStore;
use boolive\core\file\File;
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
                        if (!empty($info['file'])){
                            $info['is_default_file'] = false;
//                            $info['is_file'] = true;
//                            $info['value'] = $info['file'];
                        }
                        if (!empty($info['logic'])){
                            $info['is_default_logic'] = false;
                        }
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
        // select, from, depth
        $dir = ($cond['from']==='')? DIR : DIR.trim($cond['from'],'/').'/';
        $objects =[];
        try {
            if ($cond['depth'] > 0){
                // Игнорируемые директории/файлы
                $ignore = array_flip(['.','..','.git']);
                // Перебор директории в глубь. (Рекурсия на циклах)
                if ($dh = new \DirectoryIterator($dir)) {
                    $stack = [['name' => '', 'dh' => $dh, 'depth' => $cond['depth'], 'parent' => $cond['from']]];
                    do {
                        $curr = end($stack);
                        while($curr['dh']->valid()){
                            /** @var \DirectoryIterator $file */
                            $file = $curr['dh']->current();
                            $curr['dh']->next();
                            if (($name = $file->getFilename())!==''){
                                if (!isset($ignore[$name])) {
                                    $uri = ($curr['name']==='')? $curr['parent'] : $curr['parent'].'/'.$curr['name'];
                                    if ($name == $curr['name'] . '.info') {
                                        if (!($obj = Buffer::get_entity($uri))) {
                                            if (!($info = Buffer::get_info($uri))) {
                                                // Все сведения об объекте в формате json (если нет класса объекта)
                                                $f = file_get_contents($file->getPathname());
                                                $info = json_decode($f, true);
                                                if ($error = json_last_error()) {
                                                    throw new \Exception('Ошибка в "' . $curr['dir'].$name . '"');
                                                }
                                                $info['uri'] = $uri;
                                                if (!empty($info['file'])){
                                                    $info['is_default_file'] = false;
//                                                    $info['is_file'] = true;
//                                                    $info['value'] = $info['file'];
                                                }
                                                if (!empty($info['logic'])){
                                                    $info['is_default_logic'] = false;
                                                }
                                                if (!isset($info['is_default_logic'])) $info['is_default_logic'] = true;
                                                $info['is_exists'] = true;
                                            }
                                            if ($info && empty($info['is_property'])) {
                                                $info = Buffer::set_info($info);
                                                $obj = Data::entity($info);
                                            }
                                        }
                                        // Проверка объекта на соответствие услвоию [where]
                                        if ($obj && !$obj->is_property()) {
                                            if (!$cond['where'] || $obj->verify($cond['where'])) {
                                                $objects[] = $obj;
                                            }
                                        }
                                    } else
                                    if ($curr['depth'] && $file->isDir()) {
                                        if ($dh = new \DirectoryIterator($file->getPathname())) {
                                            $stack[] = ['name' => $name, 'dh' => $dh, 'depth' => $curr['depth'] - 1, 'parent' => $uri];
                                            $curr = end($stack);
                                        }
                                    }
                                }
                            }
                        }
                        array_pop($stack);
                    } while ($stack);
                }
            }
        }catch (\Exception $e){
        }

        // key, access
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
        if ($cond['struct'] == 'tree'){
            $first_level = mb_substr_count($cond['from'],'/')+1;
            $tree = [];
            $result = [];
            foreach ($objects as $obj){
                $tree[$obj->uri()] = ['object' => $obj, 'sub' => []];
                if ($obj->depth() == $first_level){
                    $key = $cond['key']? $obj->attr($cond['key']) : null;
                    if ($key){
                        $result[$key] = &$tree[$obj->uri()];
                    }else{
                        $result[] = &$tree[$obj->uri()];
                    }
                }
            }
            foreach ($tree as $uri => $obj) {
                $key = $cond['key'] ? $obj['object']->attr($cond['key']) : null;
                $p = $obj['object']->attr('parent');
                if (isset($tree[$p])) {
                    if ($key) {
                        $tree[$p]['sub'][$key] = &$tree[$uri];
                    } else {
                        $tree[$p]['sub'][] = &$tree[$uri];
                    }
                }
            }
            return $result;
        }


        return $objects;
    }

    function write($entity)
    {

    }

    function delete($entity)
    {

    }
} 