<?php
/**
 * Request
 * @aurhor Vladimir Shestakov
 * @version 1.0
 */
namespace boolive\core\request;
/**
 * @method null redirect($url) HTTP редирект на указанный http url адрес
 * @method null htmlHead($tag, $args = array(), $unique = false) Добавление тега в &lt;head&gt; Содержимое тега указывается аргументом "text"
 */
class Request implements IActivate
{
    /** @var \boolive\core\values\Input Исходные входящие данные */
    private static $source;

    /**
     * @var array Ассоциативный массив команд
     * Первое измерение (ассоциативное) - название команд, второе (числовое) - команды, третье - аргументы команд
     */
    private $commands = array();
    /**
     * Сгруппированные комманды
     * @var array
     */
    private $groups = array();
    /**
     * Входящие данные и информация о запросе
     * @var array
     */
    private $input;
    /**
     * Спрятанные входящие данные
     * @var array
     */
    private $stahes = array();

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
                $result = array();
                foreach ($array as $key => $value){
                    if (is_array($value)){
                        $result[$key] = $rec_to_array($value, $name);
                    }else{
                        $result[$key][$name] = $value;
                    }
                }
                return $result;
            };
            $files = array();
            foreach ($_FILES as $field => $data){
                $files[$field] = array();
                foreach ($data as $name => $value){
                    if (is_array($value)){
                        $files[$field] = F::arrayMergeRecursive($files[$field], $rec_to_array($value, $name));
                    }else{
                        $files[$field][$name] = $value;
                    }
                }
            }
        }else{
            $files = array();
        }

        self::$source = array(
            'REQUEST' => array(),
            'FILES' => $files,
            'COOKIE' => isset($_COOKIE)? $_COOKIE : array(),
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
            self::$source['PATH'] = array();
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
            $this->commands[$name] = array();
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
            $this->commands[$name] = array();
        }
        if ($unique){
            $keys = array();
            $result = array();
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
     * Все входящие данныи и информация о запроса
     * @return array
     */
    function getInput()
    {
        return $this->input;
    }

    /**
     * Подмешать к входящим данным
     * @param array $mix
     */
    function inputMix($mix)
    {
        if (!empty($mix) && is_array($mix)) {
            $this->input = array_replace_recursive($this->input, $mix);
        }
    }

    /**
     * Спрятать текщие входящие данные
     */
    function stashInput()
    {
        $this->stahes[] = $this->input;
    }

    /**
     * Востановить спрятанные входящие данные
     */
    function unstashInput()
    {
        $this->input = array_pop($this->stahes);
    }

    /**
     * Создание URL на основе текущего.
     * Если не указан ни один параметр, то возвращается URL текущего запроса
     * @param null|string|array $path Путь uri. Если не указан, то используется текущий путь
     * @param int $shift С какого парметра пути текущего URL делать замену на $path
     * @param array $args Массив аргументов.
     * @param bool $append Добавлять ли текущие аргументы к новым?
     * @param bool|string $host Добавлять ли адрес сайта. Если true, то добавляет адрес текущего сайта. Можно строкой указать другой сайт
     * @param string $schema Схема url. Указывается, если указан $host
     * @return string
     */
    static function url($path = null, $shift = 0, $args = array(), $append = false, $host = false, $schema = 'http://')
    {
        if (is_string($path)){
			$path = explode('/',trim($path,'/'));
		}
        if (!isset($path)) $path = array();
        $url = '';
        if (isset($path[0]) && $path[0] == 'contents'){
            array_shift($path);
        }
        // Параметры
        // Текущие параметры (текщего адреса) заменяем на указанные в $params
        $cur_path = self::$source['PATH'];
        $index = sizeof($cur_path);
        if (is_array($path) and sizeof($path) > 0){
            foreach ($path as $index => $value){
                $cur_path[$index + $shift] = $value;
            }
            $index+=$shift + 1;
        }else
        if ($shift > 0){
            $index = $shift;
        }
        // Все текущие параметры после поcледнего из измененных отсекаются
        for ($i = 0; $i < $index; $i++){
            if (isset($cur_path[$i])){
                $url.=$cur_path[$i].'/';
            }else{
                $url.='/';
            }
        }
		// Аргументы
		if (!isset($args)){
            $args = self::$source['SERVER']['argv'];
        }else{
            if ($append){
                $args = array_merge(self::$source['SERVER']['argv'], $args);
            }
        }
        if (isset($args['path'])) unset($args['path']);
		if (strlen($url) > 0){
            if (mb_substr($url,0,1)=='/') $url = mb_substr($url,1);
			$url = rtrim($url,'/');
		}
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
}