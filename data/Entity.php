<?php
/**
 * Entity
 * @aurhor Vladimir Shestakov
 * @version 1.0
 */
namespace boolive\core\data;

use boolive\core\file\File;
use boolive\core\functions\F;
use boolive\core\values\Rule;
use ArrayAccess;

class Entity implements ArrayAccess
{
    /** @const int Максимальное порядковое значение */
    const MAX_ORDER = 4294967295;
    /** @const int Идентификатор сущности - эталона всех объектов */
//    const ENTITY_ID = 4294967295;
    /** @const int Максимальная глубина для поиска */
    const MAX_DEPTH = 4294967295;

    /** @var array Атрибуты */
    public $_attributes = array(
        'uri'          => null,
        'name'         => null,
        'parent'       => null,
        'proto'        => null,
        'author'	   => null,
        'order'		   => 0,
        'created'      => 0,
        'updated'      => 0,
        'value'	 	   => '',
        'is_file'      => false,
        'is_draft'	   => false,
        'is_hidden'	   => false,
        'is_mandatory' => false,
        'is_property'  => false,
        'is_relative'  => false,
        'is_link'      => false,
        'is_default_value' => false,
        'is_default_logic' => true,
        //'is_completed' => false,
        'is_accessible'=> true,
        'is_exists'    => false,
    );
    /** @var array of Entity Свойства объекта. */
    protected $_properties = array();
    /** @var array of Entity Подчиненные объекты не являющиеся свойствами */
    protected $_children = array();
    /** @var array Названия измененных атрибутов */
    protected $_changes = array();
    /** @var bool Признак, проверен ли объект */
    protected $_checked = false;
    /** @var Entity|bool|null Экземпляр родителя или false, если родителя нет */
    protected $_parent;
    /** @var Entity|bool|null Экземпляр прототипа или false, если прототипа нет. */
    protected $_proto;
    /** @var Entity|bool|null Экземпляр автора объекта или false, если нет автора */
    protected $_author;
    /** @var Entity|bool|null Экземпляр объекта-прототипа, на который ссылается или false, если нет признака ссылки */
    protected $_link;
    /** @var Entity|bool|null Экземпляр объекта-прототипа, от которого наследуется значение или false, если значение своё */
    protected $_def_value;
    /** @var Entity|bool|null Экземпляр объекта-прототипа, чей класс используется или false, если нет класс свой */
    protected $_def_logic;
    /**
     * Признак, требуется ли подобрать уникальное имя перед сохранением или нет?
     * Если строка, то определяет базовое имя, к кторому будут подбираться числа для уникальности
     * @var bool|string
     */
    protected $_auto_naming = false;
    /** @var string Текущее имя перед сохранением, если изменялось */
    protected $_current_name;

    /**
     * Конструктор
     * @param array $info Атрибуты объекта и свойства
     */
    function __construct($info = array())
    {
        if ((!isset($info['parent']) || !isset($info['name'])) && isset($info['uri'])){
            $names = F::splitRight('/', $info['uri'], true);
            $info['name'] = $names[1];
            if (!isset($info['parent'])){
                $info['parent'] = $names[0];
            }
        }
        if (isset($info['properties'])){
            foreach ($info['properties'] as $name => $prop){
                $prop['name'] = $name;
                $prop['is_property'] = true;
                if (!isset($prop['created']) && isset($info['created'])) $prop['created'] = $info['created'];
                if (!isset($prop['updated']) && isset($info['updated'])) $prop['updated'] = $info['updated'];
                $prop['is_exists'] = $info['is_exists'];
                if (isset($info['uri'])){
                    $prop['uri'] = $info['uri'].'/'.$name;
                }else
                if (isset($this->_attributes['uri'])){
                    $prop['uri'] = $this->_attributes['uri'].'/'.$name;
                }
                // Если у свойства нет прототипа, то определение прототипа через прототипы родителей
                if (!isset($prop['proto']) && isset($info['proto'])){
                    $p = Data::read($info['proto']);
                    do{
                        $property = $p->{$name};
                    }while (!$property && ($p = $p->proto()));
                    if ($property){
                        $prop['proto'] = $property->uri();
                    }
                }
                $this->_properties[$name] = Data::entity($prop);
                $this->_properties[$name]->_parent = $this;
            }
            unset($info['properties']);
        }
        $this->_attributes = array_replace($this->_attributes, $info);
    }

