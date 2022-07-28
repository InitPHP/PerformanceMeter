<?php
/**
 * PerformanceMeter.php
 *
 * This file is part of InitPHP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    http://initphp.github.io/license.txt  MIT
 * @version    1.0
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\PerformanceMeter;

use function explode;
use function microtime;
use function memory_get_usage;
use function round;
use function strtolower;

class PerformanceMeter
{

    protected static array $pointers = [];

    /**
     * Ölçüm noktası oluşturur.
     *
     * @param string $name
     */
    public static function setPointer(string $name): void
    {
        $mtime = explode(' ', microtime());
        self::$pointers[strtolower($name)] = [
            'time'      => $mtime[1] + $mtime[0],
            'memory'    => memory_get_usage(),
        ];
    }

    /**
     * @see PerformansMeter::setPointer()
     * @param string $name
     * @return void
     */
    public static function mark(string $name): void
    {
        self::setPointer($name);
    }

    /**
     * İki ölçüm noktası arasındaki zamanı ölçer.
     *
     * @param string $startPoint <p>Ölçüm için kullanılacak birinci noktanın adı.</p>
     * @param string|null $endPoint <p>Ölçüm için kullanılacak ikinci noktanın adı. <code>null</code> ise kullanıldığı yer kabul edilir.</p>
     * @param int $decimal <p>Sonucun kayar noktalı sayı kısmında noktadan sonra kaç hane gösterileceğini belirtir.</p>
     * @return float
     */
    public static function elapsedTime(string $startPoint, ?string $endPoint = null, int $decimal = 4): float
    {
        $start = self::getPointer($startPoint);
        $end = self::getPointer($endPoint);
        return round(($end['time'] - $start['time']), $decimal);
    }

    /**
     * İki ölçüm noktası arasındaki bellek kullanımını ölçer.
     *
     * @param string $startPoint <p>Ölçüm için kullanılacak birinci noktanın adı.</p>
     * @param string|null $endPoint <p>Ölçüm için kullanılacak ikinci noktanın adı. <code>null</code> ise kullanıldığı yer kabul edilir.</p>
     * @param int $decimal <p>Sonucun kayar noktalı sayı kısmında noktadan sonra kaç hane gösterileceğini belirtir.</p>
     * @return string
     */
    public static function memoryUsage(string $startPoint, ?string $endPoint = null, int $decimal = 2): string
    {
        $start = self::getPointer($startPoint);
        $end = self::getPointer($endPoint);
        $memoryUse = $end['memory'] - $start['memory'];
        if($memoryUse < 1048576){
            return round(($memoryUse / 1024), $decimal) . 'KB';
        }
        return round(($memoryUse / 1048576), $decimal) . 'MB';
    }

    /**
     * @param string|null $name
     * @return array
     */
    protected static function getPointer(?string $name = null): array
    {
        if($name !== null){
            $name = strtolower($name);
        }
        if($name === null || !isset(self::$pointers[$name])){
            $mtime = explode(' ', microtime());
            return [
                'time'      => $mtime[1] + $mtime[0],
                'memory'    => memory_get_usage()
            ];
        }
        return self::$pointers[$name];
    }
}
