<?php
/**
 * Правило для проверки и фильтра значений.
 * Правило указывает, каким должно быть значение. Проверку и фильтр выполняет класс \boolive\core\values\Check
 *
 * @link http://boolive.ru/createcms/rules-for-filter
 * @version 3.0
 * @author Vladimir Shestakov <boolive@yandex.ru>
 */

namespace boolive\core\values;

/**
 * Стандартные фильтры к правилу.
 * Методы создания правила с указанием первого фильтра.
 * @method static \boolive\core\values\Rule bool() Булево: false, 'false', 'off', 'no', '', '0' => false, иначе true
 * @method static \boolive\core\values\Rule int() Целое число в диапазоне от -2147483648 до 2147483647
 * @method static \boolive\core\values\Rule double() Действительное число в диапазоне от -1.7976931348623157E+308 до 1.7976931348623157E+308
 * @method static \boolive\core\values\Rule string() Строка любой длины
 * @method static \boolive\core\values\Rule scalar() Строка, число, булево
 * @method static \boolive\core\values\Rule null() Неопределенное значение. При этом проверяемый элемент должен существовать!
 * @method static \boolive\core\values\Rule arrays() Массив
 * @method static \boolive\core\values\Rule object() Объект указываемого класса
 * @method static \boolive\core\values\Rule entity() Объект класса \boolive\core\data\Entity или URI объекта, который можно получить из БД. В аргументе фильтра указывается условие на объект в виде массива.
 * @method static \boolive\core\values\Rule values() Объект класса \boolive\core\values\Values
 * @method static \boolive\core\values\Rule any() Любое правило из перечисленных или любой тип значения, если не перечислены варианты правил
 * @method static \boolive\core\values\Rule forbidden() Запрещенный. Требуется отсутствие элемента
 * @method static \boolive\core\values\Rule eq() Равен указанному значению
 * @method static \boolive\core\values\Rule not() Не равен указанному значению
 * @method static \boolive\core\values\Rule in() Допустимые значения. Через запятую или массив
 * @method static \boolive\core\values\Rule not_in() Недопустимые значения. Через запятую или массив
 * @method static \boolive\core\values\Rule escape() Экранирование html символов
 * @method static \boolive\core\values\Rule striptags() Вырезание html тегов
 * @method static \boolive\core\values\Rule email() Email адрес
 * @method static \boolive\core\values\Rule url() URL
 * @method static \boolive\core\values\Rule uri() URI = URL + URN, возможно отсутсвие части URL или URN
 * @method static \boolive\core\values\Rule ip() IP
 * @method static \boolive\core\values\Rule regexp() Проверка на совпадение одному из регулярных выражений. Выражения через запятую или массив
 * @method static \boolive\core\values\Rule ospatterns() Проверка на совпадения одному из паттернов в стиле оболочки операционной системы: "*gr[ae]y". Паттерны запятую или массив
 * @method static \boolive\core\values\Rule color() HEX формат числа. Код цвета #FFFFFF. Возможны сокращения и опущение #
 * @method static \boolive\core\values\Rule lowercase() Преобразует строку в нижний регистр
 * @method static \boolive\core\values\Rule uppercase() Преобразует строку в верхний регистр
 * @method static \boolive\core\values\Rule condition() Условие поиска или валидации объекта
 *
 * Методы добавления фильтра к объекту правила.
 * @method \boolive\core\values\Rule max($max) Максимальное значение. Правая граница отрезка. Максимальный размер массива
 * @method \boolive\core\values\Rule min($min) Минимальное значение. Левая граница отрезка. Минимальный размер массива
 * @method \boolive\core\values\Rule less($less) Меньше указанного значения. Правая граница интервала. Размер массива меньше указанного
 * @method \boolive\core\values\Rule more($more) Больше указанного значения. Левая граница интервала. Размер массива больше указанного
 * @method \boolive\core\values\Rule eq() Равен указанному значению
 * @method \boolive\core\values\Rule not() Не равен указанному значению
 * @method \boolive\core\values\Rule in() Допустимые значения. Через запятую или массив
 * @method \boolive\core\values\Rule not_in() Недопустимые значения. Через запятую или массив
 * @method \boolive\core\values\Rule required() Должен существовать
 * @method \boolive\core\values\Rule default($value) Значение по умолчанию, если есть ошибки. Ошибка удаляется
 * @method \boolive\core\values\Rule ignore() Коды игнорируемых ошибок
 * @method \boolive\core\values\Rule trim() Обрезание строки
 * @method \boolive\core\values\Rule escape() Экранирование html символов
 * @method \boolive\core\values\Rule striptags() Вырезание html тегов
 * @method \boolive\core\values\Rule email() Email адрес
 * @method \boolive\core\values\Rule url() URL
 * @method \boolive\core\values\Rule uri() URI = URL + URN, возможно отсутсвие части URL или URN
 * @method \boolive\core\values\Rule ip() IP
 * @method \boolive\core\values\Rule regexp() Проверка на совпадение одному из регулярных выражений. Выражения через запятую или массив
 * @method \boolive\core\values\Rule ospatterns() Проверка на совпадения одному из паттернов в стиле оболочки операционной системы: "*gr[ae]y". Паттерны запятую или массив
 * @method \boolive\core\values\Rule color() HEX формат числа. Код цвета #FFFFFF. Возможны сокращения и опущение #
 * @method \boolive\core\values\Rule file_upload() Информация о загружаемом файле в виде массива
 * @method \boolive\core\values\Rule lowercase() Преобразует строку в нижний регистр
 * @method \boolive\core\values\Rule uppercase() Преобразует строку в верхний регистр
 * @method \boolive\core\values\Rule condition() Условие поиска или валидации объекта
 */