    function __destruct(){}

    ############################################
    #                                          #
    #             Attributes                   #
    #                                          #
    ############################################

    /**
     * Правило на атрибуты
     * @return Rule
     */
    protected function rule()
    {
        return Rule::arrays(array(
            'name'         => Rule::string()->regexp('|^[^/@:#\\\\]*$|')->min(IS_INSTALL?1:0)->max(100)->required(), // Имя объекта без символов /@:#\
            'parent'       => Rule::any(Rule::uri(), Rule::null()), // URI родителя
            'proto'        => Rule::any(Rule::uri(), Rule::null()), // URI прототипа
            'author'	   => Rule::any(Rule::uri(), Rule::null()), // Автор (идентификатор объекта-пользователя)
            'order'		   => Rule::int()->max(Entity::MAX_ORDER), // Порядковый номер. Уникален в рамках родителя
            'created'      => Rule::int(), // Дата создания в секундах
            'updated'      => Rule::int(), // Дата обновления в секундах
            'value'	 	   => Rule::string()->max(65535), // Значение до 65535 сиволов
            'is_file'	   => Rule::bool(), // Признак, привязан ли файл?
            'is_draft'	   => Rule::bool(), // Признак, в черновике или нет?
            'is_hidden'	   => Rule::bool(), // Признак, скрытый или нет?
            'is_mandatory' => Rule::bool(), // Признак, обязательный или дополненый?
            'is_property'  => Rule::bool(), // Признак, свойство или самостоятельный объект?
            'is_relative'  => Rule::bool(), // Прототип относительный или нет?
//            'is_completed' => Rule::bool(), // Признак, дополнен объект свойствами прототипа или нет?
            'is_link'      => Rule::bool(), // Ссылка или нет?
            'is_default_value' => Rule::bool(), // Признак, используется значение прототипа или своё?
            'is_default_logic' => Rule::bool(), // Признак, используется класс прототипа или свой?
            // Сведения о загружаемом файле. Не является атрибутом объекта, но используется при сохранении
            'file'	=> Rule::arrays(array(
                'tmp_name'	=> Rule::string(), // Путь на связываемый файл
                'name'		=> Rule::lowercase()->ospatterns('*.*')->ignore('lowercase')->required(), // Имя файла, из которого будет взято расширение
                'size'		=> Rule::int(), // Размер в байтах
                'error'		=> Rule::int()->eq(0, true), // Код ошибки. Если 0, то ошибки нет
                'type'      => Rule::string(), // MIME тип файла
                'content'   => Rule::string()
            )),
            // Сведения о классе объекта (загружаемый файл или программный код). Не является атрибутом объекта
            'logic' => Rule::arrays(array(
                'content'   => Rule::string(), // Программный код класса
                'tmp_name'	=> Rule::string(), // Путь на файл, если класс загржается в виде файла
                'size'		=> Rule::int(), // Размер в байтах
                'error'		=> Rule::int()->eq(0, true), // Код ошибки. Если 0, то ошибки нет
                'type'      => Rule::string() // MIME тип файла
            ))
        ));
    }

    /**
     * Все атрибуты объекта
     * @return array
     */
    function attributes()
    {
        return $this->_attributes;
    }

    /**
     * Атрибут объекта по имени
     * Необходимо учитывать, что некоторые атрибуты могут быть ещё не инициалироваными
     * @param string $name Назавние возвращаемого атрибута
     * @return mixed Значение атрибута
     */
    function attr($name)
    {
        return $this->_attributes[$name];
    }

    /**
     * URI объекта
     * @return string
     */
    function uri()
    {
        return $this->_attributes['uri'];
    }

