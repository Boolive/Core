<?php
/**
 * Класс
 *
 * @version 1.0
 */
namespace boolive\core\data;

use boolive\core\file\File;
use boolive\core\functions\F;

class Data
{
    /**
     * @param $uri
     * @return bool|Entity
     */
    static function read($uri)
    {
        if ($uri === ''){
            $file = DIR.'project.info';
        }else{
            $file = DIR.trim($uri,'/').'/'.File::fileName($uri).'.info';
        }
        try {
            if (is_file($file)) {
                $info = file_get_contents($file);
                $info = json_decode($info, true);
                $info['uri'] = $uri;
                if (!isset($info['is_default_logic'])) $info['is_default_logic'] = true;
                $info['is_exists'] = true;
                $entity = self::entity($info);
            }else{
                list($parent_uri, $name) = F::splitRight('/', $uri);
                if ($parent = self::read($parent_uri)){
                    $entity = $parent->child($name);
                }else{
                    $entity = false;
                }
            }
        }catch (\Exception $e){
            return false;
        }
        return $entity;
    }

    /**
     * @param $cond
     * <code>
     * [
     *      -'select' => 'children', //self, children, parents, heirs, protos, child, link
     *      -'calc' => 'count', // false, exists, count, [sum, attr], [max, attr], [min, attr], [avg, attr]
     *      -'from' => '/contents',
     *      -'depth' => [1,1], // minimum and maximum depth
     *      'struct' => 'array', // value, object, array, tree
     *      -'where' => [], //filter condition
     *      'order' => [['name', 'asc'], ['value','desc']],
     *      'limit' => [0,100],
     *      -'key' => 'name', // attribute name
     *      -'access' => true // check read access
     * ];
     * </code>
     * @return array
     * @throws \Exception

     */
    static function find($cond)
    {
        $cond = self::normalizeCond($cond);
        // select, from, depth
        $dir = DIR.$cond['from'];
        $objects =array();
        $trim_pos = mb_strlen(DIR);
        foreach (new \DirectoryIterator($dir) as $d) {
            if ($d->isDir()){
                $fname = $d->getPathname().'/'.$d->getBasename();
                if (is_file($fname.'.info')){
                    // Все сведения об объекте в формате json (если нет класса объекта)
                    $f = file_get_contents($fname.'.info');
                    $info = json_decode($f, true);
                    if ($error = json_last_error()){
                        throw new \Exception('Ошибка в "'.$d->getPathname().'"');
                    }
                    $info['uri'] = preg_replace('#\\\\#u','/',mb_substr($d->getPathname(), $trim_pos));
                    //if (!empty($info['uri'])) $info['uri'] = '/'.$info['uri'];
                    if (!isset($info['is_default_logic'])) $info['is_default_logic'] = true;
                    $info['is_exists'] = true;
                    $obj = Data::entity($info);
                    if (!$cond['where'] || $obj->verify($cond['where'])){
                        $objects[] = $obj;
                    }else{
                        //Buffer::remove($obj->uri());
                    }
                }
            }
        }

        // where, key, access
        // order (not for value and object)
        if ($order = $cond['order']) {
            $order_cnt = count($order);
            usort($objects, function ($a, $b) use ($order, $order_cnt) {
                /** @var $a Entity */
                /** @var $b Entity */
                $i = 0;
                do {
                    if (count($order[$i]) == 3) {
                        $a = $a->{$order[$i][0]};
                        $b = $b->{$order[$i][0]};
                        array_shift($order[$i]);
                    }
                    $a = $a ? $a->$order[$i][0]():null;
                    $b = $b ? $b->$order[$i][0]():null;
                    if ($a == $b) {
                        $comp = 0;
                    } else {
                        $comp = ($a > $b || $a == null) ? 1 : -1;
                        if ($order[$i][1] == 'desc') $comp = -$comp;
                    }
                    $i++;
                } while ($comp == 0 && $i < $order_cnt);
                return $comp;
            });
        }
        // limit (not for value and object)

        // calc

        // struct

        return $objects;
    }

