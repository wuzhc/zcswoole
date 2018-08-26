<?php

namespace utils;


use ReflectionClass;

/**
 * 调试工具类
 * Class DebugUtil
 * @package utils
 * ＠author wuzhc 2018-06-23
 */
class DebugUtil
{
    /**
     * 调试信息
     * @param int $t1 程序开始执行时间 microtime(true)
     * @param string $msg 调试信息
     * @param bool $isExit 是否终止程序
     */
    public static function debugInfo($t1, $msg = '', $isExit = false)
    {
        $t2 = microtime(true);
        $costTime = sprintf('%.3f', round($t2 - $t1, 3));
        $eol = php_sapi_name() == 'cli' ? PHP_EOL : '<br>';
        echo "\033[31m " . '[ 耗时: '. $costTime . 's ] :' . "\033[0m" . ' ' . $msg . $eol;

        if (true === $isExit) {
            exit('end');
        }
    }

    /**
     * 打印数据,支持多个变量同时输出
     * 第一个参数为打印方法,默认为print_r
     */
    public static function dump()
    {
        $num = func_num_args();
        if ($num == 0) {
            exit();
        }

        if (php_sapi_name() == 'cli') {
            $pre = '';
            $br = PHP_EOL;
        } else {
            $pre = '<hr><pre>';
            $br = '<br>';
        }

        $args = func_get_args();
        if ($num > 1
            && is_string($args[0])
            && in_array($args[0], array('print_r', 'var_dump'))
        ) {
            $func = array_shift($args);
        } else {
            $func = 'print_r';
        }

        foreach ($args as $arg) {
            echo $pre;
            if (is_object($arg)) {
                $className = get_class($arg);
                echo '[ ' . $className . ' ]' . $br;
                $obj = new ReflectionClass($className);
                foreach ($obj->getProperties() as $property) {
                    if (false === $property->isPublic()) {
                        continue;
                    }
                    echo $property->getName() . ' : ';
                    call_user_func($func, $property->getValue($arg));
                    echo $br;
                }
            } else {
                call_user_func($func, $arg);
            }
            echo $br;
        }

        exit();
    }


}