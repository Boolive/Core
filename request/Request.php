<?php
/**
 * Request
 * @aurhor Vladimir Shestakov
 * @version 1.0
 */
namespace boolive\core\request;

use boolive\core\errors\Error;
use boolive\core\IActivate;
use boolive\core\values\Check;
use boolive\core\values\Rule;

/**
 * @method null redirect($url) HTTP редирект на указанный http url адрес
 * @method null htmlHead($tag, $attr = [], $unique = false) Добавление тега в &lt;head&gt; Содержимое тега указывается атрибутом "text"
 */
class Request implements IActivate, \ArrayAccess, \Countable
{
    /** @var array Исходные входящие данные */
    private static $source;

    /**
     * @var array Ассоциативный массив команд
     * Первое измерение (ассоциативное) - название команд, второе (числовое) - команды, третье - аргументы команд
     */
    private $commands = [];
    /**
     * Сгруппированные комманды
     * @var array
     */
    private $groups = [];
    /**
     * Входящие данные и информация о запросе
     * @var array
     */
    private $input;
    /**
     * Отфильтрованные данные
     * @var array
     */
    private $filtered = [];
    /**
     * Ошибки во входящих данных, обнаруженные при фильтре
     * @var null|Error
     */
    private $errors;
    /**
     * Спрятанные входящие данные
     * @var array
     */
    private $stahes = [];

    /**
     * Активация модуля
     * Создание общего контейнера входящих данных
     */
    static function activate()
    {
        if (get_magic_quotes_gpc()){
            $process = array(&$_GET, &$_POST, &$_COOKIE, &$_REQUEST);
            while (list($key, $val) = each($process)){
                foreach ($val as $k => $v){
                    unset($process[$key][$k]);
                    if (is_array($v)){
                        $process[$key][stripslashes($k)] = $v;
                        $process[] = &$process[$key][stripslashes($k)];
                    }else{
                        $process[$key][stripslashes($k)] = stripslashes($v);
                    }
                }
            }
            unset($process);
        }
        // Нормализация массива $_FILES в соответствии с именованием полей формы
        if (isset($_FILES)){
            // Перегруппировка элементов массива $_FILES
            $rec_to_array = function ($array, $name) use (&$rec_to_array){
                $result = [];
                foreach ($array as $key => $value){
                    if (is_array($value)){
                        $result[$key] = $rec_to_array($value, $name);
                    }else{
                        $result[$key][$name] = $value;
                    }
                }
                return $result;
            };
            $files = [];
            foreach ($_FILES as $field => $data){
                $files[$field] = [];
                foreach ($data as $name => $value){
                    if (is_array($value)){
                        $files[$field] = F::arrayMergeRecursive($files[$field], $rec_to_array($value, $name));
                    }else{
                        $files[$field][$name] = $value;
                    }
                }
            }
        }else{
            $files = [];
        }

        self::$source = array(
            'REQUEST' => [],
            'FILES' => $files,
            'COOKIE' => isset($_COOKIE)? $_COOKIE : [],
            'RAW' => file_get_contents("php://input"), // Неформатированные данные
            'SERVER' => $_SERVER
        );
        if (isset($_SERVER['REQUEST_URI'])){
            $_SERVER['REQUEST_URI'] = preg_replace('#\?{1}#u', '&', $_SERVER['REQUEST_URI'], 1);
//            $request_uri = preg_replace('#^'.preg_quote(DIR_WEB).'#u', '/', $_SERVER['REQUEST_URI'], 1);
            parse_str('path='.$_SERVER['REQUEST_URI'], self::$source['REQUEST']);
            self::$source['SERVER']['argv'] = self::$source['REQUEST'];
            self::$source['SERVER']['argc'] = sizeof(self::$source['REQUEST']);
        }
        // Элементы пути URI
        if (isset(self::$source['REQUEST']['path']) && (self::$source['REQUEST']['path'] = rtrim(self::$source['REQUEST']['path'],'/ '))){
            self::$source['PATH'] = explode('/', trim(self::$source['REQUEST']['path'],' /'));
        }else{
            self::$source['PATH'] = [];
        }
        if (isset($_POST)){
            self::$source['REQUEST'] = array_replace_recursive(self::$source['REQUEST'], $_POST);
        }
        // Аргументы из консоли (режим CLI)
        if (php_sapi_name() == 'cli' && isset($_SERVER['argv'])){
            self::$source['REQUEST']['method'] = 'CLI';
            self::$source['SERVER']['argv'] = $_SERVER['argv'];
            self::$source['SERVER']['argc'] = $_SERVER['argc'];
        }
        self::$source['ARG'] = array_flip(self::$source['SERVER']['argv']);
        // Метод запроса
        if (isset(self::$source['SERVER']['REQUEST_METHOD']) && !isset(self::$source['REQUEST']['method'])){
            self::$source['REQUEST']['method'] = self::$source['SERVER']['REQUEST_METHOD'];
        }
    }

    function __construct()
    {
        $this->input = self::$source;
    }

    /**
     * Добавление команды через вызов одноименной функции.
     * @param string $name Название команды
     * @param array $args аргументы команды
     * @return void
     */
    function __call($name, $args)
    {
        $this->addCommand($name, $args);
    }