    /**
     * Нормализация услвоия выборки
     * @param string|array $cond Условие поиска
     * @param bool $full_normalize Признак, выполнять полную нормализацию или только конвертирование в массив?
     *                             Полная нормализация используется, если в условии указаны не все параметры
     * @param array $default Условие по умолчанию
     * @throws \Exception
     * @return array
     */
    static function normalizeCond($cond, $full_normalize = true, $default = array())
    {
        if (!empty($cond['correct'])) return $cond;
        $result = array();
        // Определение формата условия - массив, url, строка, массив из объекта и url
        if (is_array($cond)){
            if (sizeof($cond) == 2 && isset($cond[0]) && $cond[0] instanceof Entity && isset($cond[1])){
                // Пара из объекта и uri на подчиенного. uri может быть строковым условием
                $entity = $cond[0];
                $str_cond = $cond[1];
            }else{
                // обычный массив условия
                $result = $cond;
            }
        }else{
            $str_cond = $cond;
        }
        // Декодирование строкового услвоия в массив
        if (isset($str_cond)){
            if (!preg_match('/^[^=]+\(/ui', $str_cond)){
                $result = self::condStringToArray(self::condUrlToStrnig($str_cond), true);
            }else{
                $result = self::condStringToArray($str_cond, true);
            }
            if (isset($entity)) $result['from'] = array($entity, $result['from']);
        }
        if (!empty($default)) $result = array_replace_recursive($default, $result);

        if ($full_normalize){
            // select - что выбирать. объект, подчиненных, наследников, родителей, прототипы
            if (empty($result['select'])){
                // по умолчанию self (выбирается from)
                $result['select'] = 'self';
            }
            // calc - вычислять количество выбранных объектов или максимальные, минимальные, средние значения, или проверять существование.
            if (empty($result['calc'])){
                $result['calc'] = false;
            }

            // struct - структура результата. Экземпляр объекта, массив объектов, вычисляемое значение или дерево объектов
            if (empty($result['struct'])){
                if ($result['calc']){
                    $result['struct'] = 'value';
                }else
                if ($result['select'] == 'self' || $result['select'] == 'child'){
                    $result['struct'] = 'object';
                }else
                if (empty($result['struct'])){
                    $result['struct'] = 'array';
                }else{
                    $result['calc'] = false;
                }
            }

            // depth - глубина выборки. Два значения - начальная и конечная глубина относительно from.
            if (!isset($result['depth'])){
                // По умолчанию в зависимости от select
                if ($result['select'] == 'self' || $result['select'] == 'link'){
                    $result['depth'] = array(0,0);
                }else
                if ($result['select'] == 'parents' || $result['select'] == 'protos' || $result['struct'] == 'tree'){
                    // выбор всех родителей или прототипов
                    $result['depth'] = array(1, Entity::MAX_DEPTH);
                }else{
                    // выбор непосредственных подчиненных или наследников
                    $result['depth'] = array(1,1);
                }
            }else{
                if (!is_array($result['depth'])){
                    $result['depth'] = array(1, $result['depth']);
                }
                $result['depth'][1] = ($result['depth'][1] === 'max')? Entity::MAX_DEPTH : $result['depth'][1];
            }

            // from - от куда или какой объект выбирать. Строка, число, массив
            // Если URI, то дополнительно определяются секции, в которых выполнять поиск
            if (empty($result['from'])){
                $result['from'] = '';
            }else
            if (is_array($result['from'])){
                if (count($result['from'])==2 && $result['from'][0] instanceof Entity && is_scalar($result['from'][1])){
                    $result['from'] = $result['from'][0]->uri().'/'.$result['from'][1];
                }else{
                    throw new \Exception('Incorrect "from" in a search condition');
                }
            }else
            if ($result['from'] instanceof Entity){
                $result['from'] = $result['from']->uri();
            }

            // where - условие выборки
            if (empty($result['where'])) $result['where'] = false;

            // order - сортировка. Можно указывать атрибуты и названия подчиненных объектов (свойств)
            if (isset($result['order'])){
                if (!empty($result['order']) && !is_array(reset($result['order']))){
                    $result['order'] = array($result['order']);
                }
            }
            if (empty($result['order'])){
                if ($result['select'] == 'children' || $result['struct'] == 'tree'){
                    $result['order'] = array(array('order', 'asc'));
                }else{
                    $result['order'] = false;
                }
            }
            if ($result['calc'] == 'exists'){
                $result['limit'] = false;
                $result['order'] = false;
            }

            // limit - ограничения выборки, начальный объект и количество
            if ($result['calc'] == 'exists'){
                $result['limit'] = array(0,1);
            }else
            if (empty($result['limit'])){
                $result['limit'] = false;
            }

            // key - если результат список, то определяет какой атрибут использовать в качестве ключа
            if (!isset($result['key'])){
                $result['key'] = false;
            }

            // access - проверять или нет доступ. Если проверять, то к условию добавятся условия доступа на чтение
            if (isset($result['access'])){
                $result['access'] = (bool)$result['access'];
            }else{
                $result['access'] = false;
            }

            // Упорядочивание параметров (для создания корректных хэш-ключей для кэша)
            $result = array(
                'select' => $result['select'],
                'calc' => $result['calc'],
                'from' => $result['from'],
                'depth' => $result['depth'],
                'struct' => $result['struct'],
                'where' => $result['where'],
                'order' => $result['order'],
                'limit' => $result['limit'],
                'key' => $result['key'],
                'access' => $result['access'],
                'correct' => true
            );
        }
        return $result;
    }

