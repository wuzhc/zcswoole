<?php

namespace command;


use Swoole\Process;
use Swoole\Timer;
use zcswoole\command\Command;
use zcswoole\command\CommandContext;
use zcswoole\Router;
use zcswoole\services\RedisDB;
use zcswoole\utils\ConsoleUtil;

/**
 * 多进程程序,根据队列积压消息数量动态创建进程处理队列任务,原理参考swoole-jobs
 * Class QueueCommand
 * @package command
 * @see https://github.com/kcloze/swoole-jobs
 * @author wuzhc 2018-08-30
 */
class QueueCommand extends Command
{
    public $queues
        = [
            'myJob_2' => [
                'name'           => 'myJob_2',
                'max_worker_num' => 10,
                'min_worker_num' => 2,
                'max_pop_num'    => 1000
            ],
            'myJob_1' => [
                'name'           => 'myJob_1',
                'max_worker_num' => 10,
                'min_worker_num' => 3,
                'max_pop_num'    => 1000
            ],
        ];

    // 进程状态
    const MASTER_STATUS_WAITING = 'waiting';
    const MASTER_STATUS_RUNNING = 'running';
    const MASTER_STATUS_STOP = 'stop';

    // 进程类型
    const WORKER_STATIC_TYPE = 'static';
    const WORKER_DYNAMIC_TYPE = 'dynamic';

    public $path = DIR_ROOT . '/app/data/queue';
    public $pidFile = DIR_ROOT . '/app/data/queue.pid';
    public $pidInfoFile = DIR_ROOT . '/app/data/queue.txt';
    public $log = DIR_ROOT . '/app/data/queue.log';
    public $mpid = 0;                                        // 主进程ID
    public $workers = [];                                    // 子进程数据
    public $workersInfo = [];                                // 子进程内容
    public $maxExecTime = 3600;                              // 单个子进程最大执行时间
    public $maxTaskNum = 1000;                               // 单个子进程最大处理任务数

    public $dynamicWorkerNum = [];

    /**
     * @param CommandContext $context
     */
    public function execute(CommandContext $context)
    {
        // TODO: Implement execute() method.
        switch ($context->getAction()) {
            case 'start':
                $this->start();
                break;
            case 'status':
                ConsoleUtil::success("can not support status");
                break;
            case 'reload':
                ConsoleUtil::success("can not support reload");
                break;
            case 'stop':
                $mpid = $this->getMasterPid();
                if ($mpid) {
                    Process::kill($mpid, SIGUSR1);
                } else {
                    ConsoleUtil::error("master process has exit");
                }
                break;
            case 'kill':
                $mpid = $this->getMasterPid();
                if ($mpid) {
                    Process::kill($mpid);
                } else {
                    ConsoleUtil::error("master process has exit");
                }
                break;
        }
    }

    /**
     * 启动
     */
    public function start()
    {
        $pid = @file_get_contents($this->pidFile);
        if ($pid && \swoole_process::kill($pid, 0)) {
            ConsoleUtil::error("queue is running, you can stop it");
        }

        $this->mpid = posix_getpid();
        $this->saveMasterPid($this->mpid);
        $this->saveMasterData([
            'pid'    => $this->mpid,
            'status' => self::MASTER_STATUS_RUNNING
        ]);

        // 启动子进程作为worker处理消息
        foreach ($this->queues as $name => $queue) {
            for ($i = 0; $i < $queue['min_worker_num']; $i++) {
                $this->createProcess($queue['name'], self::WORKER_STATIC_TYPE);
            }
        }

        $this->installSignal();     // 安装信号处理器
        $this->setTimer();          // 定时器检测队列积压消息数量,从而决定是否再启动新worker处理任务
    }