    /**
     * Имя объекта
     * @param null|string $new Новое имя
     * @param bool $choose_unique Признак, подобрать ли уникальное имя. Если $new не указан, то будет подбираться постфик к текущему имени
     * @return string
     */
    function name($new = null, $choose_unique = false)
    {
        if (!isset($new) && $choose_unique) $new = $this->_attributes['name'];
        if (isset($new) && ($this->_attributes['name'] != $new || $choose_unique)){
            // Фильтр имени
            $new = preg_replace('/\s/ui','_',$new);
            // Запоминаем текущее имя
            if (!isset($this->_current_name)) $this->_current_name = $this->_attributes['name'];
            if ($choose_unique) $this->_auto_naming = $new;
            $this->_attributes['name'] = $new;
        }
        return $this->_attributes['name'];
    }

    /**
     * Parent of this object
     * @param null|string|Entity $new New parent. URI or object
     * @param bool $return_entity Признак, возвращать объект вместо uri
     * @return string|Entity|false
     */
    function parent($new = null, $return_entity = false)
    {
        if (isset($new)){
            if ($new instanceof Entity){
                $this->_parent = $new;
                $new = $new->uri();
            }else{
                $this->_parent = null; // for reloading
            }
            if ($new != $this->_attributes['parent']){
                $this->_attributes['parent'] = $new;
                $this->_attributes['order'] = Entity::MAX_ORDER;
                $this->_changes['parent'] = true;
                $this->_changes['order'] = true;
                if ($this->is_exists()) $this->name(null, true);
            }
        }
        if ($return_entity){
            if (!isset($this->_parent)){
                if (isset($this->_attributes['parent'])){
                    $this->_parent = Data::read($this->_attributes['parent']);
                }else{
                    $this->_parent = false;
                }
            }
            return $this->_parent;
        }
        return $this->_attributes['parent'];
    }

    /**
     * Prototype of this object
     * @param null|string|Entity $new Новый прототип. URI или объект
     * @param bool $return_entity Признак, возвращать объект вместо uri
     * @return string|Entity|false
     */
    function proto($new = null, $return_entity = false)
    {
        if (isset($new)){
            if ($new instanceof Entity){
                $this->_proto = $new;
                $new = $new->uri();
            }else{
                $this->_proto = null; // for reloading
            }
            if ($new != $this->_attributes['proto']){
                $this->_attributes['proto'] = $new;
                $this->_changes['proto'] = true;
            }
        }
        if ($return_entity){
            if (!isset($this->_proto)){
                if (isset($this->_attributes['proto'])){
                    $this->_proto = Data::read($this->_attributes['proto']);
                }else{
                    $this->_proto = false;
                }
            }
            return $this->_proto;
        }
        return $this->_attributes['proto'];
    }

    /**
     * Author of this object
     * @param null|string|Entity $new Новый автор. URI или объект
     * @param bool $return_entity Признак, возвращать объект вместо uri
     * @return mixed
     */
    function author($new = null, $return_entity = false)
    {
        if (isset($new)){
            if ($new instanceof Entity){
                $this->_author = $new;
                $new = $new->uri();
            }else{
                $this->_author = null; // for reloading
            }
            if ($new != $this->_attributes['author']){
                $this->_attributes['author'] = $new;
                $this->_changes['author'] = true;
            }
        }
        if ($return_entity){
            if (!isset($this->_author)){
                if (isset($this->_attributes['author'])){
                    $this->_author = Data::read($this->_attributes['author']);
                }else{
                    $this->_author = false;
                }
            }
            return $this->_author;
        }
        return $this->_attributes['author'];
    }

    /**
     * Порядковый номер
     * @param int $new Новое порядковое значение
     * @return int
     */
    function order($new = null)
    {
        if (isset($new) && ($this->_attributes['order'] != $new)){
            $this->_attributes['order'] = (int)$new;
            $this->_changes['order'] = true;
        }
        return $this->_attributes['order'];
    }

    /**
     * Дата создания в TIMESTAMP
     * @param int $new Новая дата создания
     * @return int
     */
    function created($new = null)
    {
        if (isset($new) && ($this->_attributes['created'] != $new)){
            $this->_attributes['created'] = (int)$new;
            $this->_changes['created'] = true;
        }
        return $this->_attributes['created'];
    }

