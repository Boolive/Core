<?php
/**
 * 
 * @author Vladimir Shestakov
 * @version 1.0
 */
namespace boolive\basic\controller;

use boolive\core\data\Entity;

class controller extends Entity
{
    function start()
    {
        if ($this->startCheck()){
            ob_start();
                // Выполнение своей работы
                $result = $this->work();
                if (!($result === false || is_array($result))){
                    $result = ob_get_contents().$result;
                }
            ob_end_clean();
            return $result;
        }
        return false;
    }

    function startCheck()
    {
        return true;
    }

    function work()
    {

    }
}