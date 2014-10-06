<?php
/**
 * Entity
 * @aurhor Vladimir Shestakov
 * @version 1.0
 */
namespace boolive\core\data;

use boolive\core\functions\F;
use boolive\core\values\Rule;

class Entity
{
    /** @const int Максимальное порядковое значение */
    const MAX_ORDER = 4294967295;
    /** @const int Идентификатор сущности - эталона всех объектов */
    const ENTITY_ID = 4294967295;
    /** @const int Максимальная глубина для поиска */
    const MAX_DEPTH = 4294967295;

    /** @const int Автоматический выбор типа значения */
    const VALUE_AUTO = 0;
    /** @const int Простой тип. Строка до 255 символов */
    const VALUE_SIMPLE = 1;
    /** @const int Текстовый тип длиной до 64Кб с возможностью полнотекстового поиска */
    const VALUE_TEXT = 2;
    /** @const int Объект связан с файлом. Значением объекта является имя файла. */
    const VALUE_FILE = 3;

    /** @var array Атрибуты */
    public $_attribs = array(
        'uri'          => null,
        'name'         => null,
        'order'		   => 0,
        'date_create'  => 0,
        'date_update'  => 0,
        'parent'       => null,
        'parent_cnt'   => null,
        'proto'        => null,
        'proto_cnt'    => null,
        'value'	 	   => '',
        'value_type'   => Entity::VALUE_AUTO,
        'author'	   => null,
        'is_draft'	   => false,
        'is_hidden'	   => false,
        'is_mandatory' => false,
        'is_property'  => false,
        'is_relative'  => false,
        'is_link'      => false,
        'is_default_value' => false,
        'is_default_class' => true,
        'is_completed' => false,
        'is_accessible'=> true,
        'is_exist'     => false,
    );

    /**
     * Конструктор
     * @param array $attribs Атрибуты объекта, а также атрибуты подчиенных объектов
     * @param int $tree_depth До какой глубины (вложенности) создавать экземпляры подчиненных объектов
     */
    function __construct($attribs = array())
    {
        if (!empty($attribs['id'])){
            $attribs['is_exist'] = true;
        }
        if (!isset($attribs['parent']) && !isset($attribs['name']) && isset($attribs['uri'])){
            $names = F::splitRight('/', $attribs['uri'], true);
            $attribs['name'] = $names[1];
            if (!isset($attribs['parent'])){
                $attribs['parent'] = $names[0];
            }
        }
        if (isset($attribs['children'])){
//            foreach ($attribs['children'] as $name => $child){
//                $child['name'] = $name;
//                if (isset($attribs['uri'])){
//                    $child['uri'] = $attribs['uri'].'/'.$name;
//                }else
//                if (isset($this->_attribs['uri'])){
//                    $child['uri'] = $this->_attribs['uri'].'/'.$name;
//                }
//                $this->_children[$name] = Data::entity($child);
//                $this->_children[$name]->_parent = $this;
//            }
            unset($attribs['children']);
        }
        $this->_attribs = array_replace($this->_attribs, $attribs);
    }

    function __destruct(){}

    /**
     * Правило на атрибуты
     * @return Rule
     */
    protected function rule()
    {
        return Rule::arrays(array(
            'id'           => Rule::any(Rule::int(), Rule::null()), // Сокращенный или полный URI
            'name'         => Rule::string()->regexp('|^[^/@:#\\\\]*$|')->min(IS_INSTALL?1:0)->max(100)->required(), // Имя объекта без символов /@:#\
            'order'		   => Rule::int()->max(Entity::MAX_ORDER), // Порядковый номер. Уникален в рамках родителя
            'date_create'  => Rule::int(), // Дата создания в секундах
            'date_update'  => Rule::int(), // Дата обновления в секундах
            'parent'       => Rule::any(Rule::uri(), Rule::null()), // URI родителя
            'proto'        => Rule::any(Rule::uri(), Rule::null()), // URI прототипа
            'value'	 	   => Rule::string()->max(65535), // Значение до 65535 сиволов
            'value_type'   => Rule::int()->min(0)->max(4), // Код типа значения. Определяет способ хранения (0=авто, 1=простое, 2=текст, 3=файл)
            'author'	   => Rule::any(Rule::uri(), Rule::null()), // Автор (идентификатор объекта-пользователя)
            'is_draft'	   => Rule::bool(), // Признак, в черновике или нет?
            'is_hidden'	   => Rule::bool(), // Признак, скрытый или нет?
            'is_mandatory' => Rule::bool(), // Признак, обязательный или дополненый?
            'is_property'  => Rule::bool(), // Признак, свойство или самостоятельный объект?
            'is_relative'  => Rule::bool(), // Прототип относительный или нет?
            'is_completed' => Rule::bool(), // Признак, дополнен объект свойствами прототипа или нет?
            'is_link'      => Rule::bool(), // Ссылка или нет?
            'is_default_value' => Rule::bool(), // Используется значение прототипа или своё? Идентификатор или булево
            'is_default_class' => Rule::bool(), // Используется класс прототипа или свой? Идентификатор прототипа или булево
            // Сведения о загружаемом файле. Не является атрибутом объекта, но используется в общей обработке
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
}