    /**
     * Дата последнего изменения в TIMESTAMP
     * @param int $new Новая дата изменения
     * @return int
     */
    function updated($new = null)
    {
        if (isset($new) && ($this->_attributes['updated'] != $new)){
            $this->_attributes['updated'] = (int)$new;
            $this->_changes['updated'] = true;
        }
        return $this->_attributes['updated'];
    }

    /**
     * Значение
     * @param mixed $new Новое значение. Если привязан файл, то имя файла без пути
     * @return mixed
     */
    function value($new = null)
    {
        if (isset($new) && ($this->_attributes['value'] != $new)){
            $this->_attributes['value'] = (bool)$new;
            $this->_changes['value'] = true;
        }
        if (($proto = $this->is_default_value(null, true)) && $proto->is_exists()){
           return $proto->value();
        }
        return $this->_attributes['value'];
    }

    /**
     * Файл, ассоциированный с объектом
     * @param null|array|string $new Информация о новом файле. Полный путь к новому файлу или сведения из $_FILES
     * @param bool $root Возвращать полный путь или от директории сайта
     * @return null|string
     */
    function file($new = null, $root = false)
    {
        // Установка нового файла
        if (isset($new)){
            if (empty($new)){
                unset($this->_attributes['file']);
                $this->_attributes['is_file'] = false;
            }else{
                if (is_string($new)){
                    $new = array(
                        'tmp_name'	=> $new,
                        'name' => basename($new),
                        'size' => @filesize($new),
                        'error'	=> is_file($new)? 0 : true
                    );
                }
                if (empty($new['name']) && $this->is_file()){
                    $new['name'] = $this->name().'.'.File::fileExtention($this->file());
                }
                $this->_attributes['file'] = $new;
                $this->_attributes['is_file'] = true;
            }
            $this->_attributes['is_default_value'] = false;
            $this->_changes['value'] = true;
            $this->_changes['is_file'] = true;
            $this->_changes['file'] = true;
            $this->_changes['is_default_value'] = true;
        }
        // Возврат пути к текущему файлу, если есть
        if ($this->_attributes['is_file']){
            if (($proto = $this->is_default_value(null, true)) && $proto->is_exists()){
                $file = $proto->file(null, $root);
                return $file;
            }else{
                $file = $this->dir($root);
                return $file.$this->_attributes['value'];
            }
        }
        return null;
    }

    /**
     * Путь на файл используемого класса (логики)
     * @param null $new Установка своего класса. Сведения о загружаемом файле или его программный код
     * @param bool $root Возвращать полный путь или от директории сайта?
     * @return string
     */
    function logic($new = null, $root = false)
    {
        if (isset($new)){
            if (is_string($new)){
                $new = array(
                    'tmp_name'	=> $new,
                    'size' => @filesize($new),
                    'error'	=> is_file($new)? 0 : true
                );
            }
            $this->_attributes['logic'] = $new;
            $this->_attributes['is_default_logic'] = false;
            $this->_changes['is_default_logic'] = true;
            $this->_changes['logic'] = true;
        }
        if ($proto = $this->is_default_logic(null, true)){
            $path = $proto->logic(null, $root);
        }else
        if ($this->_attributes['is_default_logic']) {
            $path = ($root ? DIR : '/').'vendor/boolive/core/data/Entity.php';
        }else{
            $path = $this->dir($root).$this->name().'.php';
        }
        return $path;
    }

    /**
     * Директория объекта
     * @param bool $root Признак, возвращать путь от корня сервера или от web директории (www)
     * @return string
     */
    function dir($root = false)
    {
        $dir = $this->uri();
        if ($root){
            return DIR.$dir.'/';
        }else{
            return $dir.'/';
        }
    }

