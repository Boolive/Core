<?php
/**
 * Значения, передаваемые в php-шаблон.
 *
 * @example
 * Пример использования:
 *  $v = new TemplatePHPValues();
 *  $v[0] = 'A&B';
 *  echo $v[0]; // A&amp;B
 *  echo $v->html(0); //A&B
 * @link http://boolive.ru/createcms/making-page
 * @version 2.0
 * @author Vladimir Shestakov <boolive@yandex.ru>
 */
namespace boolive\core\template\php;

use boolive\core\values\Values,
    boolive\core\values\Rule;

class PHPTemplateValues extends Values
{
    function __toString()
    {
        return (string)$this->get(Rule::escape());
    }
}