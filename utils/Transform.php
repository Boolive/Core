<?php
/**
 * Transform
 * @aurhor Vladimir Shestakov
 * @version 1.0
 */
namespace boolive\core\utils;

class Transform
{
//    static function transform($file, &$errors, $args)
//    {
//        return $file;
//    }

    /** @const Тип масштабирования. */
    const FIT_IN = 1; // Ширина или высота входят в указанную область, но могут быть меньше её.
    const FIT_OUT_LEFT_TOP = 2; // Ширина или высота может выходить за указанную область, но будут отсечены
    const FIT_OUT_LEFT_MIDDLE = 4;
    const FIT_OUT_LEFT_BOTTOM = 8;
    const FIT_OUT_CENTER_TOP = 32;
    const FIT_OUT_CENTER_MIDDLE = 64;
    const FIT_OUT = 64;
    const FIT_OUT_CENTER_BOTTOM = 128;
    const FIT_OUT_RIGHT_TOP = 256;
    const FIT_OUT_RIGHT_MIDDLE = 512;
    const FIT_OUT_RIGHT_BOTTOM = 1024;
    const FIT_FILL = 2048; // Непропорциональное масштабирование - полное соответсвие указанной области

    /** @const Направление масштабирования*/
    const SCALE_ANY = 0; // Уменьшать или увеличивать автоматически
    const SCALE_DOWN = 1; // Только уменьшать
    const SCALE_UP = 2; // Только увеличивать

    /** @const Направления отражения */
    const FLIP_X = 1;
    const FLIP_Y = 2;

    /** @var null Ресурс изображения для функций GD */
    private $_handler = null;
    /** @var array Информация о текущем ресурсе. Размеры, расширение */
    private $_info = array();
    /** @var array Массив заданных трансформаций. Выполняются в момент запроса результата трансфрмации */
    private $_transforms = array();
    /** @var array Заданные трансформации в строковом формате */
    private $_transforms_str = '';
    /** @var string Расширение, в котором сохранить */
    private $_convert;
    /** @var string Путь на исходный файл */
    private $file;

    function __construct($file)
    {
        $this->file = trim($file,'/\\');
    }

    function __destruct()
    {
        $this->reset();
    }

    static function create($file)
    {
        return new self($file);
    }


    /**
     * Сброс трансформаций
     * @return $this
     */
    function reset()
    {
        $this->_transforms = array();
        $this->_transforms_str = '';
        $this->_info = null;
        if ($this->_handler) imagedestroy($this->_handler);
        $this->_handler = null;
        return $this;
    }

    /**
     * Ресурс изображения для функций GD
     * @return resource
     * @throws \Exception
     */
    function handler()
    {
        if (!isset($this->_handler)){
            $file = $this->getDir().'/'.$this->file;
            switch ($this->ext()){
                case 'gif':
                    $this->_handler = @imagecreatefromgif($file);
                    break;
                case 'png':
                    $this->_handler = @imagecreatefrompng($file);
                    break;
                case 'jpg':
                    $this->_handler = @imagecreatefromjpeg($file);
                    break;
                default:
                    throw new \Exception('Не поддерживаем тип файла-изображения');
            }
        }
        return $this->_handler;
    }

    /**
     * Информация об изображении
     * @return array
     */
    function info()
    {
        if (empty($this->_info)){
            $file = $this->getDir().'/'.$this->file;
            if (is_file($file) && ($info = getimagesize($file))){
                $ext = array(1 => 'gif', 2 => 'jpg', 3 => 'png', 4 => 'swf', 5 => 'psd', 6 => 'bmp', 7 => 'tiff', 8 => 'tiff',
                       9 => 'jpc', 10 => 'jp2', 11 => 'jpx', 12 => 'jb2', 13 => 'swc', 14 => 'iff', 15 => 'wbmp', 16 => 'xbmp'
                );
                $this->_info = array(
                    'width' => $info[0],
                    'height' => $info[1],
                    'ext' => $ext[$info[2]],
                    'quality' => 100
                );
                if (empty($this->_convert)) $this->_convert = $ext[$info[2]];
            }
        }
        return $this->_info;
    }