    /**
     * Добавить новую команду
     * @param string $name
     * @param array $args
     * @param bool $prepand
     */
    function addCommand($name, $args = [], $prepand = false)
    {
        if (!isset($this->commands[$name])){
            $this->commands[$name] = [];
        }
        if ($prepand){
            array_unshift($this->commands[$name], $args);
        }else{
            $this->commands[$name][] = $args;
        }
        // Добавление команды в группы
        foreach ($this->groups as $key => $g){
            $this->groups[$key][] = array($name, $args, $prepand);
        }
    }

    function addCommandList($commands)
    {
        foreach ($commands as $c) $this->addCommand($c[0], $c[1], $c[2]);
    }

    /**
     * Выбор команд по имени
     * @param string $name
     * @param bool $unique
     * @return array
     */
    function getCommands($name, $unique = false)
    {
        if (!isset($this->commands[$name])){
            $this->commands[$name] = [];
        }
        if ($unique){
            $keys = [];
            $result = [];
            foreach ($this->commands[$name] as $com){
                $key = serialize($com);
                if (!isset($keys[$key])){
                    $result[] = $com;
                    $keys[$key] = true;
                }
            }
            unset($keys);
            return $result;
        }
        return $this->commands[$name];
    }

    /**
     * Удаление команд по имени
     * @param string $name Имя удаляемых команд
     */
    function removeCommand($name)
    {
        unset($this->commands[$name]);
        foreach ($this->groups as $key => $commands){
            foreach ($commands as $i => $c){
                if ($c[0] == $name){
                    unset($this->groups[$key][$i]);
                }
            }
        }
    }

    /**
     * Открыть новыую группу команд
     */
    function openGroup()
    {
        $this->groups[] = [];
    }

    /**
     * Вернуть команды текущей группы
     * @return array
     */
    function getGorup()
    {
        return end($this->groups);
    }

    /**
     * Закрыть группу команд и вернуть список команд закрытой группы
     * @return array
     */
    function closeGroup()
    {
        return array_pop($this->groups);
    }

    /**
     * Все отфильтрованные входящие данные
     * @return array
     */
    function getFiltered()
    {
        return $this->filtered;
    }

    /**
     * Ошибки при фильтре входящих данных
     * @return null|Error
     */
    function getErrors()
    {
        return $this->errors;
    }

    /**
     * Подмешать к входящим данным
     * @param array $mix
     */
    function mix($mix)
    {
        if (!empty($mix) && is_array($mix)) {
            $this->input = array_replace_recursive($this->input, $mix);
        }
    }

    /**
     * Спрятать текщие входящие данные
     */
    function stash()
    {
        $this->stahes[] = [$this->input, $this->filtered, $this->errors];
        $this->errors = null;
    }

    /**
     * Востановить спрятанные входящие данные
     */
    function unstash()
    {
        list($this->input, $this->filtered, $this->errors) = array_pop($this->stahes);
    }

    /**
     * Создание URL на основе текущего.
     * Если не указан ни один параметр, то возвращается URL текущего запроса
     * @param null|string|array $path Путь uri. Если не указан, то используется текущий путь
     * @param array $args Массив аргументов.
     * @param bool $append Добавлять ли текущие аргументы к новым?
     * @param bool|string $host Добавлять ли адрес сайта. Если true, то добавляет адрес текущего сайта. Можно строкой указать другой сайт
     * @param string $schema Схема url. Указывается, если указан $host
     * @return string
     */
    static function url($path = null, $args = [], $append = false, $host = false, $schema = 'http://')
    {
        // Путь. Если null, то текущий
        if (!isset($path)){
            $path = self::$source['REQUEST']['path'];
        }
        // Если начинается с /contents, то обрезать
        if (mb_substr($path,0,9) == '/contents'){
            $path = mb_substr($path,10);
        }
        $url = trim($path,'/');
		// Аргументы
		if (!isset($args)){
            $args = self::$source['SERVER']['argv'];
        }else{
            if ($append){
                $args = array_merge(self::$source['SERVER']['argv'], $args);
            }
        }
        if (isset($args['path'])) unset($args['path']);
        if (is_array($args)){
			foreach ($args as $name => $value){
				$url .= '&'.$name.($value!==''?'='.$value:'');
			}
		}
		if ($host){
			return $schema.($host===true?HTTP_HOST.'/':$host).$url;
		}else{
			return '/'.$url;
		}
    }

    function setFilter(Rule $rule)
    {
        $this->filtered = Check::filter($this->input, $rule, $this->errors);
        return !isset($this->errors) || !$this->errors->isExist();
    }
    /**
     * Whether a offset exists
     * @param mixed $offset An offset to check for.
     * @return boolean
     */
    public function offsetExists($offset)
    {
        return isset($this->filtered[$offset]);
    }

    /**
     * Offset to retrieve
     * @param mixed $offset The offset to retrieve.
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->filtered[$offset];
    }

    /**
     * Offset to set
     * @param mixed $offset The offset to assign the value to.
     * @param mixed $value The value to set.
     */
    public function offsetSet($offset, $value)
    {
        $this->filtered[$offset] = $value;
    }

    /**
     * Offset to unset
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        unset($this->filtered[$offset]);
    }

    /**
     * Count elements of filtered input
     * @return int
     */
    public function count()
    {
        return count($this->filtered);
    }

    public function __debugInfo()
    {
        return [
            'filtered' => $this->filtered,
            'input' => $this->input,
            'error' => $this->errors,
            'commands' => $this->commands
            ];
    }
}