    /**
     * 创建进程
     * @param string $queueName 队列名称
     * @param string $workerType 进程worker类型 (WORKER_DYNAMIC_TYPE or WORKER_STATIC_TYPE)
     * @return int
     */
    public function createProcess($queueName, $workerType = self::WORKER_DYNAMIC_TYPE)
    {
        $process = new Process(function (Process $worker) use ($queueName, $workerType) {
            $beginTime = time();
            $redis = RedisDB::getConnection();
            $queueInfo = $this->queues[$queueName];
            $type = $workerType == self::WORKER_DYNAMIC_TYPE ? 'dynamic' : 'static';
            do {
                $len = $redis->lLen($queueName);
                if ($len == 0) {
                    sleep(5);
                }

                for ($i = 0; $i < $queueInfo['max_pop_num']; $i++) {
                    if (self::MASTER_STATUS_RUNNING != $this->getMasterData('status')) {
                        break;
                    }
                    if ($task = $redis->rPop($queueName)) {
                        $taskData = json_decode($task, true);
                        $target = $taskData['router'] ?? '';
                        $params = $taskData['params'] ?? [];
                        $router = new Router($target);
                        list($controller, $action) = $router->parse();
                        if (class_exists($controller)) {
                            if (false !== call_user_func_array([$controller, $action],$params)) {
                                file_put_contents($this->log, "$type worker $worker->pid handle $task success \n", FILE_APPEND);
                                ConsoleUtil::success("$type worker $worker->pid handle $task success");
                            } else {
                                ConsoleUtil::error("$type worker $worker->pid handle $task failed", false);
                            }
                        } else {
                            ConsoleUtil::error("Class $controller is not exist", false);
                        }
                    } else {
                        break;
                    }
                }

            } while (self::MASTER_STATUS_RUNNING == $this->getMasterData('status')
            && $workerType == self::WORKER_STATIC_TYPE
            && $beginTime + $this->maxExecTime > time());

            $redis->close();
            ConsoleUtil::msg("$type worker $worker->pid will exit");
        }, false, false);

        if ($pid = $process->start()) {
            $this->workers[$pid] = $process;
            $this->workersInfo[$pid] = ['type' => $workerType, 'queue' => $queueName];
            if ($workerType == self::WORKER_DYNAMIC_TYPE) {
                if (!isset($this->dynamicWorkerNum[$queueName])) {
                    $this->dynamicWorkerNum[$queueName] = 0;
                }
                $this->dynamicWorkerNum[$queueName]++;
            }
            ConsoleUtil::msg(($workerType == self::WORKER_DYNAMIC_TYPE ? 'dynamic' : 'static') . " worker $pid start");
        }

        return $pid;
    }

    /**
     * 安装信号处理器
     */
    public function installSignal()
    {
        // 子进程退出处理器
        Process::signal(SIGCHLD, function () {
            while ($res = Process::wait(false)) {
                $pid = $res['pid'];
                if (($workerInfo = $this->workersInfo[$pid]) && ($process = $this->workers[$pid])) {
                    // 进程退出后删除worker信息
                    unset($this->workers[$pid], $this->workersInfo[$pid]);

                    // 静态worker退出后,再重新创建一个新进程;动态进程退出后不新建进程,动态worker数量减一
                    if ($workerInfo['type'] == self::WORKER_STATIC_TYPE) {
                        ConsoleUtil::msg("static worker $process->pid restart");
                        // 3次尝试新建worker
                        for ($i = 0; $i < 3; $i++) {
                            $newPid = $this->createProcess($workerInfo['queue'], self::WORKER_STATIC_TYPE);
                            if ($newPid) {
                                break;
                            }
                        }
                    } else {
                        // 该队列下动态worker数量
                        $dynamicWorkerNum = $this->dynamicWorkerNum[$workerInfo['queue']] ?? 0;
                        if ($dynamicWorkerNum > 0) {
                            --$this->dynamicWorkerNum[$workerInfo['queue']];
                            ConsoleUtil::msg("dynamic worker $pid has exit");
                        }
                    }
                }
            }
        });

        // 终端强制终止
        Process::signal(SIGINT, function () {
            if (self::MASTER_STATUS_STOP === $this->getMasterStatus()) {
                ConsoleUtil::success("master has exit");
            }
            $this->killChildWorkers();
            $this->exitMaster();
        });

        // 命令强制退出
        Process::signal(SIGTERM, function () {
            if (self::MASTER_STATUS_STOP === $this->getMasterStatus()) {
                ConsoleUtil::success("master has exit");
            }
            $this->killChildWorkers();
            $this->exitMaster();
        });

        // 平滑退出
        Process::signal(SIGUSR1, function () {
            if (self::MASTER_STATUS_STOP === $this->getMasterStatus()) {
                ConsoleUtil::success("master has exit");
            }
            $this->saveMasterData(['status' => self::MASTER_STATUS_WAITING]);
        });
    }