    /**
     * Ширина
     * @return int|bool
     */
    function width()
    {
        if ($info = $this->info()){
            return $info['width'];
        }else{
            return false;
        }
    }

    /**
     * Высота
     * @return int|bool
     */
    function height()
    {
        if ($info = $this->info()){
            return $info['height'];
        }else{
            return false;
        }
    }

    /**
     * Изменение размера
     * @param int $width Требуемая ширена изображения
     * @param int $height Требуемая высота изображения
     * @param int $fit Тип масштабирования. Указывается константами Transform::FIT_*
     * @param int $scale Направление масштабирования. Указывается константами Transform::SCALE_*
     * @param bool $do Признак, выполнять трансформацию (true) или отложить до результата (пути на файл)
     * @return $this
     */
    function resize($width, $height, $fit = Transform::FIT_OUT_LEFT_TOP, $scale = Transform::SCALE_ANY, $do = false)
    {
        if (is_string($fit)) $fit = constant('self::'. $fit);
        if (is_string($scale)) $scale = constant('self::'. $scale);
        $width = max(0, min($width, 1500));
        $height = max(0, min($height, 1500));
        $fit = intval($fit);
        $scale = intval($scale);
        if (!$do){
            $this->_transforms[] = array('resize', array($width, $height, $fit, $scale, true));
            $this->_transforms_str.= 'resize('.$width.'x'.$height.'x'.$fit.'x'.$scale.')';
        }else{
            if ($handler = $this->handler()){
                // Выполение масштабирования
                $src = array('x' => 0, 'y' => 0, 'w' => $this->width(), 'h' => $this->height());
                $new = array('x' => 0, 'y' => 0, 'w' => $width, 'h' => $height);
                //
                $do_scale = false;
                $dw = $src['w'] - $new['w'];
                $dh = $src['h'] - $new['h'];
                // Коррекция масштабирования
                $can_scale = function($d) use($scale){
                    // Только увеличивать
                    if ($scale == Transform::SCALE_UP){
                        return min($d, 0);
                    }else
                    // Только уменьшать
                    if ($scale == Transform::SCALE_DOWN){
                        return max($d, 0);
                    }
                    return $d;
                };
                if ($new['w'] != 0 && $new['h'] != 0 || $new['h']!=$new['w']){
                    // Автоматически ширена или высота
                    if (($new['w'] == 0 || $new['h'] == 0) && ($do_scale = $can_scale($dw))){
                        $ratio = $src['w'] / $src['h'];
                        if ($new['w'] == 0){
                            $new['w'] = round($new['h'] * $ratio);
                        }else{
                            $new['h'] = round($new['w'] / $ratio);
                        }
                    }else
                    // Максимальное изменение
                    if ($fit === self::FIT_IN){
                        $ratio = $src['w'] / $src['h'];
                        if ($dw > $dh && ($do_scale = $can_scale($dw))){
                            $new['h'] = round($new['w'] / $ratio);
                        }else
                        if ($dw < $dh && ($do_scale = $can_scale($dh))){
                            $new['w'] = round($new['h'] * $ratio);
                        }else
                        if ($dw == $dh){
                            $do_scale = $can_scale($dw);
                        }
                    }else
                    // Минимальное изменение
                    if ($fit >= self::FIT_OUT_LEFT_TOP && $fit <= self::FIT_OUT_RIGHT_BOTTOM){
                        $ratio = $new['w'] / $new['h'];
                        if ($dw < $dh && ($do_scale = $can_scale($dw))){
                            $last = $src['h'];
                            $src['h'] = round($src['w'] / $ratio);
                            if ($fit & (self::FIT_OUT_LEFT_BOTTOM | self::FIT_OUT_CENTER_BOTTOM | self::FIT_OUT_RIGHT_BOTTOM)){
                                $src['y'] = $last - $src['h'];
                            }else
                            if ($fit & (self::FIT_OUT_LEFT_MIDDLE | self::FIT_OUT_CENTER_MIDDLE | self::FIT_OUT_RIGHT_MIDDLE)){
                                $src['y'] = round(($last - $src['h']) / 2);
                            }
                        }else
                        if ($dw > $dh && ($do_scale = $can_scale($dh))){
                            $last = $src['w'];
                            $src['w'] = round($src['h'] * $ratio);
                            if ($fit & (self::FIT_OUT_RIGHT_TOP | self::FIT_OUT_RIGHT_MIDDLE | self::FIT_OUT_RIGHT_BOTTOM)){
                                $src['x'] = $last - $src['w'];
                            }else
                            if ($fit & (self::FIT_OUT_CENTER_TOP | self::FIT_OUT_CENTER_MIDDLE | self::FIT_OUT_CENTER_BOTTOM)){
                                $src['x'] = round(($last - $src['w']) / 2);
                            }
                        }else
                        if ($dw == $dh){
                            $do_scale = $can_scale($dw);
                        }
                    }
                    if ($do_scale){
                        $img = imagecreatetruecolor($new['w'], $new['h']);
                        imagealphablending($img, false);
                        imagesavealpha($img, true);
                        imagecopyresampled($img, $this->_handler, $new['x'], $new['y'], $src['x'], $src['y'], $new['w'], $new['h'], $src['w'], $src['h']);
                        imagedestroy($this->_handler);
                        $this->_handler = $img;
                        $this->_info['width'] = $new['w'];
                        $this->_info['height'] = $new['h'];
                    }
                }
            }
        }
        return $this;
    }