    /**
     * Признак, привязан ли файл
     * @param bool $new Новое значение признака
     * @return bool
     */
    function is_file($new = null)
    {
        if (isset($new) && ($this->_attributes['is_file'] != $new)){
            $this->_attributes['is_file'] = (bool)$new;
            $this->_changes['is_file'] = true;
        }
        if (($proto = $this->is_default_value(null, true)) && $proto->is_exists()){
           return $proto->is_file();
        }
        return $this->_attributes['is_file'];
    }

    /**
     * Признак, в черновике ли оъект?
     * @param bool $new Новое значение признака
     * @return bool
     */
    function is_draft($new = null)
    {
        if (isset($new) && ($this->_attributes['is_draft'] != $new)){
            $this->_attributes['is_draft'] = (bool)$new;
            $this->_changes['is_draft'] = true;
        }
        return $this->_attributes['is_draft'];
    }

    /**
     * Признак, скрытый ли оъект?
     * @param bool $new Новое значение признака
     * @return bool
     */
    function is_hidden($new = null)
    {
        if (isset($new) && ($this->_attributes['is_hidden'] != $new)){
            $this->_attributes['is_hidden'] = (bool)$new;
            $this->_changes['is_hidden'] = true;
        }
        return $this->_attributes['is_hidden'];
    }

    /**
     * Признак, обязательный ли оъект для наследования?
     * @param bool $new Новое значение признака
     * @return bool
     */
    function is_mandatory($new = null)
    {
        if (isset($new) && ($this->_attributes['is_mandatory'] != $new)){
            $this->_attributes['is_mandatory'] = (bool)$new;
            $this->_changes['is_mandatory'] = true;
        }
        return $this->_attributes['is_mandatory'];
    }

    /**
     * Признак, является ли оъект свойством?
     * @param bool $new Новое значение признака
     * @return bool
     */
    function is_property($new = null)
    {
        if (isset($new) && ($this->_attributes['is_property'] != $new)){
            $this->_attributes['is_property'] = (bool)$new;
            $this->_changes['is_property'] = true;
        }
        return $this->_attributes['is_property'];
    }

    /**
     * Признак, является ли прототип относительным?
     * Используется при прототипировании родительского объекта
     * @param bool $new Новое значение признака
     * @return bool
     */
    function is_relative($new = null)
    {
        if (isset($new) && ($this->_attributes['is_relative'] != $new)){
            $this->_attributes['is_relative'] = (bool)$new;
            $this->_changes['is_relative'] = true;
        }
        return $this->_attributes['is_relative'];
    }

    /**
     * Object referenced by this object
     * @param null|bool $new Новое значение признака
     * @param bool $return_entity Признак, возвращать объект вместо uri
     * @return bool||Entity
     */
    function is_link($new = null, $return_entity = false)
    {
        if (isset($new) && ($this->_attributes['is_link'] != $new)){
            $this->_attributes['is_link'] = (bool)$new;
            $this->_changes['is_link'] = true;
        }
        if ($return_entity){
            if (!isset($this->_link)){
                if (empty($this->_attributes['is_link'])){
                    $this->_link = false;
                }else
                if (($this->_link = $this->proto(null, true))){
                    if ($p = $this->_link->is_link(null, true)) $this->_link = $p;
                }
            }
            return $this->_link;
        }
        return $this->_attributes['is_link'];
    }

    /**
     * Признак, значение наследуется от прототипа?
     * @param bool $new Новое значение признака
     * @param bool $return_entity Признак, возвращать объект вместо uri
     * @return bool|Entity
     */
    function is_default_value($new = null, $return_entity = false)
    {
        if (isset($new) && ($this->_attributes['is_default_value'] != $new)){
            $this->_attributes['is_default_value'] = (bool)$new;
            $this->_changes['is_default_value'] = true;
            if ($this->_attributes['is_default_value']){
                $this->_attributes['value'] = null;
                $this->_attributes['is_file'] = null;
            }
        }
        if ($return_entity){
            if (!isset($this->_def_value)){
                if (empty($this->_attributes['is_default_value'])){
                    $this->_def_value = false;
                }else
                if (($this->_def_value = $this->proto(null, true))){
                    if ($p = $this->_def_value->is_default_value(null, true)) $this->_def_value = $p;
                }
            }
            return $this->_def_value;
        }
        return $this->_attributes['is_default_value'];
    }

