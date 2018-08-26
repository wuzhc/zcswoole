<?php

namespace app\controllers;


use Cron\CronExpression;
use zcswoole\components\RpcClient;
use zcswoole\Config;
use zcswoole\http\HttpController;
use zcswoole\ZCSwoole;

/**
 * Class Index
 * @package app\controllers
 * @author wuzhc 2018-08-09
 */
Class Index extends HttpController
{
    /**
     * 业务逻辑
     */
    public function index()
    {
        $this->render('index', [
            'msg' => 'Get started with zcswoole'
        ]);
    }

    public function mysql()
    {
        $config = Config::get('mysql');
        $db = new \MysqliDb ($config['host'], $config['user'], $config['password'], $config['dbname']);
        $records = $db->get('swoole');
        $this->endJson($records);
    }

    public function welcome()
    {
        $this->render();
    }

    public function user()
    {
        $params = $this->request->get['name'];
        $this->response->end($params);
    }

    public function log()
    {
        foreach (ZCSwoole::$app->server->connections as $connection) {
            print_r($connection);
            echo "\r\n";
        }
        $this->response->end('OK');
    }

    public function cronb()
    {
        $cron = \Cron\CronExpression::factory('@monthly');
        $this->response->end($cron->getNextRunDate()->getTimestamp() . '------'  . $cron->getPreviousRunDate()->format('Y-m-d H:i:s'));
    }

    public function rpc()
    {
        $i = 0;
        $t1 = microtime(true);

        for ($j=0;$j<20;$j++) {
            if (ZCSwoole::$app->rpcClient->asyncRequest(['name'=>'wuzhc','age'=>10])) {
            }
        }

        $this->response->end(microtime(true)-$t1);
    }
}