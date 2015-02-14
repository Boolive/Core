<?php
/**
 * Ядро платформы Boolive
 *
 * Подготавливает условия для работы проекта.
 * Осуществляет автоматическую загрузку свох классов.
 * Генерирует события активации и деактивации.
 * Обрабатывает ошибки и исключения, сообщает о них генерацией события.
 * @author Vladimir Shestakov <boolive@yandex.ru>
 * @version 2.0
 */
namespace boolive\core {

    use Exception;
    use ErrorException;
    use Composer\Autoload\ClassLoader;

    class Core
    {
        /** @var ClassLoader */
        private static $loader;
        /** @var array Список активированных классов */
        static private $activated;
        /** @var array Список подключенных классов */
        static private $included;
        /** @var int Текущий уровень фиксации ошибок в настройках PHP */
        static private $error_reporting;

        static private $config;

        static function activate(ClassLoader $loader)
        {
            self::$loader = $loader;
            self::$loader->unregister();
            self::$activated = [__CLASS__ => __CLASS__];
            self::$included = [__CLASS__ => __CLASS__];
            self::$error_reporting = error_reporting();
            // Регистрация метода-обработчика автозагрузки классов
            spl_autoload_register(['\boolive\core\Core', 'loadClass'], true, true);
            // Регистрация метода-обработчка завершения выполнения системы
            register_shutdown_function(['\boolive\core\Core', 'deactivate']);
            // Регистрация обработчика исключений
            set_exception_handler(['\boolive\core\Core', 'exception']);
            // Регистрация обработчика ошибок
            set_error_handler(['\boolive\core\Core', 'error']);
            // Временая зона
            date_default_timezone_set('UTC');
            // Настройка кодировки
            mb_internal_encoding('UTF-8');
            mb_regex_encoding('UTF-8');
            mb_http_output('UTF-8');
            // При необходимости, каждый класс может автоматически подключиться и активироваться, обработав событие Core::activate.
            \boolive\core\events\Events::trigger('Core::activate');
        }

        /**
         * Завершение выполнения системы
         * Метод вызывается автоматически интерпретатором при завершение всех действий системы
         * или при разрыве соединения с клиентом
         */
        static function deactivate()
        {
            \boolive\core\events\Events::trigger('Core::deactivate');
        }

        /**
         * Загрузка и активация класса
         * @param string $class_name Class name with namespace
         * @return bool
         * @throws \ErrorException
         */
        static function loadClass($class_name)
        {
            if ($file = self::$loader->findFile($class_name)) {
                self::$included[$class_name] = $class_name;
                \Composer\Autoload\includeFile($file);
                if (isset(class_implements($class_name, false)['boolive\core\IActivate'])) {
                    self::$activated[$class_name] = $class_name;
                    $class_name::activate();
                }
                return true;
            }else{
                throw new ErrorException('Class "'.$class_name.'" not found', 2);
            }
        }

        static function start()
        {
            return 'Hello world';
        }

        /**
         * Обработчик исключений
         * Вызывается автоматически при исключениях и ошибках
         * @param \Exception $e Обрабатываемое исключение
         * @return bool
         */
        static function exception($e)
        {
            // Если обработчики событий не вернут положительный результат, то
            // обрабатываем исключение по умолчанию
            if (!\boolive\core\events\Events::trigger('Core::error', [$e])){
                trace_log(get_class($e).' ['.$e->getCode().']: '.$e->getMessage().' in '.$e->getFile().' on line '.$e->getLine());
                if (isset($e->xdebug_message)){
                    echo '<table cellspacing="0" cellpadding="1" border="1" dir="ltr">'.$e->xdebug_message.'</table>';
                }else{
                    trace($e, 'error');
                }
            };
        }

        /**
         * Обработчик ошбок PHP
         * Преобразование php ошибки в исключение для стандартизации их обработки
         * @param int $errno Код ошибки
         * @param string $errstr Сообщение
         * @param string $errfile Файл ошибки
         * @param int $errline Номер строки с ошибкой
         * @throws ErrorException Если ошибка не игнорируется, то превращается в исключение
         * @return bool
         */
        static function error($errno, $errstr, $errfile, $errline)
        {
            if (!(self::$error_reporting & $errno)){
                return false;
            }
            throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
        }

        /**
         * Список активированных классов.
         * Классы, у которых был вызован метод Activate().
         * @return array Названия классов
         */
        public static function getActivated()
        {
            return self::$activated;
        }

        /**
         * Список подключенных классов
         * Классы, php-файлы которых подключены (include)
         * @return array Названия классов
         */
        public static function getIncluded()
        {
            return self::$included;
        }

        /**
         * Путь на файл класса по стандарту PSR-0
         * @param string $class_name Имя класса с namespace
         * @return string Путь к файлу от корня сервера
         */
        public static function getClassFile($class_name)
        {
            return self::$loader->findFile($class_name);
        }

        /**
         * Проверка, активирован ли класс. Был ли вызван у класса метод Activate()?
         * @param string $class Имя класса
         * @return bool
         */
        public static function isActivate($class)
        {
            $class = ltrim($class, '\\');
            return isset(self::$activated[$class]);
        }

        /**
         * Проверка, подключен ли файл класса.
         * @param string $class Имя класса
         * @return bool
         */
        public static function isIncluded($class)
        {
            $class = ltrim($class, '\\');
            return isset(self::$included[$class]);
        }

        /**
         * Проверка, установлен ли класс
         * Знает ли система о существовании указанного файла?
         * @param string $class_name Имя класса
         * @return bool
         */
        public static function isExists($class_name)
        {
            if (class_exists($class_name, false) || interface_exists($class_name)){
                return true;
            }
            return is_file(self::getClassFile($class_name));
        }

        /**
         * Проверка существования не абстрактного класса
         * @param string $class_name Имя класса с учетом namespace
         * @return bool
         */
        public static function isCompleteClass($class_name)
        {
            $result = class_exists($class_name);
            if ($result){
                $testClass  = new \ReflectionClass($class_name);
                $result = !$testClass->isAbstract();
                unset($testClass);
            }
            return $result;
        }
    }
}
namespace {
    /**
     * Трассировка переменной с автоматическим выводом значения
     * Сделано из-за лени обращаться к классу Trace :)
     * @param mixed $var Значение для трассировки
     * @param null $key
     * @return \boolive\core\develop\Trace Объект трассировки
     */
    function trace($var = null, $key = null)
    {
        return \boolive\core\develop\Trace::groups('trace')->group($key)->set($var)->out();
    }

    function trace_log($var = null, $key = null)
    {
        \boolive\core\develop\Trace::groups('trace')->group($key)->set($var)->log();
    }
}