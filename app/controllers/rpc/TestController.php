<?php

namespace app\controllers\rpc;


use zcswoole\Controller;

/**
 * Rpc测试类
 * Class TestController
 * @package app\controllers\rpc
 */
class TestController extends Controller
{
    public function getName()
    {
        return 'rpc server return wuzhc';
    }
}