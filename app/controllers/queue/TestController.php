<?php
/**
 * Created by PhpStorm.
 * User: wuzhc
 * Date: 18-9-2
 * Time: 下午1:10
 */

namespace app\controllers\queue;


use zcswoole\Controller;
use zcswoole\services\RedisDB;
use zcswoole\utils\Console;

class TestController extends Controller
{
    public function saveName($name)
    {
        sleep(1);
        RedisDB::getConnection()->lPush('queue_success', $name);
    }
}