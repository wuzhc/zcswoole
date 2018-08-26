<?php

namespace utils;


class FileUtil
{
    /**
     * 文件后缀明
     * @param $name
     * @return mixed
     */
    public static function getExt($name)
    {
        $arr = explode('.', $name);
        return end($arr);
    }

    /**
     * 创建目录
     * @param $path
     * @return bool
     */
    public static function createDir($path)
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            return mkdir($dir, 0777, true);
        }
    }
}