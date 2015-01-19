<?php
/**
 * IStore
 * @aurhor Vladimir Shestakov
 * @version 1.0
 */
namespace boolive\core\data;

interface IStore
{
    function __construct($key, $params);

    /**
     * @param $uri
     * @return bool|Entity
     */
    function read($uri);

    function find($cond);

    function write($entity);

    function delete($entity);
} 