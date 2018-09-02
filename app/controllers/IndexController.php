<?php

namespace app\controllers;


use common\config\Constant;
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
Class IndexController extends HttpController
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
        $this->response->end($cron->getNextRunDate()->getTimestamp() . '------' . $cron->getPreviousRunDate()
                ->format('Y-m-d H:i:s'));
    }

    /**
     * 远程调用demo
     */
    public function rpc()
    {
        $t1 = microtime(true);

        $recv = '';
        $error = 'nothing';
        $res = ZCSwoole::$app->rpcClient->request('/rpc/test/getName');
        if ($res['status'] === Constant::STATUS_SUCCESS) {
            $data = $res['data'];
            if ($data['status'] === Constant::STATUS_SUCCESS) {
                $recv = $data['data'];
            } else {
                $error = $res['msg'];
            }
        } else {
            $error = $res['msg'];
        }

        $timer = microtime(true) - $t1;
        $this->response->write("timer: $timer<br>");
        $this->response->write("error: $error<br>");
        $this->response->write("recv: $recv<br>");
        $this->response->end();
    }
}