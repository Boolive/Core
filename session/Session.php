<?php
/**
 * Сессии
 *
 * @version 1.0
 * @date 10.01.2013
 * @author Vladimir Shestakov <boolive@yandex.ru>
 */
namespace boolive\core\session;

use boolive\core\auth\Auth;
use boolive\core\events\Events;
use boolive\core\IActivate;

class Session implements IActivate
{
    static function activate()
    {
		// Сессиия идентифицируется по пользователю
        ini_set('session.use_cookies', 0);
		self::init();
        // При смене пользователя сменить сессию
		Events::on('Auth:set_user', ['\boolive\core\session\Session', 'init']);
	}

    /**
	 * Инициализация сессии
	 * @return void
	 */
	static function init()
    {
		session_write_close();
        if (IS_INSTALL){
            session_id(Auth::get_user()->value(null, true));
        }else{
            session_id('install');
        }
		session_start();
	}
    /**
	 * Идентификатор сессии
	 * @return string
	 */
	static function id()
    {
		return session_id();
	}

    /**
     * Выбор значения
     * @param string $key Ключ значения
     * @return mixed
     */
    static function get($key)
    {
        return isset($_SESSION[$key])? $_SESSION[$key] : null;
    }

    /**
     * Установка значения
     * @param string|int $key Ключ значения
     * @param mixed $value Устанавливаемое значение
     * @return mixed Установленное значение
     */
    static function set($key, $value)
    {
        return $_SESSION[$key] = $value;
    }

    /**
     * Удаление значения
     * @param string $key Ключ значения
     */
    static function remove($key)
    {
        unset($_SESSION[$key]);
    }

    /**
     * Удаление всех значений
     */
    static function clear()
    {
        $_SESSION = array();
    }

    /**
     * Проверка сузестоваония значения. При этом значение может быть null
     * @param string $key Ключ значения
     * @return bool
     */
    static function is_exist($key)
    {
        return isset($_SESSION[$key]) || array_key_exists($key, $_SESSION);
    }

    /**
     * проверка, является ли значение пустым. Значение пустое, если его нет или равно null, пустой строке, 0
     * @param string $key Ключ значения
     * @return bool
     */
    static function is_empty($key)
    {
        return empty($_SESSION[$key]);
    }

    /**
     * Проверка системных требований
     * @return array Массив сообщений - требований для установки
     */
    static function systemRequirements()
    {
        $requirements = array();
        session_id('systemRequirements');
        @session_start();
		$_SESSION['check'] = 'check';
		@session_write_close();
		unset($_SESSION['check']);
		@session_start();
		if (empty($_SESSION['check']) || $_SESSION['check'] != 'check'){
			$requirements[] = 'Не работают пользовательские сессии в PHP';
		}
		@session_write_close();
        return $requirements;
    }
}