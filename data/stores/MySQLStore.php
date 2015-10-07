<?php
/**
 * Класс для подключения и использования базы MySQL
 *
 * @version 1.0
 * @date 18.09.2015
 * @author Polina Shestakova <paulinep@yandex.ru>
 */
namespace boolive\core\data\stores;

use boolive\core\data\IStore;
use boolive\core\database\DB;
use boolive\core\data\Buffer;


class MySQLStore implements IStore
{
    private $db = false;

    function __construct($key, $params)
    {
        $this->db = DB::connect($params);
    }

    function read($uri)
    {
        if (!($entity = Buffer::get_entity($uri))){
                    // Экземпляра объекта в буфере нет, проверяем массив его атрибутов в буфере
                    $info = Buffer::get_info($uri);
                    if (empty($info)){
                        try {
                            $q = $this->db->prepare('
                                SELECT * FROM `entity` WHERE `entity`.uri = :uri
                            ');
                            $q->execute(['uri'=> $uri]);
                            $row = $q->fetch(DB::FETCH_ASSOC);

                        }catch(\Exception $e){
                            return false;
                        }
                    }
        }
    }

    function find($cond) {
        // TODO: Implement find() method.
    }

    function write($entity) {
        // TODO: Implement write() method.
    }

    function delete($entity) {
        // TODO: Implement delete() method.
    }
}