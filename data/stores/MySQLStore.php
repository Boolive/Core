<?php
/**
 * MySQLStrore
 * @aurhor Vladimir Shestakov
 * @version 1.0
 */
namespace boolive\core\data\stores;

use boolive\core\auth\Auth;
use boolive\core\data\Buffer;
use boolive\core\data\Data;
use boolive\core\data\Entity;
use boolive\core\data\IStore;
use boolive\core\database\DB;
use boolive\core\errors\Error;
use boolive\core\events\Events;
use boolive\core\file\File;
use boolive\core\functions\F;

class MySQLStore implements IStore
{
    /** @var DB */
    public $db;
    /** @var string Ключ хранилища, по которому хранилище выбирается для объектов и создаются короткие URI */
    private $key;
    /** @var array Хэш uri => id Идентфиикатор объекта в хранилище*/
    private $_local_ids = [];
    /** @var array Хэш id => [] Идентфикаторы родителей */
    private $_parents_ids =[];
    /** @var array Хэш id => [] Идентификаторы прототипов */
    private $_protos_ids =[];
    /** @var array Хэш id => uri  */
    private $_global_ids = [];

    private $_parents_queue = [];
    private $_protos_queue = [];

    /**
     * Конструктор экземпляра хранилища
     * @param string $key Ключ хранилища. Используется для формирования и распознования сокращенных URI
     * @param array $params Параметры подключения к базе данных
     */
    function __construct($key, $params)
    {
        $this->key = $key;
        $this->db = DB::connect($params);
//        Events::on('Boolive::deactivate', [$this, 'deactivate']);
    }

    /**
     * Обработчик системного события deactivate (завершение работы системы)
     */
    function deactivate()
    {
//        if ($this->_local_ids_change) Cache::set('mysqlstore/localids', F::toJSON($this->_local_ids, false));
    }

