<?php
/**
 * Created by PhpStorm.
 * User: wuzhc
 * Date: 18-8-30
 * Time: 上午9:54
 */

namespace command;


use Swoole\Process;
use Swoole\Timer;
use zcswoole\command\Command;
use zcswoole\command\CommandContext;
use zcswoole\services\RedisDB;
use zcswoole\utils\Console;

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
                'min_worker_num' => 1,
                'max_pop_num'    => 1000
            ],
        ];

    // 进程状态
    const WORKER_STATUS_WAITING = 'waiting';
    const WORKER_STATUS_RUNNING = 'running';
    const WORKER_STATUS_STOP = 'stop';

    // 进程类型
    const WORKER_STATIC_TYPE = 'static';
    const WORKER_DYNAMIC_TYPE = 'dynamic';

    public $path = DIR_ROOT . '/app/data/queue';
    public $pidFile = DIR_ROOT . '/app/data/queue.pid';
    public $pidInfoFile = DIR_ROOT . '/app/data/queue.txt';
    public $log = DIR_ROOT . '/app/data/queue.log';
    public $mpid = 0; // 主进程ID
    public $workers = []; // 启动进程数据
    public $workersInfo = []; // 进程内容
    public $maxExecTime = 3600; // 最大执行时间
    public $maxTaskNum = 1000; // 队列堆积任务最大值时触发创建动态进程条件

    public $dynamicWorkerNum = [];

    public function execute(CommandContext $context)
    {
        // TODO: Implement execute() method.
        $this->start();             // 启动
        $this->installSignal();     // 安装信号
        $this->setTimer();          // 定时器检测队列积压消息数量,从而决定是否再启动新worker处理任务
    }

    /**
     * 安装信号处理器
     */
    public function installSignal()
    {
        Process::signal(SIGCHLD, function () {
            while ($res = Process::wait(false)) {
                $pid = $res['pid'];
                if (($workerInfo = $this->workersInfo[$pid]) && ($process = $this->workers[$pid])) {
                    if (isset($this->workers[$pid])) {
                        unset($this->workers[$pid]);
                    }
                    if (isset($this->workersInfo[$pid])) {
                        unset($this->workersInfo[$pid]);
                    }

                    if ($workerInfo['type'] == self::WORKER_STATIC_TYPE) {
                        Console::msg("static worker $process->pid restart");
                        $this->createProcess($workerInfo['queue'], self::WORKER_STATIC_TYPE);
                    } else {
                        if (isset($this->dynamicWorkerNum[$workerInfo['queue']])) {
                            --$this->dynamicWorkerNum[$workerInfo['queue']]; // 动态进程数量减一
                            Console::msg("dynamic worker $pid has exit");
                        } else {
                            echo $workerInfo['queue'] . " why \n";
                        }
                    }
                }
            }
        });
        Process::signal(SIGINT, function () {
            foreach ($this->workers as $pid => $worker) {
                \swoole_process::kill($pid);
                Console::msg("worker $pid exit");
            }

            @unlink($this->pidFile);
            @unlink($this->pidInfoFile);
            sleep(1);
            Console::error("master exit");
        });
    }

    public function setTimer()
    {
        Timer::tick(60000 * 5, [$this, 'monitor']);
    }

    /**
     * 启动
     */
    public function start()
    {
        // 是否已存在
        $pid = @file_get_contents($this->pidFile);
        if ($pid && \swoole_process::kill($pid, 0)) {
            Console::error("queue is running, you can stop it");
        }

        // 保存主进程信息
        $this->mpid = posix_getpid();
        $this->saveMasterPid($this->mpid);
        $this->saveMasterData([
            'pid'    => $this->mpid,
            'status' => self::WORKER_STATUS_RUNNING
        ]);

        // 启动子进程作为worker处理消息
        foreach ($this->queues as $name => $queue) {
            for ($i = 0; $i < $queue['min_worker_num']; $i++) {
                $this->createProcess($queue['name'], self::WORKER_STATIC_TYPE);
            }
        }
    }

    /**
     * 创建进程
     * @param $queueName
     * @param string $workerType
     * @return int
     */
    public function createProcess($queueName, $workerType = self::WORKER_DYNAMIC_TYPE)
    {
        $process = new Process(function (Process $worker) use ($queueName, $workerType) {
            // swoole_set_process_name(sprintf('php-ps:%s', $name));
            // 动态进程执行完毕之后,退出
            // 静态进程达到最大执行时间之后,退出重启
            $type = $workerType == self::WORKER_DYNAMIC_TYPE ? 'dynamic' : 'static';

            $beginTime = time();
            $redis = RedisDB::getConnection();
            $queueInfo = $this->queues[$queueName];
            do {
                $len = $redis->lLen($queueName);
                if ($len == 0) {
                    sleep(5);
                }

                for ($i = 0; $i < $queueInfo['max_pop_num']; $i++) {
                    if (self::WORKER_STATUS_RUNNING == $this->getMasterData('status')) {
                        if ($task = $redis->rPop($queueName)) {
                            // sleep(rand(0,1)); // 模拟任务处理为2s
                            Console::success("$type worker $worker->pid handle $task success");
                        } else {
                            break;
                        }
                    }
                }

            } while ($workerType == self::WORKER_STATIC_TYPE && $beginTime + $this->maxExecTime > time());
            $redis->close();
            Console::msg("$type worker $worker->pid will exit");
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
            Console::msg(($workerType == self::WORKER_DYNAMIC_TYPE ? 'dynamic' : 'static') . " worker $pid start");
        }

        return $pid;
    }

    /**
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

    protected function saveMasterData($data)
    {
        return file_put_contents($this->pidInfoFile, serialize($data));
    }

    protected function saveMasterPid($pid)
    {
        return file_put_contents($this->pidFile, $pid);
    }

    public function monitor()
    {
        if (self::WORKER_STATUS_RUNNING != $this->getMasterData('status')) {
            return;
        }

        echo "正在检测队列积压情况---------------------------------- \n";

        $redis = RedisDB::getConnection();
        foreach ($this->queues as $name => $queue) {
            $len = $redis->lLen($name); // 队列堆积消息数量
            if ($len > $this->maxTaskNum) {
                if (!isset($this->dynamicWorkerNum[$name])) {
                    $this->dynamicWorkerNum[$name] = 0;
                }

                // 临时启动动态worker,处理繁忙的队列
                $dynamicWorkerNum = $this->dynamicWorkerNum[$name];
                if (($dynamicWorkerNum + $queue['min_worker_num']) < $queue['max_worker_num']) {
                    $this->createProcess($name, self::WORKER_DYNAMIC_TYPE);
                }
            }
        }
    }
}