    /**
     * Признак, класс (которым определяется логика объекта) наследуется от прототипа?
     * @param bool $new Новое значение признака
     * @param bool $return_entity Признак, возвращать объект вместо uri
     * @return bool|Entity
     */
    function is_default_logic($new = null, $return_entity = false)
    {
        if (isset($new) && ($this->_attributes['is_default_logic'] != $new)){
            $this->_attributes['is_default_logic'] = (bool)$new;
            $this->_changes['is_default_logic'] = true;
        }
        if ($return_entity){
            if (!isset($this->_def_logic)){
                if (empty($this->_attributes['is_default_logic'])){
                    $this->_def_logic = false;
                }else
                if (($this->_def_logic = $this->proto(null, true))){
                    if ($p = $this->_def_logic->is_default_logic(null, true)) $this->_def_logic = $p;
                }
            }
            return $this->_def_logic;
        }
        return $this->_attributes['is_default_logic'];
    }

    /**
     * Признак, есть ли доступ на оъект?
     * @return bool
     */
    function is_accessible()
    {
        return $this->_attributes['is_accessible'];
    }

    /**
     * Признак, сущесвтует ли оъект?
     * @return bool
     */
    function is_exists()
    {
        return $this->_attributes['is_exists'];
    }

    ############################################
    #                                          #
    #             Properties                   #
    #                                          #
    ############################################

    public function __get($name)
    {
        if (isset($this->_properties[$name])){
            return $this->_properties[$name];
        }else{
            return false;
        }
    }

    public function __set($name, $prop)
    {

    }

    public function __isset($name)
    {

    }

    public function __unset($name)
    {

    }

    public function properties($cond = array())
    {

    }

    ############################################
    #                                          #
    #               Children                   #
    #                                          #
    ############################################

    /**
     * @param mixed $offset
     * @return boolean true on success or false on failure.
     */
    public function offsetExists($offset)
    {
        // TODO: Implement offsetExists() method.
    }

    /**
     * @param mixed $offset
     * @return mixed Can return all value types.
     */
    public function offsetGet($offset)
    {
        // TODO: Implement offsetGet() method.
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        // TODO: Implement offsetSet() method.
    }

    /**
     * @param mixed $offset
     * @return void
     */
    public function offsetUnset($offset)
    {
        // TODO: Implement offsetUnset() method.
    }

    ############################################
    #                                          #
    #                 Entity                   #
    #                                          #
    ############################################

    /**
     * Признак, изменены атрибуты объекта или нет
     * @return bool
     */
    function is_changed()
    {
        return empty($this->_changes);
    }

    /**
     * Вызов несуществующего метода
     * Если объект внешний, то вызов произведет модуль секции объекта
     * @param string $method
     * @param array $args
     * @return null|void
     */
    function __call($method, $args)
    {
        return false;
    }

    /**
     * При обращении к объекту как к скалярному значению (строке), возвращается значение атрибута value
     * @example
     * print $object;
     * $value = (string)$obgect;
     * @return mixed
     */
    function __toString()
    {
        return (string)$this->value();
    }

    /**
     * Клонирование объекта
     */
    function __clone()
    {
        foreach ($this->_properties as $name => $child){
            $this->_properties[$name] = clone $child;
        }
        foreach ($this->_children as $name => $child){
            $this->_children[$name] = clone $child;
        }
    }

    /**
     * Информация для var_dump() и trace()
     * @return mixed
     */
    public function __debugInfo()
    {
        $info['_attributes'] = $this->_attributes;
        $info['_properties'] = $this->_properties;
        $info['_changes'] = $this->_changes;
        $info['_checked'] = $this->_checked;
//        $info['_proto'] = $this->_proto;
//        $info['_parent'] = $this->_parent;
        $info['_children'] = $this->_children;

//        if ($this->_errors) $info['_errors'] = $this->_errors->toArrayCompact(false);
        return $info;
    }
}