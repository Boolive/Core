<?php
/**
 * Модуль аутентификации пользователя
 * Определяет текущего пользователя
 *
 * @version 1.0
 * @author Vladimir Shestakov <boolive@yandex.ru>
 */
namespace boolive\core\auth;

use boolive\core\config\Config;
use boolive\core\data\Data,
    boolive\core\request\Request;
use boolive\core\data\Entity;
use boolive\core\events\Events;
use boolive\core\IActivate;
use boolive\core\values\Rule;

class Auth implements IActivate
{
    /** @var array Конфигурация */
    private static $config;
    /** @var Entity Текущий пользователь */
    private static $user;

    private static $input;

    static function activate()
    {
        // Конфиг хранилищ
        self::$config = Config::read('auth');
        self::$input = new Request();
        self::$input->setFilter(Rule::arrays([
            'COOKIE' => Rule::arrays([
                'ID' => Rule::string()
            ])
        ]));
    }

    /**
     * Текущий пользователь
     * @return Entity
     */
    static function get_user()
    {
        // Автоматическая аутентификация пользователя
        if (!isset(self::$user)){
            self::remind();
        }
        return self::$user;
    }

    /**
     * Установка текущего пользователя
     * Используется при "ручной" аутентификации, например, формой входа. При этом поиск пользователя
     * по логину, паролю или другим параметрам выполняется моделью формы входа.
     * @param null | Entity $user Авторизованный пользователь или NULL для отмены авторизации
     * @param int $duration Длительность в секундах запоминания пользователя. Если 0, то пользователь запоминается на период работы браузера
     */
    static function set_user($user, $duration = 0)
    {
        if ($user instanceof Entity){
            self::$user = $user;
            self::remember($duration);
        }else{
            // Забыть текущего пользователя и создать нового гостя
            unset(self::$input['COOKIE']['ID']);
            self::remind();
        }
        Events::trigger('Auth::set_user', [self::get_user()]);
    }

    /**
     * Вспомнить пользователя
     * @return Entity
     */
    protected static function remind()
    {
        self::$user = null;
        if (!empty(self::$input['COOKIE']['ID'])){
            $ID = explode('|', self::$input['COOKIE']['ID']);
        }else{
            $ID = '';
        }
        // Период запоминания пользователя
        $duration = empty($ID[0])? 0 : $ID[0]; // не больше месяца (примерно)
        // Хэш пользователя для поиска (авторизации)
        $hash = empty($ID[1]) ? '' : $ID[1];
        // Если есть кука, то ищем пользователя в БД
        if ($hash){
            $result = Data::find(array(
                'from' => self::$config['users-list'],
                'select' => 'children',
                'depth' => 'max',
                'where' => array(
                    array('value', '=', $hash),
//                    array('not','is_link')
                ),
                'key' => false,
                'limit' => array(0, 1),
                'comment' => 'auth user by cookie',
            ), false);
            // Пользователь найден и не истекло время его запоминания
            if (!empty($result)){
                self::$user = $result[0];
            }
        }else{
            $hash = self::get_unique_hash();
        }
        // Новый гость
        if (!self::$user){
            self::$user = Data::create(self::$config['user'], self::$config['users-list']);
            self::$user->value($hash);
            $duration = 0;
        }
        self::remember($duration);
    }

    /**
     * Запомнить пользователя для последующего автоматического входа
     * @param int $duration Длительность запоминания в секундах. Если 0, то пользователь запоминается на период работы браузера
     */
    protected static function remember($duration = 0)
    {
        $duration = max(0, min($duration, 3000000)); // не больше месяца (примерно)
        $hash = self::$user->value(null, true);
        // Запомнить hash
        if (!$hash){
            self::$user->value($hash = self::get_unique_hash());
            //self::$user->save(false, false);
        }
        // Запомнить время визита (не чаще раза за 5 минут)
//        if (self::$user->isExist() && (Data2::read(array(self::$user, 'visit_time'), false)->value() < (time()-300))){
//            // Обновление времени визита
//            self::$user->visit_time = time();
//            //self::$user->visit_time->save(true, false);
//        }
        setcookie('ID', $duration.'|'.$hash, ($duration ? time()+$duration : 0), '/');
    }

    /**
	 * Уникальное хэш-значение
	 * @return string
	 */
	static function get_unique_hash()
    {
		return hash('sha256', uniqid(rand(), true).serialize($_SERVER));
	}

    static function is_super_admin()
    {
        return in_array(self::get_user()->uri(), self::$config['super-admins']);
    }
}