    /**
     * 获取主进程状态
     * @return mixed|null|string
     */
    protected function getMasterStatus()
    {
        $pid = $this->getMasterPid();
        if ($pid && Process::kill($pid, 0)) {
            return $this->getMasterData('status');
        } else {
            return self::MASTER_STATUS_STOP;
        }
    }

    /**
     * 强制退出子进程
     */
    protected function killChildWorkers()
    {
        foreach ($this->workers as $pid => $worker) {
            if (\swoole_process::kill($pid)) {
                ConsoleUtil::success("worker $pid exit success");
            } else {
                ConsoleUtil::error("worker $pid exit failed", false);
            }
        }
    }

    /**
     * 主进程退出
     */
    protected function exitMaster()
    {
        @unlink($this->pidFile);
        @unlink($this->pidInfoFile);
        sleep(1);
        ConsoleUtil::error("master exit");
    }

    /**
     * 设置定时器
     */
    public function setTimer()
    {
        Timer::tick(3000, [$this, 'monitor']);
    }

    /**
     * 获取主进程数据
     * @param string $key
     * @return mixed|null
     */
    protected function getMasterData($key = '')
    {
        $data = unserialize(file_get_contents($this->pidInfoFile));
        if ($key) {
            return $data[$key] ?? null;
        }

        return $data;
    }

    /**
     * 保存主进程数据 (主要是状态数据,用于子进程共享该状态)
     * @param $data
     * @return bool|int
     */
    protected function saveMasterData($data)
    {
        return file_put_contents($this->pidInfoFile, serialize($data));
    }

    /**
     * 保存主进程id
     * @param $pid
     * @return bool|int
     */
    protected function saveMasterPid($pid)
    {
        return file_put_contents($this->pidFile, $pid);
    }

    /**
     * 获取主进程id
     * @return bool|string
     */
    protected function getMasterPid()
    {
        return file_get_contents($this->pidFile);
    }

    /**
     * 定时监控
     */
    public function monitor()
    {
        $masterStatus = $this->getMasterData('status');
        if (self::MASTER_STATUS_WAITING === $masterStatus) {
            // 平滑退出,等所有子进程退出后再退出主进程
            if (count($this->workers) === 0) {
                Process::kill($this->getMasterPid());
            } else {
                return ;
            }
        } elseif (self::MASTER_STATUS_RUNNING === $masterStatus) {
            ConsoleUtil::msg("正在检测队列积压任务数---------------------");
            $redis = RedisDB::getConnection();
            foreach ($this->queues as $name => $queue) {
                $len = $redis->lLen($name);
                // 队列堆积任务超过最大任务峰值,动态新建worker分担压力
                if ($len < $this->maxTaskNum) {
                    continue;
                }

                if (!isset($this->dynamicWorkerNum[$name])) {
                    $this->dynamicWorkerNum[$name] = 0;
                }

                // 若没达到最大worker数量,临时再启动动态worker,处理繁忙的队列
                $dynamicWorkerNum = $this->dynamicWorkerNum[$name];
                if (($dynamicWorkerNum + $queue['min_worker_num']) < $queue['max_worker_num']) {
                    $this->createProcess($name, self::WORKER_DYNAMIC_TYPE);
                }
            }
        } else {
            return ;
        }


    }
}
