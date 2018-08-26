<?php

namespace app\controllers\backend;


use common\config\Constant;
use zcswoole\http\HttpController;
use zcswoole\services\MysqliDB;
use zcswoole\ZCSwoole;

/**
 * 后台管理首页
 * Class Site
 * @package app\controllers\backend
 */
class Site extends HttpController
{
    public $sessionKey = '';
    public $userID = '';

    public function beforeAction()
    {
        $userID = $this->request->cookie['userID'] ?? null;
        if ($userID) {
            $this->userID = $userID;
            $this->sessionKey = 'session:user:' . $userID;
        }
    }

    /**
     * 登录页面
     */
    public function login()
    {
        if ($data = $this->request->post) {
            $username = $data['username'] ?? null;
            $password = $data['password'] ?? null;
            if (!$username || !$password) {
                $this->endJson(null, 0, '用户名或密码不能为空');
            } else {
                $res = MysqliDB::instance()
                    ->where('account', $username)
                    ->where('password', $password)
                    ->getOne(Constant::CHAT_USER);
                if ($res) {
                    $key = 'session:user:' . $res['id'];
                    ZCSwoole::$app->session->set($key, $res, 7200);
                    $this->response->cookie('userID', $res['id'], 0, '/');
                    $this->endJson(null, 0, '登录成功');
                } else {
                    $this->endJson(null, 1, '用户名或密码不正确');
                }
            }
        } else {
            $this->render('login', [
                'name' => 'zcswoole'
            ]);
        }
    }

    /**
     * 退出登录
     */
    public function logout()
    {
        ZCSwoole::$app->session->drop($this->sessionKey);
        $this->response->cookie('userID', null, null, '/');
        $this->response->redirect($this->createUrl('backend/site/login'));
    }

    /**
     * 首页
     */
    public function index()
    {
        if ($this->isGuest()) {
            $this->response->redirect($this->createUrl('backend/site/login'));
        } else {
            $this->render('index', [
                'name' => 'zcswoole',
                'user' => ZCSwoole::$app->session->get($this->sessionKey, 'name')
            ]);
        }
    }
    
    protected function isGuest()
    {
        if (!$this->sessionKey) {
            return true;
        } else {
            return ZCSwoole::$app->session->get($this->sessionKey, 'id') ? false : true;
        }
    }

    public function test()
    {
        $cookie = $this->request->cookie;
        print_r($cookie);
        $this->response->end(json_encode($cookie));
    }

    /**
     * 欢迎页
     */
    public function welcome()
    {
        $stats = $this->server->stats();

        $startTime = $stats['start_time'];
        $hasRunTime = time() - $startTime;
        $hasRunTime = floor(($hasRunTime / 3600)) . '天' . floor($hasRunTime % 3600 /3600) . '小时';

        $this->render('welcome', [
            'name' => 'zcswoole',
            'time' => date('Y-m-d H:i:s'),
            'user' => ZCSwoole::$app->session->get($this->sessionKey, 'name'),
            'phpVersion' => PHP_VERSION,
            'phpMode' => php_sapi_name(),
            'zendVersion' => zend_version(),
            'os' => PHP_OS,
            'server' => php_uname(),
            'host' => $this->server->host,
            'uploadSize' => get_cfg_var("upload_max_filesize")?get_cfg_var("upload_max_filesize"):"不允许上传附件",
            'execTime' => ini_get("max_execution_time"),
            'memory' => ini_get("memory_limit")?ini_get("memory_limit"):"无",
            'connectNum' => $stats['connection_num'],
            'workerNum' => $this->server->setting['worker_num'],
            'requestNum' => $stats['request_count'],
            'acceptNum' => $stats['accept_count'],
            'closeNum' => $stats['close_count'],
            'taskNum' => $stats['tasking_num'],
            'coroutineNum' => $stats['coroutine_num'],
            'startTime' => date('Y-m-d H:i:s', $startTime),
            'hasRunTime' => $hasRunTime
        ]);
    }


}