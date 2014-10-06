<?php
/**
 * Класс
 *
 * @version 1.0
 */
namespace boolive\core\data;

use boolive\core\file\File;
use boolive\core\functions\F;

class Data
{
    static function read($uri)
    {
        if ($uri === ''){
            $file = DIR.'site.info';
        }else{
            $file = DIR.trim($uri,'/').'/'.File::fileName($uri).'.info';
        }
        try{
            $data = file_get_contents($file);
            $data = json_decode($data, true);
            $entity = new Entity($data);
        }catch (\Exception $e){
            return false;
        }
        return $entity;
    }

    static function write()
    {

    }

    static function delete()
    {

    }
}