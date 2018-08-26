<?php

namespace utils;


/**
 * 视频文件处理工具类
 * Class FFmpegUtil
 * @package utils
 * @author wuzhc 2018-06-23
 */
class FFmpegUtil
{
    /**
     * 生成视频GIF图片
     * @param string $file 文件路径
     * @param string $gifPath 生成文件名称(包括路径)
     * @param int $ss 多少秒后开始生成
     * @param int $t gif多少秒
     * @param array $extra
     */
    public static function generateGif($file, $gifPath, $ss = 30, $t = 10, $extra = array())
    {
        // git尺寸
        if (!empty($extra['size'])) {
            $size = $extra['size'];
        } else {
            $size = '280*200';
        }

        exec('ffmpeg -ss '. $ss .' -t '. $t .' -i "'. $file .'" -f gif -n -s ' . $size . ' "'. $gifPath .'" > /dev/null 2>&1');
    }

    /**
     * 获取视频播放时长
     * @param $file
     * @return array
     */
    public static function getDuration($file)
    {
        $command = "ffmpeg -i '".$file."' 2>&1 | grep 'Duration' | cut -d ' ' -f 4 | sed s/,//";
        $date = exec($command);
        $duration = explode(':', $date);
        return $duration[0]*3600 + $duration[1]*60+ round($duration[2]);
    }
}