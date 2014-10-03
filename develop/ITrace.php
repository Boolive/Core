<?php
/**
 * Интерфейс получения от объекта значений для трассировки (вывода)
 *
 * @author Azat Galiev <AzatGaliev@live.ru>
 * @version 2.0
 */
namespace boolive\core\develop;

interface ITrace
{
    /**
     * Возвращает значения для трассировки
     * @return array
     */
    function trace();
}