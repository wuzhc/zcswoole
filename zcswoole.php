<?php
/**
 * 终端命令执行, e.g. php7 zcswoole.php httpServer arg1=value1 arg2=value=2 ...
 * @author wuzhc 2018-08-14
 */
use zcswoole\command\CommandController;

define('DIR_ROOT', __DIR__);
date_default_timezone_set('PRC');

include DIR_ROOT . '/vendor/autoload.php';
$config = include DIR_ROOT . '/common/config/config.php';
(new CommandController($config))->run();