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
            $info = file_get_contents($file);
            $info = json_decode($info, true);
            $info['uri'] = $uri;
            if (!isset($info['is_default_logic'])) $info['is_default_logic'] = true;
            $info['is_exists'] = true;
            $entity = self::entity($info);
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

    static function entity($info)
    {
//        $key = isset($attribs['uri'])? $attribs['uri'] : (isset($attribs['id'])? $attribs['id'] : null);
//        if (!isset($key) || !($entity = Buffer2::get($key))){
            try{
                if (isset($info['uri']) && (empty($info['uri']) || preg_match('#^[a-zA-Z_0-9\/]+$#ui', $info['uri']))){
                    if (!empty($info['is_default_logic'])){
                        if (isset($info['proto'])){
                            // Класс от прототипа
                            $class = get_class(self::read($info['proto']));
                        }else{
                            // Класс наследуется, но нет прототипа
                            $class = '\boolive\core\data\Entity';
                        }
                    }else{
                        $namespace = str_replace('/', '\\', rtrim($info['uri'],'/'));
                        // Свой класс
                        if (empty($namespace)){
                            $class = '\\site';
                        }else
                        if (substr($namespace,0,7) === '\\vendor'){
                            $class = substr($namespace,7).'\\'.basename($namespace);
                        }else{
                            $class = $namespace.'\\'.basename($namespace);
                        }
                    }
                }else{
                    $class = '\boolive\core\data\Entity';
                }
                $entity = new $class($info);
            }catch (\ErrorException $e){
                $entity = new Entity($info);
            }
//            if (isset($key)) Buffer2::set($entity);
//        }
        return $entity;
    }


}