    /**
     * Преобразование условия из URL формата в обычный сроковый
     * Пример:
     *  Условие: from=/main/&where=is(/library/Comment)&limit=0,10
     *  Означает: выбрать 10 подчиненных у объекта /main, которые прототипированы от /library/Comment (можно не писать "from=")
     * @param string $uri Условие поиска в URL формате
     * @return array
     */
    static function condUrlToStrnig($uri)
    {
        $uri = trim($uri);
        if (mb_substr($uri,0,4)!='from'){
            if (preg_match('/^[a-z]+=/ui', $uri)){
                $uri = 'from=&'.$uri;
            }else{
                $uri = 'from='.$uri;
            }
        }
        $uri = preg_replace('#/?\?{1}#u', '&', $uri, 1);
        parse_str($uri, $params);
        $result = '';
        foreach ($params as $key => $item) $result.=$key.'('.$item.')';
        return $result;
    }

    /**
     * Преобразование условия поиска из массива или строки в url формат
     * @param string|array $cond Исходное условие поиска
     * @return string Преобразованное в URL условие
     */
    static function condToUrl($cond)
    {
        $cond = self::normalizeCond($cond, array(), true);
        if (is_array($cond['from'])){
            $info = parse_url(reset($cond['from']));
            $base_url = '';
            if (isset($info['scheme'])) $base_url.= $info['scheme'].'://';
            if (isset($info['host'])) $base_url.= $info['host'];
            if ($base_url_length = mb_strlen($base_url)){
                foreach ($cond['from'] as $i => $from){
                    if (mb_substr($from,0,$base_url_length) == $base_url) $cond['from'][$i] = mb_substr($from, $base_url_length);
                }
            }
        }
        //if (sizeof($cond['select']) == 1) $cond['select'] = $cond['select'][0];
        if ($cond['select'] == 'self'){
            unset($cond['select'], $cond['depth']);
        }
        unset($cond['correct']);
        foreach ($cond as $key => $c){
            if (empty($c)) unset($cond[$key]);
        }
        $url = F::toJSON($cond, false);
        $url = mb_substr($url, 1, mb_strlen($url)-2, 'UTF-8');
        $url = strtr($url, array(
                         '[' => '(',
                         ']' => ')',
                         ',""]' => ',)',
                         '"="' => '"eq"',
                         '"!="' => '"neq"',
                         '">"' => '"gt"',
                         '">="' => '"gte"',
                         '"<"' => '"lt"',
                         '"<="' => '"lte"'
                    ));
        $url = preg_replace_callback('/"([^"]*)"/ui', function($m){
                        $replacements = array("\\", "/", "\"", "\n", "\r", "\t", "\x08", "\x0c");
                        $escapers = array("\\\\", "\\/", "\\\"", "\\n", "\\r", "\\t", "\\f", "\\b");
                        return urlencode(str_replace($escapers, $replacements, $m[1]));
                    }, $url);
        $url = preg_replace('/,([a-z_]+):/ui','&$1=',$url);
        $url = preg_replace('/\(([a-z_]+),/ui','$1(',$url);
        $url = preg_replace('/\),/ui',')$1',$url);
        $url = mb_substr($url, 5, mb_strlen($url)-5);
        if (isset($base_url)){
            $url = $base_url.'?from='.$url;
        }else{
            $info = explode('&', $url, 2);
            if (!empty($info)){
                $url = urldecode($info[0]).'?'.$info[1];
            }
        }
        return $url;
    }

