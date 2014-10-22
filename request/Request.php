<?php
/**
 * Request
 * @aurhor Vladimir Shestakov
 * @version 1.0
 */
namespace boolive\core\request;

class Request implements IActivate
{
    /** @var \boolive\core\values\Input Общий контейнер всех входящих данных */
    private static $source;

    private $input;

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
        $values = array(
            'REQUEST' => array(),
            'FILES' => isset($_FILES)? self::normalizeFiles() : array(),
            'COOKIE' => isset($_COOKIE)? $_COOKIE : array(),
            'RAW' => file_get_contents("php://input"), // Неформатированные данные
            'SERVER' => $_SERVER
        );
        if (isset($_SERVER['REQUEST_URI'])){
            $_SERVER['REQUEST_URI'] = preg_replace('#\?{1}#u', '&', $_SERVER['REQUEST_URI'], 1);
//            $request_uri = preg_replace('#^'.preg_quote(DIR_WEB).'#u', '/', $_SERVER['REQUEST_URI'], 1);
            parse_str('path='.$_SERVER['REQUEST_URI'], $values['REQUEST']);
            $values['SERVER']['argv'] = $values['REQUEST'];
            $values['SERVER']['argc'] = sizeof($values['REQUEST']);
        }
        // Элементы пути URI
        if (isset($values['REQUEST']['path']) && ($values['REQUEST']['path'] = rtrim($values['REQUEST']['path'],'/ '))){
            $values['PATH'] = explode('/', trim($values['REQUEST']['path'],' /'));
        }else{
            $values['PATH'] = array();
        }
        if (isset($_POST)){
            $values['REQUEST'] = array_replace_recursive($values['REQUEST'], $_POST);
        }
        // Аргументы из консоли (режим CLI)
        if (php_sapi_name() == 'cli' && isset($_SERVER['argv'])){
            $values['REQUEST']['method'] = 'CLI';
            $values['SERVER']['argv'] = $_SERVER['argv'];
            $values['SERVER']['argc'] = $_SERVER['argc'];
        }
        $values['ARG'] = array_flip($values['SERVER']['argv']);
        // Метод запроса
        if (isset($values['SERVER']['REQUEST_METHOD']) && !isset($values['REQUEST']['method'])){
            $values['REQUEST']['method'] = $values['SERVER']['REQUEST_METHOD'];
        }
        // Создание контейнера
        self::$source = $values;
    }

    function __construct()
    {
        $this->input = self::$source;
    }

    function input()
    {
        return $this->input;
    }
} 