    /**
     * @param $uri
     * @return Entity|null
     */
    function read($uri)
    {
        if (!($entity = Buffer::get_entity($uri))){
            // Экземпляра объекта в буфере нет, проверяем массив его атрибутов в буфере
            $info = Buffer::get_info($uri);
            if (empty($info)){
                try {
                    // выбор по uri
                    $q = $this->db->prepare('
                        SELECT o.* FROM {ids} as i , {objects} as o
                        WHERE i.id = o.id AND i.uri = :uri
                        LIMIT 0,1
                    ');
                    $q->execute(['uri' => $uri]);
                    if ($info = $q->fetch(DB::FETCH_ASSOC)){
                        $info['is_exists'] = true;

                        $this->_local_ids[$uri] = intval($info['id']);
                        $this->_global_ids[$info['id']] = $uri;

                        if (!empty($info['file'])) {
                            $info['is_default_file'] = false;
                        }

                        // Выборка текста
                        if (!empty($info['is_text'])){
                            $q = $this->db->query('SELECT `value` FROM {text} WHERE id = '.intval($info['id']));
                            if ($row = $q->fetch(DB::FETCH_ASSOC)){
                                $info['value'] = $row['value'];
                            }
                        }

                        // вместо parent, proto, author их uri
                        $info['parent'] = $this->globalId($info['parent']);
                        $info['proto'] = $this->globalId($info['proto']);
                        $info['author'] = $this->globalId($info['author']);



                        unset($info['id'], $info['is_text']);
                    }
                    $info['uri'] = $uri;

                    // Инфо о свойствах в буфер
                    $info = Buffer::set_info($info);

                } catch (\Exception $e) {
                    return null;
                }
            }
            // Создать экземпляр без свойств
            $entity = Data::entity($info);
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
        return [];
    }

    /**
     * Сохранение сущности
     * @param Entity $entity
     * @throws Error
     */
    function write($entity)
    {

        // @todo
        // Для нового объекта нужны id всех родителей и всех прототипов, чтобы их прописать в таблицу иерархических отношений
        // Смена родителя/прототипа требует соответсвующие сдвиги в таблице отношений
        // При смене uri нужно обновить uri подчиненных
        // Для создания локального идентификатора нужно оперировать сущностью, чтобы сделать запись и в таблицы отношений


        $attr = $entity->attributes();
        // Локальные id
        $attr['parent'] = isset($attr['parent']) ? $this->localId($attr['parent']) : 0;
        $attr['proto'] = isset($attr['proto']) ? $this->localId($attr['proto']) : 0;
        $attr['author'] = isset($attr['author']) ? $this->localId($attr['author']) : 0;//$this->localId(Auth::get_user()->uri());

        // Подбор уникального имени, если указана необходимость в этом
        if ($entity->is_changed('uri') || !$entity->is_exists()) {
            $q = $this->db->prepare('SELECT 1 FROM {objects} WHERE parent=? AND `name`=? LIMIT 0,1');
            $q->execute(array($attr['parent'], $attr['name']));
            if ($q->fetch()){
                //Выбор записи по шаблону имени с самым большим префиксом
                $q = $this->db->prepare('SELECT `name` FROM {objects} WHERE parent=? AND `name` REGEXP ? ORDER BY CAST((SUBSTRING_INDEX(`name`, "_", -1)+1) AS SIGNED) DESC LIMIT 0,1');
                $q->execute(array($attr['parent'], '^'.$attr['name'].'(_[0-9]+)?$'));
                if ($row = $q->fetch(DB::FETCH_ASSOC)){
                    preg_match('|^'.preg_quote($attr['name']).'(?:_([0-9]+))?$|u', $row['name'], $match);
                    $attr['name'].= '_'.(isset($match[1]) ? ($match[1]+1) : 1);
                }
            }
            $entity->name($attr['name']);
            $attr['uri'] = $entity->uri();
        }

        Buffer::set_entity($entity);

        // Локальный идентификатор объекта
        $attr['id'] = $this->localId($entity->uri($entity->is_exists()), true, $new_id);

        // Если смена файла, то удалить текущий файл
        if ($entity->is_changed('file')){
            $current_file = $entity->changes('file');
            if (is_string($current_file)) File::delete($entity->dir(true).$current_file);
        }
        // Если привязан файл
        if (!$entity->is_default_file()){
            if ($entity->is_file()){
                $file_attache = $entity->file();
                if (is_array($file_attache)){
                    // Загрузка файла
                    if (!($attr['file'] = Data::save_file($entity, $file_attache))){
                        $attr['file'] = '';
                    }
                }else{
                    $attr['file'] = basename($file_attache);
                }
            }else{
                // файла нет, но нужно отменить наследование файла
                $attr['file'] = '';
            }
        }else{
            $attr['file'] = '';
        }

        // Уникальность order
        // Если изменено на конкретное значение (не максимальное)
        if ($attr['order'] != Entity::MAX_ORDER && (!$entity->is_exists() || $entity->is_changed('order'))){
            // Проверка, занят или нет новый order
            $q = $this->db->prepare('SELECT 1 FROM {objects} WHERE `parent`=? AND `order`=?');
            $q->execute(array($attr['parent'], $attr['order']));
            if ($q->fetch()){
                // Сдвиг order существующих записей, чтоб освободить значение для новой
                $q = $this->db->prepare('
                    UPDATE {objects} SET `order` = `order`+1
                    WHERE `parent`=? AND `order`>=?'
                );
                $q->execute(array($attr['parent'], $attr['order']));
            }
            unset($q);
        }else
        // Новое максимальное значение для order, если объект новый или явно указано order=null
        if (!$entity->is_exists() || $attr['order'] == Entity::MAX_ORDER){
            // Порядковое значение вычисляется от максимального существующего
            $q = $this->db->prepare('SELECT MAX(`order`) m FROM {objects} WHERE parent=?');
            $q->execute(array($attr['parent']));
            if ($row = $q->fetch(DB::FETCH_ASSOC)){
                $attr['order'] = $row['m']+1;
            }
            unset($q);
        }

        $this->db->beginTransaction();

        // Если новое имя или родитель, то обновить свой URI и URI подчиненных
        if ($entity->is_changed('name') || $entity->is_changed('parent')){
            if ($entity->is_exists()){
                $current_uri = $entity->uri(true);
                $current_name = $entity->is_changed('name')? $entity->changes('name') : $attr['name'];
                // Текущий URI
                $names = F::splitRight('/', $current_uri, true);
                $uri = (isset($names[0])?$names[0].'/':'').$current_name;
                // Новый URI
                $names = F::splitRight('/', $attr['uri'], true);
                $uri_new = (isset($names[0])?$names[0].'/':'').$attr['name'];
                $entity->change('uri', $uri_new);

                // @todo Обновление URI подчиенных в базе
                // нужно знать текущий уковень вложенности и локальный id

                //
    //            $q = $this->db->prepare('UPDATE {ids}, {parents} SET {ids}.uri = CONCAT(?, SUBSTRING(uri, ?)) WHERE {parents}.parent_id = ? AND {parents}.object_id = {ids}.id AND {parents}.is_delete=0');
    //            $v = array($uri_new, mb_strlen($uri)+1, $attr['id']);
    //            $q->execute($v);
    //            // Обновление уровней вложенностей в objects
    //            if (!empty($current) && $current['parent']!=$attr['parent']){
    //                $dl = $attr['parent_cnt'] - $current['parent_cnt'];
    //                $q = $this->db->prepare('UPDATE {objects}, {parents} SET parent_cnt = parent_cnt + ? WHERE {parents}.parent_id = ? AND {parents}.object_id = {objects}.id AND {parents}.is_delete=0');
    //                $q->execute(array($dl, $attr['id']));
    //                // Обновление отношений
    //                $this->makeParents($attr['id'], $attr['parent'], $dl, true);
    //            }
                if (!empty($uri) && is_dir(DIR.$uri)){
                    // Переименование/перемещение папки объекта
                    $dir = DIR.$uri_new;
                    File::rename(DIR.$uri, $dir);
                    if ($entity->is_changed('name')){
                        // Переименование файла класса
                        File::rename($dir.'/'.$current_name.'.php', $dir.'/'.$attr['name'].'.php');
                        // Переименование .info файла
                        File::rename($dir.'/'.$current_name.'.info', $dir.'/'.$attr['name'].'.info');
                    }
                }
                unset($q);
            }
            // Обновить URI подчиненных объектов не фиксируя изменения
            $entity->updateChildrenUri();
        }

        // Если значение больше 255
        if (mb_strlen($attr['value']) > 255){
            $q = $this->db->prepare('
                INSERT INTO {text} (`id`, `value`)
                VALUES (:id, :value)
                ON DUPLICATE KEY UPDATE `value` = :value
            ');
            $q->execute(array(':id' => $attr['id'], ':value' => $attr['value']));
            $attr['value'] = mb_substr($attr['value'],0,255);
            $attr['is_text'] = 1;
        }else{
            $attr['is_text'] = 0;
        }

        // Запись
        $attr_names = array(
            'id', 'parent', 'proto', 'author', 'order', 'name', 'value', 'file', 'is_text',
            'is_draft', 'is_hidden', 'is_link', 'is_mandatory', 'is_property', 'is_relative',
            'is_default_value', 'is_default_logic', 'created', 'updated'
        );
        $cnt = sizeof($attr_names);
        // Запись объекта (создание или обновление при наличии)
        // Объект идентифицируется по id
        if (!$entity->is_exists()){
            $q = $this->db->prepare('
                INSERT INTO {objects} (`'.implode('`, `', $attr_names).'`)
                VALUES ('.str_repeat('?,', $cnt-1).'?)
                ON DUPLICATE KEY UPDATE `'.implode('`=?, `', $attr_names).'`=?
            ');
            $i = 0;
            foreach ($attr_names as $name){
                $value = $attr[$name];
                $i++;
                $type = is_int($value)? DB::PARAM_INT : (is_bool($value) ? DB::PARAM_BOOL : (is_null($value)? DB::PARAM_NULL : DB::PARAM_STR));
                $q->bindValue($i, $value, $type);
                $q->bindValue($i+$cnt, $value, $type);
            }
            $q->execute();
        }else{
            $q = $this->db->prepare('
                UPDATE {objects} SET `'.implode('`=?, `', $attr_names).'`=? WHERE id = ?
            ');
            $i = 0;
            foreach ($attr_names as $name){
                $value = $attr[$name];
                $i++;
                $type = is_int($value)? DB::PARAM_INT : (is_bool($value) ? DB::PARAM_BOOL : (is_null($value)? DB::PARAM_NULL : DB::PARAM_STR));
                $q->bindValue($i, $value, $type);
            }
            $q->bindValue(++$i, $attr['id']);
            $q->execute();
        }

        $this->db->commit();

        foreach ($entity->children() as $child){
            Data::write($child);
        }

        return true;
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
                $result['file'] = '';
            }
        }
        if (!$entity->is_default_logic()) $result['is_default_logic'] = true;
        if ($entity->is_draft()) $result['is_draft'] = true;
        if ($entity->is_hidden()) $result['is_hidden'] = true;
        if ($entity->is_mandatory()) $result['is_mandatory'] = true;
        if ($entity->is_property()) $result['is_property'] = true;
        if ($entity->is_relative()) $result['is_relative'] = true;
        if ($entity->is_link()) $result['is_link'] = true;

        return $result;
    }

    private function makeParents($id, $parent_id)
    {
        if ($parent_id) {
            if (!isset($this->_parents_ids[$parent_id])){
                $this->_parents_queue[$parent_id][] = $id;
            }else {
                $ids = $this->_parents_ids[$parent_id];
            }
        }else {
            $ids = [];
        }
        if (isset($ids)) {
            $ids['id'] = $id;
            $ids['id' . count($ids)] = $id;
            $this->_parents_ids[$id] = $ids;
            $names = array_keys($ids);
            $this->db->exec('INSERT INTO {parents1} (`' . implode('`, `', $names) . '`) VALUES (' . implode(',', $ids) . ')');
        }
    }

    private function makeProtos($id, $proto_id)
    {
        if ($proto_id) {
            if (!isset($this->_protos_ids[$proto_id])){
                $this->_protos_queue[$proto_id][] = $id;
            }else {
                $ids = $this->_protos_ids[$proto_id];
            }
        }else {
            $ids = [];
        }
        if (isset($ids)) {
            $ids['id'] = $id;
            $ids['id' . count($ids)] = $id;
            $this->_protos_ids[$id] = $ids;
            $names = array_keys($ids);
            $this->db->exec('INSERT INTO {protos1} (`' . implode('`, `', $names) . '`) VALUES (' . implode(',', $ids) . ')');
        }
    }

    /**
     * Создание идентификатора для указанного URI.
     * Если объект с указанным URI существует, то будет возвращен его идентификатор
     * @param string $uri URI для которого нужно получить идентификатор
     * @param bool $create Создать идентификатор, если отсутствует?
     * @param bool $is_new Возвращаемый прзнак, был ли создан новый идентификатор?
     * @return int|null
     */
    function localId($uri, $create = true, &$is_new = false)
    {
        $is_new = false;
        if (!is_string($uri)){
            return 0;
        }
        // Из кэша
        if (!isset($this->_local_ids)){
//            if ($local_ids = Cache::get('mysqlstore/localids')){
//                $this->_local_ids = json_decode($local_ids, true);
//            }else{
                $this->_local_ids = array();
//            }
        }
        if (isset($this->_local_ids[$uri])) return $this->_local_ids[$uri];
        // Поиск идентифкатора URI
        $q = $this->db->prepare('SELECT id FROM {ids} WHERE `uri`=? LIMIT 0,1 FOR UPDATE');
        $q->execute(array($uri));
        if ($row = $q->fetch(DB::FETCH_ASSOC)){
            $id = $row['id'];
            $is_new = false;
            $this->_local_ids[$uri] = $id;
            $this->_global_ids[$id] = $uri;
            // Отношение с родителями
            $q =  $this->db->prepare('SELECT * FROM {parents1} WHERE `id`=? LIMIT 0,1');
            $q->execute(array($id));
            if ($row = $q->fetch(DB::FETCH_ASSOC)){
                $this->_parents_ids[$id] = F::array_clear($row);
            }
            // Отношение с прототипами
            $q =  $this->db->prepare('SELECT * FROM {protos1} WHERE `id`=? LIMIT 0,1');
            $q->execute(array($id));
            if ($row = $q->fetch(DB::FETCH_ASSOC)){
                $this->_protos_ids[$id] = F::array_clear($row);
            }
        }else
        if ($create){
            // Создание идентификатора для URI
            $q = $this->db->prepare('INSERT INTO {ids} (`id`, `uri`) VALUES (null, ?)');
            $q->execute([$uri]);
            $id = $this->db->lastInsertId('id');
            $is_new = true;
            $obj = Data::read($uri);
            // Отношения с родителями
            $this->makeParents($id, $this->localId($obj->parent()));
            // Отношения с прототипами
            $this->makeProtos($id, $this->localId($obj->proto()));

            if (isset($this->_parents_queue[$id])){
                foreach ($this->_parents_queue[$id] as $child_id) $this->makeParents($child_id, $id);
                unset($this->_parents_queue[$id]);
            }
            if (isset($this->_protos_queue[$id])){
                foreach ($this->_protos_queue[$id] as $heir_id) $this->makeProtos($heir_id, $id);
                unset($this->_protos_queue[$id]);
            }
        }else{
            return 0;
        }
        unset($q);
        return intval($id);
    }

    /**
     * Опредление URI по локальному идентификатору
     * @param int $id Локальный идентификатор
     * @return null|string URI или null, если не найдено соответствий
     */
    function globalId($id)
    {
        if (empty($id)){
            return null;
        }
        if (isset($this->_global_ids[$id])){
            return $this->_global_ids[$id];
        }
        // Поиск URI по идентифкатору
        $q = $this->db->prepare('SELECT uri FROM {ids} WHERE `id`=? LIMIT 0,1 FOR UPDATE');
        $q->execute([$id]);
        if ($row = $q->fetch(DB::FETCH_ASSOC)){
            $uri = $row['uri'];
            $this->_local_ids[$uri] = intval($id);
            $this->_global_ids[$id] = $uri;
            return $uri;
        }
        return null;
    }

    /**
     * Создание хранилища
     * @param $connect
     * @param null $errors
     * @throws Error|null
     */
    static function createStore($connect, &$errors = null)
    {
//        try{
//            if (!$errors) $errors = new \boolive\errors\Error('Некоректные параметры доступа к СУБД', 'db');
//            // Проверка подключения и базы данных
//            $db = new DB('mysql:host='.$connect['host'].';port='.$connect['port'], $connect['user'], $connect['password'], array(DB::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES "utf8" COLLATE "utf8_bin"'), $connect['prefix']);
//            $db_auto_create = false;
//            try{
//                $db->exec('USE `'.$connect['dbname'].'`');
//            }catch (\Exception $e){
//                // Проверка исполнения команды USE
//                if ((int)$db->errorCode()){
//                    $info = $db->errorInfo();
//                    // Нет прав на указанную бд (и нет прав для создания бд)?
//                    if ($info[1] == '1044'){
//                        $errors->dbname->no_access = "No access";
//                        throw $errors;
//                    }else
//                    // Отсутсвует указанная бд?
//                    if ($info[1] == '1049'){
//                        // создаем
//                        $db->exec('CREATE DATABASE `'.$connect['dbname'].'` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci');
//                        $db_auto_create = true;
//                        $db->exec('USE `'.$connect['dbname'].'`');
//                    }
//                }
//            }
//            // Проверка поддержки типов таблиц InnoDB
//            $support = false;
//            $q = $db->query('SHOW ENGINES');
//            while (!$support && ($row = $q->fetch(\PDO::FETCH_ASSOC))){
//                if ($row['Engine'] == 'InnoDB' && in_array($row['Support'], array('YES', 'DEFAULT'))) $support = true;
//            }
//            if (!$support){
//                // Удаляем автоматически созданную БД
//                if ($db_auto_create) $db->exec('DROP DATABASE IF EXISTS `'.$connect['dbname'].'`');
//                $errors->common->no_innodb = "No InnoDB";
//                throw $errors;
//            }
//            // Есть ли таблицы в БД?
//            $pfx = $connect['prefix'];
//            $tables = array($pfx.'ids', $pfx.'objects', $pfx.'protos', $pfx.'parents');
//            $q = $db->query('SHOW TABLES');
//            while ($row = $q->fetch(DB::FETCH_NUM)/* && empty($config['prefix'])*/){
//                if (in_array($row[0], $tables)){
//                    // Иначе ошибка
//                    $errors->dbname->db_not_empty = "Database is not empty";
//                    throw $errors;
//                }
//            }
            // Создание таблиц
            $db->exec("
                CREATE TABLE {ids} (
                   `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                   `uri` varchar(1000) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY `uri` (`uri`(255))
                ) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COMMENT='Идентификация путей (URI)'
            ");
            $db->exec("
                CREATE TABLE `objects` (
                    `id` int(10) unsigned NOT NULL COMMENT 'Идентификатор по таблице ids',
                    `parent` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'Идентификатор родителя',
                    `proto` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'Идентификатор прототипа',
                    `author` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'Идентификатор автора',
                    `order` int(11) NOT NULL DEFAULT '0' COMMENT 'Порядковый номер. Уникален в рамках родителя',
                    `name` varchar(50) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL COMMENT 'Имя',
                    `value` varchar(255) NOT NULL DEFAULT '' COMMENT 'Строковое значение',
                    `file` varchar(55) NOT NULL DEFAULT '' COMMENT 'Имя файла, если есть',
                    `is_text` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Признак, есть текст в таблице текста',
                    `is_draft` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Черновик или нет?',
                    `is_hidden` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Скрыт или нет?',
                    `is_link` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Используетя как ссылка или нет?',
                    `is_mandatory` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Обязательный (1) или нет (0)? ',
                    `is_property` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Свойство (1) или нет (0)? ',
                    `is_relative` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Относительный (1) или нет (0) прототип?',
                    `is_default_value` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Используется значение прототипа или своё',
                    `is_default_file` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Используется файл прототипа или свой?',
                    `is_default_logic` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Используется класс прототипа или свой?',
                    `created` int(11) unsigned NOT NULL DEFAULT '0' COMMENT 'Дата создания',
                    `updated` int(11) unsigned NOT NULL DEFAULT '0' COMMENT 'Дата изменения',
                    PRIMARY KEY (`id`),
                    KEY `child` (`parent`,`order`,`name`,`value`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Объекты'
            ");
            $db->exec("
                CREATE TABLE `text` (
                    `id` INT(10) UNSIGNED NOT NULL COMMENT 'Идентификатор объекта',
                    `value` TEXT NOT NULL COMMENT 'Текстовое значение',
                    PRIMARY KEY (`id`)
                ) ENGINE=MYISAM DEFAULT CHARSET=utf8 COMMENT='Текстовые значения объектов'
            ");
            $db->exec("
                CREATE TABLE `parents1` (
                  `id` INT(10) UNSIGNED NOT NULL,
                  `id1` INT(10) UNSIGNED NOT NULL,
                  `id2` INT(10) UNSIGNED NOT NULL,
                  `id3` INT(10) UNSIGNED NOT NULL,
                  `id4` INT(10) UNSIGNED NOT NULL,
                  `id5` INT(10) UNSIGNED NOT NULL,
                  `id6` INT(10) UNSIGNED NOT NULL,
                  `id7` INT(10) UNSIGNED NOT NULL,
                  `id8` INT(10) UNSIGNED NOT NULL,
                  `id9` INT(10) UNSIGNED NOT NULL,
                  `id10` INT(10) UNSIGNED NOT NULL,
                  PRIMARY KEY (`id`)
                ) ENGINE=INNODB DEFAULT CHARSET=utf8
            ");
            $db->exec("
                CREATE TABLE `protos1` (
                  `id` INT(10) UNSIGNED NOT NULL,
                  `id1` INT(10) UNSIGNED NOT NULL,
                  `id2` INT(10) UNSIGNED NOT NULL,
                  `id3` INT(10) UNSIGNED NOT NULL,
                  `id4` INT(10) UNSIGNED NOT NULL,
                  `id5` INT(10) UNSIGNED NOT NULL,
                  `id6` INT(10) UNSIGNED NOT NULL,
                  `id7` INT(10) UNSIGNED NOT NULL,
                  `id8` INT(10) UNSIGNED NOT NULL,
                  `id9` INT(10) UNSIGNED NOT NULL,
                  `id10` INT(10) UNSIGNED NOT NULL,
                  PRIMARY KEY (`id`)
                ) ENGINE=INNODB DEFAULT CHARSET=utf8
            ");
//        }catch (\PDOException $e){
//			// Ошибки подключения к СУБД
//			if ($e->getCode() == '1045'){
//				$errors->user->no_access = "No accecss";
//				$errors->password->no_access = "No accecss";
//			}else
//			if ($e->getCode() == '2002'){
//				$errors->host->not_found = "Host not found";
//                if ($connect['port']!=3306){
//                    $errors->port->not_found = "Port no found";
//                }
//			}else{
//				$errors->common = $e->getMessage();
//			}
//			if ($errors->is_exist()) throw $errors;
//		}
    }
}