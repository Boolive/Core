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
use boolive\core\errors\Error;
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
            $info = Buffer::get_info($uri);
            if (empty($info)){
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
                        $error = json_last_error();
                        if ($error != JSON_ERROR_NONE) {
                            $info = [];
                        }
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
            if (empty($info) && $uri){
                // Поиск объекта в свойствах объекта
                list($parent_uri, $name) = F::splitRight('/', $uri);
                if ($parent = Data::read($parent_uri)) {
                    $props = Buffer::get_props($parent_uri);
                    $entity = $parent->child($name, isset($props[$name]));
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

    /**
     * @param $cond
     * <code>
     * [
     *      'select' => 'children', //self, children, parents, heirs, protos, child, link
     *      'calc' => 'count', // false, exists, count, [sum, attr], [max, attr], [min, attr], [avg, attr]
     *      'from' => '/contents',
     *      'depth' => 1, // maximum depth
     *      'struct' => 'array', // value, object, array, tree
     *      'where' => [], //filter condition
     *      'order' => [['name', 'asc'], ['value','desc']],
     *      'limit' => [0,100],
     *      'key' => 'name', // attribute name
     *      'access' => true // check read access
     * ];
     * </code>
     * @return array
     */
    function find($cond)
    {
        // select, from, depth
        $dir = ($cond['from']==='')? DIR : DIR.trim($cond['from'],'/').'/';
        $objects =[];
        try {
            if ($cond['depth'] > 0){
                if ($cond['select'] == 'properties'){
                    // Читаем, чтобы инфо о свойства попало в буфер
                    $from = $this->read($cond['from']);// need for Buffer::get_props
                    if ($prop_names = Buffer::get_props($cond['from'])){
                        foreach ($prop_names as $prop_uri){
                            // Проверка объекта на соответствие услвоию [where]
                            if ($obj = $this->read($prop_uri)) {
                                if (!$cond['where'] || $obj->verify($cond['where'])) {
                                    $objects[] = $obj;
                                    if ($cond['calc'] == 'exists') break;
                                }
                            }
                        }
                    }
                }else
                if ($cond['select'] == 'children') {
                    // Игнорируемые директории/файлы
                    $ignore = array_flip(['.', '..', '.git']);
                    // Перебор директории в глубь. (Рекурсия на циклах)
                    if ($dh = new \DirectoryIterator($dir)) {
                        $stack = [['name' => '', 'dh' => $dh, 'depth' => $cond['depth'], 'parent' => $cond['from']]];
                        do {
                            $curr = end($stack);
                            while ($curr['dh']->valid()) {
                                /** @var \DirectoryIterator $file */
                                $file = $curr['dh']->current();
                                $cur_dh = $curr['dh'];
                                if (($name = $file->getFilename()) !== ''){
                                    if (!isset($ignore[$name])) {
                                        $uri = ($curr['name'] === '') ? $curr['parent'] : $curr['parent'] . '/' . $curr['name'];
                                        if ($name == $curr['name'] . '.info') {
                                            if (!($obj = Buffer::get_entity($uri))) {
                                                if (!($info = Buffer::get_info($uri))) {
                                                    // Все сведения об объекте в формате json (если нет класса объекта)
                                                    $f = file_get_contents($file->getPathname());
                                                    $info = json_decode($f, true);
                                                    $error = json_last_error();
                                                    if ($error != JSON_ERROR_NONE) {
                                                        $info = [];
//                                                        throw new \Exception('Ошибка в "' . $curr['dir'] . $name . '"');
                                                    }
                                                    $info['uri'] = $uri;
                                                    if (!empty($info['file'])) {
                                                        $info['is_default_file'] = false;
//                                                    $info['is_file'] = true;
//                                                    $info['value'] = $info['file'];
                                                    }
                                                    if (!empty($info['logic'])) {
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
                                                    if ($cond['calc'] == 'exists') break;
                                                }
                                            }
                                        } else
                                        if ($curr['depth'] && $file->isDir()){
                                            if ($dh = new \DirectoryIterator($file->getPathname())) {
                                                $stack[] = ['name' => $name, 'dh' => $dh, 'depth' => $curr['depth'] - 1, 'parent' => $uri];
                                                $curr = end($stack);
                                            }
                                        }
                                    }
                                }
                                $cur_dh->next();
                            }
                            array_pop($stack);
                        } while ($stack);
                    }
                }
            }
        }catch (\Exception $e){
        }

        // access

        // calc
        if ($cond['calc']){
            $calc = null;
            if ($cond['calc'] == 'exists'){
                $calc = !empty($objects);
            }else
            if ($cond['calc'] == 'count'){
                $calc = count($objects);
            }else
            if (is_array($cond['calc']) && count($cond['calc'])==2){
                $attr = $cond['calc'][1];
                foreach ($objects as $o){
                    switch ($cond['calc'][0]){
                        case 'min':
                            $calc = $calc===null? $o->attr($attr) : min($o->attr($attr), $calc);
                            break;
                        case 'max':
                            $calc = $calc===null? $o->attr($attr) : max($o->attr($attr), $calc);
                            break;
                        case 'sum':
                        case 'avg':
                        default:
                            $calc+= $o->attr($attr);
                            break;
                    }
                }
                if  ($objects && $cond['calc'][0] == 'avg') $calc = $calc / count($objects);
            }
            return $calc;
        }

        // order
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
                        $comp = ($a > $b || $a === null) ? 1 : -1;
                        if ($order[$i][1] == 'desc') $comp = -$comp;
                    }
                    $i++;
                } while ($comp == 0 && $i < $order_cnt);
                return $comp;
            });
        }

        // limit (not for value and object)
        if ($cond['limit']){
            $objects = array_slice($objects, $cond['limit'][0], $cond['limit'][1]);
        }

        // struct, key
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
        }else
        if ($cond['struct'] == 'array' && $cond['key']){
            $result = [];
            /** @var Entity $item */
            foreach ($objects as $item){
                $result[$item->attr($cond['key'])] = $item;
            }
            return $result;
        }
        return $objects;
    }

    /**
     * Сохранение сущности
     * @param Entity $entity
     * @throws Error
     */
    function write($entity)
    {
        // Если объект свойство, то сохранять родительский объект??
        if ($entity->is_property()){
            if ($parent = $entity->parent(null, true)) {
                $parent->__set($entity->name(), $entity);
                Data::write($parent);
            }
        }else {
            // Текущие сведения об объекте
            $info = [];
            if ($entity->is_exists()) {
                // Текущие сведения об объекта
                $uri = $entity->is_changed('uri') ? $entity->changes('uri') : $entity->uri();
                if ($uri === '') {
                    $file = DIR . 'project.info';
                } else {
                    $file = DIR . trim($uri, '/') . '/' . File::fileName($uri) . '.info';
                }
                if (is_file($file)) {
                    $info = file_get_contents($file);
                    $info = json_decode($info, true);
                }
            }
            // Подбор уникального имени
            // @todo Перенос php файлов влечет за собой фатальные ошибки!!!! так как меняется namespace и class name
            if ($entity->is_changed('uri') || !$entity->is_exists()) {
                // Проверка уникальности нового имени путём создания папки
                // Если подбор уникального имени, то создавать пока не создаться (попробовать постфикс)
                $path = dirname($entity->dir(true)) . '/';
                $name = $entity->name();
                if ($new_path = File::makeUniqueDir($path, $name, 1, $entity->is_auto_namig())) {
                    $entity->name(basename($new_path));
                    $info['name'] = $entity->name();
                } else {
                    $entity->errors()->_attributes->name->add(new Error('Не уникальное', 'unique'));
                    throw $entity->errors();
                }
                // Перемещение старой папки в новую
                if ($entity->is_exists()) {
                    File::rename($entity->dir(true, true), $entity->dir(true, false));
                }
                if ($entity->is_changed('name') && $entity->is_exists()) {
                    // @todo Переименовать .info, .php и, возможно, привязанный файл.
                }
                // Обновить URI подчиненных объектов не фиксируя изменения
                $entity->updateChildrenUri();
            }

            // Новые сведения об объекте
            $info_new = $this->export($entity, isset($info['properties']) ? $info['properties'] : [], function (Entity $entity, $file) {
                return Data::save_file($entity, $file);
            });

            // Порядковый номер
            // 1. Подбор максимального среди существующих
            // 2. Смещение порядка у последующих объектов

            // Сохранить объект с свлйствами JSON
            $uri = $entity->uri();
            if ($uri === '') {
                $file = DIR . 'project.info';
            } else {
                $file = DIR . trim($uri, '/') . '/' . File::fileName($uri) . '.info';
            }
            File::create(F::toJSON($info_new, true), $file);
        }
        // Сохранить подчиненные
        foreach ($entity->children() as $child){
            /** @var Entity $child */
            if (!$child->is_property()) Data::write($child);
        }
    }

    function delete($entity)
    {

    }

    /**
     * @param Entity $entity
     * @param array $properties
     * @param \Closure $file_save_callback Функция для обработки подключенных к объекту файлов
     * @return array
     */
    protected function export($entity, $properties = [], $file_save_callback = null)
    {
        // Новые сведения об объекте
        $result = [];
        $result['proto'] = $entity->proto();
        if ($entity->author()) $result['author'] = $entity->author();
        if ($entity->order() < Entity::MAX_ORDER) $result['order'] = $entity->order();
        if ($entity->created() > 0) $result['created'] = $entity->created();
        if ($entity->updated() > 0) $result['updated'] = $entity->updated();
        // value
        if (!$entity->is_default_value()) $result['value'] = $entity->value();
        // file
        if (!$entity->is_default_file()){
            if ($entity->is_file()){
                $file_attache = $entity->file();
                if (is_array($file_attache)){
                    // Загрузка файла
                    if ($file_save_callback instanceof \Closure){
                        if (!($result['file'] = $file_save_callback($entity, $file_attache))){
                            unset($result['file']);
                        }
                    }
                }else{
                    $result['file'] = basename($file_attache);
                }
            }else{
                // файла нет, но нужно отменить наследование файла
                $result['file'] = null;
            }
        }
        if (!$entity->is_default_logic()) $result['logic'] = true;
        if ($entity->is_draft()) $result['is_draft'] = true;
        if ($entity->is_hidden()) $result['is_hidden'] = true;
        if ($entity->is_mandatory()) $result['is_mandatory'] = true;
//        if ($entity->is_property()) $result['is_property'] = $entity->is_property();
        if ($entity->is_relative()) $result['is_relative'] = true;
        if ($entity->is_link()) $result['is_link'] = true;

        if (is_array($properties)) {
            $result['properties'] = $properties;
        }

        /** @var Entity $child */
        foreach ($entity->children() as $name => $child){
            if ($child->is_property()){
                $result['properties'][$name] = $this->export($child, isset($properties[$name]['properties'])?$properties[$name]['properties']:[], $file_save_callback);
            }
        }
        if (empty($result['properties'])){
            unset($result['properties']);
        }
        return $result;
    }
}