<?php
/**
 * MySQLStrore
 * @aurhor Vladimir Shestakov
 * @version 1.0
 */
namespace boolive\core\data\stores;

use boolive\core\data\Buffer;
use boolive\core\data\Data;
use boolive\core\data\Entity;
use boolive\core\data\IStore;
use boolive\core\database\DB;
use boolive\core\errors\Error;
use boolive\core\events\Events;

class MySQLStore implements IStore
{
    /** @var DB */
    public $db;
    /** @var string Ключ хранилища, по которому хранилище выбирается для объектов и создаются короткие URI */
    private $key;

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

    function read($uri)
    {
        if (!($entity = Buffer::get_entity($uri))){
            // Экземпляра объекта в буфере нет, проверяем массив его атрибутов в буфере
            $info = Buffer::get_info($uri);
            if (empty($info)){
                try {
                    $info = [
                        'uri' => $uri
                    ];
                    $info['is_exists'] = true;
                    // Инфо о свойствах в буфер
                    $info = Buffer::set_info($info);

                } catch (\Exception $e) {
                    return false;
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
            $this->_local_ids_change = true;
        }else
        if ($create){
            // Создание идентификатора для URI
            $q = $this->db->prepare('INSERT INTO {ids} (`id`, `uri`) VALUES (null, ?)');
            $q->execute(array($uri));
            $id = $this->db->lastInsertId('id');
            $is_new = true;
        }else{
            return 0;
        }
        unset($q);
        return intval($id);
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