    /**
     * Преобразование строкового условия в массив
     * Пример:
     *  Условие: select(children)from(/main)where(is(/library/Comment))limit(0,10)
     *  Означает: выбрать 10 подчиненных у объекта /main, которые прототипированы от /library/Comment (можно не писать "from=")
     * @param $cond
     * @param bool $accos Признак, конвертировать ли первый уровень массива в ассоциативный?
     *                    Используется для общего условия, когда задаются from, where и другие параметры.
     *                    Не используется для отдельной конвертации условий в where
     * @return array
     */
    static function condStringToArray($cond, $accos = false)
    {
        // Добавление запятой после закрывающей скобки, если следом нет закрывающих скобок
        $cond = preg_replace('/(\)(\s*[^\s\),$]))/ui','),$2', $cond);
        // name(a) => (name,a)
        $cond = preg_replace('/\s*([a-z_]+)\(/ui','($1,', $cond);
        // Все значения в кавычки
        $cond = preg_replace_callback('/(,|\()([^,)(]+)/ui', function($m){
                    $escapers = array("\\", "/", "\"", "\n", "\r", "\t", "\x08", "\x0c");
                    $replacements = array("\\\\", "\\/", "\\\"", "\\n", "\\r", "\\t", "\\f", "\\b");
                    return $m[1].'"'.str_replace($escapers, $replacements, $m[2]).'"';
                }, $cond);
        $cond = strtr($cond, array(
                    '(' => '[',
                    ')' => ']',
                    ',)' => ',""]',
                    '",eq"' => '",="',
                    '",neq"' => '",!="',
                    '",gt"' => '",>"',
                    '",gte"' => '",>="',
                    '",lt"' => '",<"',
                    '",lte"' => '",<="',
                ));
        $cond = '['.$cond.']';
        $cond = json_decode($cond);
        if ($accos && $cond){
            foreach ($cond as $key => $item){
                if (is_array($item)){
                    $k = array_shift($item);
                    unset($cond[$key]);
                    if (sizeof($item)==1) $item = $item[0];
                    if ($item === 'false' || $item === '0') $item = false;
                    $cond[$k] = $item;
                }else{
                    unset($cond[$key]);
                }
            }
        }
        return $cond;
    }


    static function write()
    {

    }

    static function delete()
    {

    }

    static function entity($info)
    {
        $key = isset($info['uri'])? $info['uri'] : null;
        if (!isset($key) || !($entity = Buffer::get($key))){
            try{
                $name = basename($info['uri']);
                if (isset($info['uri']) && (empty($info['uri']) || preg_match('#^[a-zA-Z_0-9\/]+$#ui', $info['uri']))){
                    if (!empty($info['is_default_logic'])){
                        if (isset($info['proto'])){
                            // Класс от прототипа
                            $class = get_class(self::read($info['proto']));
                        }else{
                            // Класс наследуется, но нет прототипа
                            $class = '\boolive\core\data\Entity';
                        }
                    }else{
                        $namespace = str_replace('/', '\\', rtrim($info['uri'],'/'));
                        // Свой класс
                        if (empty($namespace)){
                            $class = '\\project';
                        }else
                        if (substr($namespace,0,7) === '\\vendor'){
                            $class = substr($namespace,7).'\\'.$name;
                        }else{
                            $class = $namespace.'\\'.$name;
                        }
                    }
                }else{
                    $class = '\boolive\core\data\Entity';
                }
                if (isset($info['value']) && !isset($info['is_default_value'])){
                    $info['is_default_value'] = false;
                }
                $entity = new $class($info);
            }catch (\ErrorException $e){
                $entity = new Entity($info);
            }
            if (isset($key)) Buffer::set($entity);
        }
        return $entity;
    }


}