    /**
     * Обрезание изображения
     * @param int $left Левая граница
     * @param int $top Верхняя граница
     * @param int $right Правая граница
     * @param int $bottom Нижняя граница
     * @param bool $do Признак, выполнять трансформацию (true) или отложить до результата (пути на файл)
     * @return $this
     */
    function crop($left, $top, $right, $bottom, $do = false)
    {
        $left = intval($left);
        $top = intval($top);
        $right = intval($right);
        $bottom = intval($bottom);
        if (!$do){
            $this->_transforms[] = array('crop', array($left, $top, $right, $bottom, true));
            $this->_transforms_str.='crop('.$left.'x'.$top.'x'.$right.'x'.$bottom.')';
        }else{
            // Выполение обрезания
            if ($right < $left) {
                list($left, $right) = array($right, $left);
            }
            if ($bottom < $top) {
                list($top, $bottom) = array($bottom, $top);
            }
            $crop_width = $right - $left;
            $crop_height = $bottom - $top;
            $new = imagecreatetruecolor($crop_width, $crop_height);
            imagealphablending($new, false);
            imagesavealpha($new, true);
            imagecopyresampled($new, $this->handler(), 0, 0, $left, $top, $crop_width, $crop_height, $crop_width, $crop_height);
            $this->_info['width'] = $crop_width;
            $this->_info['height'] = $crop_height;
            imagedestroy($this->_handler);
            $this->_handler = $new;
        }
		return $this;
    }

    /**
     * Поворот изображения
     * @param float $angle Угол поворота от -360 до 360
     * @param bool $do Признак, выполнять трансформацию (true) или отложить до результата (пути на файл)
     * @return $this
     */
    function rotate($angle, $do = false) {
		$angle = min(max(floatval($angle), -360), 360);
        if (!$do){
            $this->_transforms[] = array('rotate', array($angle, true));
            $this->_transforms_str.='rotate('.$angle.')';
        }else{
            $rgba = array(255,255,255,0);
            $handler = $this->handler();
            $bg_color = imagecolorallocatealpha($handler, $rgba[0], $rgba[1], $rgba[2], $rgba[3]);
            $new = imagerotate($handler, $angle, $bg_color);
            imagesavealpha($new, true);
            imagealphablending($new, true);
            $this->_info['width'] = imagesx($new);
            $this->_info['height'] = imagesy($new);
            imagedestroy($this->_handler);
            $this->_handler = $new;
        }
		return $this;
	}

