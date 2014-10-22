<?php
/**
 * Шаблонизация с помощью PHP
 * @link http://boolive.ru/createcms/making-page
 * @version 2.0
 * @author Vladimir Shestakov <boolive@yandex.ru>
 */
namespace boolive\core\template\php;

class PHPTemplate
{
    /**
     * Создание текста из шаблона
     * В шаблон вставляются переданные значения
     * При обработки шаблона могут довыбираться значения из $entity и создаваться команды в $request
     * @param string $entity Полный путь на файл шаблона
     * @param array $v Значения для шаблона
     * @throws \Exception
     * @return string
     */
    function render($template, $v)
    {
        try{
            // Массив $v достпуен в php-файле шаблона, подключамом ниже
            $v = new PHPTemplateValues($v, null);
            ob_start();
                include($template);
                $result = ob_get_contents();
            ob_end_clean();
        }catch (\Exception $e){
            ob_end_clean();
//          if ($e->getCode() == 2){
//              echo "Template file '{$entity->file()}' not found";
//          }else{
                throw $e;
//          }
        }
        return $result;
    }
}