class Rule
{
    /** @var array Фильтры */
    private $filters = [];

    /**
     * Создание правила.
     * Создаётся и возвращается объект правила с добавленным первым фильтром, название которого соответсвует
     * названию вызванного метода. Аргументы правила являются аргументами вызыванного метода.
     * @static
     * @example Rule::int();
     * @param string $method Название фильтра (метода)
     * @param array $args Аргументы фильтра (метода)
     * @return \boolive\core\values\Rule Новый объект правила
     */
    static function __callStatic($method, $args)
    {
        $rule = new Rule();
        $rule->filters[$method] = $args;
        return $rule;
    }

    /**
     * Установка фильтра
     * Если фильтр уже установлен, то он будет заменен новым
     * @example Rule::int()->max(10)->filter2($arg);
     * @param string $name Имя фильтра
     * @param array $args Аргументы фильтра
     * @return \boolive\core\values\Rule
     */
    function __call($name, $args)
    {
        $this->filters[$name] = $args;
        return $this;
    }

    /**
     * Выбор фильтра по имени
     * @example $f = $rule->int->max;
     * @param string $name Название фильтра
     * @return array Аргументы фильтра
     */
    function &__get($name)
    {
        return $this->filters[$name];
    }

    /**
     * Установка фильтра через присвоение
     * @example $rule->max = 10; //установка фильтра max с аргументом 10
     * @param string $name Название фильтра
     * @param mixed $args Массив аргументов. Если не является массивом, то значение будет помещено в массив
     */
    function __set($name, $args)
    {
        if (!is_array($args)) $args = array($args);
        $this->filters[$name] = $args;
    }

    /**
     * Проверка существования фильтра
     * @example $is_exist = isset($rule->max);
     * @param string $name Название фильтра
     * @return bool
     */
    function __isset($name)
    {
        return isset($this->filters[$name]);
    }

    /**
     * Удаление фильтра
     * @example unset($rule->max);
     * @param string $name Название фильтра
     */
    function __unset($name)
    {
        unset($this->filters[$name]);
    }

    /**
     * Выбор всех фильтров
     * @return array Ассоциативный массив фильтров, где ключ элемента - название фильтра, а значение - аргументы фильтра
     */
    function getFilters()
    {
        return $this->filters;
    }

    function __debugInfo()
    {
        return $this->filters;
    }
}