    /**
     * Отражение изображения
     * @param int $dir Направление отражения. Задаётся константами Transform::FLIP_*
     * @param bool $do Признак, выполнять трансформацию (true) или отложить до результата (пути на файл)
     * @return $this
     */
    function flip($dir = self::FLIP_X, $do = false) {
		$dir = intval($dir);
        if (!$do){
            $this->_transforms[] = array('flip', array($dir, true));
            $this->_transforms_str.='flip('.$dir.')';
        }else{
            $new = imagecreatetruecolor($w = $this->width(), $h = $this->height());
            $src = $this->handler();
            imagealphablending($new, false);
            imagesavealpha($new, true);
            switch ($dir) {
                case self::FLIP_Y:
                    for ($i = 0; $i < $h; $i++) imagecopy($new, $src, 0, $i, 0, $h - $i - 1, $w, 1);
                    break;
                default:
                    for ($i = 0; $i < $w; $i++) imagecopy($new, $src, $i, 0, $w - $i - 1, 0, 1, $h);
            }
            imagedestroy($this->_handler);
            $this->_handler = $new;
        }
		return $this;
	}

    /**
     * Преобразование в серые тона
     * @param bool $do Признак, выполнять трансформацию (true) или отложить до результата (пути на файл)
     * @return $this
     */
    function gray($do = false)
    {
		if (!$do){
            $this->_transforms[] = array('gray', array(true));
            $this->_transforms_str.='gray()';
        }else{
            imagefilter($this->handler(), IMG_FILTER_GRAYSCALE);
        }
		return $this;
	}

    /**
     * Качество изображения для jpg и png
     * @param int $percent от 0 до 100
     * @return $this
     */
    function quality($percent)
    {
        $this->info();
        $this->_info['quality'] = intval($percent);
        if ($percent!=100){
            $this->_transforms_str.='quality('.$this->_info['quality'].')';
        }
        return $this;
    }

    /**
     * Смена расширения
     * @param string $type Новое расширение (gif, png, jpg)
     * @return $this
     */
    function convert($type)
    {
        if (in_array($type, array('gif','png','jpg'))){
            $this->_transforms_str.='convert('.$type.')';
            $this->_convert = $type;
        }
        return $this;
    }

    /**
     * Файл, ассоциированный с объектом.
     * Если были выполнены трансформации, то возвращается путь на трансформированное изображение
     */
    function file($transformed = true, $remake = false)
    {
        if ($transformed && !empty($this->_transforms_str)){
            $pos = mb_strrpos($this->file, '.');
            if ($pos === false){
                $names = [null, $this->file];
            }else{
                $names = [mb_substr($this->file, 0, $pos, 'UTF-8'), mb_substr($this->file, $pos+1, null, 'UTF-8')];
            }
            if (empty($this->_convert)) $this->_convert = $this->ext();

            $file = $names[0].'.'.$this->_transforms_str.'.'.$this->_convert;
            $root_file = $this->getDir().'/'.$names[0].'.'.$this->_transforms_str.'.'.$this->_convert;

            if ($remake && is_file($root_file)){
                unlink($root_file);
            }
            if (!is_file($root_file)){
                foreach ($this->_transforms as $trans){
                    call_user_func_array(array($this, $trans[0]), $trans[1]);
                }
                $this->_info['width'] = @imagesx($this->_handler);
                $this->_info['height'] = @imagesy($this->_handler);
                @imageinterlace($this->_handler, true);
                switch ($this->_convert) {
                    case 'gif':
                        $result = @imagegif($this->_handler, $root_file);
                        break;
                    case 'jpg':
                        $result = @imagejpeg($this->_handler, $root_file, round($this->_info['quality']));
                        break;
                    case 'png':
                        $result = @imagepng($this->_handler, $root_file, round(9 * $this->_info['quality'] / 100));
                        break;
                    default:
                        throw new \Exception('Не поддерживаем тип файла-изображения "'.$this->_convert.'"');
                }
                if (!$result) {
                    throw new \Exception('Не удалось сохранить изображение: '.$root_file);
                }
            }
        }else{
            $file = $this->file;
        }
        return '/'.$file;
    }

    public function ext()
    {
        if ($list = explode('.', $this->file)){
            $ext = strtolower(end($list));
            if ($ext == 'jpeg') $ext = 'jpg';
            return $ext;
        }else{
            return false;
        }
    }

    public function getDir($to_public = true)
    {
        return rtrim(DIR,'\\/');
    }

    function __toString()
    {
        try{
            return $this->file();
        }catch (\Exception $e){
            return $e->getMessage();
        }
    }
}