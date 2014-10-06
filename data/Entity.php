<?php
/**
 * Entity
 * @aurhor Vladimir Shestakov
 * @version 1.0
 */
namespace boolive\core\data;

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
        'is_default_class' => true,
        //'is_completed' => false,
        'is_accessible'=> true,
        'is_exists'    => false,
    );
    /** @var array of Entity Свойства объекта. */
    protected $_properties = array();
    /** @var array of Entity Подчиненные объекты */
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
    /** @var Entity|bool|null Экземпляр объекта, на который ссылается или false, если нет признака ссылки */
    protected $_link;
    /**
     * Признак, требуется ли подобрать уникальное имя перед сохранением или нет?
     * Также означает, что текущее имя (uri) объекта временное
     * Если строка, то определяет базовое имя, к кторому будут подбираться числа для уникальности
     * @var bool|string
     */
    protected $_auto_naming = false;
    /** @var string Тенкущение имя перед сохранением, если изменялось */
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
                $this->_properties[$name] = new Entity($prop);
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
            'date_create'  => Rule::int(), // Дата создания в секундах
            'date_update'  => Rule::int(), // Дата обновления в секундах
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
            'is_default_class' => Rule::bool(), // Признак, используется класс прототипа или свой?
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
            'class' => Rule::arrays(array(
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
            if ($choose_unique) $this->_automnaming = $new;
            $this->_attributes['name'] = $new;
        }
        return $this->_attributes['name'];
    }

    /**
     * Parent of this object
     * @param null|string|Entity $new New parent
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
                $this->_attribs['order'] = Entity::MAX_ORDER;
                $this->_changes['parent'] = true;
                $this->_changes['order'] = true;
                if ($this->isExists()) $this->name(null, true);
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
     * @param null|string|Entity $new Новый прототип
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
     * @param null $new
     * @param bool $return_entity
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




    function order($new)
    {
        if (isset($new) && ($this->_attributes['is_link'] != $new)){
            $this->_attributes['is_link'] = (bool)$new;
            $this->_changes['is_link'] = true;
        }
        return $this->_attributes['is_link'];
    }
    function created($new)
    {
        if (isset($new) && ($this->_attributes['is_link'] != $new)){
            $this->_attributes['is_link'] = (bool)$new;
            $this->_changes['is_link'] = true;
        }
        return $this->_attributes['is_link'];
    }
    function updated($new)
    {
        if (isset($new) && ($this->_attributes['is_link'] != $new)){
            $this->_attributes['is_link'] = (bool)$new;
            $this->_changes['is_link'] = true;
        }
        return $this->_attributes['is_link'];
    }
    function value($new)
    {
        if (isset($new) && ($this->_attributes['is_link'] != $new)){
            $this->_attributes['is_link'] = (bool)$new;
            $this->_changes['is_link'] = true;
        }
        return $this->_attributes['is_link'];
    }
    function is_file($new)
    {
        if (isset($new) && ($this->_attributes['is_link'] != $new)){
            $this->_attributes['is_link'] = (bool)$new;
            $this->_changes['is_link'] = true;
        }
        return $this->_attributes['is_link'];
    }
    function is_draft($new)
    {
        if (isset($new) && ($this->_attributes['is_link'] != $new)){
            $this->_attributes['is_link'] = (bool)$new;
            $this->_changes['is_link'] = true;
        }
        return $this->_attributes['is_link'];
    }
    function is_hidden($new)
    {
        if (isset($new) && ($this->_attributes['is_link'] != $new)){
            $this->_attributes['is_link'] = (bool)$new;
            $this->_changes['is_link'] = true;
        }
        return $this->_attributes['is_link'];
    }
    function is_mandatory($new)
    {
        if (isset($new) && ($this->_attributes['is_link'] != $new)){
            $this->_attributes['is_link'] = (bool)$new;
            $this->_changes['is_link'] = true;
        }
        return $this->_attributes['is_link'];
    }
    function is_property($new)
    {
        if (isset($new) && ($this->_attributes['is_link'] != $new)){
            $this->_attributes['is_link'] = (bool)$new;
            $this->_changes['is_link'] = true;
        }
        return $this->_attributes['is_link'];
    }
    function is_relative($new)
    {
        if (isset($new) && ($this->_attributes['is_link'] != $new)){
            $this->_attributes['is_link'] = (bool)$new;
            $this->_changes['is_link'] = true;
        }
        return $this->_attributes['is_link'];
    }
    /**
     * Object referenced by this object
     * @param null $new
     * @return mixed
     */
    function is_link($new)
    {
        if (isset($new) && ($this->_attributes['is_link'] != $new)){
            $this->_attributes['is_link'] = (bool)$new;
            $this->_changes['is_link'] = true;
        }
        return $this->_attributes['is_link'];
    }
    function is_default_value($new)
    {
        if (isset($new) && ($this->_attributes['is_link'] != $new)){
            $this->_attributes['is_link'] = (bool)$new;
            $this->_changes['is_link'] = true;
        }
        return $this->_attributes['is_link'];
    }
    function is_default_class($new)
    {
        if (isset($new) && ($this->_attributes['is_link'] != $new)){
            $this->_attributes['is_link'] = (bool)$new;
            $this->_changes['is_link'] = true;
        }
        return $this->_attributes['is_link'];
    }
    function is_accessible()
    {
        return $this->_attributes['is_link'];
    }
    function is_exists()
    {
        return $this->_attributes['is_link'];
    }

    ############################################
    #                                          #
    #             Properties                   #
    #                                          #
    ############################################

    public function __get($name)
    {

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
}